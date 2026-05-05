#!/usr/bin/env python3
"""
Stress test mobile upload endpoints (/api/summary and /api/detail) with real users.

Usage example:
  python scripts/stress_mobile_upload.py \
    --base-url https://savera_api.ungguldinamika.com \
    --company UDU \
    --users-csv users.csv \
    --target-users 1000 \
    --concurrency 1000 \
    --requests-per-user 1

CSV format (header required):
  login,password,employee_id,mac_address,company
  50423807,admin,29,AA:BB:CC:DD:EE:FF,UDU
"""

from __future__ import annotations

import argparse
import asyncio
import csv
import json
import random
import statistics
import string
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import httpx


@dataclass
class UserCred:
    login: str
    password: str
    employee_id: int
    mac_address: str
    company: str


@dataclass
class ReqResult:
    ok: bool
    status: int
    latency_ms: float
    endpoint: str
    error: str = ""


def now_device_time() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def epoch_sec() -> int:
    return int(time.time())


def rand_metric_series(length: int = 120) -> dict[str, list[dict[str, Any]]]:
    start = epoch_sec() - (length * 60)
    activity: list[dict[str, Any]] = []
    sleep: list[dict[str, Any]] = []
    stress: list[dict[str, Any]] = []
    spo2: list[dict[str, Any]] = []
    hr_manual: list[dict[str, Any]] = []
    hr_max: list[dict[str, Any]] = []
    hr_rest: list[dict[str, Any]] = []

    sleep_kinds = ["LIGHT_SLEEP", "DEEP_SLEEP", "REM_SLEEP", "UNKNOWN"]
    sleep_weights = [0.46, 0.24, 0.22, 0.08]

    for i in range(length):
        ts = start + (i * 60)
        kind = random.choices(sleep_kinds, sleep_weights, k=1)[0]
        hr = max(42, min(130, int(random.gauss(68, 7))))
        spo = max(88, min(100, int(random.gauss(96, 1.5))))
        strs = max(10, min(95, int(random.gauss(36, 18))))

        activity.append(
            {
                "timestamp": ts,
                "kind": kind,
                "rawKind": 0,
                "intensity": -0.01,
                "rawIntensity": -1,
                "steps": random.randint(0, 5),
                "distanceCm": random.randint(0, 50),
                "activeCalories": random.randint(0, 3),
                "heartRate": hr,
                "provider": "StressTestProvider",
            }
        )
        sleep.append({"label": "sleep", "timestamp": ts, "value": random.randint(1, 5)})
        stress.append({"timestamp": ts * 1000, "type": "UNKNOWN", "stress": strs})
        spo2.append({"timestamp": ts * 1000, "type": "UNKNOWN", "spo2": spo})
        hr_manual.append({"timestamp": ts * 1000, "heartRate": hr, "value": hr})
        hr_max.append({"timestamp": ts * 1000, "heartRateMax": hr + random.randint(0, 8), "value": hr + random.randint(0, 8)})
        hr_rest.append({"timestamp": ts * 1000, "heartRateResting": max(40, hr - random.randint(3, 10)), "value": max(40, hr - random.randint(3, 10))})

    return {
        "user_activity": activity,
        "user_sleep": sleep,
        "user_stress": stress,
        "user_spo2": spo2,
        "user_heart_rate_manual": hr_manual,
        "user_heart_rate_max": hr_max,
        "user_heart_rate_resting": hr_rest,
    }


def build_summary_payload(user: UserCred) -> dict[str, Any]:
    return {
        "active": random.randint(20, 400),
        "steps": random.randint(500, 12000),
        "heart_rate": random.randint(50, 100),
        "distance": random.randint(300, 8000),
        "calories": random.randint(100, 1500),
        "spo2": random.randint(92, 99),
        "stress": random.randint(15, 80),
        "sleep": random.randint(180, 520),
        "sleep_type": random.choice(["day", "night"]),
        "device_time": now_device_time(),
        "mac_address": user.mac_address,
        "employee_id": user.employee_id,
    }


def build_detail_payload(user: UserCred) -> dict[str, Any]:
    data = rand_metric_series()
    return {
        "device_time": now_device_time(),
        "mac_address": user.mac_address,
        "employee_id": user.employee_id,
        "app_version": "android-stress-1.0.0",
        **data,
    }


def read_users(csv_path: Path) -> list[UserCred]:
    users: list[UserCred] = []
    with csv_path.open("r", encoding="utf-8-sig", newline="") as f:
        rdr = csv.DictReader(f)
        for row in rdr:
            login = (row.get("login") or "").strip()
            password = (row.get("password") or "").strip()
            company = (row.get("company") or "").strip()
            employee_id_raw = (row.get("employee_id") or "").strip()
            mac = (row.get("mac_address") or "").strip()
            if not (login and password and company and employee_id_raw and mac):
                continue
            try:
                employee_id = int(employee_id_raw)
            except ValueError:
                continue
            users.append(
                UserCred(
                    login=login,
                    password=password,
                    employee_id=employee_id,
                    mac_address=mac,
                    company=company,
                )
            )
    return users


async def login_token(client: httpx.AsyncClient, base_url: str, user: UserCred) -> tuple[str | None, int, str]:
    url = f"{base_url.rstrip('/')}/api/login"
    headers = {"company": user.company, "accept": "application/json"}
    payload = {"email": user.login, "password": user.password}
    try:
        r = await client.post(url, json=payload, headers=headers)
    except Exception as e:
        return None, 0, str(e)
    if r.status_code != 200:
        err = ""
        try:
            err = json.dumps(r.json(), ensure_ascii=False)[:300]
        except Exception:
            err = (r.text or "")[:300]
        return None, r.status_code, err
    body = r.json()
    return body.get("token"), r.status_code, ""


async def hit_endpoint(
    client: httpx.AsyncClient,
    base_url: str,
    endpoint: str,
    token: str,
    company: str,
    payload: dict[str, Any],
) -> ReqResult:
    url = f"{base_url.rstrip('/')}/api/{endpoint}"
    headers = {
        "accept": "application/json",
        "content-type": "application/json",
        "authorization": f"Bearer {token}",
        "company": company,
    }
    t0 = time.perf_counter()
    try:
        r = await client.post(url, headers=headers, json=payload)
        dt = (time.perf_counter() - t0) * 1000.0
        ok = 200 <= r.status_code < 300
        err = ""
        if not ok:
            try:
                err = json.dumps(r.json(), ensure_ascii=False)[:300]
            except Exception:
                err = (r.text or "")[:300]
        return ReqResult(ok=ok, status=r.status_code, latency_ms=dt, endpoint=endpoint, error=err)
    except Exception as e:
        dt = (time.perf_counter() - t0) * 1000.0
        return ReqResult(ok=False, status=0, latency_ms=dt, endpoint=endpoint, error=str(e))


async def worker(
    sem: asyncio.Semaphore,
    client: httpx.AsyncClient,
    base_url: str,
    user: UserCred,
    requests_per_user: int,
) -> list[ReqResult]:
    results: list[ReqResult] = []
    async with sem:
        token, login_status, login_error = await login_token(client, base_url, user)
        if not token:
            error_text = f"login failed: {user.login}"
            if login_error:
                error_text = f"{error_text} | {login_error}"
            results.append(ReqResult(ok=False, status=login_status or 0, latency_ms=0, endpoint="login", error=error_text))
            return results

        for _ in range(requests_per_user):
            summary_payload = build_summary_payload(user)
            detail_payload = build_detail_payload(user)

            results.append(await hit_endpoint(client, base_url, "summary", token, user.company, summary_payload))
            results.append(await hit_endpoint(client, base_url, "detail", token, user.company, detail_payload))
    return results


def pct(sorted_vals: list[float], p: float) -> float:
    if not sorted_vals:
        return 0.0
    k = int((len(sorted_vals) - 1) * p)
    return sorted_vals[k]


def print_report(results: list[ReqResult], duration_s: float) -> None:
    total = len(results)
    ok = sum(1 for r in results if r.ok)
    fail = total - ok
    lats = [r.latency_ms for r in results if r.latency_ms > 0]
    lats_sorted = sorted(lats)
    by_ep: dict[str, list[ReqResult]] = {}
    for r in results:
        by_ep.setdefault(r.endpoint, []).append(r)

    print("\n=== STRESS TEST REPORT ===")
    print(f"total requests      : {total}")
    print(f"success             : {ok}")
    print(f"failed              : {fail}")
    print(f"duration            : {duration_s:.2f}s")
    print(f"throughput          : {(total / duration_s) if duration_s > 0 else 0:.2f} req/s")
    if lats:
        print(f"latency avg         : {statistics.mean(lats):.2f} ms")
        print(f"latency p50         : {pct(lats_sorted, 0.50):.2f} ms")
        print(f"latency p95         : {pct(lats_sorted, 0.95):.2f} ms")
        print(f"latency p99         : {pct(lats_sorted, 0.99):.2f} ms")
        print(f"latency max         : {max(lats):.2f} ms")

    print("\nby endpoint:")
    for ep, rs in sorted(by_ep.items()):
        ep_ok = sum(1 for x in rs if x.ok)
        ep_fail = len(rs) - ep_ok
        ep_lats = [x.latency_ms for x in rs if x.latency_ms > 0]
        ep_avg = statistics.mean(ep_lats) if ep_lats else 0.0
        print(f"  - {ep:8s} total={len(rs):5d} ok={ep_ok:5d} fail={ep_fail:5d} avg={ep_avg:8.2f}ms")

    failed = [r for r in results if not r.ok][:12]
    if failed:
        print("\nfailed samples:")
        for f in failed:
            print(f"  - [{f.endpoint}] status={f.status} err={f.error}")


def expand_users(users: list[UserCred], target_users: int) -> list[UserCred]:
    if target_users <= len(users):
        return users[:target_users]
    out = list(users)
    i = 0
    while len(out) < target_users:
        src = users[i % len(users)]
        out.append(src)
        i += 1
    return out


async def main_async(args: argparse.Namespace) -> int:
    csv_path = Path(args.users_csv)
    if not csv_path.exists():
        print(f"users csv not found: {csv_path}")
        return 2
    users = read_users(csv_path)
    if not users:
        print("no valid users in csv")
        return 2

    users = expand_users(users, args.target_users)
    print(f"loaded users        : {len(users)}")
    print(f"concurrency         : {args.concurrency}")
    print(f"requests per user   : {args.requests_per_user} (summary+detail per cycle)")
    print(f"base url            : {args.base_url}")

    timeout = httpx.Timeout(connect=15.0, read=120.0, write=120.0, pool=120.0)
    limits = httpx.Limits(max_keepalive_connections=max(100, args.concurrency), max_connections=max(200, args.concurrency * 2))
    sem = asyncio.Semaphore(args.concurrency)

    t0 = time.perf_counter()
    results: list[ReqResult] = []
    rounds = 0
    async with httpx.AsyncClient(timeout=timeout, limits=limits, verify=args.verify_tls) as client:
        if args.duration_seconds > 0:
            deadline = time.perf_counter() + args.duration_seconds
            while time.perf_counter() < deadline:
                rounds += 1
                tasks = [worker(sem, client, args.base_url, u, args.requests_per_user) for u in users]
                nested = await asyncio.gather(*tasks)
                for sub in nested:
                    results.extend(sub)
                if args.pause_seconds > 0:
                    await asyncio.sleep(args.pause_seconds)
        else:
            rounds = 1
            tasks = [worker(sem, client, args.base_url, u, args.requests_per_user) for u in users]
            nested = await asyncio.gather(*tasks)
            results = [item for sub in nested for item in sub]
    duration_s = time.perf_counter() - t0

    if rounds > 1:
        print(f"rounds              : {rounds}")
    print_report(results, duration_s)
    return 0


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(description="Stress test Savera API mobile uploads with real users")
    p.add_argument("--base-url", required=True, help="API host, e.g. https://savera_api.ungguldinamika.com")
    p.add_argument("--users-csv", required=True, help="CSV file with login,password,employee_id,mac_address,company")
    p.add_argument("--company", default="", help="Optional default company to fill empty CSV company")
    p.add_argument("--target-users", type=int, default=1000, help="Virtual users to run (will reuse CSV rows if less)")
    p.add_argument("--concurrency", type=int, default=300, help="Concurrent workers")
    p.add_argument("--requests-per-user", type=int, default=1, help="How many summary+detail cycles per user")
    p.add_argument("--duration-seconds", type=int, default=0, help="Run continuously for N seconds (0 = single round)")
    p.add_argument("--pause-seconds", type=float, default=0.0, help="Pause between rounds in duration mode")
    p.add_argument("--verify-tls", action="store_true", default=False, help="Enable TLS cert verification (default off)")
    return p


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    # Fill blank company from --company
    csv_path = Path(args.users_csv)
    rows: list[dict[str, str]] = []
    with csv_path.open("r", encoding="utf-8-sig", newline="") as f:
        rdr = csv.DictReader(f)
        fn = rdr.fieldnames or []
        for row in rdr:
            if args.company and not (row.get("company") or "").strip():
                row["company"] = args.company
            rows.append(row)
    with csv_path.open("w", encoding="utf-8", newline="") as f:
        wr = csv.DictWriter(f, fieldnames=rows[0].keys() if rows else ["login", "password", "employee_id", "mac_address", "company"])
        wr.writeheader()
        wr.writerows(rows)

    return asyncio.run(main_async(args))


if __name__ == "__main__":
    raise SystemExit(main())

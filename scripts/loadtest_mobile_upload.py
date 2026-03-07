#!/usr/bin/env python3
import argparse
import concurrent.futures
import datetime as dt
import json
import random
import statistics
import threading
import time
import urllib.error
import urllib.request
from collections import Counter


def parse_args():
    parser = argparse.ArgumentParser(description="Load test mobile upload with endpoint fallback.")
    parser.add_argument("--base-urls", required=True, help="Comma separated base urls, example: http://a,http://b,http://192.168.1.2:5001")
    parser.add_argument("--company", required=True, help="Company code header, example: SAVERA")
    parser.add_argument("--token", default="", help="Bearer token. If empty, script will try login.")
    parser.add_argument("--email", default="", help="Login email/username if token empty.")
    parser.add_argument("--password", default="", help="Login password if token empty.")
    parser.add_argument("--users", type=int, default=1000, help="Virtual users count.")
    parser.add_argument("--concurrency", type=int, default=100, help="Parallel workers.")
    parser.add_argument("--timeout", type=float, default=8.0, help="Request timeout per try (seconds).")
    parser.add_argument("--mode", choices=["summary", "detail", "both", "network"], default="both")
    parser.add_argument("--employee-id", type=int, default=1)
    parser.add_argument("--department-id", type=int, default=1)
    parser.add_argument("--shift-id", type=int, default=0, help="0 = null shift_id")
    parser.add_argument("--mac-address", default="AA:BB:CC:DD:EE:FF")
    parser.add_argument("--app-version", default="loadtest-1.0.0")
    return parser.parse_args()


def now_iso():
    return dt.datetime.now().isoformat(timespec="seconds")


def send_json(url, path, payload, headers, timeout):
    body = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        url=url.rstrip("/") + path,
        data=body,
        headers={**headers, "Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        text = resp.read().decode("utf-8", errors="replace")
        return resp.status, text


def send_with_fallback(base_urls, path, payload, headers, timeout):
    last_error = None
    last_base = ""
    for base in base_urls:
        last_base = base
        try:
            status, text = send_json(base, path, payload, headers, timeout)
            return {
                "ok": 200 <= status < 300,
                "status": status,
                "base_url": base,
                "error": "",
                "response": text[:300],
            }
        except urllib.error.HTTPError as ex:
            body = ex.read().decode("utf-8", errors="replace")
            return {
                "ok": False,
                "status": ex.code,
                "base_url": base,
                "error": body[:300],
                "response": body[:300],
            }
        except Exception as ex:
            last_error = str(ex)
            continue

    return {
        "ok": False,
        "status": 0,
        "base_url": last_base,
        "error": last_error or "all fallback failed",
        "response": "",
    }


def login_token(base_urls, company, email, password, timeout):
    headers = {"company": company}
    payload = {"email": email, "password": password}
    result = send_with_fallback(base_urls, "/api/login", payload, headers, timeout)
    if not result["ok"]:
        raise RuntimeError("login failed: " + result["error"])
    parsed = json.loads(result["response"] or "{}")
    token = parsed.get("token", "")
    if not token:
        raise RuntimeError("login success but token empty")
    return token


def build_summary_payload(args, user_no):
    steps = 3000 + (user_no % 7000)
    return {
        "active": 80,
        "active_text": "active",
        "steps": steps,
        "steps_text": str(steps),
        "heart_rate": 72,
        "heart_rate_text": "72",
        "distance": 2.1,
        "distance_text": "2.1km",
        "calories": 130,
        "calories_text": "130",
        "spo2": 98,
        "spo2_text": "98",
        "stress": 12,
        "stress_text": "12",
        "sleep": 420,
        "sleep_text": "07:00",
        "device_time": now_iso(),
        "mac_address": args.mac_address,
        "app_version": args.app_version,
        "employee_id": args.employee_id,
        "department_id": args.department_id,
        "shift_id": (None if args.shift_id <= 0 else args.shift_id),
        "is_fit1": 0,
        "is_fit2": 0,
        "is_fit3": 1,
        "upload_key": f"lt-summary-{user_no}-{int(time.time() * 1000)}",
        "request_id": f"lt-rid-{user_no}-{int(time.time() * 1000)}",
        "user_activity": "[]",
        "user_sleep": "[]",
        "user_stress": "[]",
        "user_spo2": "[]",
        "network_transport": "wifi",
        "is_network_available": True,
        "is_api_reachable": True,
        "is_api_slow": False,
        "latency_ms": random.randint(20, 120),
    }


def build_detail_payload(args, user_no):
    return {
        "device_time": now_iso(),
        "mac_address": args.mac_address,
        "app_version": args.app_version,
        "employee_id": args.employee_id,
        "upload_key": f"lt-detail-{user_no}-{int(time.time() * 1000)}",
        "user_activity": "[]",
        "user_sleep": "[]",
        "user_stress": "[]",
        "user_spo2": "[]",
        "user_heart_rate_max": "[]",
        "user_heart_rate_resting": "[]",
        "user_heart_rate_manual": "[]",
        "network_transport": "wifi",
        "is_network_available": True,
        "is_api_reachable": True,
        "is_api_slow": False,
        "latency_ms": random.randint(20, 120),
    }


def build_network_payload(args, user_no):
    return {
        "measured_at": now_iso(),
        "employee_id": args.employee_id,
        "mac_address": args.mac_address,
        "app_version": args.app_version,
        "network_transport": "wifi",
        "is_network_available": True,
        "is_api_reachable": True,
        "is_api_slow": False,
        "latency_ms": random.randint(20, 120),
        "trace_id": f"lt-net-{user_no}-{int(time.time() * 1000)}",
    }


def run_user(user_no, args, base_urls, headers):
    started = time.perf_counter()
    details = []

    if args.mode in ("summary", "both"):
        summary = send_with_fallback(
            base_urls,
            "/api/summary",
            build_summary_payload(args, user_no),
            headers,
            args.timeout,
        )
        details.append(("summary", summary))
        if not summary["ok"]:
            return time.perf_counter() - started, details

    if args.mode in ("detail", "both"):
        detail = send_with_fallback(
            base_urls,
            "/api/detail",
            build_detail_payload(args, user_no),
            headers,
            args.timeout,
        )
        details.append(("detail", detail))
        if not detail["ok"]:
            return time.perf_counter() - started, details

    if args.mode == "network":
        network = send_with_fallback(
            base_urls,
            "/api/network-probe",
            build_network_payload(args, user_no),
            headers,
            args.timeout,
        )
        details.append(("network", network))

    return time.perf_counter() - started, details


def percentile(sorted_values, p):
    if not sorted_values:
        return 0.0
    k = (len(sorted_values) - 1) * p
    f = int(k)
    c = min(f + 1, len(sorted_values) - 1)
    if f == c:
        return float(sorted_values[f])
    return float(sorted_values[f] * (c - k) + sorted_values[c] * (k - f))


def main():
    args = parse_args()
    base_urls = [x.strip().rstrip("/") for x in args.base_urls.split(",") if x.strip()]
    if not base_urls:
        raise SystemExit("base urls empty")

    token = args.token.strip()
    if not token:
        if not args.email or not args.password:
            raise SystemExit("token kosong. Isi --token atau --email + --password.")
        token = login_token(base_urls, args.company, args.email, args.password, args.timeout)
        print("Login success. token acquired.")

    headers = {
        "Authorization": f"Bearer {token}",
        "company": args.company,
    }

    print(f"Running load test: users={args.users}, concurrency={args.concurrency}, mode={args.mode}")
    start_wall = time.perf_counter()

    latencies = []
    ok_count = 0
    fail_count = 0
    endpoint_counter = Counter()
    status_counter = Counter()
    fail_samples = []
    lock = threading.Lock()

    with concurrent.futures.ThreadPoolExecutor(max_workers=args.concurrency) as executor:
        futures = [executor.submit(run_user, i + 1, args, base_urls, headers) for i in range(args.users)]
        for f in concurrent.futures.as_completed(futures):
            elapsed, details = f.result()
            with lock:
                latencies.append(elapsed)
            user_ok = True
            for kind, item in details:
                with lock:
                    status_counter[f"{kind}:{item['status']}"] += 1
                    if item["base_url"]:
                        endpoint_counter[item["base_url"]] += 1
                if not item["ok"]:
                    user_ok = False
                    if len(fail_samples) < 10:
                        fail_samples.append(
                            {
                                "kind": kind,
                                "status": item["status"],
                                "error": item["error"],
                                "base_url": item["base_url"],
                            }
                        )
            with lock:
                if user_ok:
                    ok_count += 1
                else:
                    fail_count += 1

    total_wall = time.perf_counter() - start_wall
    lat_sorted = sorted(latencies)

    print("\n=== RESULT ===")
    print(f"users_total      : {args.users}")
    print(f"users_success    : {ok_count}")
    print(f"users_failed     : {fail_count}")
    print(f"success_rate     : {(ok_count / max(1, args.users)) * 100:.2f}%")
    print(f"duration_seconds : {total_wall:.2f}")
    print(f"throughput_usr_s : {args.users / max(0.001, total_wall):.2f}")
    print(f"latency_avg_ms   : {statistics.mean(latencies) * 1000:.2f}")
    print(f"latency_p50_ms   : {percentile(lat_sorted, 0.50) * 1000:.2f}")
    print(f"latency_p95_ms   : {percentile(lat_sorted, 0.95) * 1000:.2f}")
    print(f"latency_p99_ms   : {percentile(lat_sorted, 0.99) * 1000:.2f}")

    print("\nendpoint_used:")
    for base, n in endpoint_counter.most_common():
        print(f"- {base}: {n}")

    print("\nstatus_count:")
    for k, n in status_counter.most_common():
        print(f"- {k}: {n}")

    if fail_samples:
        print("\nfail_samples:")
        for x in fail_samples:
            print("- " + json.dumps(x, ensure_ascii=False))


if __name__ == "__main__":
    main()

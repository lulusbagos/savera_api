<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savera Monitoring Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0b1f2a;
            --muted: #617385;
            --line: rgba(15,61,82,.16);
            --cyan: #05a8c9;
            --blue: #0b75d1;
            --green: #11845b;
            --red: #c23030;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "Space Grotesk", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 12% 10%, rgba(5,168,201,.22), transparent 28%),
                radial-gradient(circle at 88% 8%, rgba(17,132,91,.18), transparent 30%),
                linear-gradient(135deg, #f8fbfd 0%, #eef5f7 58%, #dfecef 100%);
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(15,61,82,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15,61,82,.045) 1px, transparent 1px);
            background-size: 44px 44px;
        }
        .card {
            width: min(460px, 100%);
            border-radius: 30px;
            padding: 30px;
            background: rgba(255,255,255,.88);
            border: 1px solid var(--line);
            box-shadow: 0 28px 80px rgba(13,48,68,.16);
            backdrop-filter: blur(18px);
            position: relative;
            overflow: hidden;
        }
        .card::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -100px;
            top: -110px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(5,168,201,.20), rgba(11,117,209,.10));
        }
        .brand {
            position: relative;
            z-index: 1;
            display: flex;
            gap: 14px;
            align-items: center;
            margin-bottom: 28px;
        }
        .mark {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: linear-gradient(135deg, #0f3344, var(--cyan));
            display: grid;
            place-items: center;
            color: white;
            font-family: "JetBrains Mono", monospace;
            font-weight: 700;
            box-shadow: 0 16px 30px rgba(5,168,201,.24);
        }
        .eyebrow {
            font-family: "JetBrains Mono", monospace;
            font-size: 11px;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--cyan);
            font-weight: 700;
        }
        h1 {
            margin: 4px 0 0;
            font-size: 25px;
            letter-spacing: -.7px;
        }
        p {
            position: relative;
            z-index: 1;
            margin: 0 0 22px;
            color: var(--muted);
            line-height: 1.55;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-family: "JetBrains Mono", monospace;
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }
        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 15px 16px;
            font: 700 16px "JetBrains Mono", monospace;
            outline: none;
            color: var(--ink);
            background: rgba(255,255,255,.9);
        }
        input:focus {
            border-color: rgba(5,168,201,.55);
            box-shadow: 0 0 0 4px rgba(5,168,201,.12);
        }
        button {
            width: 100%;
            margin-top: 16px;
            border: 0;
            border-radius: 18px;
            padding: 15px 18px;
            cursor: pointer;
            color: white;
            font-weight: 700;
            font-family: "Space Grotesk", sans-serif;
            background: linear-gradient(135deg, #0f3344, var(--cyan));
            box-shadow: 0 16px 34px rgba(5,168,201,.22);
        }
        .error {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 16px;
            color: var(--red);
            background: rgba(194,48,48,.10);
            border: 1px solid rgba(194,48,48,.18);
            font-size: 13px;
            font-weight: 700;
        }
        .hint {
            margin-top: 16px;
            font-family: "JetBrains Mono", monospace;
            font-size: 11px;
            color: var(--muted);
            text-align: center;
        }
    </style>
</head>
<body>
    <form class="card" method="POST" action="{{ route('dashboard-login.submit') }}">
        @csrf
        <div class="brand">
            <div class="mark">S</div>
            <div>
                <div class="eyebrow">Restricted Monitoring</div>
                <h1>Savera API Dashboard</h1>
            </div>
        </div>
        <p>Masukkan password untuk membuka dashboard monitoring API, upload queue, grafik performa, dan worker heartbeat.</p>
        @if ($error)
            <div class="error">{{ $error }}</div>
        @endif
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" autofocus required>
        <button type="submit">Masuk Dashboard</button>
        <div class="hint">Endpoint mobile upload tetap berjalan tanpa login dashboard.</div>
    </form>
</body>
</html>

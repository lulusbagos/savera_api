<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAVERA // OPS COMMAND CENTER</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        :root {
            --bg:            #f4f7fb;
            --bg2:           #ecf2fb;
            --bg3:           #e4ecf8;
            --panel:         #ffffff;
            --panel-hover:   #f7faff;
            --outline:       rgba(59, 130, 246, 0.16);
            --outline-strong:rgba(59, 130, 246, 0.26);
            --muted:         #6b7280;
            --text:          #0f172a;
            --text-dim:      #334155;
            --cyan:   #0284c7;
            --blue:   #2563eb;
            --purple: #7c3aed;
            --green:  #059669;
            --amber:  #d97706;
            --red:    #dc2626;
            --pink:   #db2777;
            --teal:   #0f766e;
            --glow-cyan:   rgba(2, 132, 199, 0.12);
            --glow-blue:   rgba(37, 99, 235, 0.12);
            --glow-purple: rgba(124, 58, 237, 0.12);
            --glow-green:  rgba(5, 150, 105, 0.12);
            --glow-amber:  rgba(217, 119, 6, 0.12);
            --glow-red:    rgba(220, 38, 38, 0.12);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }
        body.theme-dark {
            --bg:            #0b1220;
            --bg2:           #111a2b;
            --bg3:           #162238;
            --panel:         #101b2e;
            --panel-hover:   #16243b;
            --outline:       rgba(96, 165, 250, 0.20);
            --outline-strong:rgba(96, 165, 250, 0.32);
            --muted:         #93a4be;
            --text:          #e6edf7;
            --text-dim:      #c7d2e5;
            --cyan:          #38bdf8;
            --blue:          #60a5fa;
            --purple:        #a78bfa;
            --green:         #34d399;
            --amber:         #fbbf24;
            --red:           #f87171;
            --pink:          #f472b6;
            --teal:          #2dd4bf;
            --glow-cyan:     rgba(56, 189, 248, 0.12);
            --glow-blue:     rgba(96, 165, 250, 0.12);
            --glow-purple:   rgba(167, 139, 250, 0.12);
            --glow-green:    rgba(52, 211, 153, 0.12);
            --glow-amber:    rgba(251, 191, 36, 0.12);
            --glow-red:      rgba(248, 113, 113, 0.12);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse 100% 60% at 20% -10%, rgba(37,99,235,0.10) 0%, transparent 60%),
                radial-gradient(ellipse 70% 50% at 80% 5%, rgba(124,58,237,0.08) 0%, transparent 55%),
                radial-gradient(ellipse 60% 80% at 50% 110%, rgba(14,165,233,0.08) 0%, transparent 60%);
            color: var(--text);
            font-family: 'Poppins', system-ui, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(0,200,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,200,255,0.025) 1px, transparent 1px);
            background-size: 52px 52px;
        }
        body::after {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.05) 2px, rgba(0,0,0,0.05) 4px);
        }
        .topbar-glow {
            position: fixed; top: 0; left: 0; right: 0; height: 2px; z-index: 200;
            background: linear-gradient(90deg, transparent 0%, rgba(37,99,235,0.45) 50%, transparent 100%);
            opacity: 0.85;
        }
        .topnav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px; height: 58px;
            background: rgba(255,255,255,0.95);
            border-bottom: 1px solid var(--outline);
            backdrop-filter: blur(16px);
            position: sticky; top: 0; z-index: 100;
        }
        .nav-brand { display: flex; align-items: center; gap: 12px; }
        .nav-logo {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, var(--cyan), var(--purple));
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.12);
        }
        .nav-logo svg { width: 18px; height: 18px; stroke: #fff; stroke-width: 2; }
        .nav-title { font-weight: 700; font-size: 14px; letter-spacing: 0.12em; text-transform: uppercase; color: var(--blue); }
        .nav-subtitle { font-size: 10px; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; font-family: 'JetBrains Mono', monospace; }
        .nav-right { display: flex; align-items: center; gap: 10px; }
        .live-pill {
            display: flex; align-items: center; gap: 7px;
            padding: 5px 12px; border-radius: 999px;
            background: rgba(0,255,170,0.08);
            border: 1px solid rgba(0,255,170,0.25);
            color: var(--green); font-size: 11px; font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
        }
        .live-pill.paused { background: rgba(255,184,0,0.08); border-color: rgba(255,184,0,0.25); color: var(--amber); }
        .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green); animation: pulse-dot 1.4s ease-in-out infinite; flex-shrink: 0; }
        .dot.paused { background: var(--amber); animation: none; }
        @keyframes pulse-dot {
            0%,100% { opacity: 0.5; transform: scale(0.95); }
            50%      { opacity: 1; transform: scale(1.12); }
        }
        .nav-btn {
            padding: 6px 14px; border-radius: var(--radius-sm);
            border: 1px solid var(--outline-strong); background: rgba(0,212,255,0.06);
            color: var(--text-dim); font-weight: 600; font-size: 11px;
            letter-spacing: 0.05em; text-transform: uppercase; cursor: pointer; transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }
        .nav-btn:hover { background: rgba(0,212,255,0.12); color: var(--cyan); border-color: rgba(0,212,255,0.4); }
        .nav-btn.primary { background: linear-gradient(135deg, rgba(0,212,255,0.15), rgba(176,96,255,0.12)); border-color: rgba(0,212,255,0.4); color: var(--cyan); }
        .theme-toggle {
            min-width: 130px;
            text-transform: none;
            letter-spacing: 0.02em;
        }

        /* â”€â”€ TICKER â”€â”€ */
        .ticker-bar {
            background: rgba(0,212,255,0.03); border-bottom: 1px solid var(--outline);
            padding: 0 28px; height: 26px; overflow: hidden;
            display: flex; align-items: center;
        }
        .ticker-label { font-size: 9px; font-weight: 700; color: var(--cyan); text-transform: uppercase; letter-spacing: 0.1em; font-family: 'JetBrains Mono', monospace; white-space: nowrap; margin-right: 16px; flex-shrink: 0; }
        .ticker-scroll { flex: 1; overflow: hidden; white-space: nowrap; }
        .ticker-inner { display: inline-block; font-size: 10px; color: var(--muted); font-family: 'JetBrains Mono', monospace; animation: ticker-run 30s linear infinite; }
        .ticker-inner span { margin: 0 24px; }
        .ticker-inner .tg { color: var(--green); }
        .ticker-inner .tw { color: var(--amber); }
        .ticker-inner .tb { color: var(--red); }
        @keyframes ticker-run { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

        /* â”€â”€ SHELL â”€â”€ */
        .shell { max-width: 1440px; margin: 0 auto; padding: 28px 28px 60px; position: relative; z-index: 1; }

        /* â”€â”€ PAGE HEADER â”€â”€ */
        .page-header { margin-bottom: 28px; }
        .page-header h1 {
            font-size: 32px; font-weight: 700; letter-spacing: -0.5px;
            background: linear-gradient(135deg, #0f172a 0%, var(--blue) 55%, var(--purple) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            line-height: 1.1;
        }
        .page-header-sub { font-size: 11px; color: var(--muted); margin-top: 6px; letter-spacing: 0.1em; text-transform: uppercase; font-family: 'JetBrains Mono', monospace; }
        .meta-strip { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 16px; }
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 999px;
            border: 1px solid var(--outline); background: var(--panel);
            color: var(--muted); font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
        }
        .badge.health-good  { background: rgba(0,255,170,0.08); border-color: rgba(0,255,170,0.3); color: var(--green); }
        .badge.health-warn  { background: rgba(255,184,0,0.08); border-color: rgba(255,184,0,0.3); color: var(--amber); }
        .badge.health-bad   { background: rgba(255,60,90,0.08); border-color: rgba(255,60,90,0.3); color: var(--red); }

        /* â”€â”€ SECTION LABEL â”€â”€ */
        .section-label {
            font-size: 10px; font-weight: 700; letter-spacing: 0.16em;
            text-transform: uppercase; color: var(--cyan);
            margin: 32px 0 14px;
            display: flex; align-items: center; gap: 10px;
            font-family: 'JetBrains Mono', monospace;
        }
        .section-label .sl-icon { width: 5px; height: 5px; background: var(--cyan); border-radius: 50%; flex-shrink: 0; }
        .section-label::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, var(--outline-strong), transparent); }

        /* â”€â”€ GRID â”€â”€ */
        .grid { display: grid; gap: 14px; }
        .grid-6 { grid-template-columns: repeat(6, 1fr); }
        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); }
        @media (max-width: 1200px) { .grid-6 { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 760px)  { .grid-6 { grid-template-columns: repeat(2, 1fr); } .grid-2 { grid-template-columns: 1fr; } }

        /* â”€â”€ CARD â”€â”€ */
        .card {
            background: var(--panel);
            border: 1px solid var(--outline);
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            position: relative; overflow: hidden;
            backdrop-filter: blur(10px);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .card::before { content: ''; position: absolute; top: 0; left: 0; width: 14px; height: 14px; border-top: 1px solid rgba(0,212,255,0.5); border-left: 1px solid rgba(0,212,255,0.5); border-radius: var(--radius-lg) 0 0 0; pointer-events: none; }
        .card::after  { content: ''; position: absolute; bottom: 0; right: 0; width: 14px; height: 14px; border-bottom: 1px solid rgba(0,212,255,0.3); border-right: 1px solid rgba(0,212,255,0.3); border-radius: 0 0 var(--radius-lg) 0; pointer-events: none; }
        .card:hover { border-color: var(--outline-strong); }
        .card-cyan,
        .card-purple,
        .card-green,
        .card-amber,
        .card-red { box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06); }
        .card-cyan:hover,
        .card-purple:hover,
        .card-green:hover,
        .card-amber:hover,
        .card-red:hover { box-shadow: 0 12px 26px rgba(15, 23, 42, 0.09); }
        .card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 6px; }
        .card-title { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
        .card h3 { font-size: 15px; font-weight: 700; color: var(--text); }

        /* â”€â”€ STAT CARD â”€â”€ */
        .stat-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
        .stat-icon svg { width: 20px; height: 20px; stroke-width: 1.8; }
        .stat-icon.cyan   { background: var(--glow-cyan);   color: var(--cyan); }
        .stat-icon.purple { background: var(--glow-purple); color: var(--purple); }
        .stat-icon.green  { background: var(--glow-green);  color: var(--green); }
        .stat-icon.amber  { background: var(--glow-amber);  color: var(--amber); }
        .stat-icon.red    { background: var(--glow-red);    color: var(--red); }
        .stat-icon.blue   { background: var(--glow-blue);   color: var(--blue); }
        .stat-value { font-size: 36px; font-weight: 700; letter-spacing: -1.5px; line-height: 1; color: var(--text); font-family: 'JetBrains Mono', monospace; }
        .stat-value.glow-cyan   { color: var(--cyan); }
        .stat-value.glow-green  { color: var(--green); }
        .stat-value.glow-red    { color: var(--red); }
        .stat-value.glow-amber  { color: var(--amber); }
        .stat-value.glow-purple { color: var(--purple); }

        .bubble-field {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }
        .bubble {
            position: absolute;
            bottom: -90px;
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.10);
            border: 1px solid rgba(37, 99, 235, 0.14);
            animation: bubble-up linear infinite;
        }
        @keyframes bubble-up {
            0% {
                transform: translateY(0) scale(0.85);
                opacity: 0;
            }
            12% { opacity: 0.55; }
            88% { opacity: 0.42; }
            100% {
                transform: translateY(-120vh) scale(1.08);
                opacity: 0;
            }
        }
        .bubble.b1 { left: 4%; width: 24px; height: 24px; animation-duration: 22s; animation-delay: 0s; }
        .bubble.b2 { left: 11%; width: 14px; height: 14px; animation-duration: 16s; animation-delay: 2s; }
        .bubble.b3 { left: 19%; width: 30px; height: 30px; animation-duration: 24s; animation-delay: 3s; }
        .bubble.b4 { left: 30%; width: 18px; height: 18px; animation-duration: 17s; animation-delay: 1s; }
        .bubble.b5 { left: 42%; width: 28px; height: 28px; animation-duration: 26s; animation-delay: 4s; }
        .bubble.b6 { left: 56%; width: 16px; height: 16px; animation-duration: 18s; animation-delay: 2.5s; }
        .bubble.b7 { left: 68%; width: 26px; height: 26px; animation-duration: 23s; animation-delay: 5s; }
        .bubble.b8 { left: 79%; width: 13px; height: 13px; animation-duration: 15s; animation-delay: 1.5s; }
        .bubble.b9 { left: 88%; width: 20px; height: 20px; animation-duration: 20s; animation-delay: 3.5s; }
        .bubble.b10 { left: 95%; width: 15px; height: 15px; animation-duration: 19s; animation-delay: 0.8s; }
        .stat-label { font-size: 11px; font-weight: 600; color: var(--muted); margin-top: 5px; text-transform: uppercase; letter-spacing: 0.08em; }
        .stat-tags  { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 12px; }
        .tag { padding: 3px 8px; border-radius: 5px; font-size: 10px; font-weight: 700; background: rgba(255,255,255,0.05); color: var(--muted); letter-spacing: 0.04em; text-transform: uppercase; }
        .tag.good   { background: rgba(0,255,170,0.10); color: var(--green); }
        .tag.warn   { background: rgba(255,184,0,0.10); color: var(--amber); }
        .tag.bad    { background: rgba(255,60,90,0.10); color: var(--red); }
        .tag.info   { background: rgba(0,212,255,0.10); color: var(--cyan); }
        .tag.purple { background: rgba(176,96,255,0.10); color: var(--purple); }

        /* â”€â”€ INFRA â”€â”€ */
        .infra-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
        .infra-card { background: var(--panel); border: 1px solid var(--outline); border-radius: var(--radius-md); padding: 16px 18px; position: relative; overflow: hidden; transition: border-color 0.25s; }
        .infra-card:hover { border-color: var(--outline-strong); }
        .infra-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--cyan), transparent); opacity: 0.4; }
        .infra-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .infra-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--green); flex-shrink: 0; animation: pulse-dot 2s ease-in-out infinite; }
        .infra-dot.warn { background: var(--amber); }
        .infra-dot.bad  { background: var(--red); }
        .infra-icon { display:flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:7px; background: rgba(0,212,255,0.1); color:var(--cyan); }
        .infra-icon svg { width:14px; height:14px; stroke-width:2; }
        .infra-name { font-size: 12px; font-weight: 700; color: var(--text); letter-spacing: 0.04em; }
        .infra-value { font-size: 22px; font-weight: 700; color: var(--cyan); letter-spacing: -0.5px; font-family: 'JetBrains Mono', monospace; }
        .infra-detail { font-size: 10px; color: var(--muted); margin-top: 3px; letter-spacing: 0.04em; }
        .infra-bar { height: 3px; border-radius: 999px; background: rgba(255,255,255,0.06); margin-top: 12px; overflow: hidden; }
        .infra-bar-fill { height: 100%; border-radius: 999px; transition: width 0.6s ease; }
        .infra-bar-fill.cyan   { background: linear-gradient(90deg, var(--cyan), var(--blue)); }
        .infra-bar-fill.green  { background: linear-gradient(90deg, var(--green), var(--teal)); }
        .infra-bar-fill.amber  { background: linear-gradient(90deg, var(--amber), #ff8c00); }
        .infra-bar-fill.purple { background: linear-gradient(90deg, var(--purple), var(--pink)); }
        .infra-bar-fill.indigo { background: linear-gradient(90deg, var(--blue), var(--cyan)); }

        /* â”€â”€ ALERT â”€â”€ */
        .alert-list { display: flex; flex-direction: column; gap: 8px; margin-top: 14px; }
        .alert-grid { columns: 2; gap: 8px; }
        @media (max-width: 900px) { .alert-grid { columns: 1; } }
        .alert { border: 1px solid var(--outline); border-radius: var(--radius-md); padding: 12px 14px; background: var(--panel); position: relative; overflow: hidden; transition: border-color 0.2s; break-inside: avoid; margin-bottom: 8px; }
        .alert:hover { border-color: var(--outline-strong); }
        .alert-error   { border-left: 3px solid var(--red); }
        .alert-warning { border-left: 3px solid var(--amber); }
        .alert-info    { border-left: 3px solid var(--blue); }
        .alert-top { display: flex; align-items: center; gap: 8px; justify-content: space-between; }
        .level { padding: 3px 8px; border-radius: 5px; font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
        .level.info     { background: rgba(61,127,255,0.15); color: var(--blue); }
        .level.warning  { background: rgba(255,184,0,0.15); color: var(--amber); }
        .level.error    { background: rgba(255,60,90,0.15); color: var(--red); }
        .level.critical { background: rgba(255,60,90,0.20); color: var(--red); border: 1px solid rgba(255,60,90,0.4); }
        .alert-msg  { font-size: 11px; color: var(--text-dim); margin: 8px 0 6px; line-height: 1.55; word-break: break-word; }
        .alert-foot { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        /* â”€â”€ CHIPS â”€â”€ */
        .chip { padding: 5px 12px; border-radius: 999px; border: 1px solid var(--outline); background: var(--panel); color: var(--muted); font-size: 10px; font-weight: 700; cursor: pointer; transition: all 0.15s; letter-spacing: 0.06em; text-transform: uppercase; }
        .chip:hover { border-color: rgba(0,212,255,0.3); color: var(--cyan); background: rgba(0,212,255,0.06); }
        .chip.active { color: var(--cyan); border-color: rgba(0,212,255,0.45); background: rgba(0,212,255,0.10); }
        .pill-outline { padding: 3px 10px; border-radius: 999px; border: 1px solid var(--outline); color: var(--muted); font-size: 10px; font-family: 'JetBrains Mono', monospace; }
        .method-chip { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; background: rgba(0,212,255,0.12); color: var(--cyan); font-family: 'JetBrains Mono', monospace; }
        .method-chip.post { background: rgba(0,255,170,0.10); color: var(--green); }
        .method-chip.put  { background: rgba(255,184,0,0.10); color: var(--amber); }
        .method-chip.delete { background: rgba(255,60,90,0.10); color: var(--red); }
        .status-2xx { color: var(--green); font-weight: 700; }
        .status-4xx { color: var(--amber); font-weight: 700; }
        .status-5xx { color: var(--red);   font-weight: 700; }

        /* â”€â”€ TABLE â”€â”€ */
        .table-wrap { overflow-x: auto; }
        .table-wrap::-webkit-scrollbar { height: 4px; }
        .table-wrap::-webkit-scrollbar-thumb { background: rgba(0,212,255,0.2); border-radius: 2px; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { border-bottom: 1px solid var(--outline-strong); }
        th { padding: 10px; text-align: left; color: var(--cyan); font-size: 9px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; font-family: 'JetBrains Mono', monospace; background: rgba(0,212,255,0.03); }
        td { padding: 9px 10px; border-bottom: 1px solid rgba(0,200,255,0.04); font-size: 11px; color: var(--text-dim); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(0,212,255,0.03); }
        .table-scroll { max-height: 420px; overflow-y: auto; }
        .table-scroll::-webkit-scrollbar { width: 4px; }
        .table-scroll::-webkit-scrollbar-thumb { background: rgba(0,212,255,0.15); border-radius: 2px; }
        .category-chip { padding: 2px 7px; border-radius: 5px; font-weight: 700; font-size: 10px; letter-spacing: 0.04em; text-transform: uppercase; }
        .category-chip.api      { background: rgba(0,212,255,0.12); color: var(--cyan); }
        .category-chip.database { background: rgba(255,184,0,0.12); color: var(--amber); }
        .category-chip.storage  { background: rgba(0,255,170,0.12); color: var(--green); }
        .category-chip.general  { background: rgba(255,255,255,0.05); color: var(--muted); }
        .timestamp { color: var(--muted); font-size: 10px; font-family: 'JetBrains Mono', monospace; }
        .level-pill { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
        .level-pill.info    { background: rgba(61,127,255,0.12); color: var(--blue); }
        .level-pill.warning { background: rgba(255,184,0,0.12); color: var(--amber); }
        .level-pill.error, .level-pill.critical { background: rgba(255,60,90,0.12); color: var(--red); }
        .status-good { color: var(--green); font-weight: 700; }
        .status-warn { color: var(--amber); font-weight: 700; }
        .status-bad  { color: var(--red);   font-weight: 700; }

        /* â”€â”€ CHART â”€â”€ */
        .chart-card { display: flex; flex-direction: column; gap: 14px; }
        .chart-wrap { height: 280px; position: relative; }
        .chart-hint { font-size: 10px; color: var(--muted); font-family: 'JetBrains Mono', monospace; line-height: 1.6; }

        /* â”€â”€ MISC â”€â”€ */
        .empty { color: var(--muted); padding: 32px 0; text-align: center; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; font-family: 'JetBrains Mono', monospace; }
        .mono  { font-family: 'JetBrains Mono', monospace; }
        .muted { color: var(--muted); font-size: 11px; }
        .divider { height: 1px; background: var(--outline); margin: 8px 0; }
        footer { margin-top: 40px; color: var(--muted); font-size: 10px; text-align: center; padding-bottom: 16px; letter-spacing: 0.08em; text-transform: uppercase; font-family: 'JetBrains Mono', monospace; }
        footer code { background: rgba(0,212,255,0.08); padding: 2px 8px; border-radius: 4px; color: var(--cyan); border: 1px solid var(--outline); }
        .filter-row { display: flex; gap: 6px; flex-wrap: wrap; }

        /* Comfort theme refresh: calmer light mode, softer dark mode */
        :root {
            --surface-glass: rgba(255, 255, 255, 0.82);
            --surface-soft: rgba(248, 251, 255, 0.92);
            --nav-surface: rgba(255, 255, 255, 0.86);
            --table-head: rgba(37, 99, 235, 0.055);
            --table-row-hover: rgba(14, 165, 233, 0.055);
            --chart-tick: #5b6b83;
            --chart-grid: rgba(71, 85, 105, 0.12);
            --tooltip-bg: rgba(255, 255, 255, 0.97);
            --tooltip-border: rgba(37, 99, 235, 0.22);
            --shadow-soft: 0 20px 48px rgba(15, 23, 42, 0.08);
            --shadow-card: 0 12px 30px rgba(15, 23, 42, 0.065);
        }

        body.theme-dark {
            --surface-glass: rgba(15, 27, 46, 0.78);
            --surface-soft: rgba(16, 27, 46, 0.92);
            --nav-surface: rgba(10, 18, 32, 0.82);
            --table-head: rgba(56, 189, 248, 0.08);
            --table-row-hover: rgba(56, 189, 248, 0.08);
            --chart-tick: #9fb0c8;
            --chart-grid: rgba(148, 163, 184, 0.12);
            --tooltip-bg: rgba(15, 23, 42, 0.96);
            --tooltip-border: rgba(56, 189, 248, 0.26);
            --shadow-soft: 0 22px 58px rgba(0, 0, 0, 0.30);
            --shadow-card: 0 18px 42px rgba(0, 0, 0, 0.24);
        }

        body {
            background:
                radial-gradient(900px 520px at 8% -16%, rgba(37, 99, 235, 0.13), transparent 62%),
                radial-gradient(720px 420px at 92% 0%, rgba(20, 184, 166, 0.11), transparent 58%),
                radial-gradient(720px 460px at 52% 112%, rgba(14, 165, 233, 0.10), transparent 60%),
                linear-gradient(180deg, var(--bg) 0%, var(--bg2) 48%, var(--bg3) 100%);
        }

        body.theme-dark {
            background:
                radial-gradient(980px 540px at 8% -18%, rgba(56, 189, 248, 0.16), transparent 62%),
                radial-gradient(760px 460px at 96% 2%, rgba(45, 212, 191, 0.11), transparent 58%),
                radial-gradient(780px 520px at 54% 116%, rgba(167, 139, 250, 0.11), transparent 60%),
                linear-gradient(180deg, #07101f 0%, #0b1424 48%, #0d1728 100%);
        }

        body::before {
            background-image:
                linear-gradient(var(--chart-grid) 1px, transparent 1px),
                linear-gradient(90deg, var(--chart-grid) 1px, transparent 1px);
            opacity: 0.62;
        }

        body::after {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.08), transparent 24%, rgba(15,23,42,0.03)),
                repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(15,23,42,0.018) 3px, rgba(15,23,42,0.018) 5px);
            opacity: 0.42;
        }

        body.theme-dark::after {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.035), transparent 26%, rgba(0,0,0,0.14)),
                repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(255,255,255,0.018) 3px, rgba(255,255,255,0.018) 5px);
            opacity: 0.55;
        }

        .topnav {
            background: var(--nav-surface);
            border-bottom-color: var(--outline);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }

        body.theme-dark .topnav {
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.24);
        }

        .ticker-bar {
            background: var(--surface-glass);
            backdrop-filter: blur(16px);
        }

        .page-header {
            border: 1px solid var(--outline);
            border-radius: 24px;
            padding: 24px;
            background: linear-gradient(145deg, var(--surface-glass), rgba(255,255,255,0.48));
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(18px);
        }

        body.theme-dark .page-header {
            background: linear-gradient(145deg, rgba(15,27,46,.88), rgba(16,36,59,.64));
        }

        .page-header h1 {
            background: linear-gradient(135deg, var(--text) 0%, var(--blue) 58%, var(--teal) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-btn,
        .chip,
        .pill-outline,
        .badge,
        .tag {
            border-radius: 999px;
        }

        .nav-btn {
            background: var(--surface-soft);
            border-color: var(--outline);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .nav-btn:hover {
            transform: translateY(-1px);
            background: rgba(14, 165, 233, 0.10);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        }

        .theme-toggle {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.12), rgba(45, 212, 191, 0.10));
            color: var(--blue);
        }

        body.theme-dark .theme-toggle {
            color: var(--cyan);
        }

        .card,
        .infra-card,
        .alert {
            background: linear-gradient(180deg, var(--surface-glass), var(--surface-soft));
            border-color: var(--outline);
            box-shadow: var(--shadow-card);
        }

        body.theme-dark .card,
        body.theme-dark .infra-card,
        body.theme-dark .alert {
            background: linear-gradient(180deg, rgba(16,27,46,.88), rgba(14,25,43,.94));
        }

        .card:hover,
        .infra-card:hover,
        .alert:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-soft);
        }

        .card::before,
        .card::after {
            opacity: 0.42;
        }

        th {
            background: var(--table-head);
            color: var(--blue);
        }

        body.theme-dark th {
            color: var(--cyan);
        }

        td {
            border-bottom-color: var(--outline);
        }

        tr:hover td {
            background: var(--table-row-hover);
        }

        .chart-wrap {
            border: 1px solid var(--outline);
            border-radius: 16px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.34);
        }

        body.theme-dark .chart-wrap {
            background: rgba(2, 6, 23, 0.18);
        }

        .bubble {
            background: rgba(14, 165, 233, 0.08);
            border-color: rgba(14, 165, 233, 0.16);
        }

        body.theme-dark .bubble {
            background: rgba(56, 189, 248, 0.08);
            border-color: rgba(56, 189, 248, 0.16);
        }

        @media (prefers-reduced-motion: reduce) {
            .bubble,
            .ticker-inner,
            .dot {
                animation: none !important;
            }

            .card,
            .infra-card,
            .alert,
            .nav-btn {
                transition: none !important;
            }
        }

        @media (max-width: 700px) {
            .shell { padding: 14px; }
            .topnav { padding: 0 14px; }
            .page-header h1 { font-size: 22px; }
            .ticker-bar { display: none; }
        }
    </style>
</head>
<body>

    <div class="bubble-field" aria-hidden="true">
        <span class="bubble b1"></span>
        <span class="bubble b2"></span>
        <span class="bubble b3"></span>
        <span class="bubble b4"></span>
        <span class="bubble b5"></span>
        <span class="bubble b6"></span>
        <span class="bubble b7"></span>
        <span class="bubble b8"></span>
        <span class="bubble b9"></span>
        <span class="bubble b10"></span>
    </div>

    <div class="topbar-glow"></div>

    <!-- TICKER BAR -->
    <div class="ticker-bar">
        <div class="ticker-label">&#9672; SYS</div>
        <div class="ticker-scroll">
            <div class="ticker-inner">
                <span>NGINX <span class="tg">ACTIVE</span></span>
                <span>PHP-CGI <span class="tg">96 WORKERS</span></span>
                <span>CLOUDFLARE TUNNEL <span class="tg">RUNNING</span></span>
                <span>POSTGRESQL <span class="tg">CONNECTED</span></span>
                <span>AUTO-REFRESH <span class="tg">15s</span></span>
                <span>NGINX <span class="tg">ACTIVE</span></span>
                <span>PHP-CGI <span class="tg">96 WORKERS</span></span>
                <span>CLOUDFLARE TUNNEL <span class="tg">RUNNING</span></span>
                <span>POSTGRESQL <span class="tg">CONNECTED</span></span>
                <span>AUTO-REFRESH <span class="tg">15s</span></span>
            </div>
        </div>
    </div>

    <!-- TOP NAV -->
    <nav class="topnav">
        <div class="nav-brand">
            <div class="nav-logo"><i data-lucide="shield"></i></div>
            <div>
                <div class="nav-title">Savera // Ops Center</div>
                <div class="nav-subtitle">API Infrastructure Command Center</div>
            </div>
        </div>
        <div class="nav-right">
            <div class="live-pill" id="live-pill">
                <div class="dot" id="live-dot"></div>
                <span id="last-updated">Connecting...</span>
            </div>
            <button class="nav-btn theme-toggle" id="theme-toggle-btn">
                <i data-lucide="moon" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>Dark
            </button>
            <a href="/docs" class="nav-btn"><i data-lucide="book-open" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>API Docs</a>
            <a href="/upload-monitoring" class="nav-btn"><i data-lucide="upload-cloud" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>Upload Monitor</a>
            <form method="POST" action="{{ route('dashboard-logout') }}" style="margin:0">
                @csrf
                <button type="submit" class="nav-btn"><i data-lucide="log-out" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>Logout</button>
            </form>
            <button class="nav-btn" id="refresh-btn"><i data-lucide="refresh-cw" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>Refresh</button>
            <button class="nav-btn primary" id="pause-btn"><i data-lucide="pause" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>Pause</button>
        </div>
    </nav>

    <div class="shell">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1>Operations Center</h1>
            <div class="page-header-sub">Monitor API &middot; Mobile &middot; Database &middot; Storage &middot; Infrastructure &middot; Realtime</div>
            <div class="meta-strip">
                <span class="badge health-good" id="health-badge">&#9679; Initializing...</span>
                <span class="badge" id="uptime-chip"><i data-lucide="timer" style="width:11px;height:11px;vertical-align:middle"></i>&nbsp;Uptime: &ndash;</span>
                <span class="badge" id="log-file-meta"><i data-lucide="file-text" style="width:11px;height:11px;vertical-align:middle"></i>&nbsp;Log: &ndash;</span>
                <span class="badge" id="auto-chip"><i data-lucide="refresh-cw" style="width:11px;height:11px;vertical-align:middle"></i>&nbsp;Auto 5s realtime</span>
            </div>
        </div>

        <!-- SECTION: SERVER INFRASTRUCTURE -->
        <div class="section-label"><span class="sl-icon"></span>Server Infrastructure</div>
        <div class="infra-grid">
            <div class="infra-card">
                <div class="infra-header"><div class="infra-dot"></div><div class="infra-icon"><i data-lucide="server"></i></div><div class="infra-name">Nginx</div></div>
                <div class="infra-value">Active</div><div class="infra-detail">worker_processes auto &middot; 4096 conn</div>
                <div class="infra-bar"><div class="infra-bar-fill green" style="width:92%"></div></div>
            </div>
            <div class="infra-card">
                <div class="infra-header"><div class="infra-dot"></div><div class="infra-icon"><i data-lucide="cpu"></i></div><div class="infra-name">PHP-CGI Workers</div></div>
                <div class="infra-value">12 &times; 8</div><div class="infra-detail">96 concurrent &middot; OPcache 256MB</div>
                <div class="infra-bar"><div class="infra-bar-fill cyan" style="width:88%"></div></div>
            </div>
            <div class="infra-card">
                <div class="infra-header"><div class="infra-dot"></div><div class="infra-icon"><i data-lucide="cloud"></i></div><div class="infra-name">Cloudflare Tunnel</div></div>
                <div class="infra-value">Running</div><div class="infra-detail">savera_api.ungguldinamika.com</div>
                <div class="infra-bar"><div class="infra-bar-fill green" style="width:100%"></div></div>
            </div>
            <div class="infra-card">
                <div class="infra-header"><div class="infra-dot warn"></div><div class="infra-icon"><i data-lucide="layers"></i></div><div class="infra-name">PHP Queue</div></div>
                <div class="infra-value mono" id="queue-mode">&ndash;</div><div class="infra-detail" id="queue-status">Loading...</div>
                <div class="infra-bar"><div class="infra-bar-fill amber" id="queue-bar" style="width:60%"></div></div>
            </div>
            <div class="infra-card">
                <div class="infra-header"><div class="infra-dot"></div><div class="infra-icon"><i data-lucide="database"></i></div><div class="infra-name">PostgreSQL</div></div>
                <div class="infra-value">Connected</div><div class="infra-detail">Internal Network &middot; saveradata</div>
                <div class="infra-bar"><div class="infra-bar-fill green" style="width:82%"></div></div>
            </div>
            <div class="infra-card">
                <div class="infra-header"><div class="infra-dot"></div><div class="infra-icon"><i data-lucide="file-text"></i></div><div class="infra-name">Log Source</div></div>
                <div class="infra-value" id="log-size">&ndash; MB</div><div class="infra-detail" id="log-last-time">&ndash;</div>
                <div class="infra-bar"><div class="infra-bar-fill purple" id="log-bar" style="width:10%"></div></div>
            </div>
        </div>

        <!-- SECTION: API METRICS -->
        <div class="section-label"><span class="sl-icon"></span>API Metrics</div>
        <div class="grid grid-6">
            <div class="card card-green">
                <div class="stat-icon green"><i data-lucide="check-circle-2"></i></div>
                <div class="stat-value glow-green" id="api-success">0</div>
                <div class="stat-label">Request Berhasil</div>
                <div class="stat-tags"><span class="tag info" id="api-failed">0 gagal</span><span class="tag" id="api-error-rate">0% error</span></div>
            </div>
            <div class="card card-red">
                <div class="stat-icon red"><i data-lucide="alert-triangle"></i></div>
                <div class="stat-value" id="last-error-time" style="font-size:20px;letter-spacing:-0.5px">&ndash;</div>
                <div class="stat-label">Error Terakhir</div>
                <div class="stat-tags"><span class="tag warn" id="db-slow">0 slow query</span></div>
            </div>
            <div class="card card-cyan">
                <div class="stat-icon cyan"><i data-lucide="hard-drive"></i></div>
                <div class="stat-value glow-cyan" id="storage-writes">0</div>
                <div class="stat-label">Storage Writes</div>
                <div class="stat-tags"><span class="tag" id="storage-health">&ndash;</span><span class="tag bad" id="storage-errors">0 gagal</span></div>
            </div>
            <div class="card card-purple">
                <div class="stat-icon purple"><i data-lucide="smartphone"></i></div>
                <div class="stat-value glow-purple" id="upload-total">0</div>
                <div class="stat-label">Upload Mobile</div>
                <div class="stat-tags"><span class="tag warn" id="upload-fail-rate">0% fail</span><span class="tag info" id="upload-avg-ms">0 ms avg</span></div>
            </div>
            <div class="card card-amber">
                <div class="stat-icon amber"><i data-lucide="zap"></i></div>
                <div class="stat-value glow-amber" id="queue-backlog-val">&ndash;</div>
                <div class="stat-label">Queue Backlog</div>
                <div class="stat-tags"><span class="tag" id="queue-worker">&ndash;</span><span class="tag" id="queue-store">&ndash;</span></div>
            </div>
            <div class="card">
                <div class="stat-icon blue"><i data-lucide="activity"></i></div>
                <div class="stat-value" id="uptime-text">&ndash;</div>
                <div class="stat-label">Uptime</div>
                <div class="stat-tags"><span class="tag info" id="uptime-since">sejak &ndash;</span></div>
            </div>
        </div>

        <!-- SECTION: MOBILE UPLOAD MONITORING -->
        <div class="section-label"><span class="sl-icon"></span>Mobile Upload Monitoring</div>
        <div class="grid grid-4">
            <div class="card card-amber">
                <div class="stat-icon amber"><i data-lucide="inbox"></i></div>
                <div class="stat-value glow-amber" id="upload-monitor-pending">0</div>
                <div class="stat-label">Pending Server</div>
                <div class="stat-tags"><span class="tag warn">received / queued</span></div>
            </div>
            <div class="card card-cyan">
                <div class="stat-icon cyan"><i data-lucide="loader-2"></i></div>
                <div class="stat-value glow-cyan" id="upload-monitor-processing">0</div>
                <div class="stat-label">Sedang Diproses</div>
                <div class="stat-tags"><span class="tag info" id="upload-monitor-worker">worker -</span></div>
            </div>
            <div class="card card-green">
                <div class="stat-icon green"><i data-lucide="check-check"></i></div>
                <div class="stat-value glow-green" id="upload-monitor-completed">0</div>
                <div class="stat-label">Completed</div>
                <div class="stat-tags"><span class="tag good">JSON tersimpan</span></div>
            </div>
            <div class="card card-red">
                <div class="stat-icon red"><i data-lucide="circle-alert"></i></div>
                <div class="stat-value glow-red" id="upload-monitor-failed">0</div>
                <div class="stat-label">Failed</div>
                <div class="stat-tags"><a class="tag bad" href="/upload-monitoring">lihat detail</a></div>
            </div>
        </div>

        <!-- SECTION: ACTIVE USERS -->
        <div class="section-label"><span class="sl-icon"></span>Active Users / MAC Monitor</div>
        <div class="card card-cyan">
            <div class="card-head">
                <div>
                    <h3>Aktivitas User &amp; Perangkat</h3>
                    <div class="muted" style="margin-top:3px;font-size:10px;letter-spacing:0.05em;text-transform:uppercase">MAC address &middot; login terakhir &middot; menu terakhir &middot; request &amp; error per sesi</div>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="pill-outline" id="active-user-count">0 devices</span>
                    <span class="pill-outline" id="user-snap-time">&ndash;</span>
                </div>
            </div>
            <div class="table-scroll table-wrap" style="margin-top:14px">
                <table>
                    <thead><tr>
                        <th style="width:14%">MAC / IP</th><th style="width:7%">User ID</th><th style="width:10%">User</th>
                        <th style="width:10%">Login Terakhir</th>
                        <th style="width:10%">Versi App</th><th style="width:10%">Upload Terakhir</th>
                        <th style="width:10%">IP Terakhir</th><th style="width:8%">Jenis IP</th>
                        <th style="width:10%">Network</th>
                        <th style="width:7%">Down</th>
                        <th style="width:7%">Up</th>
                        <th style="width:7%">Ping</th>
                        <th style="width:8%">Sumber</th>
                        <th style="width:11%">Terakhir Aktif</th><th style="width:11%">Menu Terakhir</th>
                        <th style="width:7%">Method</th><th style="width:7%">Status</th>
                        <th style="width:8%">Kualitas</th><th style="width:6%">Req</th>
                        <th style="width:6%">Error</th><th>Riwayat Menu</th>
                    </tr></thead>
                    <tbody id="user-activity-body">
                        <tr><td colspan="21" class="empty">Initializing device monitor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION: PERFORMANCE CHARTS -->
        <div class="section-label"><span class="sl-icon"></span>Performance Charts</div>
        <div class="grid grid-2">
            <div class="card chart-card card-cyan">
                <div class="card-head">
                    <div><h3>Request Duration</h3><div class="chart-hint" id="req-hint">Awaiting data...</div></div>
                    <span class="pill-outline" id="req-window-label">30 req</span>
                </div>
                <div class="chart-wrap"><canvas id="reqChart"></canvas></div>
            </div>
            <div class="card chart-card card-green">
                <div class="card-head">
                    <div><h3>Storage Write Speed</h3><div class="chart-hint" id="storage-hint">Awaiting data...</div></div>
                    <span class="pill-outline">60 jobs</span>
                </div>
                <div class="chart-wrap"><canvas id="storageChart"></canvas></div>
            </div>
        </div>

        <!-- SECTION: ALERT FEED -->
        <div class="section-label"><span class="sl-icon"></span>Alert Feed</div>
        <div class="card">
            <div class="card-head">
                <div>
                    <h3>Error &amp; Warning Terbaru</h3>
                    <div class="muted" style="margin-top:3px;font-size:10px;text-transform:uppercase;letter-spacing:0.06em">Sinyal penting dari log yang perlu ditindak</div>
                </div>
                <div class="filter-row">
                    <button class="chip active" data-filter="all">Semua</button>
                    <button class="chip" data-filter="api">API</button>
                    <button class="chip" data-filter="database">DB</button>
                    <button class="chip" data-filter="storage">Storage</button>
                    <button class="chip" id="errors-only">Error Only</button>
                </div>
            </div>
            <div class="alert-list alert-grid" id="alert-feed"><div class="empty">Awaiting alerts...</div></div>
        </div>

        <!-- SECTION: LOG & REQUEST TABLES -->
        <div class="section-label"><span class="sl-icon"></span>Log &amp; Request Timeline</div>
        <div class="grid grid-2">
            <div class="card">
                <div class="card-head">
                    <h3>Timeline Log</h3>
                    <span class="pill-outline" id="filter-label">Semua kategori</span>
                </div>
                <div class="table-scroll table-wrap" style="margin-top:14px">
                    <table>
                        <thead><tr>
                            <th style="width:18%">Waktu</th><th style="width:11%">Level</th>
                            <th style="width:13%">Kategori</th><th>Pesan</th>
                        </tr></thead>
                        <tbody id="log-body"><tr><td colspan="4" class="empty">Loading log data...</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h3>Request Stream</h3>
                    <span class="muted" style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em">Live from cache</span>
                </div>
                <div class="table-scroll table-wrap" style="margin-top:14px">
                    <table>
                        <thead><tr>
                            <th style="width:18%">Waktu</th><th style="width:8%">Method</th>
                            <th style="width:36%">URI</th><th style="width:8%">Status</th>
                            <th style="width:12%">Durasi</th><th style="width:18%">MAC</th>
                        </tr></thead>
                        <tbody id="req-body"><tr><td colspan="6" class="empty">Loading request stream...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <footer>
            Read-only dashboard &middot; Log source: <code>storage/logs/laravel.log</code> &middot; No DB or API mutations performed
        </footer>
    </div>
    <script>
        const THEME_KEY = 'savera_theme';
        const LOG_REFRESH_MS = 5000;
        const USER_REFRESH_MS = 5000;
        const state = {
            timer: null, userTimer: null, chart: null, storageChart: null,
            paused: false, filter: 'all', onlyErrors: false,
            data: { summary:{}, recent:[], requests:[], storage_durations:[], operations:{}, meta:{} },
        };

        document.getElementById('refresh-btn').addEventListener('click', () => loadLogs(true));
        document.getElementById('pause-btn').addEventListener('click', togglePause);
        document.getElementById('theme-toggle-btn').addEventListener('click', toggleTheme);
        document.querySelectorAll('[data-filter]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.filter = btn.dataset.filter || 'all';
                document.querySelectorAll('[data-filter]').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                renderTable(applyFilters(state.data.recent));
                renderAlerts(state.data.recent);
                updateFilterLabel();
            });
        });
        document.getElementById('errors-only').addEventListener('click', () => {
            state.onlyErrors = !state.onlyErrors;
            document.getElementById('errors-only').classList.toggle('active', state.onlyErrors);
            renderTable(applyFilters(state.data.recent));
            renderAlerts(state.data.recent);
            updateFilterLabel();
        });

        async function loadLogs(manual = false) {
            if (state.paused && !manual) return;
            const stamp = new Date().toLocaleTimeString('id-ID');
            try {
                const res = await fetch('/logs/stream', { credentials: 'same-origin' });
                if (!res.ok) throw new Error('Fetch failed');
                const data = await res.json();
                state.data = {
                    summary: data.summary||{}, recent: data.recent||[],
                    requests: data.requests||[], storage_durations: data.storage_durations||[],
                    operations: data.operations||{}, meta: data.meta||{},
                };
                const fallbackRecent   = state.data.recent.length   ? state.data.recent   : state.data.requests;
                const fallbackRequests = state.data.requests.length ? state.data.requests : state.data.recent;
                renderSummary(state.data.summary, state.data.meta);
                renderOperations(state.data.operations);
                renderMeta(state.data.meta);
                renderAlerts(fallbackRecent);
                renderTable(applyFilters(fallbackRecent));
                renderRequests(fallbackRequests);
                renderChart(fallbackRequests);
                renderStorageChart(state.data.storage_durations);
                document.getElementById('last-updated').textContent = 'Updated ' + stamp;
                if (window.lucide) lucide.createIcons();
            } catch (err) {
                document.getElementById('last-updated').textContent = 'Error (' + stamp + ')';
                renderTable([]); renderRequests([]); renderChart([]); renderStorageChart([]); renderAlerts([]);
            }
        }

        function renderSummary(summary, meta) {
            const safe = (val) => typeof val === 'number' ? val : 0;
            const success = safe(summary.api_success), failed = safe(summary.api_failed), total = success + failed;
            const errorRate = (meta && typeof meta.error_rate === 'number') ? meta.error_rate : total ? ((failed/total)*100) : 0;
            document.getElementById('api-success').textContent = success;
            const failedEl = document.getElementById('api-failed');
            failedEl.textContent = failed + ' gagal'; failedEl.className = 'tag ' + (failed > 0 ? 'bad' : 'good');
            const errRateEl = document.getElementById('api-error-rate');
            errRateEl.textContent = errorRate.toFixed(1) + '% error';
            errRateEl.className = 'tag ' + (errorRate >= 10 ? 'bad' : errorRate >= 3 ? 'warn' : 'good');
            document.getElementById('db-slow').textContent = safe(summary.db_slow) + ' slow query';
            document.getElementById('storage-writes').textContent = safe(summary.storage_writes);
            const storErrEl = document.getElementById('storage-errors');
            storErrEl.textContent = safe(summary.storage_errors) + ' gagal';
            storErrEl.className = 'tag ' + (safe(summary.storage_errors) > 0 ? 'bad' : 'good');
            document.getElementById('upload-total').textContent = safe(summary.upload_recent_total);
            const failRateEl = document.getElementById('upload-fail-rate');
            const failRate = Number(summary.upload_fail_rate || 0);
            failRateEl.textContent = failRate.toFixed(1) + '% fail';
            failRateEl.className = 'tag ' + (failRate >= 20 ? 'bad' : failRate >= 5 ? 'warn' : 'good');
            document.getElementById('upload-avg-ms').textContent = Number(summary.upload_avg_ms||0).toFixed(1) + ' ms avg';
            document.getElementById('last-error-time').textContent = meta && meta.last_error_time ? formatRelative(meta.last_error_time) : 'No errors';
            document.getElementById('uptime-text').textContent = (meta && meta.uptime_human) || '-';
            const uptimeSince = document.getElementById('uptime-since');
            if (uptimeSince) uptimeSince.textContent = (meta && meta.uptime_since) ? 'sejak ' + meta.uptime_since : 'sejak -';
            const uptimeChip = document.getElementById('uptime-chip');
            if (uptimeChip) uptimeChip.textContent = 'Uptime: ' + ((meta && meta.uptime_human) || '-');
            const health = computeHealth(errorRate, summary, state.data.operations);
            setHealthBadge(health.status, health.tone);
        }

        function renderOperations(operations) {
            const queueMode = document.getElementById('queue-mode');
            const queueStatus = document.getElementById('queue-status');
            const queueBacklogVal = document.getElementById('queue-backlog-val');
            const queueWorker = document.getElementById('queue-worker');
            const queueStore = document.getElementById('queue-store');
            if (!queueMode || !queueStatus) return;
            queueMode.textContent = (operations && operations.dispatch_mode || '-') + '/' + (operations && operations.queue_connection || '-');
            queueStatus.textContent = (operations && operations.queue_message) || 'Queue status unavailable.';
            if (queueBacklogVal) queueBacklogVal.textContent = (operations && operations.queue_backlog != null) ? operations.queue_backlog : '-';
            if (queueWorker) {
                queueWorker.textContent = (operations && operations.worker_enabled) ? 'Worker aktif' : 'Worker off';
                queueWorker.className = 'tag ' + ((operations && operations.worker_enabled) ? 'good' : 'warn');
            }
            if (queueStore) queueStore.textContent = 'disk:' + ((operations && operations.storage_disk) || '-');
            queueStatus.className = 'infra-detail';
            const qs = operations && operations.queue_status;
            queueStatus.style.color = qs === 'error' ? 'var(--red)' : (qs === 'sync' || qs === 'worker_off') ? 'var(--amber)' : 'var(--green)';
            renderUploadMonitoring(operations && operations.upload_monitoring);
        }

        function renderUploadMonitoring(monitoring) {
            const pendingEl = document.getElementById('upload-monitor-pending');
            const processingEl = document.getElementById('upload-monitor-processing');
            const completedEl = document.getElementById('upload-monitor-completed');
            const failedEl = document.getElementById('upload-monitor-failed');
            const workerEl = document.getElementById('upload-monitor-worker');
            if (!pendingEl || !processingEl || !completedEl || !failedEl) return;

            if (!monitoring || monitoring.enabled === false) {
                pendingEl.textContent = '0';
                processingEl.textContent = '0';
                completedEl.textContent = '0';
                failedEl.textContent = '0';
                if (workerEl) {
                    workerEl.textContent = 'monitor belum aktif';
                    workerEl.className = 'tag warn';
                }
                return;
            }

            pendingEl.textContent = safe(monitoring.pending);
            processingEl.textContent = safe(monitoring.processing);
            completedEl.textContent = safe(monitoring.completed);
            failedEl.textContent = safe(monitoring.failed);
            if (workerEl) {
                const workers = Array.isArray(monitoring.workers) ? monitoring.workers : [];
                const fresh = workers.filter(function(worker) {
                    return worker && worker.last_seen_at;
                }).length;
                workerEl.textContent = fresh + ' heartbeat';
                workerEl.className = 'tag ' + (fresh > 0 ? 'good' : 'warn');
            }
        }

        function computeHealth(errorRate, summary, operations) {
            const dbSlow = summary && summary.db_slow || 0;
            const storageErrors = summary && summary.storage_errors || 0;
            const uploadFail = Number((summary && summary.upload_fail_rate) || 0);
            const queueStatus = (operations && operations.queue_status) || 'unknown';
            const queueBacklog = Number((operations && operations.queue_backlog) || 0);
            let status = 'Optimal', tone = 'good';
            if (errorRate >= 10 || storageErrors > 5 || uploadFail >= 20 || queueStatus === 'error' || queueBacklog >= 100) {
                status = 'Critical'; tone = 'bad';
            } else if (errorRate >= 3 || dbSlow > 5 || storageErrors > 0 || uploadFail >= 5 || queueStatus === 'sync' || queueStatus === 'worker_off' || queueBacklog >= 20) {
                status = 'Perlu dipantau'; tone = 'warn';
            }
            return { status, tone };
        }

        function setHealthBadge(status, tone) {
            const badge = document.getElementById('health-badge');
            badge.textContent = 'o ' + status;
            badge.className = 'badge ' + (tone === 'bad' ? 'health-bad' : tone === 'warn' ? 'health-warn' : 'health-good');
        }

        function renderMeta(meta) {
            const logPath = (meta && meta.log_path) || '-';
            const logSize = Number((meta && meta.log_size_mb) || 0).toFixed(2);
            const logSizeMb = parseFloat(logSize);
            const logFileEl = document.getElementById('log-file-meta');
            if (logFileEl) logFileEl.textContent = logPath !== '-' ? 'Log: ' + logPath.split(/[/\\]/).pop() : 'Log: -';
            const logSizeEl = document.getElementById('log-size');
            if (logSizeEl) logSizeEl.textContent = logSize + ' MB';
            const logLastEl = document.getElementById('log-last-time');
            if (logLastEl) logLastEl.textContent = (meta && meta.last_log_time) || '-';
            const logBar = document.getElementById('log-bar');
            if (logBar) logBar.style.width = Math.min(100, logSizeMb * 5) + '%';
        }

        function renderAlerts(entries) {
            const alertFeed = document.getElementById('alert-feed');
            const filtered = applyFilters(entries).filter(function(entry) {
                const level = (entry.level || '').toUpperCase();
                const message = (entry.message || '').toLowerCase();
                return ['ERROR','CRITICAL','WARNING'].includes(level) || message.includes('fail');
            }).slice(0, 14);
            if (!filtered.length) { alertFeed.innerHTML = '<div class="empty">No alerts detected.</div>'; return; }
            alertFeed.innerHTML = filtered.map(function(entry) {
                const level = (entry.level || '').toLowerCase();
                const levelClass = level === 'error' || level === 'critical' ? 'alert-error' : level === 'warning' ? 'alert-warning' : 'alert-info';
                return '<div class="alert ' + levelClass + '"><div class="alert-top"><span class="level ' + level + '">' + (entry.level||'INFO') + '</span><span class="timestamp">' + (entry.time||'') + '</span></div><div class="alert-msg">' + escapeHtml(entry.message||'') + '</div><div class="alert-foot"><span class="category-chip ' + (entry.category||'general') + '">' + (entry.category||'general') + '</span><span class="muted">' + (entry.env||'') + '</span></div></div>';
            }).join('');
        }

        function renderTable(entries) {
            const body = document.getElementById('log-body');
            if (!entries.length) { body.innerHTML = '<tr><td colspan="4" class="empty">No log entries.</td></tr>'; return; }
            body.innerHTML = entries.map(function(entry) {
                const cat = entry.category || 'general', level = (entry.level || '').toLowerCase();
                return '<tr><td><div class="timestamp">' + (entry.time||'') + '</div></td><td><span class="level-pill ' + level + '">' + (entry.level||'') + '</span></td><td><span class="category-chip ' + cat + '">' + cat + '</span></td><td style="font-size:11px;word-break:break-word">' + escapeHtml(entry.message||'') + '</td></tr>';
            }).join('');
        }

        function renderRequests(entries) {
            const body = document.getElementById('req-body');
            if (!entries.length) { body.innerHTML = '<tr><td colspan="6" class="empty">No requests.</td></tr>'; return; }
            body.innerHTML = entries.slice(0, 60).map(function(entry) {
                const status = Number(entry.status||0);
                const statusCls = status >= 500 ? 'status-5xx' : status >= 400 ? 'status-4xx' : 'status-2xx';
                const method = (entry.method||'GET').toUpperCase();
                const methCls = method === 'POST' ? 'post' : method === 'PUT' ? 'put' : method === 'DELETE' ? 'delete' : '';
                return '<tr><td><div class="timestamp">' + (entry.time||'') + '</div></td><td><span class="method-chip ' + methCls + '">' + method + '</span></td><td style="font-size:11px;word-break:break-all">' + escapeHtml(entry.uri||'') + '</td><td><span class="' + statusCls + '">' + (entry.status||'') + '</span></td><td class="mono" style="font-size:11px">' + (entry.duration_ms ? Number(entry.duration_ms).toFixed(1) + 'ms' : '-') + '</td><td class="mono" style="font-size:10px">' + (entry.mac||'-') + '</td></tr>';
            }).join('');
        }

        function renderChart(entries) {
            if (typeof Chart === 'undefined') return;
            const recent = entries.slice(0, 30).reverse();
            const labels = recent.map(function(e,i){ return e.time ? e.time.split(' ')[1] || ('#'+String(i+1)) : ('#'+String(i+1)); });
            const durations = recent.map(function(e){ return Number(e.duration_ms||0); });
            const errorSpikes = recent.map(function(e){
                const s = Number(e.status || 0);
                return s >= 500 ? 100 : (s >= 400 ? 60 : 0);
            });
            const ctx = document.getElementById('reqChart').getContext('2d');
            const grad = ctx.createLinearGradient(0,0,0,280);
            grad.addColorStop(0,'rgba(0,212,255,0.35)'); grad.addColorStop(1,'rgba(0,212,255,0)');
            if (!state.chart) {
                state.chart = new Chart(ctx, { type:'line', data:{ labels, datasets:[
                    { label:'Duration (ms)', data:durations, fill:true, tension:0.4, backgroundColor:grad, borderColor:'rgba(0,212,255,0.9)', borderWidth:2, pointRadius:2, pointBackgroundColor:'rgba(0,212,255,1)', pointHoverRadius:5, yAxisID:'y' },
                    { type:'bar', label:'Error Spike', data:errorSpikes, borderWidth:0, backgroundColor:'rgba(255,99,132,0.42)', borderRadius:2, yAxisID:'y' }
                ] }, options:chartOptions() });
            } else {
                state.chart.data.labels = labels;
                state.chart.data.datasets[0].data = durations;
                state.chart.data.datasets[1].data = errorSpikes;
                state.chart.update();
            }
            const reqLabel = document.getElementById('req-window-label');
            if (reqLabel) reqLabel.textContent = recent.length + ' req';
            updateRequestHint(durations);
        }

        function renderStorageChart(durations) {
            if (typeof Chart === 'undefined') return;
            const recent = durations.slice(0, 60).reverse();
            const labels = recent.map(function(_,i){ return '#'+String(i+1); });
            const dataPoints = recent.map(function(v){ return Number(v||0); });
            const bgColors = dataPoints.map(function(v){ return v>80?'rgba(255,60,90,0.7)':v>30?'rgba(255,184,0,0.7)':'rgba(0,255,170,0.7)'; });
            const bdColors = dataPoints.map(function(v){ return v>80?'rgba(255,60,90,1)':v>30?'rgba(255,184,0,1)':'rgba(0,255,170,1)'; });
            const ctx = document.getElementById('storageChart').getContext('2d');
            if (!state.storageChart) {
                state.storageChart = new Chart(ctx, { type:'bar', data:{ labels, datasets:[{ label:'Write Speed (ms)', data:dataPoints, backgroundColor:bgColors, borderColor:bdColors, borderWidth:1, borderRadius:3 }] }, options:chartOptions() });
            } else {
                state.storageChart.data.labels = labels; state.storageChart.data.datasets[0].data = dataPoints;
                state.storageChart.data.datasets[0].backgroundColor = bgColors; state.storageChart.data.datasets[0].borderColor = bdColors;
                state.storageChart.update();
            }
            updateStorageHint(dataPoints); updateStorageHealth(dataPoints);
        }

        function chartOptions() {
            const tickColor = cssVar('--chart-tick', '#5b6b83');
            const gridColor = cssVar('--chart-grid', 'rgba(71, 85, 105, 0.12)');
            const legendColor = cssVar('--text-dim', '#334155');
            const tooltipBg = cssVar('--tooltip-bg', 'rgba(255,255,255,0.97)');
            const tooltipBorder = cssVar('--tooltip-border', 'rgba(37,99,235,0.22)');
            const tooltipTitle = cssVar('--text', '#0f172a');
            const tooltipBody = cssVar('--text-dim', '#334155');
            return { responsive:true, maintainAspectRatio:false, animation:{duration:350,easing:'easeOutCubic'},
                scales:{
                    x:{ ticks:{color:tickColor,font:{family:"'JetBrains Mono',monospace",size:9},maxRotation:0}, grid:{color:gridColor}, border:{color:gridColor} },
                    y:{ ticks:{color:tickColor,font:{family:"'JetBrains Mono',monospace",size:9}}, grid:{color:gridColor}, border:{color:gridColor} }
                },
                plugins:{ legend:{labels:{color:legendColor,font:{family:"'Poppins',sans-serif",size:11}}}, tooltip:{backgroundColor:tooltipBg,borderColor:tooltipBorder,borderWidth:1,titleColor:tooltipTitle,bodyColor:tooltipBody, callbacks:{label:function(ctx){ return ctx.dataset.label === 'Error Spike' ? (' '+ctx.parsed.y.toFixed(0)+' level') : (' '+ctx.parsed.y.toFixed(1)+' ms'); }}} }
            };
        }

        function cssVar(name, fallback) {
            const value = getComputedStyle(document.body).getPropertyValue(name).trim();
            return value || fallback;
        }

        function refreshChartTheme() {
            const options = chartOptions();
            [state.chart, state.storageChart].forEach(function(chart) {
                if (!chart) return;
                chart.options.scales.x.ticks.color = options.scales.x.ticks.color;
                chart.options.scales.x.grid.color = options.scales.x.grid.color;
                chart.options.scales.x.border.color = options.scales.x.border.color;
                chart.options.scales.y.ticks.color = options.scales.y.ticks.color;
                chart.options.scales.y.grid.color = options.scales.y.grid.color;
                chart.options.scales.y.border.color = options.scales.y.border.color;
                chart.options.plugins.legend.labels.color = options.plugins.legend.labels.color;
                chart.options.plugins.tooltip.backgroundColor = options.plugins.tooltip.backgroundColor;
                chart.options.plugins.tooltip.borderColor = options.plugins.tooltip.borderColor;
                chart.options.plugins.tooltip.titleColor = options.plugins.tooltip.titleColor;
                chart.options.plugins.tooltip.bodyColor = options.plugins.tooltip.bodyColor;
                chart.update('none');
            });
        }

        function updateStorageHealth(points) {
            const el = document.getElementById('storage-health'); if (!el) return;
            if (!points.length) { el.textContent = 'Awaiting data'; return; }
            const avg = points.reduce(function(a,b){return a+b;},0) / points.length;
            el.textContent = avg>80 ? 'Slow ('+avg.toFixed(1)+'ms)' : avg>30 ? 'Monitor ('+avg.toFixed(1)+'ms)' : 'Stable ('+avg.toFixed(1)+'ms)';
            el.className = 'tag ' + (avg>80 ? 'bad' : avg>30 ? 'warn' : 'good');
        }
        function updateStorageHint(pts) {
            const el = document.getElementById('storage-hint'); if (!el) return;
            if (!pts.length) { el.textContent = 'Awaiting write jobs... (<30ms good | 30-80ms watch | >80ms slow)'; return; }
            const avg = pts.reduce(function(a,b){return a+b;},0) / pts.length;
            el.textContent = 'avg '+avg.toFixed(1)+'ms | last '+pts[0].toFixed(1)+'ms | '+(avg<30?'Good: fast':'avg>30ms: monitor');
        }
        function updateRequestHint(pts) {
            const el = document.getElementById('req-hint'); if (!el) return;
            if (!pts.length) { el.textContent = 'Awaiting requests... (<200ms good | 200-500ms watch | >500ms slow)'; return; }
            const avg = pts.reduce(function(a,b){return a+b;},0) / pts.length;
            el.textContent = 'avg '+avg.toFixed(1)+'ms | last '+pts[0].toFixed(1)+'ms | '+(avg<200?'Good: fast':avg<500?'Watch: avg>200ms':'Slow: avg>500ms');
        }

        function applyFilters(entries) {
            return (entries||[]).filter(function(entry) {
                const cat = (entry.category||'general').toLowerCase();
                const level = (entry.level||'').toUpperCase();
                const message = (entry.message||'').toLowerCase();
                const matchCat = state.filter === 'all' ? true : cat === state.filter;
                const isError = ['ERROR','CRITICAL','WARNING'].includes(level) || message.includes('fail');
                return matchCat && (!state.onlyErrors || isError);
            });
        }

        function updateFilterLabel() {
            const label = document.getElementById('filter-label');
            const map = { all:'Semua kategori', api:'API saja', database:'Database saja', storage:'Storage saja' };
            label.textContent = (map[state.filter] || 'Semua') + (state.onlyErrors ? ' | error only' : '');
        }

        function escapeHtml(unsafe) {
            return String(unsafe||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }

        function formatRelative(time) {
            const parsed = new Date(String(time).replace(' ','T'));
            if (Number.isNaN(parsed.getTime())) return time;
            const diff = Date.now() - parsed.getTime();
            if (diff < 0) return time;
            const minutes = Math.floor(diff/60000);
            if (minutes < 1) return 'Just now';
            if (minutes < 60) return minutes + 'm ago';
            const hours = Math.floor(minutes/60);
            if (hours < 24) return hours + 'h ago';
            return Math.floor(hours/24) + 'd ago';
        }

        function togglePause() {
            state.paused = !state.paused;
            const btn = document.getElementById('pause-btn');
            const pill = document.getElementById('live-pill');
            const dot = document.getElementById('live-dot');
            btn.innerHTML = state.paused
                ? '<i data-lucide="play" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>Resume'
                : '<i data-lucide="pause" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>Pause';
            btn.classList.toggle('primary', !state.paused);
            if (pill) pill.classList.toggle('paused', state.paused);
            dot.classList.toggle('paused', state.paused);
            if (window.lucide) lucide.createIcons();
        }

        function applyTheme(theme) {
            const isDark = theme === 'dark';
            document.body.classList.toggle('theme-dark', isDark);
            const btn = document.getElementById('theme-toggle-btn');
            if (btn) {
                btn.innerHTML = isDark
                    ? '<i data-lucide="sun" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>Putih'
                    : '<i data-lucide="moon" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i>Dark';
            }
            refreshChartTheme();
            if (window.lucide) lucide.createIcons();
        }

        function initTheme() {
            let saved = 'light';
            try { saved = localStorage.getItem(THEME_KEY) || 'light'; } catch (_) {}
            applyTheme(saved === 'dark' ? 'dark' : 'light');
        }

        function toggleTheme() {
            const next = document.body.classList.contains('theme-dark') ? 'light' : 'dark';
            applyTheme(next);
            try { localStorage.setItem(THEME_KEY, next); } catch (_) {}
        }

        async function loadUsers() {
            try {
                const res = await fetch('/logs/users', { credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                renderUsers(data);
            } catch (_) {}
        }

        function renderUsers(data) {
            const tbody = document.getElementById('user-activity-body');
            const countEl = document.getElementById('active-user-count');
            const snapEl = document.getElementById('user-snap-time');
            if (!tbody) return;
            const users = (data.active_users || []).slice(0, 20);
            const total = data.total || users.length;
            if (countEl) countEl.textContent = users.length + ' / ' + total + ' devices';
            if (snapEl) snapEl.textContent = data.snapshot_at ? data.snapshot_at.slice(11, 19) : '-';
            if (!users.length) { tbody.innerHTML = '<tr><td colspan="21" class="empty">No active devices detected</td></tr>'; return; }
            const statusCls = function(s) { return !s ? '' : s>=500 ? 'status-5xx' : s>=400 ? 'status-4xx' : 'status-2xx'; };
            const shortDateTime = function(v) {
                if (!v) return '-';
                const s = String(v);
                if (s.length >= 16) return s.slice(5, 16).replace(' ', ' ');
                return s;
            };
            tbody.innerHTML = users.map(function(u) {
                const mac = u.mac || '-';
                const uid = u.user_id ? '#'+u.user_id : '-';
                const uname = u.user_name || '-';
                const lastLogin = shortDateTime(u.last_login_at);
                const appVersion = u.app_version || 'N/A';
                const uploadAt = u.last_upload_at ? u.last_upload_at.slice(11,19) : 'N/A';
                const ip = u.last_ip || '-';
                const rawScope = (u.ip_scope || 'unknown').toLowerCase();
                const networkType = u.network_type || (rawScope === 'public' ? 'public' : (rawScope === 'local' ? 'wifi/local' : 'unknown'));
                const networkSource = u.network_source || 'server_estimate';
                const networkMobileRaw = u.network_type_mobile || null;
                const normalizeNetworkType = function(v) {
                    const x = String(v || '').toLowerCase();
                    if (x === 'mobile') return 'cellular';
                    if (x === 'wifi/local') return 'wifi';
                    return x || 'unknown';
                };
                const toTitle = function(v) {
                    const x = normalizeNetworkType(v);
                    if (x === 'cellular') return 'Cellular';
                    if (x === 'wifi') return 'WiFi';
                    if (x === 'public') return 'Public';
                    if (x === 'local') return 'Local';
                    if (x === 'ethernet') return 'Ethernet';
                    if (x === 'vpn') return 'VPN';
                    return x.toUpperCase();
                };
                const networkMobile = normalizeNetworkType(networkMobileRaw);
                const networkServer = normalizeNetworkType(networkType);
                const effectiveScope = (function () {
                    if (networkSource === 'mobile_report') {
                        if (networkMobile === 'cellular' || networkMobile === 'public') return 'public';
                        if (networkMobile === 'wifi' || networkMobile === 'local') return 'local';
                    }
                    return rawScope;
                })();
                const scopeLabel = effectiveScope === 'public' ? 'PUBLIC' : (effectiveScope === 'local' ? 'LOCAL' : 'UNKNOWN');
                const speedTier = (u.speed_tier || 'unknown').toUpperCase();
                const scopeStyle = effectiveScope === 'public'
                    ? 'background:rgba(34,197,94,0.16);color:#6ee7b7;border:1px solid rgba(34,197,94,0.45)'
                    : (effectiveScope === 'local'
                        ? 'background:rgba(245,158,11,0.16);color:#fcd34d;border:1px solid rgba(245,158,11,0.45)'
                        : 'background:rgba(148,163,184,0.16);color:#cbd5e1;border:1px solid rgba(148,163,184,0.45)');
                const lastSeen = u.last_seen ? u.last_seen.slice(11,19) : '-';
                const lastRoute = u.last_route || '-';
                const method = (u.last_method || 'GET').toUpperCase();
                const methCls = method==='POST' ? 'post' : method==='PUT' ? 'put' : '';
                const status = u.last_status || '-';
                const ms = u.last_ms!=null ? Number(u.last_ms).toFixed(1)+'ms' : '-';
                const dlVal = (u.downlink_mbps_mobile != null) ? Number(u.downlink_mbps_mobile) : null;
                const ulVal = (u.uplink_mbps_mobile != null) ? Number(u.uplink_mbps_mobile) : null;
                const rttVal = (u.rtt_ms_mobile != null) ? Number(u.rtt_ms_mobile) : null;
                const serverMbps = (u.speed_kbps_est != null) ? Number(u.speed_kbps_est) / 1024 : null;
                const dl = dlVal != null ? dlVal.toFixed(1) + ' Mbps' : (serverMbps != null ? serverMbps.toFixed(2) + ' Mbps est' : '-');
                const ul = ulVal != null ? ulVal.toFixed(1) + ' Mbps' : '-';
                const rtt = rttVal != null ? rttVal.toFixed(0) + ' ms' : (u.last_ms != null ? Number(u.last_ms).toFixed(0) + ' ms req' : '-');
                const netDisplay = networkSource === 'mobile_report'
                    ? toTitle(networkMobile || networkServer)
                    : toTitle(networkServer);
                const sourceText = networkSource === 'mobile_report' ? 'mobile-report' : 'server-estimate';
                const sourceCls = networkSource === 'mobile_report' ? 'tag good' : 'tag warn';
                const quality = (function(){
                    if (dlVal == null || rttVal == null) {
                        if (speedTier === 'VERY_FAST' || speedTier === 'FAST') return {label:'Server Cepat', cls:'tag good'};
                        if (speedTier === 'MEDIUM') return {label:'Server Sedang', cls:'tag info'};
                        if (speedTier === 'SLOW') return {label:'Server Lambat', cls:'tag bad'};
                        return {label:'Estimasi', cls:'tag warn'};
                    }
                    if (dlVal >= 20 && rttVal <= 60) return {label:'Sangat Cepat', cls:'tag good'};
                    if (dlVal >= 8 && rttVal <= 120) return {label:'Normal', cls:'tag info'};
                    return {label:'Lambat', cls:'tag bad'};
                })();
                const req = u.request_count||0, err = u.error_count||0;
                const routes = (u.routes||[]).join(', ')||'-';
                return '<tr><td class="mono" style="font-size:10px">'+mac+'</td><td class="mono" style="color:var(--cyan)">'+uid+'</td><td class="mono" style="font-size:10px">'+uname+'</td><td class="mono" style="font-size:10px">'+lastLogin+'</td><td class="mono" style="font-size:10px">'+appVersion+'</td><td class="mono" style="font-size:10px">'+uploadAt+'</td><td class="mono" style="font-size:10px">'+ip+'</td><td><span class="pill-outline" style="font-size:10px;'+scopeStyle+'">'+scopeLabel+'</span></td><td class="mono" style="font-size:10px">'+netDisplay+'</td><td class="mono" style="font-size:10px">'+dl+'</td><td class="mono" style="font-size:10px">'+ul+'</td><td class="mono" style="font-size:10px">'+rtt+'</td><td><span class="'+sourceCls+'">'+sourceText+'</span></td><td class="mono">'+lastSeen+'</td><td><span class="pill-outline" style="font-size:10px">'+lastRoute+'</span></td><td><span class="method-chip '+methCls+'">'+method+'</span></td><td><span class="'+statusCls(u.last_status)+'">'+status+'</span></td><td><span class="'+quality.cls+'">'+quality.label+'</span></td><td style="color:var(--cyan);font-weight:700">'+req+'</td><td style="'+(err>0?'color:var(--red);font-weight:700':'')+'">'+err+'</td><td style="font-size:10px;color:var(--muted)">'+routes+'</td></tr>';
            }).join('');
        }

        function start() {
            const autoChip = document.getElementById('auto-chip');
            if (autoChip) autoChip.innerHTML = '<i data-lucide="refresh-cw" style="width:11px;height:11px;vertical-align:middle"></i>&nbsp;Auto ' + Math.round(LOG_REFRESH_MS/1000) + 's realtime';
            loadLogs(); loadUsers();
            state.timer = setInterval(loadLogs, LOG_REFRESH_MS);
            state.userTimer = setInterval(loadUsers, USER_REFRESH_MS);
            updateFilterLabel();
        }

        initTheme();
        start();
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>


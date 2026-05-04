<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAVERA // API DOCS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --bg:            #f6fbff;
        --panel:         rgba(255,255,255,0.92);
        --panel2:        rgba(248,252,255,0.98);
        --outline:       rgba(15,23,42,0.10);
        --outline-strong:rgba(15,23,42,0.18);
        --text:          #132033;
        --text-dim:      #4b637d;
        --muted:         #7890aa;
        --cyan:          #0284c7;
        --purple:        #7c3aed;
        --green:         #059669;
        --amber:         #d97706;
        --red:           #dc2626;
        --blue:          #2563eb;
        --teal:          #0d9488;
        --pink:          #db2777;
        --glow-cyan:     rgba(2,132,199,0.13);
        --glow-green:    rgba(5,150,105,0.12);
        --glow-purple:   rgba(124,58,237,0.12);
        --glow-amber:    rgba(217,119,6,0.12);
        --glow-red:      rgba(220,38,38,0.12);
        --radius-lg:     14px;
        --radius-md:     10px;
        --radius-sm:     7px;
    }
    html { scroll-behavior: smooth; }
    body {
        background:
            radial-gradient(circle at top left, rgba(14,165,233,0.22), transparent 32%),
            radial-gradient(circle at top right, rgba(124,58,237,0.14), transparent 28%),
            linear-gradient(180deg, #ffffff 0%, var(--bg) 42%, #edf7ff 100%);
        color: var(--text);
        font-family: 'Poppins', system-ui, sans-serif;
        font-size: 13px;
        line-height: 1.6;
        overflow-x: hidden;
    }
    body::before {
        content: '';
        position: fixed; inset: 0; z-index: 0; pointer-events: none;
        background-image:
            linear-gradient(rgba(2,132,199,0.055) 1px, transparent 1px),
            linear-gradient(90deg, rgba(124,58,237,0.045) 1px, transparent 1px);
        background-size: 52px 52px;
    }
    .topbar-glow {
        position: fixed; top: 0; left: 0; right: 0; height: 2px; z-index: 200;
        background: linear-gradient(90deg, transparent 0%, var(--cyan) 30%, var(--purple) 70%, transparent 100%);
        animation: topglow 6s ease-in-out infinite alternate;
    }
    @keyframes topglow { 0% { opacity: 0.6; } 100% { opacity: 1; box-shadow: 0 0 20px var(--cyan); } }

    /* ── TOPNAV ── */
    .topnav {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 28px; height: 58px;
        background: rgba(255,255,255,0.88);
        border-bottom: 1px solid var(--outline);
        backdrop-filter: blur(16px);
        box-shadow: 0 12px 36px rgba(15,23,42,0.08);
        position: sticky; top: 0; z-index: 100;
    }
    .nav-brand { display: flex; align-items: center; gap: 12px; }
    .nav-logo {
        width: 36px; height: 36px; border-radius: 10px;
        background: linear-gradient(135deg, var(--purple), var(--cyan));
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 14px 30px rgba(37,99,235,0.24);
    }
    .nav-logo svg { width: 18px; height: 18px; stroke: #fff; stroke-width: 2; }
    .nav-title { font-weight: 700; font-size: 14px; letter-spacing: 0.12em; text-transform: uppercase; color: var(--blue); }
    .nav-subtitle { font-size: 10px; color: var(--muted); letter-spacing: 0.1em; text-transform: uppercase; font-family: 'JetBrains Mono', monospace; }
    .nav-right { display: flex; align-items: center; gap: 10px; }
    .nav-btn {
        padding: 6px 14px; border-radius: var(--radius-sm);
        border: 1px solid var(--outline-strong); background: rgba(255,255,255,0.72);
        color: var(--text-dim); font-weight: 600; font-size: 11px;
        letter-spacing: 0.05em; text-transform: uppercase; cursor: pointer; transition: all 0.2s;
        font-family: 'Poppins', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
    }
    .nav-btn:hover { background: rgba(2,132,199,0.10); color: var(--cyan); border-color: rgba(2,132,199,0.34); }
    .nav-btn.active { color: var(--purple); border-color: rgba(124,58,237,0.34); background: rgba(124,58,237,0.08); }
    .version-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; border: 1px solid rgba(124,58,237,0.24); background: rgba(124,58,237,0.08); color: var(--purple); font-size: 10px; font-weight: 700; letter-spacing: 0.08em; font-family: 'JetBrains Mono', monospace; }

    /* ── LAYOUT ── */
    .layout { display: flex; height: calc(100vh - 58px); }
    .sidebar {
        width: 260px; flex-shrink: 0;
        background: var(--panel2);
        border-right: 1px solid var(--outline);
        overflow-y: auto; position: sticky; top: 58px;
        height: calc(100vh - 58px);
    }
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(37,99,235,0.24); border-radius: 2px; }
    .main { flex: 1; overflow-y: auto; padding: 36px 40px 80px; position: relative; z-index: 1; }
    .main::-webkit-scrollbar { width: 6px; }
    .main::-webkit-scrollbar-thumb { background: rgba(0,212,255,0.15); border-radius: 3px; }

    /* ── SIDEBAR ── */
    .sidebar-section { padding: 18px 20px 6px; }
    .sidebar-section-label { font-size: 9px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--muted); font-family: 'JetBrains Mono', monospace; margin-bottom: 8px; }
    .sidebar-link {
        display: flex; align-items: center; gap: 10px;
        padding: 7px 12px; border-radius: var(--radius-sm);
        color: var(--text-dim); font-size: 12px; font-weight: 500;
        text-decoration: none; transition: all 0.15s; border: 1px solid transparent;
        margin-bottom: 2px;
    }
    .sidebar-link:hover { color: var(--text); background: rgba(37,99,235,0.07); }
    .sidebar-link.active { color: var(--cyan); background: rgba(2,132,199,0.10); border-color: rgba(2,132,199,0.24); }
    .sidebar-link .sl-method { font-size: 9px; font-weight: 700; padding: 1px 5px; border-radius: 3px; font-family: 'JetBrains Mono', monospace; flex-shrink: 0; min-width: 36px; text-align: center; }
    .m-get    { background: rgba(0,212,255,0.15);   color: var(--cyan);   }
    .m-post   { background: rgba(0,255,170,0.12);   color: var(--green);  }
    .m-any    { background: rgba(176,96,255,0.12);  color: var(--purple); }
    .sidebar-divider { height: 1px; background: var(--outline); margin: 10px 20px; }

    /* ── HERO ── */
    .hero { margin-bottom: 40px; }
    .hero h1 {
        font-size: 38px; font-weight: 700; letter-spacing: -1px; line-height: 1.1;
        background: linear-gradient(135deg, #0f172a 0%, var(--blue) 45%, var(--teal) 100%);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .hero-sub { font-size: 13px; color: var(--text-dim); margin-top: 10px; max-width: 620px; line-height: 1.7; }
    .hero-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
    .hmeta { display: flex; align-items: center; gap: 7px; padding: 8px 14px; background: var(--panel); border: 1px solid var(--outline); border-radius: var(--radius-md); font-size: 11px; color: var(--text-dim); }
    .hmeta code { font-family: 'JetBrains Mono', monospace; color: var(--cyan); font-size: 11px; }
    .hmeta svg { width: 13px; height: 13px; stroke: var(--muted); stroke-width: 2; flex-shrink: 0; }

    /* ── SECTION LABEL ── */
    .section-label {
        font-size: 10px; font-weight: 700; letter-spacing: 0.16em;
        text-transform: uppercase; color: var(--cyan);
        margin: 36px 0 16px;
        display: flex; align-items: center; gap: 10px;
        font-family: 'JetBrains Mono', monospace;
    }
    .section-label .sl-dot { width: 5px; height: 5px; background: var(--cyan); border-radius: 50%; box-shadow: 0 0 8px var(--cyan); flex-shrink: 0; }
    .section-label.purple .sl-dot { background: var(--purple); box-shadow: 0 0 8px var(--purple); }
    .section-label.purple { color: var(--purple); }
    .section-label.green .sl-dot { background: var(--green); box-shadow: 0 0 8px var(--green); }
    .section-label.green { color: var(--green); }
    .section-label.amber .sl-dot { background: var(--amber); box-shadow: 0 0 8px var(--amber); }
    .section-label.amber { color: var(--amber); }
    .section-label::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, var(--outline-strong), transparent); }

    /* ── ENDPOINT CARD ── */
    .ep-card {
        background: var(--panel); border: 1px solid var(--outline);
        border-radius: var(--radius-lg); margin-bottom: 20px;
        overflow: hidden; transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
        position: relative;
        box-shadow: 0 16px 42px rgba(15,23,42,0.07);
    }
    .ep-card::before { content: ''; position: absolute; top: 0; left: 0; width: 14px; height: 14px; border-top: 1px solid rgba(2,132,199,0.38); border-left: 1px solid rgba(2,132,199,0.38); border-radius: var(--radius-lg) 0 0 0; pointer-events: none; }
    .ep-card:hover { border-color: var(--outline-strong); box-shadow: 0 18px 48px rgba(15,23,42,0.10); transform: translateY(-1px); }
    .ep-card.post-card::before { border-color: rgba(5,150,105,0.38); }
    .ep-header {
        display: flex; align-items: center; gap: 14px; padding: 16px 20px;
        border-bottom: 1px solid var(--outline); cursor: pointer; user-select: none;
        flex-wrap: wrap;
    }
    .ep-header:hover { background: rgba(37,99,235,0.04); }
    .method-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; font-family: 'JetBrains Mono', monospace; flex-shrink: 0; }
    .mb-get    { background: rgba(2,132,199,0.12);  color: var(--cyan);   border: 1px solid rgba(2,132,199,0.28);  }
    .mb-post   { background: rgba(5,150,105,0.12);  color: var(--green);  border: 1px solid rgba(5,150,105,0.28);  }
    .mb-any    { background: rgba(124,58,237,0.11); color: var(--purple); border: 1px solid rgba(124,58,237,0.28); }
    .ep-path { font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 600; color: var(--text); flex: 1; }
    .ep-path span { color: var(--amber); }
    .ep-title { font-size: 12px; color: var(--text-dim); }
    .auth-tag { padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; letter-spacing: 0.04em; flex-shrink: 0; }
    .auth-public { background: rgba(5,150,105,0.10); color: var(--green); border: 1px solid rgba(5,150,105,0.24); }
    .auth-private { background: rgba(217,119,6,0.10); color: var(--amber); border: 1px solid rgba(217,119,6,0.24); }
    .ep-toggle { margin-left: auto; color: var(--muted); transition: transform 0.2s; flex-shrink: 0; }
    .ep-toggle svg { width: 14px; height: 14px; stroke-width: 2; }
    .ep-body { padding: 20px; border-top: 1px solid var(--outline); display: none; }
    .ep-body.open { display: block; }
    .ep-toggle.open { transform: rotate(180deg); }

    /* ── BODY CONTENT ── */
    .ep-desc { font-size: 12px; color: var(--text-dim); line-height: 1.7; margin-bottom: 16px; }
    .ep-section-title { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); margin: 16px 0 8px; font-family: 'JetBrains Mono', monospace; }
    .field-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .field-table th { padding: 8px 10px; text-align: left; font-size: 9px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--cyan); border-bottom: 1px solid var(--outline-strong); background: rgba(2,132,199,0.06); font-family: 'JetBrains Mono', monospace; }
    .field-table td { padding: 8px 10px; font-size: 11px; color: var(--text-dim); border-bottom: 1px solid rgba(15,23,42,0.06); vertical-align: top; }
    .field-table tr:last-child td { border-bottom: none; }
    .field-table tr:hover td { background: rgba(2,132,199,0.04); }
    .field-name { font-family: 'JetBrains Mono', monospace; color: var(--text); font-weight: 600; font-size: 11px; }
    .field-type { font-family: 'JetBrains Mono', monospace; font-size: 10px; padding: 1px 6px; border-radius: 4px; background: rgba(176,96,255,0.10); color: var(--purple); }
    .field-req  { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; }
    .req-yes { background: rgba(255,60,90,0.10); color: var(--red); }
    .req-opt { background: rgba(255,255,255,0.05); color: var(--muted); }

    /* ── CODE BLOCK ── */
    .code-block {
        background: #f8fafc; border: 1px solid rgba(148,163,184,0.28); border-radius: var(--radius-md);
        padding: 16px 18px; font-family: 'JetBrains Mono', monospace; font-size: 11px;
        line-height: 1.7; color: var(--text-dim); overflow-x: auto; margin-top: 4px;
        position: relative;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
    }
    .code-block .k  { color: var(--purple); }
    .code-block .s  { color: var(--green); }
    .code-block .n  { color: var(--cyan); }
    .code-block .c  { color: var(--muted); font-style: italic; }
    .code-block .num { color: var(--amber); }
    .copy-btn {
        position: absolute; top: 10px; right: 10px;
        padding: 3px 10px; border-radius: 5px; border: 1px solid var(--outline);
        background: #ffffff; color: var(--muted); font-size: 9px; font-weight: 700;
        cursor: pointer; letter-spacing: 0.06em; text-transform: uppercase; font-family: 'Poppins', sans-serif;
        transition: all 0.15s;
    }
    .copy-btn:hover { color: var(--cyan); border-color: rgba(0,212,255,0.4); background: rgba(0,212,255,0.06); }
    .copy-btn.copied { color: var(--green); border-color: rgba(0,255,170,0.4); }

    /* ── RESPONSE ── */
    .resp-tab-bar { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
    .resp-tab { padding: 4px 12px; border-radius: 6px; font-size: 10px; font-weight: 700; cursor: pointer; letter-spacing: 0.06em; border: 1px solid var(--outline); color: var(--muted); background: transparent; transition: all 0.15s; font-family: 'Space Grotesk', sans-serif; }
    .resp-tab:hover { color: var(--text); border-color: var(--outline-strong); }
    .resp-tab.active-200 { background: rgba(0,255,170,0.10); color: var(--green); border-color: rgba(0,255,170,0.3); }
    .resp-tab.active-4xx { background: rgba(255,184,0,0.10); color: var(--amber); border-color: rgba(255,184,0,0.3); }
    .resp-tab.active-5xx { background: rgba(255,60,90,0.10); color: var(--red); border-color: rgba(255,60,90,0.3); }
    .resp-panel { display: none; }
    .resp-panel.active { display: block; }

    /* ── ALERT BOX ── */
    .info-box { padding: 12px 16px; border-radius: var(--radius-md); margin: 16px 0; font-size: 12px; line-height: 1.7; display: flex; gap: 12px; }
    .info-box svg { width: 15px; height: 15px; flex-shrink: 0; margin-top: 1px; }
    .info-box.cyan  { background: rgba(0,212,255,0.07); border: 1px solid rgba(0,212,255,0.2); color: var(--text-dim); }
    .info-box.cyan svg { stroke: var(--cyan); }
    .info-box.amber { background: rgba(255,184,0,0.07); border: 1px solid rgba(255,184,0,0.2); color: var(--text-dim); }
    .info-box.amber svg { stroke: var(--amber); }
    .info-box.green { background: rgba(0,255,170,0.07); border: 1px solid rgba(0,255,170,0.2); color: var(--text-dim); }
    .info-box.green svg { stroke: var(--green); }
    .info-box strong { color: var(--text); }
    .info-box code  { font-family: 'JetBrains Mono', monospace; font-size: 11px; background: rgba(255,255,255,0.72); padding: 0 5px; border-radius: 3px; }

    /* ── HEADER TABLE ── */
    .hdr-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: var(--radius-sm); background: rgba(248,250,252,0.86); border: 1px solid var(--outline); margin-bottom: 6px; flex-wrap: wrap; }
    .hdr-key  { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--cyan); font-weight: 600; min-width: 180px; }
    .hdr-val  { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-dim); flex: 1; }
    .hdr-req  { font-size: 9px; font-weight: 700; padding: 2px 7px; border-radius: 4px; }

    /* ── MISC ── */
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }
    .chip { padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
    .chip-cyan   { background: rgba(0,212,255,0.10); color: var(--cyan); }
    .chip-green  { background: rgba(0,255,170,0.10); color: var(--green); }
    .chip-amber  { background: rgba(255,184,0,0.10); color: var(--amber); }
    .chip-red    { background: rgba(255,60,90,0.10); color: var(--red); }
    .chip-purple { background: rgba(176,96,255,0.10); color: var(--purple); }
    footer { text-align: center; padding: 40px 0 20px; color: var(--muted); font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; font-family: 'JetBrains Mono', monospace; }
    @media (max-width: 760px) {
        .sidebar { display: none; }
        .main { padding: 20px 16px 60px; }
        .hero h1 { font-size: 26px; }
        .topnav { padding: 0 14px; }
    }

/* ═══════════════════════ PASSWORD GATE ═══════════════════════ */
#pw-gate {
    position: fixed; inset: 0; z-index: 9999;
    background:
        radial-gradient(circle at 20% 20%, rgba(14,165,233,0.22), transparent 32%),
        radial-gradient(circle at 80% 0%, rgba(124,58,237,0.16), transparent 28%),
        linear-gradient(180deg, #ffffff 0%, #eef8ff 100%);
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 0;
}
#pw-gate .gate-card {
    background: rgba(255,255,255,0.94);
    border: 1px solid rgba(2,132,199,0.18);
    border-radius: 16px;
    padding: 48px 44px 40px;
    width: 100%; max-width: 400px;
    box-shadow: 0 24px 70px rgba(15,23,42,0.14);
    text-align: center;
}
#pw-gate .gate-logo {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(2,132,199,0.14), rgba(124,58,237,0.14));
    border: 1px solid rgba(2,132,199,0.22);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    font-size: 22px;
}
#pw-gate h2 {
    font-size: 18px; font-weight: 700; letter-spacing: 0.06em;
    color: var(--text); margin-bottom: 6px;
}
#pw-gate .gate-sub {
    font-size: 12px; color: var(--text-dim); margin-bottom: 28px;
}
#pw-gate .gate-input-wrap {
    position: relative; margin-bottom: 14px;
}
#pw-gate input {
    width: 100%;
    padding: 12px 44px 12px 16px;
    background: rgba(248,250,252,0.94);
    border: 1px solid rgba(15,23,42,0.12);
    border-radius: 8px;
    color: var(--text);
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    outline: none;
    transition: border-color .2s;
    letter-spacing: 0.08em;
}
#pw-gate input:focus { border-color: rgba(2,132,199,0.5); box-shadow: 0 0 0 4px rgba(2,132,199,0.09); }
#pw-gate input.shake {
    animation: pwShake .35s ease;
    border-color: var(--red) !important;
}
#pw-gate .eye-btn {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--text-dim); display: flex;
}
#pw-gate .gate-btn {
    width: 100%;
    padding: 12px;
    background: linear-gradient(90deg, #0284c7, #2563eb);
    border: none; border-radius: 8px;
    color: #fff; font-family: 'Poppins', sans-serif;
    font-size: 13px; font-weight: 600; letter-spacing: 0.06em;
    cursor: pointer; transition: opacity .2s, transform .1s;
    margin-bottom: 14px;
}
#pw-gate .gate-btn:hover { opacity: .88; }
#pw-gate .gate-btn:active { transform: scale(.98); }
#pw-gate .gate-err {
    font-size: 11px; color: var(--red);
    height: 16px; margin-bottom: 8px;
    transition: opacity .2s;
}
#pw-gate .gate-hint {
    font-size: 10px; color: var(--muted);
}
@keyframes pwShake {
    0%,100%{ transform: translateX(0) }
    20%{ transform: translateX(-7px) }
    40%{ transform: translateX(7px) }
    60%{ transform: translateX(-5px) }
    80%{ transform: translateX(5px) }
}
</style>
</head>
<body>
    <!-- Health Monitor Section -->
    <section id="health-monitor-demo" style="margin:40px 0;">
        <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:18px;color:var(--blue);">Health Monitor (Demo)</h2>
        <div style="overflow-x:auto;">
            <table id="hm-table" style="width:100%;border-collapse:collapse;background:var(--panel);border-radius:10px;overflow:hidden;">
                <thead style="background:var(--cyan);color:#fff;">
                    <tr>
                        <th style="padding:8px 10px;">NIK</th>
                        <th style="padding:8px 10px;">Nama</th>
                        <th style="padding:8px 10px;">Departemen</th>
                        <th style="padding:8px 10px;">Shift</th>
                        <th style="padding:8px 10px;">Jam Tidur</th>
                        <th style="padding:8px 10px;">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>123456</td>
                        <td>Andi</td>
                        <td>Produksi</td>
                        <td>Night</td>
                        <td>6.2</td>
                        <td><button class="btn-detail" data-nik="123456" style="padding:4px 10px;border-radius:6px;background:var(--blue);color:#fff;border:none;cursor:pointer;">Lihat Grafik</button></td>
                    </tr>
                    <tr>
                        <td>654321</td>
                        <td>Budi</td>
                        <td>HRD</td>
                        <td>Day</td>
                        <td>5.7</td>
                        <td><button class="btn-detail" data-nik="654321" style="padding:4px 10px;border-radius:6px;background:var(--blue);color:#fff;border:none;cursor:pointer;">Lihat Grafik</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="hm-detail-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.18);z-index:9999;align-items:center;justify-content:center;">
            <div style="background:#fff;padding:32px 28px 18px 28px;border-radius:14px;min-width:340px;max-width:98vw;box-shadow:0 8px 32px rgba(0,0,0,0.13);position:relative;">
                <button id="hm-close-modal" style="position:absolute;top:10px;right:10px;background:var(--red);color:#fff;border:none;border-radius:5px;padding:2px 10px;cursor:pointer;">Tutup</button>
                <h3 style="font-size:1.1rem;font-weight:600;margin-bottom:12px;color:var(--blue);">Detail Grafik Jam Tidur</h3>
                <canvas id="hm-sleep-chart" width="420" height="220"></canvas>
            </div>
        </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
    // Dummy data, ganti dengan fetch file JSON sesuai NIK
    const sleepData = {
        '123456': [
            {x:'2026-04-29T22:00',y:0}, {x:'2026-04-29T23:00',y:1}, {x:'2026-04-30T00:00',y:1}, {x:'2026-04-30T01:00',y:1}, {x:'2026-04-30T02:00',y:0}
        ],
        '654321': [
            {x:'2026-04-29T08:00',y:0}, {x:'2026-04-29T09:00',y:1}, {x:'2026-04-29T10:00',y:1}, {x:'2026-04-29T11:00',y:0}
        ]
    };
    let chart;
    document.querySelectorAll('.btn-detail').forEach(btn => {
        btn.onclick = function() {
            const nik = this.getAttribute('data-nik');
            const data = sleepData[nik] || [];
            document.getElementById('hm-detail-modal').style.display = 'flex';
            if(chart) chart.destroy();
            chart = new Chart(document.getElementById('hm-sleep-chart').getContext('2d'), {
                type: 'bar',
                data: { labels: data.map(d=>d.x), datasets: [{ label: 'Jam Tidur', data: data.map(d=>d.y), backgroundColor: 'rgba(2,132,199,0.7)' }] },
                options: { scales: { y: { beginAtZero:true, title:{display:true,text:'Tidur (1=ya,0=tidak)'} }, x: { title:{display:true,text:'Jam'} } }, plugins:{ legend:{display:false} } }
            });
        }
    });
    document.getElementById('hm-close-modal').onclick = function() {
        document.getElementById('hm-detail-modal').style.display = 'none';
    };
    </script>

<!-- PASSWORD GATE -->
<div id="pw-gate">
    <div class="gate-card">
        <div class="gate-logo">🔒</div>
        <h2>SAVERA // API DOCS</h2>
        <p class="gate-sub">Halaman ini dilindungi password.<br>Hanya untuk developer yang berwenang.</p>
        <div class="gate-input-wrap">
            <input type="password" id="pw-input" placeholder="Masukkan password..." autocomplete="off" autofocus>
            <button class="eye-btn" type="button" id="eye-btn" onclick="togglePwVis()" tabindex="-1">
                <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <div class="gate-err" id="pw-err"></div>
        <button class="gate-btn" onclick="checkPw()">MASUK &rarr;</button>
        <p class="gate-hint">Hubungi tim admin untuk mendapatkan akses.</p>
    </div>
</div>

<script>
(function(){
    // Hash SHA-256 dari "systemintegration"
    const HASH = 'f4323317591e7ce27ca116c72305fdf4a7ea6b10373587a3e107e1aeb655ecb1';
    async function sha256(str){
        const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(str));
        return Array.from(new Uint8Array(buf)).map(b=>b.toString(16).padStart(2,'0')).join('');
    }
    // Cek session
    if(sessionStorage.getItem('docs_auth')==='1'){
        document.getElementById('pw-gate').style.display='none';
    }
    window.checkPw = async function(){
        const val = document.getElementById('pw-input').value;
        const hash = await sha256(val);
        if(hash===HASH){
            sessionStorage.setItem('docs_auth','1');
            const gate = document.getElementById('pw-gate');
            gate.style.transition='opacity .4s';
            gate.style.opacity='0';
            setTimeout(()=>gate.style.display='none', 400);
        } else {
            const inp = document.getElementById('pw-input');
            const err = document.getElementById('pw-err');
            err.textContent = 'Password salah. Coba lagi.';
            inp.classList.add('shake');
            inp.value='';
            setTimeout(()=>{ inp.classList.remove('shake'); err.textContent=''; }, 1500);
        }
    };
    window.togglePwVis = function(){
        const inp = document.getElementById('pw-input');
        inp.type = inp.type==='password' ? 'text' : 'password';
    };
    document.addEventListener('keydown', function(e){
        if(e.key==='Enter'){ window.checkPw(); }
    });
})();
</script>

<div class="topbar-glow"></div>

<!-- TOPNAV -->
<nav class="topnav">
    <div class="nav-brand">
        <div class="nav-logo"><i data-lucide="book-open"></i></div>
        <div>
            <div class="nav-title">SAVERA // API DOCS</div>
            <div class="nav-subtitle">Mobile Developer Reference</div>
        </div>
    </div>
    <div class="nav-right">
        <span class="version-badge"><i data-lucide="tag" style="width:10px;height:10px;vertical-align:middle"></i>&nbsp;v1.0</span>
        <a href="/" class="nav-btn"><i data-lucide="monitor" style="width:12px;height:12px"></i> Dashboard</a>
    </div>
</nav>

<!-- LAYOUT -->
<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-label">Overview</div>
            <a href="#overview" class="sidebar-link active"><span class="sl-method m-get">INFO</span> Base URL &amp; Auth</a>
            <a href="#headers" class="sidebar-link"><span class="sl-method m-get">INFO</span> Required Headers</a>
            <a href="#errors" class="sidebar-link"><span class="sl-method m-get">INFO</span> Error Responses</a>
        </div>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section">
            <div class="sidebar-section-label">Authentication</div>
            <a href="#ep-health"          class="sidebar-link"><span class="sl-method m-get">GET</span>  Health Check</a>
            <a href="#ep-register"        class="sidebar-link"><span class="sl-method m-post">POST</span> Register</a>
            <a href="#ep-login"           class="sidebar-link"><span class="sl-method m-post">POST</span> Login</a>
            <a href="#ep-logout"          class="sidebar-link"><span class="sl-method m-post">POST</span> Logout</a>
            <a href="#ep-change-password" class="sidebar-link"><span class="sl-method m-post">POST</span> Change Password</a>
        </div>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section">
            <div class="sidebar-section-label">Profile &amp; Device</div>
            <a href="#ep-profile" class="sidebar-link"><span class="sl-method m-get">GET</span>  Profile</a>
            <a href="#ep-device"  class="sidebar-link"><span class="sl-method m-get">GET</span>  Device Info</a>
            <a href="#ep-avatar"  class="sidebar-link"><span class="sl-method m-any">ANY</span>  Avatar</a>
            <a href="#ep-banner"  class="sidebar-link"><span class="sl-method m-get">GET</span>  Banner List</a>
        </div>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section">
            <div class="sidebar-section-label">Health Metrics</div>
            <a href="#ep-summary" class="sidebar-link"><span class="sl-method m-post">POST</span> Summary (Daily)</a>
            <a href="#ep-detail"  class="sidebar-link"><span class="sl-method m-post">POST</span> Detail (Raw)</a>
            <a href="#ep-ticket"  class="sidebar-link"><span class="sl-method m-get">GET</span>  Ticket</a>
            <a href="#ep-etiket"  class="sidebar-link"><span class="sl-method m-get">GET</span>  Etiket / Lineup</a>
            <a href="#ep-ranking" class="sidebar-link"><span class="sl-method m-get">GET</span>  Ranking</a>
        </div>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section">
            <div class="sidebar-section-label">Attendance</div>
            <a href="#ep-leave" class="sidebar-link"><span class="sl-method m-post">POST</span> Leave Request</a>
        </div>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section">
            <div class="sidebar-section-label">P5M Quiz</div>
            <a href="#ep-p5m-show"   class="sidebar-link"><span class="sl-method m-get">GET</span>  P5M Questions</a>
            <a href="#ep-p5m-submit" class="sidebar-link"><span class="sl-method m-post">POST</span> P5M Submit</a>
            <a href="#ep-p5m-scores" class="sidebar-link"><span class="sl-method m-get">GET</span>  P5M Scores</a>
            <a href="#ep-p5m-history" class="sidebar-link"><span class="sl-method m-get">GET</span>  P5M History</a>
            <a href="#ep-p5m-history-detail" class="sidebar-link"><span class="sl-method m-get">GET</span>  P5M History Detail</a>
        </div>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section">
            <div class="sidebar-section-label">Notifications</div>
            <a href="#ep-notif-list" class="sidebar-link"><span class="sl-method m-get">GET</span>  Notification List</a>
            <a href="#ep-notif-read" class="sidebar-link"><span class="sl-method m-post">POST</span> Mark as Read</a>
        </div>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section">
            <div class="sidebar-section-label">Data Structures</div>
            <a href="#ds-activity"  class="sidebar-link"><span class="sl-method m-any">OBJ</span> data_activity</a>
            <a href="#ds-spo2"      class="sidebar-link"><span class="sl-method m-any">OBJ</span> data_spo2</a>
            <a href="#ds-stress"    class="sidebar-link"><span class="sl-method m-any">OBJ</span> data_stress</a>
            <a href="#ds-sleep"     class="sidebar-link"><span class="sl-method m-any">OBJ</span> data_sleep</a>
        </div>
        <div class="sidebar-divider"></div>
        <div class="sidebar-section">
            <div class="sidebar-section-label">Integration Guide</div>
            <a href="#guide-flow"   class="sidebar-link"><span class="sl-method m-get">▶</span>  App Flow</a>
            <a href="#guide-errors" class="sidebar-link"><span class="sl-method m-get">▶</span>  Error Handling</a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">

        <!-- HERO -->
        <div class="hero" id="overview">
            <h1>API Reference</h1>
            <p class="hero-sub">Dokumentasi lengkap endpoint Savera API untuk developer mobile. Semua request harus menggunakan HTTPS. Header <code style="font-family:'JetBrains Mono',monospace;font-size:12px;background:rgba(0,212,255,0.08);padding:1px 6px;border-radius:3px;color:var(--cyan)">Accept: application/json</code> sangat disarankan untuk semua request, dan wajib untuk sebagian besar endpoint protected.</p>
            <div class="hero-meta">
                <div class="hmeta"><i data-lucide="globe"></i>&nbsp;<strong>Base URL</strong>&nbsp;<code>https://savera_api.ungguldinamika.com/api</code></div>
                <div class="hmeta"><i data-lucide="shield-check"></i>&nbsp;<strong>Auth</strong>&nbsp;<code>Bearer Token (Sanctum)</code></div>
                <div class="hmeta"><i data-lucide="layers"></i>&nbsp;<strong>Format</strong>&nbsp;<code>application/json</code></div>
            </div>
        </div>

        <!-- HEADERS SECTION -->
        <div class="section-label purple" id="headers"><span class="sl-dot"></span>Required Headers</div>

        <div class="ep-card" style="margin-bottom:12px">
            <div style="padding:20px">
                <p class="ep-desc">Sebagian besar endpoint API menggunakan header berikut. Untuk endpoint publik seperti <code style="font-family:'JetBrains Mono',monospace">/login</code>, <code style="font-family:'JetBrains Mono',monospace">/register</code>, dan <code style="font-family:'JetBrains Mono',monospace">/health</code>, header <code style="font-family:'JetBrains Mono',monospace">Accept</code> tidak wajib, tetapi tetap disarankan.</p>
                <div class="hdr-row">
                    <span class="hdr-key">Accept</span>
                    <span class="hdr-val">application/json</span>
                    <span class="hdr-req req-opt">Disarankan (publik), wajib untuk mayoritas endpoint protected</span>
                </div>
                <div class="hdr-row">
                    <span class="hdr-key">Authorization</span>
                    <span class="hdr-val">Bearer <span style="color:var(--amber)">{token}</span></span>
                    <span class="hdr-req req-yes">WAJIB (endpoint protected)</span>
                </div>
                <div class="hdr-row">
                    <span class="hdr-key">company</span>
                    <span class="hdr-val"><span style="color:var(--amber)">{company_code}</span> &nbsp;<span style="color:var(--muted);font-size:10px">contoh: TEST</span></span>
                    <span class="hdr-req req-opt">Disarankan (endpoint protected)</span>
                </div>
                <div class="hdr-row">
                    <span class="hdr-key">Content-Type</span>
                    <span class="hdr-val">application/json <span style="color:var(--muted);font-size:10px">(atau multipart/form-data untuk upload)</span></span>
                    <span class="hdr-req req-opt">Opsional (POST)</span>
                </div>
                <div class="info-box amber" style="margin-top:16px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div>Token dan <code>company.code</code> diperoleh dari endpoint <strong>Login</strong>. Simpan token di secure storage, lalu sertakan <code>Authorization: Bearer {token}</code> dan header <code>company: {company.code}</code> di request bertanda <span class="chip chip-amber">AUTH</span>. Jika header company kosong, server akan mencoba fallback ke company karyawan user.</div>
                </div>
            </div>
        </div>

        <!-- ERROR SECTION -->
        <div class="section-label amber" id="errors"><span class="sl-dot"></span>Standard Error Responses</div>
        <div class="ep-card" style="margin-bottom:12px">
            <div style="padding:20px">
                <table class="field-table">
                    <thead><tr><th>HTTP Status</th><th>Kondisi</th><th>Body</th></tr></thead>
                    <tbody>
                        <tr><td><span class="chip chip-green">200</span></td><td>Sukses</td><td><code style="font-family:'JetBrains Mono',monospace;font-size:10px">{ "message": "...", "data": {...} }</code></td></tr>
                        <tr><td><span class="chip chip-amber">401</span></td><td>Tidak terautentikasi / token invalid / header wajib endpoint protected tidak lengkap</td><td><code style="font-family:'JetBrains Mono',monospace;font-size:10px">{ "message": "Unauthenticated." }</code></td></tr>
                        <tr><td><span class="chip chip-amber">404</span></td><td>Resource tidak ditemukan</td><td><code style="font-family:'JetBrains Mono',monospace;font-size:10px">{ "message": "... not found." }</code></td></tr>
                        <tr><td><span class="chip chip-amber">422</span></td><td>Validasi gagal</td><td><code style="font-family:'JetBrains Mono',monospace;font-size:10px">{ "message": "...", "errors": { "field": ["..."] } }</code></td></tr>
                        <tr><td><span class="chip chip-amber">429</span></td><td>Too many requests / data sedang diproses</td><td><code style="font-family:'JetBrains Mono',monospace;font-size:10px">{ "message": "Data sedang diproses..." }</code></td></tr>
                        <tr><td><span class="chip chip-red">500</span></td><td>Server error</td><td><code style="font-family:'JetBrains Mono',monospace;font-size:10px">{ "message": "Terjadi kesalahan..." }</code></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════════════════════════════════ AUTH ═══════════════════════ -->
        <div class="section-label" id="authentication"><span class="sl-dot"></span>Authentication</div>

        <!-- HEALTH -->
        <div class="ep-card" id="ep-health">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/health</span>
                <span class="ep-title">Health Check</span>
                <span class="auth-tag auth-public">PUBLIC</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Cek apakah server API sedang aktif. Tidak memerlukan token maupun header <code style="font-family:'JetBrains Mono',monospace">company</code>. Cocok digunakan sebagai <strong>ping</strong> saat aplikasi startup.</p>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"message"</span>: <span class="s">"ok"</span>,
  <span class="n">"status"</span>: <span class="s">"healthy"</span>,
  <span class="n">"timestamp"</span>: <span class="s">"2026-04-24T08:00:00Z"</span>
<span class="k">}</span></div>
            </div>
        </div>

        <!-- REGISTER -->
        <div class="ep-card post-card" id="ep-register">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/register</span>
                <span class="ep-title">Register Akun Baru</span>
                <span class="auth-tag auth-public">PUBLIC</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Membuat akun user baru. Kembalikan token yang bisa langsung dipakai tanpa login ulang.</p>
                <div class="ep-section-title">Request Body (JSON)</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">name</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Nama lengkap user</td></tr>
                        <tr><td class="field-name">email</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Email unik, dipakai sebagai username login</td></tr>
                        <tr><td class="field-name">password</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Minimal 6 karakter</td></tr>
                        <tr><td class="field-name">password_confirmation</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Harus sama dengan <code style="font-family:'JetBrains Mono',monospace">password</code></td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"user"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"name"</span>: <span class="s">"BUDI"</span>, <span class="n">"email"</span>: <span class="s">"budi@example.com"</span> <span class="k">}</span>,
  <span class="n">"token"</span>: <span class="s">"1|abcdefghijklmnop..."</span>
<span class="k">}</span></div>
            </div>
        </div>

        <!-- LOGIN -->
        <div class="ep-card post-card" id="ep-login">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/login</span>
                <span class="ep-title">Login &amp; Dapatkan Token</span>
                <span class="auth-tag auth-public">PUBLIC</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Login menggunakan field <code style="font-family:'JetBrains Mono',monospace">email</code> dan password. Untuk user test Android, gunakan <code style="font-family:'JetBrains Mono',monospace">email=admin</code> dan <code style="font-family:'JetBrains Mono',monospace">password=admin</code>. Simpan <code style="font-family:'JetBrains Mono',monospace">token</code> dan <code style="font-family:'JetBrains Mono',monospace">company.code</code> yang dikembalikan. Token wajib disertakan sebagai <code style="font-family:'JetBrains Mono',monospace">Authorization: Bearer {token}</code>, sedangkan <code style="font-family:'JetBrains Mono',monospace">company.code</code> sebaiknya dikirim sebagai header <code style="font-family:'JetBrains Mono',monospace">company</code> di endpoint protected.</p>
                <div class="ep-section-title">Request Body (JSON)</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">email</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Nilai pada kolom <code style="font-family:'JetBrains Mono',monospace">users.email</code>. Pada data lama bisa berupa email normal atau identifier seperti <code style="font-family:'JetBrains Mono',monospace">admin</code>.</td></tr>
                        <tr><td class="field-name">password</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Minimal 5 karakter</td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"user"</span>: <span class="k">{</span>
    <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"name"</span>: <span class="s">"BUDI"</span>, <span class="n">"email"</span>: <span class="s">"budi@example.com"</span>,
    <span class="n">"created_at"</span>: <span class="s">"2026-01-01T00:00:00.000000Z"</span>
  <span class="k">}</span>,
  <span class="n">"token"</span>: <span class="s">"1|abcdefghijklmnop..."</span>,
  <span class="n">"company"</span>: <span class="k">{</span>
    <span class="n">"id"</span>: <span class="num">7</span>,
    <span class="n">"code"</span>: <span class="s">"TEST"</span>,
    <span class="n">"name"</span>: <span class="s">"PT Test Mobile"</span>
  <span class="k">}</span>
<span class="k">}</span></div>
                <div class="info-box cyan" style="margin-top:12px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>Token bersifat <strong>persistent</strong> — tidak expire otomatis. Untuk menghindari error <code>Company not found.</code>, Android sebaiknya menyimpan <code>company.code</code> dari response login dan mengirimkannya sebagai header <code>company</code>. Gunakan endpoint <code>/logout</code> untuk invalidasi token saat user keluar aplikasi.</div>
                </div>
            </div>
        </div>

        <!-- LOGOUT -->
        <div class="ep-card post-card" id="ep-logout">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/logout</span>
                <span class="ep-title">Logout (Hapus Token)</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Menghapus <strong>semua token</strong> milik user. Panggil saat user menekan tombol logout agar session bersih di server.</p>
                <div class="ep-section-title">Request Body</div>
                <p style="color:var(--muted);font-size:12px">Tidak ada body yang diperlukan. Cukup sertakan header Authorization.</p>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span> <span class="n">"message"</span>: <span class="s">"User logged out"</span> <span class="k">}</span></div>
            </div>
        </div>

        <!-- CHANGE PASSWORD -->
        <div class="ep-card post-card" id="ep-change-password">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/change-password</span>
                <span class="ep-title">Ganti Password</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Mengganti password user yang sedang login. Password lama harus cocok.</p>
                <div class="ep-section-title">Request Body (JSON)</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">old_password</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Password saat ini, minimal 6 karakter</td></tr>
                        <tr><td class="field-name">new_password</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Password baru, minimal 6 karakter</td></tr>
                        <tr><td class="field-name">new_password_confirmation</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Konfirmasi password baru</td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span> <span class="n">"message"</span>: <span class="s">"Password changed successfully"</span> <span class="k">}</span></div>
            </div>
        </div>

        <!-- ═══════════════════════ PROFILE & DEVICE ══════════════════════ -->
        <div class="section-label green" id="profile-device"><span class="sl-dot"></span>Profile &amp; Device</div>

        <!-- PROFILE -->
        <div class="ep-card" id="ep-profile">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/profile</span>
                <span class="ep-title">Data Profil User + Karyawan</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Mengambil data lengkap profil user termasuk data karyawan, departemen, mess, shift, dan perangkat terdaftar. Response di-cache 60 detik.</p>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"name"</span>: <span class="s">"BUDI"</span>, <span class="n">"email"</span>: <span class="s">"budi@example.com"</span>,
  <span class="n">"is_admin"</span>: <span class="num">0</span>,  <span class="c">// 1 = admin</span>
  <span class="n">"employee"</span>: <span class="k">{</span>
    <span class="n">"id"</span>: <span class="num">42</span>, <span class="n">"code"</span>: <span class="s">"EMP001"</span>, <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>,
    <span class="n">"job"</span>: <span class="s">"Operator"</span>, <span class="n">"status"</span>: <span class="s">"active"</span>,
    <span class="n">"department_name"</span>: <span class="s">"Mining"</span>, <span class="n">"mess_name"</span>: <span class="s">"Mess A"</span>,
    <span class="n">"photo"</span>: <span class="s">"avatar/foto.jpg"</span>   <span class="c">// null jika belum upload</span>
  <span class="k">}</span>,
  <span class="n">"shift"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"name"</span>: <span class="s">"SHIFT A"</span> <span class="k">}</span>,
  <span class="n">"device"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">5</span>, <span class="n">"mac_address"</span>: <span class="s">"AA:BB:CC:DD:EE:FF"</span>, <span class="n">"is_active"</span>: <span class="num">1</span> <span class="k">}</span>
<span class="k">}</span></div>
            </div>
        </div>

        <!-- DEVICE -->
        <div class="ep-card" id="ep-device">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/device/<span>{mac}</span></span>
                <span class="ep-title">Info Perangkat by MAC Address</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Validasi apakah smartwatch/perangkat sudah terdaftar di sistem berdasarkan MAC address. Gunakan ini saat perangkat pertama kali terhubung.</p>
                <div class="ep-section-title">URL Parameter</div>
                <table class="field-table">
                    <thead><tr><th>Parameter</th><th>Type</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">mac</td><td><span class="field-type">string</span></td><td>MAC address perangkat. Contoh: <code style="font-family:'JetBrains Mono',monospace">AA:BB:CC:DD:EE:FF</code></td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"id"</span>: <span class="num">5</span>,
  <span class="n">"mac_address"</span>: <span class="s">"AA:BB:CC:DD:EE:FF"</span>,
  <span class="n">"device_name"</span>: <span class="s">"Xiaomi Band 7"</span>,
  <span class="n">"brand"</span>: <span class="s">"Xiaomi"</span>,
  <span class="n">"auth_key"</span>: <span class="s">"a1b2c3d4e5f60011"</span>,  <span class="c">// kunci autentikasi smartwatch</span>
  <span class="n">"is_active"</span>: <span class="num">1</span>,
  <span class="n">"employee"</span>: <span class="k">{</span>
    <span class="n">"id"</span>: <span class="num">42</span>, <span class="n">"code"</span>: <span class="s">"EMP001"</span>, <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>,
    <span class="n">"department_name"</span>: <span class="s">"Mining"</span>, <span class="n">"mess_name"</span>: <span class="s">"Mess A"</span>
  <span class="k">}</span>
<span class="k">}</span></div>
            </div>
        </div>

        <!-- AVATAR -->
        <div class="ep-card" id="ep-avatar">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-any">GET|POST</span>
                <span class="ep-path">/avatar</span>
                <span class="ep-title">Ambil / Upload Foto Profil</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <div class="two-col">
                    <div>
                        <div class="ep-section-title">GET — Ambil foto (redirect ke URL)</div>
                        <p class="ep-desc" style="margin-bottom:0">Tidak perlu body. Server akan redirect ke URL foto. Gunakan sebagai <code style="font-family:'JetBrains Mono',monospace">&lt;img src="..."&gt;</code> atau download gambar.</p>
                    </div>
                    <div>
                        <div class="ep-section-title">POST — Upload foto baru</div>
                        <p class="ep-desc" style="margin-bottom:8px">Gunakan <code style="font-family:'JetBrains Mono',monospace">multipart/form-data</code>.</p>
                        <table class="field-table">
                            <thead><tr><th>Field</th><th>Type</th><th>Keterangan</th></tr></thead>
                            <tbody>
                                <tr><td class="field-name">company_id</td><td><span class="field-type">integer</span></td><td>ID perusahaan</td></tr>
                                <tr><td class="field-name">employee_id</td><td><span class="field-type">integer</span></td><td>ID karyawan</td></tr>
                                <tr><td class="field-name">photo</td><td><span class="field-type">file</span></td><td>JPEG/PNG/GIF, maks 2MB</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="ep-section-title">Response POST 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span> <span class="n">"message"</span>: <span class="s">"Successfully created"</span>, <span class="n">"data"</span>: <span class="k">{</span> <span class="n">"photo"</span>: <span class="s">"avatar/foto.jpg"</span> <span class="k">}</span> <span class="k">}</span></div>
            </div>
        </div>

        <!-- BANNER -->
        <div class="ep-card" id="ep-banner">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/banner</span>
                <span class="ep-title">Daftar Gambar Banner</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Mengembalikan array URL gambar banner yang aktif untuk ditampilkan di carousel/slider halaman utama aplikasi. Response di-cache 5 menit.</p>
                <div class="ep-section-title">Response 200 — Array URL</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">[</span>
  <span class="s">"https://adminsavera.indexim.id/image/banner/img1.jpg"</span>,
  <span class="s">"https://adminsavera.indexim.id/image/banner/img2.jpg"</span>
<span class="k">]</span></div>
            </div>
        </div>

        <!-- ═══════════════════════ HEALTH METRICS ═══════════════════════ -->
        <div class="section-label" id="health-metrics"><span class="sl-dot"></span>Health Metrics (Upload Data Smartwatch)</div>

        <!-- SUMMARY -->
        <div class="ep-card post-card" id="ep-summary">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/summary</span>
                <span class="ep-title">Upload Ringkasan Harian (Summary)</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Upload ringkasan data kesehatan harian dari smartwatch. Data ini digunakan untuk tiket kehadiran dan dashboard monitoring. Jika data hari yang sama sudah ada, akan di-<strong>overwrite</strong>.</p>
                <div class="info-box amber">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div>Kirim data ini <strong>sebelum</strong> memanggil <code>/ticket</code>. Tiket akan mengambil data dari summary hari ini.</div>
                </div>
                <div class="ep-section-title">Request Body (JSON)</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">mac_address</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>MAC address smartwatch terdaftar</td></tr>
                        <tr><td class="field-name">employee_id</td><td><span class="field-type">integer</span></td><td><span class="field-req req-yes">Ya</span></td><td>ID karyawan (dari profil)</td></tr>
                        <tr><td class="field-name">device_time</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Waktu perangkat, format <code style="font-family:'JetBrains Mono',monospace">YYYY-MM-DD HH:mm:ss</code></td></tr>
                        <tr><td class="field-name">active</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Ya</span></td><td>Menit aktif / aktivitas fisik</td></tr>
                        <tr><td class="field-name">steps</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Ya</span></td><td>Jumlah langkah kaki</td></tr>
                        <tr><td class="field-name">heart_rate</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Ya</span></td><td>Detak jantung rata-rata (bpm)</td></tr>
                        <tr><td class="field-name">distance</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Ya</span></td><td>Jarak tempuh (meter)</td></tr>
                        <tr><td class="field-name">calories</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Ya</span></td><td>Kalori terbakar</td></tr>
                        <tr><td class="field-name">spo2</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Ya</span></td><td>Saturasi oksigen (%)</td></tr>
                        <tr><td class="field-name">stress</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Ya</span></td><td>Level stres (0–100)</td></tr>
                        <tr><td class="field-name">sleep</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Ya</span></td><td>Total durasi tidur (menit)</td></tr>
                        <tr><td class="field-name">sleep_type</td><td><span class="field-type">string</span></td><td><span class="field-req req-opt">Opsional</span></td><td><code style="font-family:'JetBrains Mono',monospace">night</code> (default) atau <code style="font-family:'JetBrains Mono',monospace">nap</code></td></tr>
                        <tr><td class="field-name">sleep_start</td><td><span class="field-type">string</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Waktu mulai tidur</td></tr>
                        <tr><td class="field-name">sleep_end</td><td><span class="field-type">string</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Waktu selesai tidur</td></tr>
                        <tr><td class="field-name">deep_sleep</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Durasi deep sleep (menit)</td></tr>
                        <tr><td class="field-name">light_sleep</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Durasi light sleep (menit)</td></tr>
                        <tr><td class="field-name">rem_sleep</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Durasi REM sleep (menit)</td></tr>
                        <tr><td class="field-name">awake</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Durasi terjaga di malam hari (menit)</td></tr>
                        <tr><td class="field-name">is_fit1</td><td><span class="field-type">integer</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Minum obat? <code style="font-family:'JetBrains Mono',monospace">0</code>=tidak, <code style="font-family:'JetBrains Mono',monospace">1</code>=ya</td></tr>
                        <tr><td class="field-name">is_fit2</td><td><span class="field-type">integer</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Ada masalah konsentrasi? <code style="font-family:'JetBrains Mono',monospace">0</code>=tidak, <code style="font-family:'JetBrains Mono',monospace">1</code>=ya</td></tr>
                        <tr><td class="field-name">is_fit3</td><td><span class="field-type">integer</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Siap bekerja? <code style="font-family:'JetBrains Mono',monospace">0</code>=tidak, <code style="font-family:'JetBrains Mono',monospace">1</code>=ya</td></tr>
                        <tr><td class="field-name">app_version</td><td><span class="field-type">string</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Versi aplikasi mobile. Contoh: <code style="font-family:'JetBrains Mono',monospace">1.2.3</code></td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Contoh Request Body (JSON)</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"mac_address"</span>: <span class="s">"AA:BB:CC:DD:EE:FF"</span>,
  <span class="n">"employee_id"</span>: <span class="num">42</span>,
  <span class="n">"device_time"</span>: <span class="s">"2026-04-24 07:30:00"</span>,
  <span class="n">"active"</span>: <span class="num">45</span>,
  <span class="n">"steps"</span>: <span class="num">4200</span>,
  <span class="n">"heart_rate"</span>: <span class="num">72</span>,
  <span class="n">"distance"</span>: <span class="num">3100</span>,
  <span class="n">"calories"</span>: <span class="num">320</span>,
  <span class="n">"spo2"</span>: <span class="num">98</span>,
  <span class="n">"stress"</span>: <span class="num">25</span>,
  <span class="n">"sleep"</span>: <span class="num">450</span>,          <span class="c">// total tidur dalam menit</span>
  <span class="n">"deep_sleep"</span>: <span class="num">90</span>,
  <span class="n">"light_sleep"</span>: <span class="num">250</span>,
  <span class="n">"rem_sleep"</span>: <span class="num">80</span>,
  <span class="n">"awake"</span>: <span class="num">30</span>,
  <span class="n">"is_fit1"</span>: <span class="num">0</span>, <span class="n">"is_fit2"</span>: <span class="num">0</span>, <span class="n">"is_fit3"</span>: <span class="num">1</span>,
  <span class="n">"app_version"</span>: <span class="s">"1.2.3"</span>
<span class="k">}</span></div>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"message"</span>: <span class="s">"Successfully created"</span>,
  <span class="n">"data"</span>: <span class="k">{</span> <span class="n">"employee_id"</span>: <span class="num">42</span>, <span class="n">"send_date"</span>: <span class="s">"2026-04-24"</span>, <span class="n">"sleep"</span>: <span class="num">450</span>, <span class="c">/* ... */</span> <span class="k">}</span>
<span class="k">}</span></div>
            </div>
        </div>

        <!-- DETAIL -->
        <div class="ep-card post-card" id="ep-detail">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/detail</span>
                <span class="ep-title">Upload Data Detail / Raw Metrics</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Upload data metrik kesehatan detail (raw per-interval) termasuk detak jantung, SpO₂, stres, aktivitas, dan tidur per blok waktu. Data disimpan sebagai file JSON ke storage.</p>
                <div class="ep-section-title">Request Body (JSON)</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">mac_address</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>MAC address smartwatch</td></tr>
                        <tr><td class="field-name">employee_id</td><td><span class="field-type">integer</span></td><td><span class="field-req req-yes">Ya</span></td><td>ID karyawan</td></tr>
                        <tr><td class="field-name">device_time</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Timestamp data, format <code style="font-family:'JetBrains Mono',monospace">YYYY-MM-DD HH:mm:ss</code></td></tr>
                        <tr><td class="field-name">data_activity</td><td><span class="field-type">array/object</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Data aktivitas per interval</td></tr>
                        <tr><td class="field-name">data_sleep</td><td><span class="field-type">array/object</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Data tidur per fase</td></tr>
                        <tr><td class="field-name">data_stress</td><td><span class="field-type">array/object</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Data stres per interval</td></tr>
                        <tr><td class="field-name">data_spo2</td><td><span class="field-type">array/object</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Data SpO₂ per interval</td></tr>
                        <tr><td class="field-name">data_heart_rate_max</td><td><span class="field-type">array/object</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Data detak jantung maksimum</td></tr>
                        <tr><td class="field-name">data_heart_rate_resting</td><td><span class="field-type">array/object</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Data detak jantung istirahat</td></tr>
                        <tr><td class="field-name">data_heart_rate_manual</td><td><span class="field-type">array/object</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Pengukuran detak jantung manual</td></tr>
                        <tr><td class="field-name">app_version</td><td><span class="field-type">string</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Versi aplikasi mobile</td></tr>
                    </tbody>
                </table>
                <div class="info-box cyan" style="margin-top:10px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>Format lengkap tiap array (<code style="font-family:'JetBrains Mono',monospace">data_activity</code>, <code style="font-family:'JetBrains Mono',monospace">data_spo2</code>, <code style="font-family:'JetBrains Mono',monospace">data_stress</code>, <code style="font-family:'JetBrains Mono',monospace">data_sleep</code>) didokumentasikan di bagian <a href="#data-structures" style="color:var(--cyan)">Data Structures</a> di bawah.</div>
                </div>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span> <span class="n">"message"</span>: <span class="s">"Successfully created"</span>, <span class="n">"data"</span>: <span class="k">{</span> <span class="n">"device_time"</span>: <span class="s">"2026-04-24 08:30:00"</span>, <span class="c">/* ... */</span> <span class="k">}</span> <span class="k">}</span></div>
            </div>
        </div>

        <!-- TICKET -->
        <div class="ep-card" id="ep-ticket">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/ticket/<span>{id}</span></span>
                <span class="ep-title">Tiket Kehadiran Hari Ini</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Mengambil tiket kehadiran hari ini berdasarkan employee ID. Tiket berisi data kesehatan summary + info lineup operator (unit, shift, area). Harus kirim <code style="font-family:'JetBrains Mono',monospace">/summary</code> terlebih dahulu sebelum memanggil ini.</p>
                <div class="ep-section-title">URL Parameter</div>
                <table class="field-table">
                    <thead><tr><th>Parameter</th><th>Type</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">id</td><td><span class="field-type">integer</span></td><td>Employee ID (bukan user ID)</td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Response 200 — Full Ticket Object</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="c">// === Summary fields ===</span>
  <span class="n">"id"</span>: <span class="num">100</span>,
  <span class="n">"send_date"</span>: <span class="s">"2026-04-24"</span>,
  <span class="n">"send_time"</span>: <span class="s">"07:30:00"</span>,
  <span class="n">"sleep"</span>: <span class="num">450</span>,         <span class="c">// total tidur (menit)</span>
  <span class="n">"deep_sleep"</span>: <span class="num">90</span>,    <span class="c">// deep sleep (menit)</span>
  <span class="n">"light_sleep"</span>: <span class="num">250</span>,   <span class="c">// light sleep (menit)</span>
  <span class="n">"rem_sleep"</span>: <span class="num">80</span>,     <span class="c">// REM sleep (menit)</span>
  <span class="n">"awake"</span>: <span class="num">30</span>,         <span class="c">// menit terjaga saat malam</span>
  <span class="n">"sleep_type"</span>: <span class="s">"day"</span>,   <span class="c">// "day" | "night"</span>
  <span class="n">"heart_rate"</span>: <span class="num">72</span>,
  <span class="n">"spo2"</span>: <span class="num">98</span>,
  <span class="n">"stress"</span>: <span class="num">25</span>,
  <span class="n">"steps"</span>: <span class="num">4200</span>,
  <span class="n">"active"</span>: <span class="num">45</span>,
  <span class="n">"distance"</span>: <span class="num">3100</span>,
  <span class="n">"calories"</span>: <span class="num">320</span>,
  <span class="n">"is_fit1"</span>: <span class="num">0</span>,          <span class="c">// minum obat: 0=tidak, 1=ya</span>
  <span class="n">"is_fit2"</span>: <span class="num">0</span>,          <span class="c">// masalah konsentrasi: 0=tidak, 1=ya</span>
  <span class="n">"is_fit3"</span>: <span class="num">1</span>,          <span class="c">// siap bekerja: 0=tidak, 1=ya</span>
  <span class="n">"app_version"</span>: <span class="s">"1.2.3"</span>,
  <span class="n">"employee_id"</span>: <span class="num">42</span>,
  <span class="n">"department_id"</span>: <span class="num">3</span>,
  <span class="c">// === Lineup / Shift info (dari sistem lineup operator) ===</span>
  <span class="n">"shift"</span>: <span class="s">"A"</span>,           <span class="c">// shift detail (strip SHIFT prefix)</span>
  <span class="n">"hauler"</span>: <span class="s">"HD785-1"</span>,    <span class="c">// nomor unit hauler</span>
  <span class="n">"loader"</span>: <span class="s">"EX1200-6"</span>,   <span class="c">// nomor fleet loader</span>
  <span class="n">"transport"</span>: <span class="s">"BUS-03"</span>,   <span class="c">// nomor bus transport</span>
  <span class="c">// === Formatted display fields ===</span>
  <span class="n">"date"</span>: <span class="s">"24 April 2026"</span>,   <span class="c">// format tampilan tanggal</span>
  <span class="n">"time"</span>: <span class="s">"07:30:00"</span>,
  <span class="n">"sleep_text"</span>: <span class="s">"-"</span>,          <span class="c">// label tidur (diisi server)</span>
  <span class="n">"message"</span>: <span class="s">"Minum Obat: tidak\n  Ada Masalah Konsentrasi: tidak\n  Siap Bekerja: ya"</span>
<span class="k">}</span></div>
                <div class="info-box cyan" style="margin-top:12px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>Jika lineup operator belum diinput oleh admin, field <code style="font-family:'JetBrains Mono',monospace">shift</code>, <code style="font-family:'JetBrains Mono',monospace">hauler</code>, <code style="font-family:'JetBrains Mono',monospace">loader</code>, <code style="font-family:'JetBrains Mono',monospace">transport</code> akan bernilai <code style="font-family:'JetBrains Mono',monospace">"-"</code>.</div>
                </div>
            </div>
        </div>

                <!-- ETIKET -->
                <div class="ep-card" id="ep-etiket">
                        <div class="ep-header" onclick="toggleEp(this)">
                                <span class="method-badge mb-get">GET</span>
                                <span class="ep-path">/etiket</span>
                                <span class="ep-title">Lineup Operator / Etiket</span>
                                <span class="auth-tag auth-private">AUTH</span>
                                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
                        </div>
                        <div class="ep-body">
                                <p class="ep-desc">Mengambil data lineup operator dari tabel <code style="font-family:'JetBrains Mono',monospace">lineup_operator</code> untuk company aktif. Secara default endpoint akan memfilter data berdasarkan NIK user login. Dapat dipakai untuk layar etiket/lineup di mobile.</p>
                                <div class="ep-section-title">Query Parameter</div>
                                <table class="field-table">
                                        <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                                        <tbody>
                                                <tr><td class="field-name">tanggal</td><td><span class="field-type">date</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Format <code style="font-family:'JetBrains Mono',monospace">YYYY-MM-DD</code>. Jika diisi, hanya data tanggal tersebut yang diambil.</td></tr>
                                                <tr><td class="field-name">nik</td><td><span class="field-type">string</span></td><td><span class="field-req req-opt">Opsional</span></td><td>NIK operator. Jika kosong, backend otomatis pakai NIK user login.</td></tr>
                                        </tbody>
                                </table>
                                <div class="ep-section-title">Response 200</div>
                                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
    <span class="n">"message"</span>: <span class="s">"ok"</span>,
    <span class="n">"data"</span>: <span class="k">[</span>
        <span class="k">{</span>
            <span class="n">"no"</span>: <span class="num">12</span>,
            <span class="n">"tanggal"</span>: <span class="s">"2026-04-27"</span>,
            <span class="n">"unit"</span>: <span class="s">"HD785-1"</span>,
            <span class="n">"nik"</span>: <span class="s">"24011950928"</span>,
            <span class="n">"nama"</span>: <span class="s">"ALFIAN"</span>,
            <span class="n">"shift"</span>: <span class="s">"SHIFT A"</span>,
            <span class="n">"keterangan"</span>: <span class="s">"ON DUTY"</span>,
            <span class="n">"shift_detil"</span>: <span class="s">"SHIFT A"</span>,
            <span class="n">"pit"</span>: <span class="s">"PIT 1"</span>,
            <span class="n">"area"</span>: <span class="s">"NORTH"</span>,
            <span class="n">"region"</span>: <span class="s">"REG-1"</span>,
            <span class="n">"tipe_unit"</span>: <span class="s">"HAULER"</span>,
            <span class="n">"model_unit"</span>: <span class="s">"HD785"</span>,
            <span class="n">"fleet"</span>: <span class="s">"F12"</span>,
            <span class="n">"no_bus"</span>: <span class="s">"BUS-03"</span>,
            <span class="n">"company_id"</span>: <span class="num">1</span>,
            <span class="n">"updated_at"</span>: <span class="s">"2026-04-27T06:20:00.000000Z"</span>
        <span class="k">}</span>
    <span class="k">]</span>,
    <span class="n">"meta"</span>: <span class="k">{</span>
        <span class="n">"total"</span>: <span class="num">1</span>,
        <span class="n">"company_id"</span>: <span class="num">1</span>,
        <span class="n">"nik"</span>: <span class="s">"24011950928"</span>,
        <span class="n">"tanggal"</span>: <span class="s">"2026-04-27"</span>,
        <span class="n">"source_connection"</span>: <span class="s">"pgsql"</span>,
        <span class="n">"table_available"</span>: <span class="num">true</span>
    <span class="k">}</span>
<span class="k">}</span></div>
                        </div>
                </div>

        <!-- RANKING -->
        <div class="ep-card" id="ep-ranking">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/ranking/<span>{id?}</span></span>
                <span class="ep-title">Ranking Tidur Bulan Ini</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Mengembalikan ranking 10 karyawan dengan rata-rata tidur terbaik bulan ini. Jika <code style="font-family:'JetBrains Mono',monospace">id</code> disertakan, respons juga mencantumkan posisi ranking karyawan tersebut. Response di-cache 5 menit.</p>
                <div class="ep-section-title">URL Parameter</div>
                <table class="field-table">
                    <thead><tr><th>Parameter</th><th>Keterangan</th></tr></thead>
                    <tbody><tr><td class="field-name">id</td><td>Employee ID (opsional). Jika disertakan, field <code style="font-family:'JetBrains Mono',monospace">rank</code> akan berisi posisi ranking karyawan ini.</td></tr></tbody>
                </table>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"message"</span>: <span class="s">"ok"</span>, <span class="n">"total"</span>: <span class="num">150</span>, <span class="n">"rank"</span>: <span class="num">3</span>,
  <span class="n">"average"</span>: <span class="s">"07:12"</span>, <span class="n">"date"</span>: <span class="s">"24 Apr 2026"</span>,
  <span class="n">"data"</span>: <span class="k">[</span>
    <span class="k">{</span>
      <span class="n">"employee_id"</span>: <span class="num">42</span>,
      <span class="n">"code"</span>: <span class="s">"EMP001"</span>,           <span class="c">// NIK karyawan</span>
      <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>,
      <span class="n">"average_sleep_hour"</span>: <span class="s">"07:45"</span>,  <span class="c">// format HH:MM</span>
      <span class="n">"count_data"</span>: <span class="num">20</span>             <span class="c">// jumlah hari data tersedia bulan ini</span>
    <span class="k">}</span>
  <span class="k">]</span>
<span class="k">}</span></div>
            </div>
        </div>

        <!-- ═════════════════════════ ATTENDANCE ═════════════════════════ -->
        <div class="section-label amber" id="attendance"><span class="sl-dot"></span>Attendance</div>

        <!-- LEAVE -->
        <div class="ep-card post-card" id="ep-leave">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/leave</span>
                <span class="ep-title">Ajukan Izin / Cuti</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Mengajukan permohonan izin/cuti untuk hari ini. Jika sudah ada pengajuan dengan jenis yang sama hari ini, data akan di-<strong>overwrite</strong>.</p>
                <div class="ep-section-title">Request Body (JSON)</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">employee_id</td><td><span class="field-type">integer</span></td><td><span class="field-req req-yes">Ya</span></td><td>ID karyawan</td></tr>
                        <tr><td class="field-name">type</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Jenis izin. Contoh: <code style="font-family:'JetBrains Mono',monospace">SAKIT</code>, <code style="font-family:'JetBrains Mono',monospace">CUTI</code>, <code style="font-family:'JetBrains Mono',monospace">IZIN</code></td></tr>
                        <tr><td class="field-name">phone</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Nomor telepon yang bisa dihubungi</td></tr>
                        <tr><td class="field-name">note</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Ya</span></td><td>Keterangan / alasan izin</td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span> <span class="n">"message"</span>: <span class="s">"Successfully created"</span>, <span class="n">"data"</span>: <span class="k">{</span> <span class="n">"type"</span>: <span class="s">"SAKIT"</span>, <span class="n">"date"</span>: <span class="s">"2026-04-24"</span>, <span class="c">/* ... */</span> <span class="k">}</span> <span class="k">}</span></div>
            </div>
        </div>

        <!-- ═══════════════════════════ P5M ══════════════════════════════ -->
        <div class="section-label purple" id="p5m"><span class="sl-dot"></span>P5M (Pre-Shift Safety Quiz)</div>

        <!-- P5M SHOW -->
        <div class="ep-card" id="ep-p5m-show">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/p5m</span>
                <span class="ep-title">Ambil Soal P5M + Riwayat Skor</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Mengambil soal kuis P5M (Pre-Shift Safety Briefing) beserta riwayat skor 30 hari terakhir. Field <code style="font-family:'JetBrains Mono',monospace">already_submitted</code> menunjukkan apakah kuis hari ini sudah dikerjakan.</p>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"message"</span>: <span class="s">"ok"</span>,
  <span class="n">"data"</span>: <span class="k">{</span>
    <span class="n">"date"</span>: <span class="s">"2026-04-24"</span>,
    <span class="n">"already_submitted"</span>: <span class="num">false</span>,  <span class="c">// true = sudah isi hari ini</span>
    <span class="n">"today_score"</span>: <span class="num">null</span>,          <span class="c">// { "score": 80, "date": "2026-04-24" } jika sudah</span>
    <span class="n">"employee"</span>: <span class="k">{</span>               <span class="c">// data karyawan yang sedang login</span>
      <span class="n">"id"</span>: <span class="num">42</span>, <span class="n">"code"</span>: <span class="s">"EMP001"</span>, <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>,
            <span class="n">"job"</span>: <span class="s">"Operator"</span>, <span class="n">"company_id"</span>: <span class="num">1</span>, <span class="n">"company_code"</span>: <span class="s">"UDU"</span>,
            <span class="n">"company_name"</span>: <span class="s">"PT Unggul Dinamika Utama"</span>, <span class="n">"department_id"</span>: <span class="num">3</span>, <span class="n">"department_name"</span>: <span class="s">"Produksi"</span>,
            <span class="n">"company"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"code"</span>: <span class="s">"UDU"</span>, <span class="n">"name"</span>: <span class="s">"PT Unggul Dinamika Utama"</span> <span class="k">}</span>,
            <span class="n">"department"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">3</span>, <span class="n">"name"</span>: <span class="s">"Produksi"</span> <span class="k">}</span>
    <span class="k">}</span>,
    <span class="n">"quiz"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"title"</span>: <span class="s">"Safety Briefing April"</span> <span class="k">}</span>,
    <span class="n">"items"</span>: <span class="k">[</span>
      <span class="k">{</span>
        <span class="n">"id"</span>: <span class="num">10</span>, <span class="n">"seq"</span>: <span class="num">1</span>,
        <span class="n">"question"</span>: <span class="s">"APD wajib digunakan saat bekerja di area tambang adalah..."</span>,
        <span class="n">"correct_answer"</span>: <span class="s">"A"</span>,
        <span class="n">"choices"</span>: <span class="k">[</span>
          <span class="k">{</span> <span class="n">"value"</span>: <span class="s">"A"</span>, <span class="n">"text"</span>: <span class="s">"Helm safety, rompi, sepatu safety"</span> <span class="k">}</span>,
          <span class="k">{</span> <span class="n">"value"</span>: <span class="s">"B"</span>, <span class="n">"text"</span>: <span class="s">"Helm saja"</span> <span class="k">}</span>,
          <span class="k">{</span> <span class="n">"value"</span>: <span class="s">"C"</span>, <span class="n">"text"</span>: <span class="s">"Rompi dan sepatu"</span> <span class="k">}</span>,
          <span class="k">{</span> <span class="n">"value"</span>: <span class="s">"D"</span>, <span class="n">"text"</span>: <span class="s">"Tidak wajib"</span> <span class="k">}</span>
        <span class="k">]</span>
      <span class="k">}</span>
    <span class="k">]</span>,
    <span class="n">"scores"</span>: <span class="k">[</span>
            <span class="k">{</span> <span class="n">"id"</span>: <span class="num">77</span>, <span class="n">"date"</span>: <span class="s">"2026-04-23"</span>, <span class="n">"score"</span>: <span class="num">90</span>, <span class="n">"code"</span>: <span class="s">"EMP001"</span>, <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>, <span class="n">"department"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">3</span>, <span class="n">"name"</span>: <span class="s">"Produksi"</span> <span class="k">}</span>, <span class="n">"company"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"code"</span>: <span class="s">"UDU"</span>, <span class="n">"name"</span>: <span class="s">"PT Unggul Dinamika Utama"</span> <span class="k">}</span> <span class="k">}</span>,
            <span class="k">{</span> <span class="n">"id"</span>: <span class="num">76</span>, <span class="n">"date"</span>: <span class="s">"2026-04-22"</span>, <span class="n">"score"</span>: <span class="num">80</span>, <span class="n">"code"</span>: <span class="s">"EMP001"</span>, <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>, <span class="n">"department"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">3</span>, <span class="n">"name"</span>: <span class="s">"Produksi"</span> <span class="k">}</span>, <span class="n">"company"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"code"</span>: <span class="s">"UDU"</span>, <span class="n">"name"</span>: <span class="s">"PT Unggul Dinamika Utama"</span> <span class="k">}</span> <span class="k">}</span>
    <span class="k">]</span>
  <span class="k">}</span>
<span class="k">}</span></div>
            </div>
        </div>

        <!-- P5M SUBMIT -->
        <div class="ep-card post-card" id="ep-p5m-submit">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/p5m</span>
                <span class="ep-title">Submit Jawaban P5M</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Submit jawaban kuis P5M hari ini. Hanya bisa submit <strong>sekali per hari</strong>. Skor dihitung otomatis di server.</p>
                <div class="ep-section-title">Request Body (JSON)</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">quiz_id</td><td><span class="field-type">integer</span></td><td><span class="field-req req-yes">Ya</span></td><td>ID quiz dari <code style="font-family:'JetBrains Mono',monospace">/p5m GET</code></td></tr>
                        <tr><td class="field-name">answer</td><td><span class="field-type">array</span></td><td><span class="field-req req-yes">Ya</span></td><td>Min. 2 jawaban. Tiap item: <code style="font-family:'JetBrains Mono',monospace">{ "id": item_id, "value": "A" }</code></td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Contoh Request</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"quiz_id"</span>: <span class="num">1</span>,
  <span class="n">"answer"</span>: <span class="k">[</span>
    <span class="k">{</span> <span class="n">"id"</span>: <span class="num">10</span>, <span class="n">"value"</span>: <span class="s">"A"</span> <span class="k">}</span>,
    <span class="k">{</span> <span class="n">"id"</span>: <span class="num">11</span>, <span class="n">"value"</span>: <span class="s">"C"</span> <span class="k">}</span>
  <span class="k">]</span>
<span class="k">}</span></div>
                <div class="ep-section-title">Response 200 — Submit Berhasil</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"message"</span>: <span class="s">"Successfully created"</span>,
  <span class="n">"data"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">55</span>, <span class="n">"date"</span>: <span class="s">"2026-04-24"</span>, <span class="n">"score"</span>: <span class="num">80</span>, <span class="n">"quiz_id"</span>: <span class="num">1</span> <span class="k">}</span>
<span class="k">}</span></div>
                <div class="ep-section-title">Response 200 — Sudah Pernah Submit Hari Ini</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"message"</span>: <span class="s">"Already submitted"</span>,
  <span class="n">"data"</span>: <span class="num">null</span>
<span class="k">}</span></div>
                <div class="info-box amber" style="margin-top:10px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div>Jika <code style="font-family:'JetBrains Mono',monospace">already_submitted: true</code> dari <code>GET /p5m</code>, <strong>jangan tampilkan form kuis</strong>. Submit ulang akan mengembalikan HTTP 200 dengan <code>message: "Already submitted"</code> — bukan error. Cek field ini sebelum menampilkan form kuis kepada user.</div>
                </div>
            </div>
        </div>

        <!-- P5M SCORES -->
        <div class="ep-card" id="ep-p5m-scores">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/p5m/scores</span>
                <span class="ep-title">Riwayat Skor P5M</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                                <p class="ep-desc">Mengambil ringkasan nilai P5M terbaru yang sudah dikerjakan user login. Tiap row sudah memuat <code style="font-family:'JetBrains Mono',monospace">code</code>, <code style="font-family:'JetBrains Mono',monospace">fullname</code>, <code style="font-family:'JetBrains Mono',monospace">department</code>, dan <code style="font-family:'JetBrains Mono',monospace">company</code>.</p>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"message"</span>: <span class="s">"ok"</span>,
  <span class="n">"data"</span>: <span class="k">[</span>
        <span class="k">{</span> <span class="n">"id"</span>: <span class="num">55</span>, <span class="n">"date"</span>: <span class="s">"2026-04-24"</span>, <span class="n">"score"</span>: <span class="num">80</span>, <span class="n">"code"</span>: <span class="s">"EMP001"</span>, <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>, <span class="n">"department"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">3</span>, <span class="n">"name"</span>: <span class="s">"Produksi"</span> <span class="k">}</span>, <span class="n">"company"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"code"</span>: <span class="s">"UDU"</span>, <span class="n">"name"</span>: <span class="s">"PT Unggul Dinamika Utama"</span> <span class="k">}</span>, <span class="n">"quiz_title"</span>: <span class="s">"Safety Briefing April"</span> <span class="k">}</span>,
        <span class="k">{</span> <span class="n">"id"</span>: <span class="num">54</span>, <span class="n">"date"</span>: <span class="s">"2026-04-23"</span>, <span class="n">"score"</span>: <span class="num">90</span>, <span class="n">"code"</span>: <span class="s">"EMP001"</span>, <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>, <span class="n">"department"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">3</span>, <span class="n">"name"</span>: <span class="s">"Produksi"</span> <span class="k">}</span>, <span class="n">"company"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"code"</span>: <span class="s">"UDU"</span>, <span class="n">"name"</span>: <span class="s">"PT Unggul Dinamika Utama"</span> <span class="k">}</span>, <span class="n">"quiz_title"</span>: <span class="s">"Safety Briefing April"</span> <span class="k">}</span>
    <span class="k">]</span>
<span class="k">}</span></div>
                        </div>
                </div>

                <!-- P5M HISTORY -->
                <div class="ep-card" id="ep-p5m-history">
                        <div class="ep-header" onclick="toggleEp(this)">
                                <span class="method-badge mb-get">GET</span>
                                <span class="ep-path">/p5m/history</span>
                                <span class="ep-title">Riwayat P5M Yang Sudah Dikerjakan</span>
                                <span class="auth-tag auth-private">AUTH</span>
                                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
                        </div>
                        <div class="ep-body">
                                <p class="ep-desc">Mengambil riwayat P5M yang sudah dikerjakan oleh user login. Endpoint ini mendukung filter tanggal dan pagination ringan via query string, serta menyertakan summary skor untuk data yang sedang ditampilkan.</p>
                                <div class="ep-section-title">Query Parameter</div>
                                <table class="field-table">
                                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                                    <tbody>
                                        <tr><td class="field-name">from</td><td><span class="field-type">date</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Tanggal awal format <code style="font-family:'JetBrains Mono',monospace">YYYY-MM-DD</code></td></tr>
                                        <tr><td class="field-name">to</td><td><span class="field-type">date</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Tanggal akhir format <code style="font-family:'JetBrains Mono',monospace">YYYY-MM-DD</code></td></tr>
                                        <tr><td class="field-name">limit</td><td><span class="field-type">integer</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Jumlah data per halaman, default 30, max 100</td></tr>
                                        <tr><td class="field-name">page</td><td><span class="field-type">integer</span></td><td><span class="field-req req-opt">Opsional</span></td><td>Nomor halaman, default 1</td></tr>
                                    </tbody>
                                </table>
                                <div class="ep-section-title">Response 200</div>
                                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
    <span class="n">"message"</span>: <span class="s">"ok"</span>,
    <span class="n">"data"</span>: <span class="k">[</span>
        <span class="k">{</span>
            <span class="n">"id"</span>: <span class="num">55</span>,
            <span class="n">"date"</span>: <span class="s">"2026-04-24"</span>,
            <span class="n">"score"</span>: <span class="num">80</span>,
            <span class="n">"status"</span>: <span class="num">1</span>,
            <span class="n">"code"</span>: <span class="s">"EMP001"</span>,
            <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>,
            <span class="n">"job"</span>: <span class="s">"Operator"</span>,
            <span class="n">"company"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"code"</span>: <span class="s">"UDU"</span>, <span class="n">"name"</span>: <span class="s">"PT Unggul Dinamika Utama"</span> <span class="k">}</span>,
            <span class="n">"department"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">3</span>, <span class="n">"name"</span>: <span class="s">"Produksi"</span> <span class="k">}</span>,
            <span class="n">"quiz"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"title"</span>: <span class="s">"Safety Briefing April"</span> <span class="k">}</span>,
            <span class="n">"created_at"</span>: <span class="s">"2026-04-24T07:00:00.000Z"</span>
        <span class="k">}</span>
    <span class="k">]</span>,
    <span class="n">"meta"</span>: <span class="k">{</span> <span class="n">"page"</span>: <span class="num">1</span>, <span class="n">"limit"</span>: <span class="num">30</span>, <span class="n">"total"</span>: <span class="num">12</span>, <span class="n">"last_page"</span>: <span class="num">1</span>, <span class="n">"from"</span>: <span class="num">null</span>, <span class="n">"to"</span>: <span class="num">null</span> <span class="k">}</span>,
    <span class="n">"summary"</span>: <span class="k">{</span> <span class="n">"total_records"</span>: <span class="num">12</span>, <span class="n">"average_score"</span>: <span class="num">82.5</span>, <span class="n">"highest_score"</span>: <span class="num">100</span>, <span class="n">"lowest_score"</span>: <span class="num">25</span> <span class="k">}</span>
<span class="k">}</span></div>
            </div>
        </div>

                <div class="ep-card" id="ep-p5m-history-detail">
                        <div class="ep-header" onclick="toggleEp(this)">
                                <span class="method-badge mb-get">GET</span>
                                <span class="ep-path">/p5m/history/<span>{id}</span></span>
                                <span class="ep-title">Detail Histori P5M</span>
                                <span class="auth-tag auth-private">AUTH</span>
                                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
                        </div>
                        <div class="ep-body">
                            <p class="ep-desc">Mengambil detail satu histori P5M yang sudah dikerjakan, termasuk daftar jawaban per soal, jawaban benar, jawaban user, poin tiap soal, dan statistik benar/salah.</p>
                                <div class="ep-section-title">URL Parameter</div>
                                <table class="field-table">
                                        <thead><tr><th>Parameter</th><th>Type</th><th>Keterangan</th></tr></thead>
                                        <tbody><tr><td class="field-name">id</td><td><span class="field-type">integer</span></td><td>ID histori dari <code style="font-family:'JetBrains Mono',monospace">/p5m/history</code> atau <code style="font-family:'JetBrains Mono',monospace">/p5m/scores</code></td></tr></tbody>
                                </table>
                                <div class="ep-section-title">Response 200</div>
                                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
    <span class="n">"message"</span>: <span class="s">"ok"</span>,
    <span class="n">"data"</span>: <span class="k">{</span>
        <span class="n">"id"</span>: <span class="num">55</span>,
        <span class="n">"date"</span>: <span class="s">"2026-04-24"</span>,
        <span class="n">"score"</span>: <span class="num">80</span>,
        <span class="n">"code"</span>: <span class="s">"EMP001"</span>,
        <span class="n">"fullname"</span>: <span class="s">"BUDI SANTOSO"</span>,
        <span class="n">"company"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"code"</span>: <span class="s">"UDU"</span>, <span class="n">"name"</span>: <span class="s">"PT Unggul Dinamika Utama"</span> <span class="k">}</span>,
        <span class="n">"department"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">3</span>, <span class="n">"name"</span>: <span class="s">"Produksi"</span> <span class="k">}</span>,
        <span class="n">"quiz"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">1</span>, <span class="n">"title"</span>: <span class="s">"Safety Briefing April"</span> <span class="k">}</span>,
        <span class="n">"summary"</span>: <span class="k">{</span> <span class="n">"total_questions"</span>: <span class="num">4</span>, <span class="n">"correct_answers"</span>: <span class="num">3</span>, <span class="n">"wrong_answers"</span>: <span class="num">1</span>, <span class="n">"earned_points"</span>: <span class="num">75</span> <span class="k">}</span>,
        <span class="n">"answers"</span>: <span class="k">[</span>
            <span class="k">{</span>
                <span class="n">"id"</span>: <span class="num">201</span>,
                <span class="n">"item_id"</span>: <span class="num">10</span>,
                <span class="n">"seq"</span>: <span class="num">1</span>,
                <span class="n">"question"</span>: <span class="s">"APD wajib digunakan saat bekerja di area tambang adalah..."</span>,
                <span class="n">"correct_answer"</span>: <span class="s">"A"</span>,
                <span class="n">"selected_answer"</span>: <span class="s">"B"</span>,
                <span class="n">"is_correct"</span>: <span class="num">false</span>,
                <span class="n">"point"</span>: <span class="num">0</span>,
                <span class="n">"options"</span>: <span class="k">[</span>
                    <span class="k">{</span> <span class="n">"key"</span>: <span class="s">"A"</span>, <span class="n">"label"</span>: <span class="s">"Helm safety, rompi, sepatu safety"</span> <span class="k">}</span>,
                    <span class="k">{</span> <span class="n">"key"</span>: <span class="s">"B"</span>, <span class="n">"label"</span>: <span class="s">"Helm saja"</span> <span class="k">}</span>
                <span class="k">]</span>
            <span class="k">}</span>
        <span class="k">]</span>
    <span class="k">}</span>
<span class="k">}</span></div>
                        </div>
                </div>

        <!-- ══════════════════════ NOTIFICATIONS ════════════════════════ -->
        <div class="section-label green" id="notifications"><span class="sl-dot"></span>Notifications</div>

        <!-- NOTIF LIST -->
        <div class="ep-card" id="ep-notif-list">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/notifications</span>
                <span class="ep-title">Daftar Notifikasi (maks. 30)</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Mengambil 30 notifikasi terbaru untuk user yang sedang login. Notifikasi belum-dibaca (<code style="font-family:'JetBrains Mono',monospace">status=0</code>) ditampilkan di bagian atas. Field <code style="font-family:'JetBrains Mono',monospace">meta.unread_count</code> bisa dipakai untuk badge.</p>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"message"</span>: <span class="s">"ok"</span>,
  <span class="n">"data"</span>: <span class="k">[</span>
    <span class="k">{</span>
      <span class="n">"id"</span>: <span class="num">7</span>, <span class="n">"title"</span>: <span class="s">"Pengumuman Safety"</span>,
      <span class="n">"message_html"</span>: <span class="s">"&lt;p&gt;Wajib pakai APD...&lt;/p&gt;"</span>,
      <span class="n">"status"</span>: <span class="num">0</span>,  <span class="c">// 0=belum dibaca, 1=sudah dibaca</span>
      <span class="n">"published_at"</span>: <span class="s">"2026-04-24T07:00:00.000Z"</span>,
      <span class="n">"read_at"</span>: <span class="num">null</span>
    <span class="k">}</span>
  <span class="k">]</span>,
  <span class="n">"meta"</span>: <span class="k">{</span> <span class="n">"unread_count"</span>: <span class="num">2</span> <span class="k">}</span>
<span class="k">}</span></div>
            </div>
        </div>

        <!-- NOTIF READ -->
        <div class="ep-card post-card" id="ep-notif-read">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/notifications/<span>{id}</span>/read</span>
                <span class="ep-title">Tandai Notifikasi Sudah Dibaca</span>
                <span class="auth-tag auth-private">AUTH</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Menandai satu notifikasi sebagai sudah dibaca. Panggil saat user membuka / tap notifikasi. Idempotent — tidak error jika dipanggil ulang.</p>
                <div class="ep-section-title">URL Parameter</div>
                <table class="field-table">
                    <thead><tr><th>Parameter</th><th>Type</th><th>Keterangan</th></tr></thead>
                    <tbody><tr><td class="field-name">id</td><td><span class="field-type">integer</span></td><td>ID notifikasi</td></tr></tbody>
                </table>
                <div class="ep-section-title">Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">{</span>
  <span class="n">"message"</span>: <span class="s">"Successfully updated"</span>,
  <span class="n">"data"</span>: <span class="k">{</span> <span class="n">"id"</span>: <span class="num">7</span>, <span class="n">"status"</span>: <span class="num">1</span>, <span class="n">"read_at"</span>: <span class="s">"2026-04-24T08:15:00.000Z"</span> <span class="k">}</span>
<span class="k">}</span></div>
            </div>
        </div>

        <!-- ═══════════════════════ DATA STRUCTURES ═══════════════════════ -->
        <div class="section-label" id="data-structures" style="background:linear-gradient(90deg,rgba(0,229,204,0.08),transparent)"><span class="sl-dot" style="background:var(--teal)"></span>Data Structures — Format Array Detail</div>
        <div class="ep-card" style="margin-bottom:12px">
            <div style="padding:18px 20px 10px">
                <p class="ep-desc">Format array yang dikirim ke endpoint <code style="font-family:'JetBrains Mono',monospace">/detail</code> dan <code style="font-family:'JetBrains Mono',monospace">/summary</code>. Data berasal langsung dari Gadget Bridge / SDK smartwatch. Setiap field array bersifat <strong>opsional</strong> — kirim hanya data yang tersedia di perangkat.</p>
            </div>
        </div>

        <!-- data_activity -->
        <div class="ep-card" id="ds-activity">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge" style="background:rgba(0,229,204,0.15);color:var(--teal);border-color:rgba(0,229,204,0.3)">ARRAY</span>
                <span class="ep-path">data_activity</span>
                <span class="ep-title">Activity + Sleep Stage per Menit</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Array objek per interval 1 menit dari smartwatch. Digunakan untuk grafik tahap tidur, heart rate, langkah, dan kalori di admin. Timestamp dalam <strong>Unix seconds</strong>.</p>
                <div class="ep-section-title">Struktur tiap item</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">timestamp</td><td><span class="field-type">integer</span></td><td>Unix timestamp dalam <strong>detik</strong> (bukan ms). Contoh: <code style="font-family:'JetBrains Mono',monospace">1767196800</code></td></tr>
                        <tr><td class="field-name">kind</td><td><span class="field-type">string</span></td><td>Status aktivitas/tidur: <code style="font-family:'JetBrains Mono',monospace">LIGHT_SLEEP</code> · <code style="font-family:'JetBrains Mono',monospace">DEEP_SLEEP</code> · <code style="font-family:'JetBrains Mono',monospace">REM_SLEEP</code> · <code style="font-family:'JetBrains Mono',monospace">UNKNOWN</code> · <code style="font-family:'JetBrains Mono',monospace">ACTIVITY</code> · <code style="font-family:'JetBrains Mono',monospace">NOT_WORN</code></td></tr>
                        <tr><td class="field-name">rawKind</td><td><span class="field-type">integer</span></td><td>Nilai mentah dari firmware (opsional)</td></tr>
                        <tr><td class="field-name">intensity</td><td><span class="field-type">float</span></td><td>Intensitas aktivitas 0.0–1.0</td></tr>
                        <tr><td class="field-name">steps</td><td><span class="field-type">integer</span></td><td>Jumlah langkah interval ini. <code style="font-family:'JetBrains Mono',monospace">-1</code> = tidak tersedia</td></tr>
                        <tr><td class="field-name">distanceCm</td><td><span class="field-type">integer</span></td><td>Jarak dalam cm. <code style="font-family:'JetBrains Mono',monospace">-1</code> = tidak tersedia</td></tr>
                        <tr><td class="field-name">activeCalories</td><td><span class="field-type">integer</span></td><td>Kalori aktif. <code style="font-family:'JetBrains Mono',monospace">-1</code> = tidak tersedia</td></tr>
                        <tr><td class="field-name">heartRate</td><td><span class="field-type">integer</span></td><td>BPM. <code style="font-family:'JetBrains Mono',monospace">255</code> = tidak terukur (akan difilter server)</td></tr>
                        <tr><td class="field-name">provider</td><td><span class="field-type">string</span></td><td>Sumber data, contoh: <code style="font-family:'JetBrains Mono',monospace">HuamiExtendedSampleProvider</code></td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Contoh Array</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">[</span>
  <span class="k">{</span>
    <span class="n">"timestamp"</span>: <span class="num">1767196800</span>,
    <span class="n">"kind"</span>: <span class="s">"DEEP_SLEEP"</span>,
    <span class="n">"rawKind"</span>: <span class="num">5</span>,
    <span class="n">"intensity"</span>: <span class="num">0.02</span>,
    <span class="n">"rawIntensity"</span>: <span class="num">5</span>,
    <span class="n">"steps"</span>: <span class="num">0</span>,
    <span class="n">"distanceCm"</span>: <span class="num">-1</span>,
    <span class="n">"activeCalories"</span>: <span class="num">-1</span>,
    <span class="n">"heartRate"</span>: <span class="num">58</span>,
    <span class="n">"provider"</span>: <span class="s">"HuamiExtendedSampleProvider"</span>
  <span class="k">}</span>,
  <span class="k">{</span>
    <span class="n">"timestamp"</span>: <span class="num">1767196860</span>,
    <span class="n">"kind"</span>: <span class="s">"LIGHT_SLEEP"</span>,
    <span class="n">"intensity"</span>: <span class="num">0.08</span>,
    <span class="n">"steps"</span>: <span class="num">0</span>,
    <span class="n">"heartRate"</span>: <span class="num">62</span>,
    <span class="n">"provider"</span>: <span class="s">"HuamiExtendedSampleProvider"</span>
  <span class="k">}</span>
<span class="k">]</span></div>
            </div>
        </div>

        <!-- data_spo2 -->
        <div class="ep-card" id="ds-spo2">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge" style="background:rgba(0,229,204,0.15);color:var(--teal);border-color:rgba(0,229,204,0.3)">ARRAY</span>
                <span class="ep-path">data_spo2</span>
                <span class="ep-title">Blood Oxygen (SpO₂) per Interval</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Array pengukuran SpO₂ per interval (biasanya setiap 10 menit). Timestamp dalam <strong>Unix milliseconds</strong> (13 digit).</p>
                <div class="ep-section-title">Struktur tiap item</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">timestamp</td><td><span class="field-type">integer</span></td><td>Unix timestamp dalam <strong>milidetik</strong> (13 digit). Contoh: <code style="font-family:'JetBrains Mono',monospace">1767200130000</code></td></tr>
                        <tr><td class="field-name">type</td><td><span class="field-type">string</span></td><td>Jenis pengukuran: <code style="font-family:'JetBrains Mono',monospace">MANUAL</code> · <code style="font-family:'JetBrains Mono',monospace">AUTOMATIC</code></td></tr>
                        <tr><td class="field-name">spo2</td><td><span class="field-type">integer</span></td><td>Saturasi oksigen %. Nilai normal 95–100</td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Contoh Array</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">[</span>
  <span class="k">{</span> <span class="n">"timestamp"</span>: <span class="num">1767200130000</span>, <span class="n">"type"</span>: <span class="s">"MANUAL"</span>, <span class="n">"spo2"</span>: <span class="num">98</span> <span class="k">}</span>,
  <span class="k">{</span> <span class="n">"timestamp"</span>: <span class="num">1767200730000</span>, <span class="n">"type"</span>: <span class="s">"AUTOMATIC"</span>, <span class="n">"spo2"</span>: <span class="num">97</span> <span class="k">}</span>
<span class="k">]</span></div>
                <div class="info-box amber" style="margin-top:10px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div><strong>Perhatian timestamp:</strong> <code>data_spo2</code> dan <code>data_stress</code> menggunakan <strong>milliseconds</strong> (13 digit), sedangkan <code>data_activity</code> menggunakan <strong>seconds</strong> (10 digit). Server akan mengkonversi otomatis.</div>
                </div>
            </div>
        </div>

        <!-- data_stress -->
        <div class="ep-card" id="ds-stress">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge" style="background:rgba(0,229,204,0.15);color:var(--teal);border-color:rgba(0,229,204,0.3)">ARRAY</span>
                <span class="ep-path">data_stress</span>
                <span class="ep-title">Level Stres per Interval</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Array pengukuran level stres per interval (biasanya setiap 5 menit). Timestamp dalam <strong>Unix milliseconds</strong>.</p>
                <div class="ep-section-title">Struktur tiap item</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">timestamp</td><td><span class="field-type">integer</span></td><td>Unix timestamp dalam milidetik (13 digit)</td></tr>
                        <tr><td class="field-name">type</td><td><span class="field-type">string</span></td><td><code style="font-family:'JetBrains Mono',monospace">UNKNOWN</code> · <code style="font-family:'JetBrains Mono',monospace">MANUAL</code> · <code style="font-family:'JetBrains Mono',monospace">RELAXED</code> · <code style="font-family:'JetBrains Mono',monospace">MILD</code> · <code style="font-family:'JetBrains Mono',monospace">MODERATE</code> · <code style="font-family:'JetBrains Mono',monospace">HIGH</code></td></tr>
                        <tr><td class="field-name">stress</td><td><span class="field-type">integer</span></td><td>Skor stres 0–100. Semakin tinggi = semakin stres</td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Contoh Array</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">[</span>
  <span class="k">{</span> <span class="n">"timestamp"</span>: <span class="num">1754064360000</span>, <span class="n">"type"</span>: <span class="s">"UNKNOWN"</span>, <span class="n">"stress"</span>: <span class="num">33</span> <span class="k">}</span>,
  <span class="k">{</span> <span class="n">"timestamp"</span>: <span class="num">1754064960000</span>, <span class="n">"type"</span>: <span class="s">"MILD"</span>, <span class="n">"stress"</span>: <span class="num">44</span> <span class="k">}</span>
<span class="k">]</span></div>
            </div>
        </div>

        <!-- data_sleep -->
        <div class="ep-card" id="ds-sleep">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge" style="background:rgba(0,229,204,0.15);color:var(--teal);border-color:rgba(0,229,204,0.3)">ARRAY</span>
                <span class="ep-path">data_sleep</span>
                <span class="ep-title">Blok Periode Tidur</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Array periode tidur (setiap blok tidur berkesinambungan). Semua timestamp dalam <strong>Unix seconds</strong>, semua durasi dalam <strong>detik</strong>.</p>
                <div class="ep-section-title">Struktur tiap item</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">sleepStart</td><td><span class="field-type">integer</span></td><td>Unix detik saat mulai tidur</td></tr>
                        <tr><td class="field-name">sleepEnd</td><td><span class="field-type">integer</span></td><td>Unix detik saat selesai tidur</td></tr>
                        <tr><td class="field-name">totalSleepDuration</td><td><span class="field-type">integer</span></td><td>Total durasi tidur dalam <strong>detik</strong></td></tr>
                        <tr><td class="field-name">deepSleepDuration</td><td><span class="field-type">integer</span></td><td>Durasi deep sleep (detik)</td></tr>
                        <tr><td class="field-name">lightSleepDuration</td><td><span class="field-type">integer</span></td><td>Durasi light sleep (detik)</td></tr>
                        <tr><td class="field-name">remSleepDuration</td><td><span class="field-type">integer</span></td><td>Durasi REM sleep (detik)</td></tr>
                        <tr><td class="field-name">awakeSleepDuration</td><td><span class="field-type">integer</span></td><td>Durasi terjaga dalam periode tidur (detik)</td></tr>
                    </tbody>
                </table>
                <div class="ep-section-title">Contoh Array</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="k">[</span>
  <span class="k">{</span>
    <span class="n">"sleepStart"</span>: <span class="num">1767208560</span>,
    <span class="n">"sleepEnd"</span>: <span class="num">1767224400</span>,
    <span class="n">"totalSleepDuration"</span>: <span class="num">15840</span>,   <span class="c">// ~264 menit = 4 jam 24 mnt</span>
    <span class="n">"deepSleepDuration"</span>: <span class="num">5760</span>,    <span class="c">// 96 menit</span>
    <span class="n">"lightSleepDuration"</span>: <span class="num">8040</span>,   <span class="c">// 134 menit</span>
    <span class="n">"remSleepDuration"</span>: <span class="num">2100</span>,     <span class="c">// 35 menit</span>
    <span class="n">"awakeSleepDuration"</span>: <span class="num">0</span>
  <span class="k">}</span>
<span class="k">]</span></div>
                <div class="info-box cyan" style="margin-top:10px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>Konversi: <code style="font-family:'JetBrains Mono',monospace">totalSleepDuration / 60</code> = menit. Field <code style="font-family:'JetBrains Mono',monospace">sleep</code> di <code>/summary</code> menggunakan satuan <strong>menit</strong>.</div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════ INTEGRATION GUIDE ══════════════════════ -->
        <div class="section-label purple" id="integration-guide" style="background:linear-gradient(90deg,rgba(176,96,255,0.08),transparent)"><span class="sl-dot" style="background:var(--purple)"></span>Integration Guide</div>

        <!-- APP FLOW -->
        <div class="ep-card" id="guide-flow">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge" style="background:rgba(176,96,255,0.15);color:var(--purple);border-color:rgba(176,96,255,0.3)">GUIDE</span>
                <span class="ep-path">Alur Lengkap Aplikasi Mobile</span>
                <span class="ep-title">Urutan API call yang direkomendasikan</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Berikut urutan call API yang direkomendasikan untuk alur utama aplikasi Savera Mobile:</p>

                <div style="margin:16px 0">
                    <div style="display:flex;flex-direction:column;gap:0">

                        <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--outline)">
                            <div style="min-width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--cyan),var(--blue));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;flex-shrink:0">1</div>
                            <div>
                                <div style="color:var(--cyan);font-weight:600;font-size:13px">Startup — Cek Server</div>
                                <p style="color:var(--text-dim);margin:4px 0 0">Panggil <code style="font-family:'JetBrains Mono',monospace">GET /api/health</code> saat app pertama dibuka. Jika gagal, tampilkan pesan server offline.</p>
                            </div>
                        </div>

                        <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--outline)">
                            <div style="min-width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--teal));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#000;flex-shrink:0">2</div>
                            <div>
                                <div style="color:var(--green);font-weight:600;font-size:13px">Login / Register</div>
                                <p style="color:var(--text-dim);margin:4px 0 0">User login via <code style="font-family:'JetBrains Mono',monospace">POST /api/login</code>. Simpan <code style="font-family:'JetBrains Mono',monospace">token</code> dan <code style="font-family:'JetBrains Mono',monospace">company.code</code> ke secure storage. Token tidak expire — cukup login sekali.</p>
                            </div>
                        </div>

                        <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--outline)">
                            <div style="min-width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--purple),var(--pink));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;flex-shrink:0">3</div>
                            <div>
                                <div style="color:var(--purple);font-weight:600;font-size:13px">Load Profile + Validasi Device</div>
                                <p style="color:var(--text-dim);margin:4px 0">Panggil <code style="font-family:'JetBrains Mono',monospace">GET /api/profile</code> untuk data karyawan (simpan <code>employee.id</code>).<br>Saat smartwatch terhubung, panggil <code style="font-family:'JetBrains Mono',monospace">GET /api/device/{mac}</code> untuk validasi perangkat.</p>
                                <div class="info-box amber" style="margin-top:8px">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <div>Endpoint protected sebaiknya mengirim header <code style="font-family:'JetBrains Mono',monospace">company</code>. Nilai company code diperoleh dari response login. Jika header kosong, server mencoba fallback dari company karyawan user.</div>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--outline)">
                            <div style="min-width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#ff8c00);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#000;flex-shrink:0">4</div>
                            <div>
                                <div style="color:var(--amber);font-weight:600;font-size:13px">Upload Data Smartwatch (Pagi Hari)</div>
                                <p style="color:var(--text-dim);margin:4px 0 0">Setelah smartwatch sync, kirim data dalam urutan ini:</p>
                                <ol style="color:var(--text-dim);margin:8px 0 0 16px;line-height:2">
                                    <li><code style="font-family:'JetBrains Mono',monospace">POST /api/detail</code> — kirim raw metrics (data_activity, data_spo2, data_stress, data_sleep)</li>
                                    <li><code style="font-family:'JetBrains Mono',monospace">POST /api/summary</code> — kirim ringkasan harian (sleep total, heart_rate, spo2, stress, dll)</li>
                                    <li><code style="font-family:'JetBrains Mono',monospace">GET /api/ticket/{employee_id}</code> — ambil tiket kehadiran (harus setelah summary)</li>
                                </ol>
                            </div>
                        </div>

                        <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--outline)">
                            <div style="min-width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#3d7fff,var(--purple));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;flex-shrink:0">5</div>
                            <div>
                                <div style="color:var(--blue);font-weight:600;font-size:13px">Tampilkan Halaman Utama</div>
                                <p style="color:var(--text-dim);margin:4px 0 0">Setelah data terupload, ambil data tampilan utama secara paralel:</p>
                                <ul style="color:var(--text-dim);margin:8px 0 0 16px;line-height:2">
                                    <li><code style="font-family:'JetBrains Mono',monospace">GET /api/banner</code> — banner carousel</li>
                                    <li><code style="font-family:'JetBrains Mono',monospace">GET /api/ranking/{employee_id}</code> — ranking tidur bulan ini</li>
                                    <li><code style="font-family:'JetBrains Mono',monospace">GET /api/p5m</code> — soal kuis P5M + riwayat skor</li>
                                    <li><code style="font-family:'JetBrains Mono',monospace">GET /api/notifications</code> — notifikasi belum dibaca</li>
                                </ul>
                            </div>
                        </div>

                        <div style="display:flex;gap:14px;align-items:flex-start;padding:12px 0">
                            <div style="min-width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--red),#ff8c00);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;flex-shrink:0">6</div>
                            <div>
                                <div style="color:var(--red);font-weight:600;font-size:13px">Izin / Cuti / Logout</div>
                                <p style="color:var(--text-dim);margin:4px 0 0">Jika user tidak hadir: <code style="font-family:'JetBrains Mono',monospace">POST /api/leave</code>. Jika logout: <code style="font-family:'JetBrains Mono',monospace">POST /api/logout</code> untuk invalidasi token di server.</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ERROR HANDLING GUIDE -->
        <div class="ep-card" id="guide-errors">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge" style="background:rgba(176,96,255,0.15);color:var(--purple);border-color:rgba(176,96,255,0.3)">GUIDE</span>
                <span class="ep-path">Error Handling &amp; Retry</span>
                <span class="ep-title">Panduan penanganan error di aplikasi mobile</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <table class="field-table">
                    <thead><tr><th>Status</th><th>Penyebab Umum</th><th>Tindakan yang Direkomendasikan</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="chip chip-amber">401</span></td>
                            <td>Token invalid / token tidak ada / context company tidak bisa ditemukan</td>
                            <td>Cek semua header. Jika token masih ada di storage, coba <code>GET /profile</code>. Jika tetap 401 → redirect ke login screen &amp; hapus token.</td>
                        </tr>
                        <tr>
                            <td><span class="chip chip-amber">404</span> <small>Company not found</small></td>
                            <td>Header <code style="font-family:'JetBrains Mono',monospace">company</code> salah, atau user belum terhubung dengan employee/company</td>
                            <td>Gunakan <code>company.code</code> dari response login. Untuk test Android saat ini nilainya <code>TEST</code>.</td>
                        </tr>
                        <tr>
                            <td><span class="chip chip-amber">404</span> <small>Employee not found</small></td>
                            <td>User login tapi belum terdaftar sebagai karyawan di admin</td>
                            <td>Tampilkan pesan "Akun belum terdaftar sebagai karyawan. Hubungi admin."</td>
                        </tr>
                        <tr>
                            <td><span class="chip chip-amber">404</span> <small>MAC not found</small></td>
                            <td>Smartwatch belum didaftarkan di sistem admin</td>
                            <td>Tampilkan pesan "Perangkat belum terdaftar. Hubungi admin untuk mendaftarkan MAC address."</td>
                        </tr>
                        <tr>
                            <td><span class="chip chip-amber">404</span> <small>Summary not found</small></td>
                            <td><code>/ticket</code> dipanggil sebelum <code>/summary</code></td>
                            <td>Pastikan upload <code>/summary</code> berhasil (HTTP 200) sebelum memanggil <code>/ticket</code>.</td>
                        </tr>
                        <tr>
                            <td><span class="chip chip-amber">422</span></td>
                            <td>Field wajib kosong atau format salah</td>
                            <td>Baca field <code style="font-family:'JetBrains Mono',monospace">errors</code> di response untuk detail per-field. Tampilkan ke user jika relevan.</td>
                        </tr>
                        <tr>
                            <td><span class="chip chip-amber">429</span></td>
                            <td>Rate limit atau data sedang diproses server</td>
                            <td>Implementasikan exponential backoff. Coba lagi setelah 5 detik, 10 detik, 30 detik.</td>
                        </tr>
                        <tr>
                            <td><span class="chip chip-red">500</span> / Network Error</td>
                            <td>Server error atau koneksi terputus</td>
                            <td>Simpan data ke antrian lokal (queue). Kirim ulang saat koneksi kembali. Jangan hapus data sebelum server konfirmasi 200.</td>
                        </tr>
                    </tbody>
                </table>
                <div class="info-box cyan" style="margin-top:16px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div><strong>Tip Upload Offline:</strong> Untuk <code>/detail</code> dan <code>/summary</code>, simpan payload ke local database jika request gagal. Sertakan <code>device_time</code> yang akurat dari waktu perangkat saat pengukuran, bukan saat pengiriman. Server menggunakan <code>device_time</code> untuk menentukan tanggal data.</div>
                </div>
            </div>
        </div>

        <div class="section-label green" id="network-monitoring"><span class="sl-dot"></span>Network Monitoring</div>

        <div class="ep-card" id="ep-network-status">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-get">GET</span>
                <span class="ep-path">/api/network-status</span>
                <span class="ep-title">Ambil status jaringan terakhir berdasarkan MAC</span>
                <span class="auth-tag auth-private">Auth: Bearer</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Mengembalikan data network report terakhir dari perangkat user berdasar header <code>mac</code>.</p>

                <div class="ep-section-title">Headers</div>
                <div class="hdr-row"><span class="hdr-key">Authorization</span><span class="hdr-val">Bearer &lt;token&gt;</span><span class="field-req req-yes">Required</span></div>
                <div class="hdr-row"><span class="hdr-key">mac</span><span class="hdr-val">AA:BB:CC:DD:EE:FF</span><span class="field-req req-yes">Required</span></div>

                <div class="ep-section-title">Contoh Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button>{
  "status": "success",
  "data": {
    "mac_address": "AA:BB:CC:DD:EE:FF",
    "network_type": "wifi",
    "is_metered": false,
    "downlink_mbps": 22.4,
    "uplink_mbps": 11.8,
    "rtt_ms": 35,
    "device_signal_level": -61,
    "device_time": "2026-04-30 14:55:12",
    "created_at": "2026-04-30T14:55:15+08:00"
  }
}</div>
            </div>
        </div>

        <div class="ep-card post-card" id="ep-network-report">
            <div class="ep-header" onclick="toggleEp(this)">
                <span class="method-badge mb-post">POST</span>
                <span class="ep-path">/api/network-report</span>
                <span class="ep-title">Kirim telemetry kualitas koneksi dari mobile</span>
                <span class="auth-tag auth-private">Auth: Bearer</span>
                <span class="ep-toggle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
            </div>
            <div class="ep-body">
                <p class="ep-desc">Simpan data kualitas jaringan user ke tabel <code>network_reports</code> untuk monitoring dashboard dan alert.</p>

                <div class="ep-section-title">Body JSON</div>
                <table class="field-table">
                    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <tr><td class="field-name">mac_address</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Yes</span></td><td>MAC perangkat (format bebas, server normalisasi)</td></tr>
                        <tr><td class="field-name">network_type</td><td><span class="field-type">string</span></td><td><span class="field-req req-yes">Yes</span></td><td>Contoh: <code>wifi</code>, <code>cellular</code>, <code>public</code>, <code>local</code></td></tr>
                        <tr><td class="field-name">is_metered</td><td><span class="field-type">boolean</span></td><td><span class="field-req req-yes">Yes</span></td><td>Apakah koneksi berbayar/terbatas kuota</td></tr>
                        <tr><td class="field-name">downlink_mbps</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Yes</span></td><td>Kecepatan download (Mbps)</td></tr>
                        <tr><td class="field-name">uplink_mbps</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Yes</span></td><td>Kecepatan upload (Mbps)</td></tr>
                        <tr><td class="field-name">rtt_ms</td><td><span class="field-type">numeric</span></td><td><span class="field-req req-yes">Yes</span></td><td>Round-trip time / latency (ms)</td></tr>
                        <tr><td class="field-name">device_signal_level</td><td><span class="field-type">integer</span></td><td><span class="field-req req-yes">Yes</span></td><td>Kekuatan sinyal dari device</td></tr>
                        <tr><td class="field-name">device_time</td><td><span class="field-type">datetime</span></td><td><span class="field-req req-opt">Optional</span></td><td>Waktu device saat capture data</td></tr>
                    </tbody>
                </table>

                <div class="ep-section-title">Contoh Request</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button>{
  "mac_address": "AA:BB:CC:DD:EE:FF",
  "network_type": "wifi",
  "is_metered": false,
  "downlink_mbps": 28.7,
  "uplink_mbps": 13.2,
  "rtt_ms": 31,
  "device_signal_level": -58,
  "device_time": "2026-04-30 15:01:27"
}</div>

                <div class="ep-section-title">Contoh Response 200</div>
                <div class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button>{
  "status": "success",
  "message": "Network report stored"
}</div>
            </div>
        </div>

        <footer>SAVERA API DOCS &mdash; BUILT FOR MOBILE DEVELOPERS &mdash; <code style="font-family:'JetBrains Mono',monospace;font-size:10px;background:rgba(0,212,255,0.08);padding:1px 6px;border-radius:3px;color:var(--cyan)">v1.0</code> &mdash; {{ date('d M Y') }}</footer>

    </main><!-- /main -->
</div><!-- /layout -->

<script>
    if (window.lucide) lucide.createIcons();

    function toggleEp(header) {
        const body = header.nextElementSibling;
        const toggle = header.querySelector('.ep-toggle');
        const isOpen = body.classList.contains('open');
        body.classList.toggle('open', !isOpen);
        toggle.classList.toggle('open', !isOpen);
    }

    function copyCode(btn) {
        const block = btn.parentElement;
        // Get text content without button text
        const text = Array.from(block.childNodes)
            .filter(n => n !== btn)
            .map(n => n.textContent)
            .join('');
        navigator.clipboard.writeText(text.trim()).then(() => {
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 1800);
        });
    }

    // Highlight active sidebar link on scroll
    const sections = document.querySelectorAll('[id^="ep-"], #overview, #headers, #errors');
    const links    = document.querySelectorAll('.sidebar-link');
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                links.forEach(l => l.classList.remove('active'));
                const a = document.querySelector(`.sidebar-link[href="#${e.target.id}"]`);
                if (a) a.classList.add('active');
            }
        });
    }, { rootMargin: '-15% 0px -70% 0px' });
    sections.forEach(s => obs.observe(s));
</script>
</body>
</html>

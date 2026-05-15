<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savera API Upload Monitoring</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #eef4f8;
            --bg-soft: #f8fbfd;
            --ink: #102033;
            --muted: #64748b;
            --panel: rgba(255,255,255,.94);
            --panel-soft: #f8fafc;
            --line: rgba(15, 52, 79, .12);
            --line-strong: rgba(15, 52, 79, .2);
            --blue: #0f6fb7;
            --cyan: #0796b3;
            --green: #13845f;
            --amber: #b77909;
            --red: #c24141;
            --dark: #0b2436;
            --navy: #0c2a3f;
            --shadow: 0 22px 60px rgba(15, 45, 70, .12);
            --shadow-soft: 0 12px 30px rgba(15, 45, 70, .08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Poppins", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 8% 4%, rgba(7,150,179,.14), transparent 24%),
                radial-gradient(circle at 92% 0%, rgba(19,132,95,.12), transparent 25%),
                linear-gradient(135deg, var(--bg-soft) 0%, var(--bg) 58%, #e5eef4 100%);
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(15,52,79,.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15,52,79,.035) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,.78), transparent 84%);
        }
        body::after {
            content: "";
            position: fixed;
            inset: auto 0 0 0;
            height: 34vh;
            pointer-events: none;
            background: linear-gradient(180deg, transparent, rgba(255,255,255,.72));
        }
        .wrap {
            width: min(1540px, calc(100% - 36px));
            margin: 0 auto;
            padding: 22px 0 58px;
            position: relative;
            z-index: 1;
        }
        .hero {
            border-radius: 28px;
            padding: 28px;
            background:
                radial-gradient(circle at 82% 12%, rgba(255,255,255,.20), transparent 26%),
                linear-gradient(135deg, #0a2436 0%, #0e5870 58%, #1488a0 100%);
            color: white;
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(255,255,255,.22);
        }
        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px),
                linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px);
            background-size: 54px 54px;
            opacity: .28;
        }
        .hero::after {
            content: "";
            position: absolute;
            width: 390px;
            height: 390px;
            border-radius: 999px;
            right: -130px;
            top: -165px;
            background: rgba(255,255,255,.12);
        }
        .hero-top, .hero-grid, .content-grid, .toolbar { display: grid; gap: 16px; }
        .hero-top {
            grid-template-columns: 1fr auto;
            align-items: start;
            position: relative;
            z-index: 1;
        }
        .eyebrow {
            font-family: "JetBrains Mono", monospace;
            font-size: 11px;
            letter-spacing: .2em;
            text-transform: uppercase;
            opacity: .82;
            color: rgba(255,255,255,.78);
        }
        h1 {
            margin: 8px 0 8px;
            font-size: clamp(34px, 4.2vw, 58px);
            line-height: .98;
            letter-spacing: -1.8px;
            font-weight: 800;
        }
        .hero p {
            margin: 0;
            max-width: 900px;
            color: rgba(255,255,255,.84);
            font-size: 15px;
            line-height: 1.7;
            font-weight: 400;
        }
        .clock {
            min-width: 230px;
            padding: 18px 22px;
            border-radius: 22px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.26);
            text-align: right;
            backdrop-filter: blur(16px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.12);
        }
        .clock .time {
            font-family: "JetBrains Mono", monospace;
            font-size: 30px;
            font-weight: 700;
        }
        .clock .date {
            margin-top: 4px;
            font-family: "JetBrains Mono", monospace;
            font-size: 11px;
            letter-spacing: .08em;
            opacity: .78;
            text-transform: uppercase;
        }
        .hero-grid {
            grid-template-columns: repeat(7, minmax(0, 1fr));
            margin-top: 24px;
            position: relative;
            z-index: 1;
        }
        .metric, .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(18px);
        }
        .metric {
            border-radius: 20px;
            padding: 16px;
            color: white;
            background: rgba(255,255,255,.13);
            border-color: rgba(255,255,255,.22);
            min-height: 116px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform .18s ease, background .18s ease, border-color .18s ease;
        }
        .metric:hover {
            transform: translateY(-2px);
            background: rgba(255,255,255,.18);
            border-color: rgba(255,255,255,.34);
        }
        .metric small {
            display: block;
            font-family: "JetBrains Mono", monospace;
            font-size: 10px;
            letter-spacing: .12em;
            text-transform: uppercase;
            opacity: .72;
        }
        .metric strong {
            display: block;
            margin-top: 8px;
            font-family: "JetBrains Mono", monospace;
            font-size: clamp(23px, 2vw, 30px);
            line-height: 1;
        }
        .metric span {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            opacity: .74;
        }
        .toolbar {
            margin: 18px 0;
            grid-template-columns: 1fr auto auto auto auto;
            align-items: center;
            padding: 14px;
            border-radius: 24px;
            background: rgba(255,255,255,.88);
            border: 1px solid var(--line);
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(18px);
        }
        .search, .select, .button {
            border: 1px solid var(--line-strong);
            border-radius: 14px;
            background: #fff;
            padding: 12px 15px;
            font-family: "Poppins", sans-serif;
            color: var(--ink);
            outline: none;
            min-height: 46px;
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }
        .search:focus, .select:focus {
            border-color: rgba(7,150,179,.42);
            box-shadow: 0 0 0 4px rgba(7,150,179,.10);
        }
        .select {
            min-width: 170px;
        }
        .button {
            cursor: pointer;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, var(--navy), var(--cyan));
            min-width: 132px;
            border-color: transparent;
            box-shadow: 0 12px 22px rgba(7,150,179,.16);
        }
        .button:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(7,150,179,.20);
        }
        .button.secondary {
            background: linear-gradient(135deg, #475569, #718096);
            box-shadow: 0 12px 22px rgba(71,85,105,.12);
        }
        .live {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            color: var(--green);
            font-weight: 700;
            justify-content: center;
            padding: 0 8px;
        }
        .dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: var(--green);
            box-shadow: 0 0 0 8px rgba(17,132,91,.12);
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 50% { transform: scale(.72); opacity: .58; } }
        .content-grid {
            grid-template-columns: minmax(0, 1.58fr) minmax(380px, .72fr);
            align-items: start;
            gap: 18px;
        }
        .panel {
            border-radius: 24px;
            padding: 20px;
            overflow: hidden;
            background: rgba(255,255,255,.95);
        }
        .panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .panel h2 {
            margin: 0;
            font-size: 20px;
            letter-spacing: -.5px;
            font-weight: 800;
        }
        .panel p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 1080px;
        }
        th {
            text-align: left;
            color: var(--muted);
            font-family: "JetBrains Mono", monospace;
            font-size: 10px;
            letter-spacing: .11em;
            text-transform: uppercase;
            padding: 13px 12px;
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
            background: #f8fafc;
        }
        td {
            padding: 14px 12px;
            border-bottom: 1px solid rgba(15,61,82,.08);
            vertical-align: middle;
        }
        tbody tr {
            transition: background .16s ease, transform .16s ease;
        }
        tr:hover td { background: rgba(7,150,179,.055); }
        .mono { font-family: "JetBrains Mono", monospace; }
        .muted { color: var(--muted); }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 10px;
            font-family: "JetBrains Mono", monospace;
            font-size: 11px;
            font-weight: 700;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .st-completed { color: var(--green); background: rgba(17,132,91,.1); border-color: rgba(17,132,91,.2); }
        .st-processing { color: var(--blue); background: rgba(11,117,209,.1); border-color: rgba(11,117,209,.2); }
        .st-queued, .st-received { color: var(--amber); background: rgba(183,121,9,.12); border-color: rgba(183,121,9,.24); }
        .st-failed { color: var(--red); background: rgba(194,48,48,.1); border-color: rgba(194,48,48,.22); }
        .st-idle { color: var(--green); background: rgba(17,132,91,.1); }
        .st-stale { color: var(--red); background: rgba(194,48,48,.1); }
        .stack { display: grid; gap: 14px; }
        .mini {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            padding: 15px;
            border-radius: 17px;
            border: 1px solid var(--line);
            background: var(--panel-soft);
            align-items: center;
        }
        .mini strong { font-size: 15px; font-weight: 800; }
        .mini small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.55;
        }
        .speed-bars {
            display: grid;
            gap: 8px;
            padding: 14px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: var(--panel-soft);
        }
        .speed-row {
            display: grid;
            grid-template-columns: 34px 1fr 58px;
            align-items: center;
            gap: 9px;
            font-family: "JetBrains Mono", monospace;
            font-size: 11px;
            color: var(--muted);
        }
        .speed-row b {
            display: block;
            height: 9px;
            min-width: 5px;
            border-radius: 999px;
            background: var(--green);
            box-shadow: 0 0 14px rgba(17,132,91,.20);
        }
        .speed-row b.warn { background: var(--amber); box-shadow: 0 0 14px rgba(183,121,9,.20); }
        .speed-row b.bad { background: var(--red); box-shadow: 0 0 14px rgba(194,48,48,.20); }
        .speed-row b.timeout {
            background: repeating-linear-gradient(45deg, rgba(194,48,48,.82) 0 6px, rgba(194,48,48,.42) 6px 12px);
            box-shadow: 0 0 14px rgba(194,48,48,.18);
        }
        .speed-row em {
            font-style: normal;
            text-align: right;
            color: var(--ink);
            font-weight: 700;
        }
        .progress {
            width: 100px;
            height: 8px;
            border-radius: 999px;
            background: rgba(15,61,82,.10);
            overflow: hidden;
        }
        .progress b {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, var(--cyan), var(--green));
            border-radius: inherit;
        }
        .empty {
            padding: 26px;
            text-align: center;
            color: var(--muted);
        }
        .table-scroll { overflow-x: auto; }
        .table-scroll::-webkit-scrollbar { height: 10px; }
        .table-scroll::-webkit-scrollbar-track { background: #edf3f7; border-radius: 999px; }
        .table-scroll::-webkit-scrollbar-thumb { background: #b7c8d5; border-radius: 999px; }
        .error-text {
            max-width: 220px;
            color: var(--red);
            font-size: 12px;
        }
        @media (max-width: 1400px) {
            .hero-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (max-width: 1180px) {
            .hero-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .content-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .wrap { width: min(100% - 22px, 1540px); padding-top: 12px; }
            .hero { padding: 22px; border-radius: 24px; }
            .hero-top, .toolbar { grid-template-columns: 1fr; }
            .clock { text-align: left; min-width: 0; }
            .hero-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .metric strong { font-size: 24px; }
            .toolbar { padding: 12px; }
            .button, .select { width: 100%; }
        }
        @media (max-width: 520px) {
            .hero-grid { grid-template-columns: 1fr; }
            h1 { font-size: 32px; letter-spacing: -1px; }
        }
    </style>
</head>
<body>
<main class="wrap">
    <section class="hero">
        <div class="hero-top">
            <div>
                <div class="eyebrow">Savera API Command Center</div>
                <h1>Mobile Upload Monitoring</h1>
                <p>Memantau upload summary, detail JSON, sleep snapshot, queue worker, dan status penyimpanan payload dari aplikasi mobile.</p>
            </div>
            <div class="clock">
                <div class="time" id="clock-time">--:--:--</div>
                <div class="date" id="clock-date">WITA</div>
            </div>
        </div>
        <div class="hero-grid">
            <div class="metric"><small>Total Window</small><strong id="m-total">0</strong><span>upload terbaca</span></div>
            <div class="metric"><small>Completed</small><strong id="m-completed">0</strong><span>JSON tersimpan</span></div>
            <div class="metric"><small>Pending</small><strong id="m-pending">0</strong><span>received / queued</span></div>
            <div class="metric"><small>Processing</small><strong id="m-processing">0</strong><span>sedang worker</span></div>
            <div class="metric"><small>Failed</small><strong id="m-failed">0</strong><span>butuh perhatian</span></div>
            <div class="metric"><small>Payload</small><strong id="m-bytes">0 MB</strong><span>total window</span></div>
            <div class="metric"><small>Storage Write</small><strong id="m-write-speed">-</strong><span id="m-write-health">menunggu data</span></div>
        </div>
    </section>

    <section class="toolbar">
        <input class="search" id="search" placeholder="Cari upload_id, user_id, employee_id, source, MAC, error...">
        <select class="select" id="status-filter">
            <option value="all">Semua Status</option>
            <option value="received">Received</option>
            <option value="queued">Queued</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
        </select>
        <button class="button" id="refresh-btn">Refresh</button>
        <form method="POST" action="/dashboard-logout" style="margin:0">
            @csrf
            <button class="button secondary" type="submit">Logout</button>
        </form>
        <div class="live"><span class="dot"></span><span id="live-text">Auto 10s</span></div>
    </section>

    <section class="content-grid">
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2>Upload Batch Terbaru</h2>
                    <p>Urutan terbaru dari mobile. Baris failed/pending bisa dipakai untuk investigasi cepat.</p>
                </div>
                <span class="chip st-processing" id="snapshot">loading</span>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>Status</th>
                        <th>Upload</th>
                        <th>Source</th>
                        <th>User</th>
                        <th>Chunk</th>
                        <th>Payload</th>
                        <th>Device</th>
                        <th>Received</th>
                        <th>Durasi</th>
                        <th>Error</th>
                    </tr>
                    </thead>
                    <tbody id="batch-body">
                    <tr><td colspan="10" class="empty">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stack">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Queue & Worker</h2>
                        <p>Pastikan worker tetap fresh dan backlog tidak menumpuk.</p>
                    </div>
                </div>
                <div id="ops-body" class="stack"></div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Storage Write Speed</h2>
                        <p>Kecepatan server menulis payload JSON. Jika cache baru kosong, panel memakai estimasi proses worker.</p>
                    </div>
                    <span class="chip st-processing" id="write-speed-count">0 jobs</span>
                </div>
                <div id="storage-speed-body" class="stack"></div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Worker Heartbeat</h2>
                        <p>Worker stale berarti retry server-side perlu dicek.</p>
                    </div>
                </div>
                <div id="worker-body" class="stack"></div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Chunk Terbaru</h2>
                        <p>Jejak potongan payload yang masuk ke server.</p>
                    </div>
                </div>
                <div id="chunk-body" class="stack"></div>
            </div>
        </div>
    </section>
</main>

<script>
    const STREAM_URL = '/upload-monitoring/stream';
    const state = { rows: [], status: 'all', query: '', timer: null };

    function fmtNumber(value) {
        return new Intl.NumberFormat('id-ID').format(Number(value || 0));
    }

    function fmtBytes(bytes) {
        const mb = Number(bytes || 0) / 1048576;
        if (mb >= 1) return mb.toFixed(2) + ' MB';
        return (Number(bytes || 0) / 1024).toFixed(1) + ' KB';
    }

    function fmtDuration(seconds) {
        if (seconds === null || seconds === undefined) return '-';
        seconds = Number(seconds);
        if (seconds < 60) return seconds + 's';
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return m + 'm ' + s + 's';
    }

    function clsStatus(status) {
        status = String(status || 'unknown').toLowerCase();
        if (status === 'completed') return 'st-completed';
        if (status === 'processing') return 'st-processing';
        if (status === 'queued') return 'st-queued';
        if (status === 'received') return 'st-received';
        if (status === 'failed') return 'st-failed';
        if (status === 'idle') return 'st-idle';
        return 'st-processing';
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (m) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m];
        });
    }

    function updateClock() {
        const now = new Date();
        document.getElementById('clock-time').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
        document.getElementById('clock-date').textContent = 'WITA - ' + now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    async function load() {
        try {
            const res = await fetch(STREAM_URL, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.enabled) {
                document.getElementById('batch-body').innerHTML = '<tr><td colspan="10" class="empty">' + esc(data.message || 'Monitoring belum aktif') + '</td></tr>';
                return;
            }
            state.rows = data.recent_batches || [];
            renderSummary(data.summary || {});
            renderOps(data.operations || {});
            renderStorageWrite(data.storage_write || {});
            renderWorkers(data.workers || []);
            renderChunks(data.recent_chunks || []);
            renderRows();
            document.getElementById('snapshot').textContent = data.snapshot_at || 'live';
        } catch (err) {
            document.getElementById('batch-body').innerHTML = '<tr><td colspan="10" class="empty">Gagal membaca monitoring: ' + esc(err.message) + '</td></tr>';
        }
    }

    function renderSummary(summary) {
        document.getElementById('m-total').textContent = fmtNumber(summary.total);
        document.getElementById('m-completed').textContent = fmtNumber(summary.completed);
        document.getElementById('m-pending').textContent = fmtNumber(summary.pending);
        document.getElementById('m-processing').textContent = fmtNumber(summary.processing);
        document.getElementById('m-failed').textContent = fmtNumber(summary.failed);
        document.getElementById('m-bytes').textContent = fmtBytes(summary.bytes_total);
    }

    function fmtMs(value) {
        if (value === null || value === undefined || value === '') return '-';
        const num = Number(value);
        if (!Number.isFinite(num)) return '-';
        return num.toFixed(num >= 100 ? 0 : 1) + 'ms';
    }

    function renderRows() {
        const body = document.getElementById('batch-body');
        const q = state.query.toLowerCase();
        const rows = state.rows.filter(function (row) {
            const okStatus = state.status === 'all' || String(row.status).toLowerCase() === state.status;
            const text = [
                row.upload_id, row.source, row.user_id, row.employee_id, row.device_id,
                row.mac_address, row.app_version, row.error_code, row.error_message
            ].join(' ').toLowerCase();
            return okStatus && (!q || text.includes(q));
        });

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="10" class="empty">Belum ada data upload untuk filter ini.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (row) {
            const progress = Math.min(100, Math.round((Number(row.chunks_received || 0) / Math.max(1, Number(row.chunks_total || 1))) * 100));
            return '<tr>' +
                '<td><span class="chip ' + clsStatus(row.status) + '">' + esc(row.status) + '</span></td>' +
                '<td><div class="mono">' + esc(row.upload_id_short || row.id) + '</div><div class="muted">#' + esc(row.id) + '</div></td>' +
                '<td><span class="chip st-processing">' + esc(row.source) + '</span></td>' +
                '<td><div class="mono">U:' + esc(row.user_id || '-') + '</div><div class="muted">E:' + esc(row.employee_id || '-') + '</div></td>' +
                '<td><div class="mono">' + esc(row.chunks_received) + '/' + esc(row.chunks_total) + '</div><div class="progress"><b style="width:' + progress + '%"></b></div></td>' +
                '<td class="mono">' + fmtBytes(row.payload_bytes_total) + '</td>' +
                '<td><div class="mono">' + esc(row.mac_address || '-') + '</div><div class="muted">' + esc(row.app_version || '-') + '</div></td>' +
                '<td><div class="mono">' + esc(row.received_at || '-') + '</div><div class="muted">' + fmtDuration(row.age_seconds) + ' lalu</div></td>' +
                '<td class="mono">' + fmtDuration(row.duration_seconds) + '</td>' +
                '<td><div class="error-text">' + esc(row.error_message || row.error_code || '-') + '</div></td>' +
                '</tr>';
        }).join('');
    }

    function renderOps(ops) {
        document.getElementById('ops-body').innerHTML =
            mini('Dispatch Mode', ops.dispatch_mode || '-', 'Queue: ' + (ops.queue_connection || '-') + ':' + (ops.queue_name || '-'), clsStatus(ops.queue_status)) +
            mini('Backlog', ops.queue_backlog === null ? '-' : ops.queue_backlog, ops.queue_message || '-', Number(ops.queue_backlog || 0) > 0 ? 'st-queued' : 'st-completed') +
            mini('Storage', ops.storage_disk || '-', 'Cache: ' + (ops.cache_store || '-'), 'st-processing');
    }

    function renderStorageWrite(info) {
        const body = document.getElementById('storage-speed-body');
        const total = Number(info.count || 0);
        const avg = info.avg_ms;
        const last = info.last_ms;
        const max = Number(info.max_ms || 0);
        const health = String(info.health || 'waiting');
        const sourceLabel = info.source_label || 'write cache';
        const cls = health === 'slow' ? 'st-failed' : health === 'watch' ? 'st-queued' : health === 'good' ? 'st-completed' : 'st-processing';
        const label = health === 'slow' ? 'Lambat' : health === 'watch' ? 'Pantau' : health === 'good' ? 'Bagus' : 'Menunggu';

        document.getElementById('m-write-speed').textContent = avg === null || avg === undefined ? '-' : fmtMs(avg);
        document.getElementById('m-write-health').textContent = total ? label + ' | ' + sourceLabel + ' | last ' + fmtMs(last) : 'belum ada write job';
        document.getElementById('write-speed-count').textContent = total + ' jobs';
        document.getElementById('write-speed-count').className = 'chip ' + cls;

        const samples = Array.isArray(info.samples) ? info.samples.slice(0, 12) : [];
        if (!samples.length) {
            body.innerHTML = '<div class="empty">Belum ada sample storage write. Data akan muncul setelah upload mobile berikutnya.</div>';
            return;
        }
        const normalizedSamples = samples.map(function (value) {
            const raw = Number(value || 0);
            const timeout = raw >= 1000;
            const display = timeout ? 155 : Math.max(1, Math.min(raw, 155));
            const cls = timeout ? 'timeout' : raw > 80 ? 'bad' : raw > 30 ? 'warn' : 'good';
            return { raw: raw, display: display, cls: cls, timeout: timeout };
        });
        const displayMax = Math.max.apply(null, normalizedSamples.map(function (sample) { return sample.display; }).concat([1]));
        const validSamples = normalizedSamples.filter(function (sample) { return !sample.timeout; });
        const validAvg = validSamples.length
            ? validSamples.reduce(function (sum, sample) { return sum + sample.raw; }, 0) / validSamples.length
            : null;
        const timeoutCount = normalizedSamples.filter(function (sample) { return sample.timeout; }).length;
        const summaryText = (validAvg === null ? 'valid avg -' : 'valid avg ' + fmtMs(validAvg))
            + ' | timeout/estimate ' + timeoutCount
            + ' | raw max ' + fmtMs(max);

        body.innerHTML =
            mini('Rata-rata', validAvg === null ? fmtMs(avg) : fmtMs(validAvg), sourceLabel + ' | ' + summaryText, cls) +
            '<div class="speed-bars">' + normalizedSamples.map(function (sample, index) {
                const pct = Math.max(4, Math.min(100, (sample.display / displayMax) * 100));
                const label = sample.timeout ? 'timeout' : fmtMs(sample.raw);
                return '<div class="speed-row"><span>#' + (index + 1) + '</span><b class="' + sample.cls + '" style="width:' + pct.toFixed(1) + '%"></b><em>' + label + '</em></div>';
            }).join('') + '</div>';
    }

    function renderWorkers(workers) {
        const el = document.getElementById('worker-body');
        if (!workers.length) {
            el.innerHTML = '<div class="empty">Belum ada heartbeat worker. Jika queue database aktif, pastikan queue worker berjalan.</div>';
            return;
        }
        el.innerHTML = workers.map(function (w) {
            const cls = w.fresh ? clsStatus(w.status) : 'st-stale';
            const note = (w.current_upload_id ? 'Upload: ' + w.current_upload_id : 'Last seen: ' + (w.last_seen_at || '-'));
            return mini(w.worker_name || 'worker', (w.status || '-'), note + ' | ok ' + w.processed_count + ' / fail ' + w.failed_count, cls);
        }).join('');
    }

    function renderChunks(chunks) {
        const el = document.getElementById('chunk-body');
        if (!chunks.length) {
            el.innerHTML = '<div class="empty">Belum ada chunk baru.</div>';
            return;
        }
        el.innerHTML = chunks.slice(0, 8).map(function (c) {
            return mini(
                (c.source || '-') + ' #' + c.chunk_index + '/' + c.chunk_count,
                c.payload_kb + ' KB',
                (c.received_at || '-') + ' | ' + (c.storage_path || '-'),
                clsStatus(c.status)
            );
        }).join('');
    }

    function mini(title, value, note, cls) {
        return '<div class="mini">' +
            '<div><strong>' + esc(title) + '</strong><small>' + esc(note) + '</small></div>' +
            '<span class="chip ' + cls + '">' + esc(value) + '</span>' +
            '</div>';
    }

    document.getElementById('search').addEventListener('input', function (e) {
        state.query = e.target.value || '';
        renderRows();
    });
    document.getElementById('status-filter').addEventListener('change', function (e) {
        state.status = e.target.value || 'all';
        renderRows();
    });
    document.getElementById('refresh-btn').addEventListener('click', load);

    updateClock();
    setInterval(updateClock, 1000);
    load();
    state.timer = setInterval(load, 10000);
</script>
</body>
</html>

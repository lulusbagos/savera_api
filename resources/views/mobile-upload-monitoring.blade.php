<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savera API Upload Monitoring</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #eef5f7;
            --ink: #0b1f2a;
            --muted: #607282;
            --panel: rgba(255,255,255,.86);
            --line: rgba(15,61,82,.14);
            --blue: #0b75d1;
            --cyan: #05a8c9;
            --green: #11845b;
            --amber: #b77909;
            --red: #c23030;
            --dark: #0f3344;
            --shadow: 0 24px 70px rgba(13,48,68,.13);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Space Grotesk", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 10% 10%, rgba(5,168,201,.18), transparent 26%),
                radial-gradient(circle at 92% 4%, rgba(17,132,91,.15), transparent 28%),
                linear-gradient(135deg, #f8fbfd 0%, var(--bg) 56%, #e3eff2 100%);
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(15,61,82,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15,61,82,.045) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,.8), transparent 82%);
        }
        .wrap {
            width: min(1540px, calc(100% - 32px));
            margin: 0 auto;
            padding: 24px 0 56px;
            position: relative;
        }
        .hero {
            border-radius: 30px;
            padding: 30px;
            background:
                linear-gradient(135deg, rgba(14,69,94,.96), rgba(8,126,154,.88)),
                radial-gradient(circle at 85% 20%, rgba(255,255,255,.2), transparent 30%);
            color: white;
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
        }
        .hero::after {
            content: "";
            position: absolute;
            width: 360px;
            height: 360px;
            border-radius: 999px;
            right: -120px;
            top: -150px;
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
            opacity: .78;
        }
        h1 {
            margin: 8px 0 8px;
            font-size: clamp(34px, 5vw, 64px);
            line-height: .94;
            letter-spacing: -2.4px;
        }
        .hero p {
            margin: 0;
            max-width: 820px;
            color: rgba(255,255,255,.82);
            font-size: 15px;
        }
        .clock {
            min-width: 230px;
            padding: 18px 20px;
            border-radius: 24px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.24);
            text-align: right;
            backdrop-filter: blur(16px);
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
            grid-template-columns: repeat(6, minmax(0, 1fr));
            margin-top: 26px;
            position: relative;
            z-index: 1;
        }
        .metric, .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            box-shadow: 0 12px 34px rgba(14,55,77,.08);
            backdrop-filter: blur(18px);
        }
        .metric {
            border-radius: 22px;
            padding: 18px;
            color: white;
            background: rgba(255,255,255,.12);
            border-color: rgba(255,255,255,.24);
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
            font-size: 30px;
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
        }
        .search, .select, .button {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255,255,255,.82);
            padding: 12px 14px;
            font-family: "Space Grotesk", sans-serif;
            color: var(--ink);
            outline: none;
        }
        .button {
            cursor: pointer;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, var(--dark), var(--cyan));
            min-width: 132px;
        }
        .button.secondary {
            background: linear-gradient(135deg, #405565, #718391);
        }
        .live {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            color: var(--green);
            font-weight: 700;
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
            grid-template-columns: 1.4fr .8fr;
            align-items: start;
        }
        .panel {
            border-radius: 28px;
            padding: 20px;
            overflow: hidden;
        }
        .panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }
        .panel h2 {
            margin: 0;
            font-size: 20px;
            letter-spacing: -.5px;
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
        }
        th {
            text-align: left;
            color: var(--muted);
            font-family: "JetBrains Mono", monospace;
            font-size: 10px;
            letter-spacing: .11em;
            text-transform: uppercase;
            padding: 12px 10px;
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }
        td {
            padding: 13px 10px;
            border-bottom: 1px solid rgba(15,61,82,.08);
            vertical-align: middle;
        }
        tr:hover td { background: rgba(5,168,201,.045); }
        .mono { font-family: "JetBrains Mono", monospace; }
        .muted { color: var(--muted); }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 5px 9px;
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
            padding: 14px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.62);
        }
        .mini strong { font-size: 15px; }
        .mini small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
        }
        .progress {
            width: 100px;
            height: 8px;
            border-radius: 999px;
            background: rgba(15,61,82,.09);
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
        .error-text {
            max-width: 220px;
            color: var(--red);
            font-size: 12px;
        }
        @media (max-width: 1180px) {
            .hero-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .content-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .hero { padding: 22px; border-radius: 24px; }
            .hero-top, .toolbar { grid-template-columns: 1fr; }
            .clock { text-align: left; min-width: 0; }
            .hero-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .metric strong { font-size: 24px; }
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
        <form method="POST" action="{{ route('dashboard-logout') }}" style="margin:0">
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
    const STREAM_URL = @json(route('mobile-upload-monitoring.stream'));
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

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>OmniCron</title>
<style>
    :root {
        --bg: #0a0d13; --panel: #10141c; --panel2: #141926; --border: #1d2432;
        --ink: #dbe2ee; --muted: #6b7691; --dim: #454e63;
        --ok: #2fd475; --warn: #f5a623; --danger: #f2555a; --info: #4f8ef7; --idle: #6b7691;
        --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    * { box-sizing: border-box; margin: 0; }
    body {
        background: var(--bg); color: var(--ink);
        font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
        min-height: 100vh;
        background-image: radial-gradient(ellipse 80% 40% at 50% -10%, rgba(79,142,247,.08), transparent);
    }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 28px 20px 60px; }

    /* Header */
    header { display: flex; align-items: center; gap: 14px; margin-bottom: 26px; }
    .mark { font-size: 19px; font-weight: 650; letter-spacing: -.02em; }
    .mark span { color: var(--info); }
    .beat { width: 9px; height: 9px; border-radius: 50%; background: var(--ok); position: relative; }
    .beat::after {
        content: ''; position: absolute; inset: -4px; border-radius: 50%;
        border: 1px solid var(--ok); opacity: 0; animation: beat 2.4s ease-out infinite;
    }
    @keyframes beat { 0% { transform: scale(.5); opacity: .8 } 70% { transform: scale(1.5); opacity: 0 } 100% { opacity: 0 } }
    .beat.paused { background: var(--dim); } .beat.paused::after { animation: none; }
    header .meta { margin-left: auto; display: flex; align-items: center; gap: 14px; color: var(--muted); font-size: 12px; }
    .counter { display: inline-flex; align-items: center; gap: 6px; }
    .counter b { color: var(--ink); font-weight: 600; }
    .counter .dot { width: 7px; height: 7px; border-radius: 50%; }
    #clock { font-family: var(--mono); font-size: 11px; color: var(--dim); }
    button.ghost {
        background: none; border: 1px solid var(--border); color: var(--muted);
        border-radius: 7px; padding: 4px 11px; font-size: 12px; cursor: pointer;
    }
    button.ghost:hover { color: var(--ink); border-color: var(--dim); }

    /* Task cards */
    .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 12px; margin-bottom: 34px; }
    .card {
        background: var(--panel); border: 1px solid var(--border); border-radius: 12px;
        padding: 15px 16px 13px; display: flex; flex-direction: column; gap: 9px;
    }
    .card .top { display: flex; align-items: flex-start; gap: 10px; }
    .card h3 { font-size: 14px; font-weight: 600; }
    .card .desc { color: var(--muted); font-size: 12px; margin-top: 1px; }
    .badge {
        margin-left: auto; display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;
        font-size: 11px; font-weight: 550; padding: 3px 9px; border-radius: 20px; border: 1px solid;
    }
    .badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .h-ok     { color: var(--ok);     border-color: rgba(47,212,117,.25); background: rgba(47,212,117,.06); }
    .h-warn   { color: var(--warn);   border-color: rgba(245,166,35,.25); background: rgba(245,166,35,.06); }
    .h-danger { color: var(--danger); border-color: rgba(242,85,90,.28);  background: rgba(242,85,90,.07); }
    .h-idle   { color: var(--muted);  border-color: var(--border);        background: none; }
    .sched { display: flex; align-items: center; gap: 9px; min-width: 0; }
    .cron {
        font-family: var(--mono); font-size: 12px; color: var(--info);
        background: rgba(79,142,247,.07); border: 1px solid rgba(79,142,247,.15);
        padding: 2px 8px; border-radius: 6px; display: inline-block; width: fit-content;
        cursor: pointer; flex-shrink: 0;
    }
    .cron:hover { border-color: rgba(79,142,247,.4); }
    .cron.overridden { color: var(--warn); background: rgba(245,166,35,.07); border-color: rgba(245,166,35,.3); }
    .words { color: var(--muted); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    input.cron-edit {
        font-family: var(--mono); font-size: 12px; color: var(--ink);
        background: var(--bg); border: 1px solid var(--info); border-radius: 6px;
        padding: 2px 8px; width: 150px; outline: none;
    }
    .card.paused { opacity: .65; }
    button.ghost.sm { padding: 3px 10px; font-size: 11px; }

    /* Last-runs strip */
    .strip { display: flex; align-items: center; gap: 3px; }
    .bar { width: 6px; height: 15px; border-radius: 2px; background: var(--ok); opacity: .85; }
    .bar.failed { background: var(--danger); }
    .bar.running { background: var(--info); animation: pulse 1.2s ease-in-out infinite; }
    .bar.pad { background: var(--border); opacity: .5; }
    .strip-ago { color: var(--muted); font-size: 11px; margin-left: 7px; }

    .card .facts { display: flex; gap: 14px; color: var(--muted); font-size: 12px; flex-wrap: wrap; }
    .card .facts b { color: var(--ink); font-weight: 550; }
    .card .facts .down, .card .facts .down b { color: var(--danger); }
    .card .foot { display: flex; align-items: center; margin-top: 2px; }
    .card .tz { color: var(--dim); font-size: 11px; }
    button.run {
        margin-left: auto; background: rgba(79,142,247,.1); color: var(--info);
        border: 1px solid rgba(79,142,247,.25); border-radius: 7px;
        padding: 4px 13px; font-size: 12px; font-weight: 550; cursor: pointer;
    }
    button.run:hover { background: rgba(79,142,247,.18); }
    button.run:disabled { opacity: .4; cursor: default; }

    /* Log head: tabs + filter */
    .log-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .tabs { display: flex; gap: 4px; background: var(--panel); border: 1px solid var(--border); border-radius: 9px; padding: 3px; }
    .tab {
        background: none; border: none; color: var(--muted); font-size: 13px; font-weight: 550;
        padding: 4px 14px; border-radius: 6px; cursor: pointer;
    }
    .tab.active { background: var(--panel2); color: var(--ink); }
    select {
        margin-left: auto; background: var(--panel); color: var(--ink); border: 1px solid var(--border);
        border-radius: 7px; padding: 5px 10px; font-size: 12px;
    }

    /* Duration chart */
    .chart {
        display: flex; align-items: flex-end; gap: 3px; height: 64px;
        background: var(--panel); border: 1px solid var(--border); border-radius: 12px;
        padding: 9px 12px; margin-bottom: 12px;
    }
    .cbar { flex: 1; min-height: 6px; background: rgba(79,142,247,.5); border-radius: 2px 2px 0 0; }
    .cbar:hover { background: var(--info); }
    .cbar.failed { background: var(--danger); }

    /* Run log */
    .log { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
    .row { border-bottom: 1px solid var(--border); }
    .row:last-child { border-bottom: none; }
    .row summary {
        list-style: none; display: grid; cursor: pointer;
        grid-template-columns: 10px 1fr 110px 90px 70px; gap: 12px; align-items: center;
        padding: 9px 16px; font-size: 13px;
    }
    .row summary::-webkit-details-marker { display: none; }
    .row summary:hover { background: var(--panel2); }
    .s-dot { width: 8px; height: 8px; border-radius: 50%; }
    .s-ok { background: var(--ok); } .s-failed { background: var(--danger); }
    .s-running { background: var(--info); animation: pulse 1.2s ease-in-out infinite; }
    @keyframes pulse { 50% { opacity: .35 } }
    .row .who { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .row .who .t { font-weight: 550; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chip {
        font-size: 10px; color: var(--muted); border: 1px solid var(--border);
        padding: 1px 6px; border-radius: 10px; flex-shrink: 0;
    }
    .row .when, .row .dur, .row .host { color: var(--muted); font-size: 12px; text-align: right; white-space: nowrap; }
    .row .body { padding: 4px 16px 14px 38px; }
    .row pre {
        font-family: var(--mono); font-size: 12px; line-height: 1.6; color: var(--ink);
        background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
        padding: 10px 13px; overflow-x: auto;
    }
    .row .err { color: var(--danger); }
    .row .meta-line { color: var(--dim); font-size: 11px; margin-top: 7px; }

    /* Queue */
    .qrow {
        display: grid; grid-template-columns: 150px 120px 1fr; gap: 12px; align-items: center;
        padding: 9px 16px; font-size: 13px; border-bottom: 1px solid var(--border);
    }
    .qrow:last-child { border-bottom: none; }
    .q-time { font-family: var(--mono); font-size: 12px; color: var(--muted); }
    .q-in { color: var(--ink); font-weight: 550; font-size: 12px; }
    .q-task { color: var(--muted); }
    .qrow.next .q-in { color: var(--info); }

    .empty { padding: 34px; text-align: center; color: var(--muted); font-size: 13px; }
    .toast {
        position: fixed; bottom: 22px; right: 22px; background: var(--panel2);
        border: 1px solid var(--border); border-left: 3px solid var(--info);
        border-radius: 9px; padding: 10px 16px; font-size: 13px;
        opacity: 0; transform: translateY(8px); transition: all .25s; pointer-events: none;
    }
    .toast.show { opacity: 1; transform: none; }
    .toast.bad { border-left-color: var(--danger); }
</style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="beat" id="beat"></div>
        <div class="mark">Omni<span>Cron</span></div>
        <div class="meta">
            <span class="counter"><span class="dot" style="background:var(--warn)"></span> stale <b id="c-stale">0</b></span>
            <span class="counter"><span class="dot" style="background:var(--danger)"></span> stuck <b id="c-stuck">0</b></span>
            <span id="clock"></span>
            <button class="ghost" id="pause">Pause</button>
        </div>
    </header>

    <div class="cards" id="cards"><div class="empty">Loading tasks&hellip;</div></div>

    <div class="log-head">
        <div class="tabs">
            <button class="tab active" data-tab="log">Run log</button>
            <button class="tab" data-tab="queue">Queued</button>
        </div>
        <select id="task-filter"><option value="">All tasks</option></select>
    </div>
    <div class="chart" id="chart" style="display:none"></div>
    <div class="log" id="log"><div class="empty">Loading runs&hellip;</div></div>
    <div class="log" id="queue" style="display:none"><div class="empty">Loading queue&hellip;</div></div>
</div>
<div class="toast" id="toast"></div>

<script>
(() => {
    const base = @json(rtrim(url(config('omnicron.dashboard.path', 'omnicron')), '/'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const el = (id) => document.getElementById(id);
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    let paused = false;
    let filter = '';
    let filterLoaded = false;
    let tab = 'log';

    // -----------------------------------------------
    // Live time: everything relative ticks every second
    // -----------------------------------------------

    const span = (s) => {
        if (s < 0) s = 0;
        if (s < 60) return s + 's';
        if (s < 600) return Math.floor(s / 60) + 'm ' + (s % 60) + 's';
        if (s < 3600) return Math.floor(s / 60) + 'm';
        if (s < 86400) return Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm';
        return Math.floor(s / 86400) + 'd';
    };
    const utcTime = (ts) => new Date(ts * 1000).toISOString().slice(11, 19);

    function tickTimes() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('[data-ago]').forEach(n => n.textContent = span(now - +n.dataset.ago) + ' ago');
        document.querySelectorAll('[data-in]').forEach(n => {
            const d = +n.dataset.in - now;
            n.textContent = d <= 0 ? 'now' : 'in ' + span(d);
        });
        el('clock').textContent = new Date().toISOString().replace('T', ' ').slice(0, 19) + ' UTC';
    }

    // -----------------------------------------------
    // Overview: the task cards
    // -----------------------------------------------

    const strip = (t) => {
        const runs = t.last_runs || [];
        const pad = Math.max(0, 12 - runs.length);
        const bars = Array(pad).fill('<span class="bar pad"></span>')
            .concat(runs.map(r =>
                `<span class="bar ${esc(r.state)}" title="${utcTime(r.started_ts)} UTC${r.duration_ms != null ? ' &middot; ' + r.duration_ms + 'ms' : ''}"></span>`))
            .join('');
        const last = runs.length
            ? `<span class="strip-ago" data-ago="${runs[runs.length - 1].started_ts}"></span>` : '';
        return `<div class="strip">${bars}${last}</div>`;
    };

    const uptime = (t) => {
        if (!t.uptime) return '';
        return t.uptime.up
            ? `<span>up <b>${esc(t.uptime.label)}</b></span>`
            : `<span class="down">down <b>${esc(t.uptime.label)}</b></span>`;
    };

    const card = (t) => `
        <div class="card${t.paused ? ' paused' : ''}">
            <div class="top">
                <div>
                    <h3>${esc(t.label)}</h3>
                    ${t.description ? `<div class="desc">${esc(t.description)}</div>` : ''}
                </div>
                <span class="badge h-${t.health}"><span class="dot"></span>${esc(t.health_label)}</span>
            </div>
            <div class="sched">
                <span class="cron${t.schedule_overridden ? ' overridden' : ''}" data-task="${esc(t.task)}"
                      title="${t.schedule_overridden ? 'Overridden - code says ' + esc(t.schedule_in_code) + '. Click to edit, clear to restore.' : 'Click to override the schedule'}">${esc(t.schedule)}</span>
                <span class="words">${esc(t.schedule_words)}</span>
            </div>
            ${strip(t)}
            <div class="facts">
                <span>last <b>${t.last_success_at ? `<span data-ago="${t.last_success_at}"></span>` : 'never'}</b>${t.duration_label ? ` &middot; ${esc(t.duration_label)}` : ''}</span>
                ${uptime(t)}
                <span>next <b>${t.paused ? 'paused' : `<span data-in="${t.next_run_at}"></span>`}</b></span>
            </div>
            <div class="foot">
                <span class="tz">${esc(t.timezone)}</span>
                <button class="ghost sm pausejob" data-task="${esc(t.task)}" data-paused="${t.paused ? 1 : 0}" style="margin-left:auto">${t.paused ? 'Resume' : 'Pause'}</button>
                <button class="run" data-task="${esc(t.task)}" style="margin-left:8px">Run now</button>
            </div>
        </div>`;

    async function overview() {
        const data = await fetch(base + '/api/overview', {headers: {Accept: 'application/json'}}).then(r => r.json());
        el('cards').innerHTML = data.tasks.length
            ? data.tasks.map(card).join('')
            : '<div class="empty">No tasks registered. <code>php artisan omnicron:task</code> makes one.</div>';
        el('c-stale').textContent = data.stale;
        el('c-stuck').textContent = data.stuck;

        if (!filterLoaded && data.tasks.length) {
            el('task-filter').innerHTML = '<option value="">All tasks</option>'
                + data.tasks.map(t => `<option value="${esc(t.task)}">${esc(t.label)}</option>`).join('');
            filterLoaded = true;
        }

        document.querySelectorAll('button.run').forEach(btn => btn.addEventListener('click', () => trigger(btn)));
        document.querySelectorAll('button.pausejob').forEach(btn => btn.addEventListener('click', () => togglePause(btn)));
        document.querySelectorAll('.cron[data-task]').forEach(pill => pill.addEventListener('click', () => editSchedule(pill)));
        tickTimes();
    }

    // -----------------------------------------------
    // The run log + response-time chart
    // -----------------------------------------------

    const row = (r) => `
        <details class="row">
            <summary>
                <span class="s-dot s-${esc(r.state)}"></span>
                <span class="who">
                    <span class="t">${esc(r.label)}</span>
                    ${(r.trigger && r.trigger !== 'schedule') ? `<span class="chip">${esc(r.trigger)}</span>` : (r.manual ? '<span class="chip">manual</span>' : '')}
                    ${r.error ? `<span class="chip" style="color:var(--danger);border-color:rgba(242,85,90,.3)">${esc(r.error.slice(0, 40))}</span>` : ''}
                </span>
                <span class="when" data-ago="${r.started_ts}"></span>
                <span class="dur">${esc(r.duration_label ?? '…')}</span>
                <span class="host">${esc(r.host ?? '')}</span>
            </summary>
            <div class="body">
                ${r.error ? `<pre class="err">${esc(r.error)}</pre>` : ''}
                ${r.output ? `<pre>${esc(JSON.stringify(r.output, null, 2))}</pre>` : ''}
                ${!r.error && !r.output ? '<pre>no output recorded</pre>' : ''}
                <div class="meta-line">${esc(r.started_at)} UTC &middot; ${esc(r.task)}</div>
            </div>
        </details>`;

    function chart(runsData) {
        const finished = runsData.filter(r => r.duration_ms != null).slice(0, 40).reverse();
        if (finished.length < 2 || tab !== 'log') { el('chart').style.display = 'none'; return; }
        const max = Math.max(...finished.map(r => r.duration_ms), 1);
        el('chart').style.display = 'flex';
        el('chart').innerHTML = finished.map(r =>
            `<span class="cbar${r.state === 'failed' ? ' failed' : ''}"
                   style="height:${Math.max(8, Math.round(r.duration_ms / max * 100))}%"
                   title="${utcTime(r.started_ts)} UTC &middot; ${esc(r.duration_label ?? r.duration_ms + 'ms')}"></span>`).join('');
    }

    async function runs() {
        const open = new Set([...document.querySelectorAll('.row[open]')].map(d => d.dataset.id));
        const query = filter ? '?task=' + encodeURIComponent(filter) : '';
        const data = await fetch(base + '/api/runs' + query, {headers: {Accept: 'application/json'}}).then(r => r.json());
        el('log').innerHTML = data.runs.length
            ? data.runs.map(row).join('')
            : '<div class="empty">No runs yet. The first tick writes the first row.</div>';
        document.querySelectorAll('.row').forEach((d, i) => {
            d.dataset.id = data.runs[i].id;
            if (open.has(data.runs[i].id)) d.setAttribute('open', '');
        });
        chart(data.runs);
        tickTimes();
    }

    // -----------------------------------------------
    // The queue: what runs next
    // -----------------------------------------------

    const qrow = (q, i) => `
        <div class="qrow${i === 0 ? ' next' : ''}">
            <span class="q-time">${esc(q.execute_at.slice(11))} &middot; ${esc(q.execute_at.slice(5, 10))}</span>
            <span class="q-in" data-in="${q.execute_ts}"></span>
            <span class="q-task">${esc(q.label)}</span>
        </div>`;

    async function queue() {
        const query = filter ? '?task=' + encodeURIComponent(filter) : '';
        const data = await fetch(base + '/api/queue' + query, {headers: {Accept: 'application/json'}}).then(r => r.json());
        el('queue').innerHTML = data.queue.length
            ? data.queue.map(qrow).join('')
            : '<div class="empty">Nothing scheduled - every task is paused.</div>';
        tickTimes();
    }

    // -----------------------------------------------
    // Actions
    // -----------------------------------------------

    async function trigger(btn) {
        btn.disabled = true;
        btn.textContent = 'Running…';
        try {
            const result = await fetch(base + '/api/run/' + btn.dataset.task, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json'},
            }).then(r => r.json());
            if (!result.ran) toast(btn.dataset.task + ' is already running elsewhere', true);
            else if (result.state === 'failed') toast(btn.dataset.task + ' failed: ' + result.error, true);
            else toast(btn.dataset.task + ' ran in ' + result.duration_ms + 'ms');
        } catch { toast('Request failed', true); }
        refresh();
    }

    async function postJob(task, body) {
        const response = await fetch(base + '/api/job/' + task, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json'},
            body: JSON.stringify(body),
        });
        const result = await response.json();
        if (!response.ok) { toast(result.error ?? 'Request failed', true); return false; }
        return true;
    }

    async function togglePause(btn) {
        const paused = btn.dataset.paused === '1';
        if (await postJob(btn.dataset.task, {paused: !paused})) {
            toast(btn.dataset.task + (paused ? ' resumed' : ' paused'));
        }
        refresh();
    }

    // The cron pill IS the override editor: Enter saves, empty restores the
    // code's schedule, Escape walks away.
    function editSchedule(pill) {
        const task = pill.dataset.task;
        const input = document.createElement('input');
        input.className = 'cron-edit';
        input.value = pill.textContent.trim();
        pill.replaceWith(input);
        input.focus();
        input.select();

        let finished = false;
        const done = () => { if (!finished) { finished = true; refresh(); } };

        input.addEventListener('keydown', async (e) => {
            if (e.key === 'Escape') return done();
            if (e.key !== 'Enter') return;
            if (await postJob(task, {schedule_override: input.value.trim()})) {
                toast(input.value.trim() ? task + ' schedule overridden' : task + ' schedule restored to code');
            }
            done();
        });
        input.addEventListener('blur', done);
    }

    let toastTimer;
    function toast(message, bad = false) {
        const t = el('toast');
        t.textContent = message;
        t.className = 'toast show' + (bad ? ' bad' : '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
    }

    // -----------------------------------------------
    // The loop
    // -----------------------------------------------

    function refresh() {
        if (paused) return;
        overview().catch(() => {});
        runs().catch(() => {});
        queue().catch(() => {});
    }

    el('pause').addEventListener('click', () => {
        paused = !paused;
        el('pause').textContent = paused ? 'Resume' : 'Pause';
        el('beat').classList.toggle('paused', paused);
        if (!paused) refresh();
    });
    el('task-filter').addEventListener('change', (e) => { filter = e.target.value; runs(); queue(); });
    document.querySelectorAll('.tab').forEach(button => button.addEventListener('click', () => {
        tab = button.dataset.tab;
        document.querySelectorAll('.tab').forEach(b => b.classList.toggle('active', b === button));
        el('log').style.display = tab === 'log' ? '' : 'none';
        el('queue').style.display = tab === 'queue' ? '' : 'none';
        if (tab === 'queue') { el('chart').style.display = 'none'; queue(); }
        else runs();
    }));

    refresh();
    setInterval(refresh, 5000);
    setInterval(tickTimes, 1000);
})();
</script>
</body>
</html>

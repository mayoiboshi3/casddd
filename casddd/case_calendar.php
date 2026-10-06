<?php
/**
 * CASE CALENDAR
 * ----------------------------------------------------------------------
 * A month-view calendar that shows how many reports came in on each day,
 * broken down by status, plus a one-line summary for the month shown.
 * Clicking a day filters the list underneath to that day; clicking it
 * again (or "Clear") removes the filter.
 *
 * One shared component used by BOTH report screens so they look and behave
 * the same:
 *   - reports.php            -> Disease Reports  (per-day counts from disease_cases)
 *   - planting_harvesting.php -> Farm Reports    (per-day counts from the loaded reports)
 *
 * Call render_case_calendar_assets() once wherever it is needed (it only
 * prints itself the first time). It defines window.CaseCalendar:
 *
 *   var cal = CaseCalendar.create({
 *       mount:    element to draw into,
 *       statuses: [{key, label, color}, ...],   // order = order shown
 *       noun:     'case' | 'report',
 *       viewKey:  optional string; remembers which month was being viewed
 *                 (so it survives the page swapping its panel),
 *       onPick:   function (dateStr)  // a day was clicked
 *       onClear:  function ()         // the selected day was clicked again / Clear
 *   });
 *   cal.setData({ events: [{d:'YYYY-MM-DD', s:'status'}, ...],
 *                 selected: 'YYYY-MM-DD' | null,   // single day being filtered on
 *                 from: 'YYYY-MM-DD' | null, to: 'YYYY-MM-DD' | null });  // optional range
 * ----------------------------------------------------------------------
 */
function render_case_calendar_assets() {
    static $done = false;
    if ($done) { return; }
    $done = true;
    ?>
<style>
    .cc { background:#fff; border:1px solid #e5e7eb; border-radius:20px; padding:18px 18px 14px; box-shadow:0 1px 2px rgba(0,0,0,0.03); }
    .cc-head { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
    .cc-nav-group { display:flex; align-items:center; gap:8px; }
    .cc-nav { width:32px; height:32px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; color:#374151; font-size:16px; font-weight:800; line-height:1; cursor:pointer; font-family:inherit; }
    .cc-nav:hover { background:#f3f4f6; }
    .cc-month { font-size:15px; font-weight:800; color:#111827; min-width:140px; text-align:center; }
    .cc-today-btn { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; padding:7px 12px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; color:#374151; cursor:pointer; font-family:inherit; }
    .cc-today-btn:hover { background:#f3f4f6; }
    .cc-summary { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
    .cc-total { font-size:11px; font-weight:800; color:#111827; margin-right:2px; }
    .cc-chip { display:inline-flex; align-items:center; gap:5px; font-size:10px; font-weight:800; color:#4b5563; background:#f9fafb; border:1px solid #eef0f3; border-radius:999px; padding:3px 9px; }
    .cc-chip i { width:8px; height:8px; border-radius:50%; display:inline-block; }
    .cc-chip-zero { opacity:0.45; }
    .cc-grid { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); gap:5px; }
    .cc-dow { text-align:center; font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#9ca3af; padding:4px 0; }
    .cc-day { position:relative; min-height:64px; padding:6px 7px; border-radius:12px; border:1px solid #f1f2f4; background:#fff; text-align:left; cursor:default; display:flex; flex-direction:column; align-items:flex-start; gap:4px; font-family:inherit; transition:border-color .12s ease, transform .12s ease; }
    .cc-day:disabled { opacity:1; }
    .cc-day.cc-has { cursor:pointer; }
    .cc-day.cc-has:hover { border-color:#9ca3af; transform:translateY(-1px); }
    .cc-heat-1 { background:#f0fdf4; }
    .cc-heat-2 { background:#dcfce7; }
    .cc-heat-3 { background:#bbf7d0; }
    .cc-num { font-size:12px; font-weight:800; color:#374151; }
    .cc-day.cc-today .cc-num { color:#fff; background:#059669; border-radius:999px; min-width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; padding:0 5px; }
    .cc-day.cc-inrange { box-shadow:inset 0 0 0 2px #a7f3d0; }
    .cc-day.cc-selected { background:#111827; border-color:#111827; }
    .cc-day.cc-selected .cc-num { color:#fff; }
    .cc-day.cc-selected.cc-today .cc-num { background:#059669; }
    .cc-pills { display:flex; flex-wrap:wrap; gap:3px; }
    .cc-pill { min-width:18px; height:18px; padding:0 5px; border-radius:999px; color:#fff; font-size:10px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }
    .cc-foot { margin-top:12px; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; font-size:11px; font-weight:700; color:#6b7280; }
    .cc-foot b { color:#111827; }
    .cc-clear { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color:#dc2626; background:none; border:none; cursor:pointer; text-decoration:underline; font-family:inherit; }
    .cc-toggle-on { background:#111827 !important; color:#fff !important; border-color:#111827 !important; }
    @media (max-width:640px) {
        .cc { padding:12px 10px 10px; }
        .cc-day { min-height:48px; padding:4px; }
        .cc-pill { min-width:15px; height:15px; font-size:9px; padding:0 3px; }
        .cc-month { min-width:110px; font-size:13px; }
    }
</style>
<script>
(function () {
    if (window.CaseCalendar) { return; }

    var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var DOW    = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    window.__ccViews = window.__ccViews || {};

    function pad(n)  { return (n < 10 ? '0' : '') + n; }
    function ymd(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }   // m is 0-based
    function todayStr() { var t = new Date(); return ymd(t.getFullYear(), t.getMonth(), t.getDate()); }
    function isIso(s) { return typeof s === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(s); }
    function shortDate(s) { var p = s.split('-'); return MONTHS[+p[1] - 1].slice(0, 3) + ' ' + (+p[2]) + ', ' + p[0]; }
    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (text !== undefined) { e.textContent = text; }   // textContent only — nothing is ever injected as HTML
        return e;
    }

    function create(opts) {
        var root     = opts.mount;
        var statuses = opts.statuses || [];
        var statusMap = {};
        statuses.forEach(function (s) { statusMap[s.key] = s; });
        var noun   = opts.noun || 'case';
        var nounPl = opts.nounPlural || (noun + 's');
        var st = { events: [], byDay: {}, selected: null, from: null, to: null, year: null, month: null, ready: false };

        function plural(n) { return n + ' ' + (n === 1 ? noun : nounPl); }
        function label(key)  { return statusMap[key] ? statusMap[key].label : key; }
        function color(key)  { return statusMap[key] ? statusMap[key].color : '#6b7280'; }

        function index() {
            st.byDay = {};
            st.events.forEach(function (e) {
                if (!e || !isIso(e.d)) { return; }
                var b = st.byDay[e.d] || (st.byDay[e.d] = { total: 0, counts: {} });
                b.total++;
                b.counts[e.s] = (b.counts[e.s] || 0) + 1;
            });
        }
        function remember() { if (opts.viewKey) { window.__ccViews[opts.viewKey] = { y: st.year, m: st.month }; } }
        function viewTo(dateStr) { var p = dateStr.split('-'); st.year = +p[0]; st.month = +p[1] - 1; remember(); }

        // First view: the month last looked at, else this month — or, if this month is empty,
        // the month of the most recent report so the calendar never opens on a blank page.
        function defaultView() {
            var saved = opts.viewKey && window.__ccViews[opts.viewKey];
            if (saved) { st.year = saved.y; st.month = saved.m; return; }
            var tp = todayStr().split('-'), monthKey = tp[0] + '-' + tp[1];
            var hasThisMonth = false, latest = null;
            Object.keys(st.byDay).forEach(function (d) {
                if (d.slice(0, 7) === monthKey) { hasThisMonth = true; }
                if (!latest || d > latest) { latest = d; }
            });
            if (!hasThisMonth && latest) { viewTo(latest); }
            else { st.year = +tp[0]; st.month = +tp[1] - 1; remember(); }
        }

        function shift(delta) {
            var m = st.month + delta, y = st.year;
            while (m < 0)  { m += 12; y--; }
            while (m > 11) { m -= 12; y++; }
            st.year = y; st.month = m; remember(); render();
        }
        function inRange(d) {
            if (st.selected || (!st.from && !st.to)) { return false; }
            return (!st.from || d >= st.from) && (!st.to || d <= st.to);
        }
        function dayTitle(d, b) {
            if (!b) { return shortDate(d) + ': no ' + nounPl; }
            var parts = [];
            statuses.forEach(function (s) { if (b.counts[s.key]) { parts.push(b.counts[s.key] + ' ' + s.label); } });
            Object.keys(b.counts).forEach(function (k) { if (!statusMap[k]) { parts.push(b.counts[k] + ' ' + k); } });
            return shortDate(d) + ': ' + plural(b.total) + ' \u2014 ' + parts.join(', ');
        }

        function render() {
            root.innerHTML = '';
            var box = el('div', 'cc');

            // ── header: ‹ September 2026 ›   [Today] ──
            var head = el('div', 'cc-head');
            var nav = el('div', 'cc-nav-group');
            var prev = el('button', 'cc-nav', '\u2039'); prev.type = 'button'; prev.setAttribute('aria-label', 'Previous month');
            prev.onclick = function () { shift(-1); };
            var next = el('button', 'cc-nav', '\u203A'); next.type = 'button'; next.setAttribute('aria-label', 'Next month');
            next.onclick = function () { shift(1); };
            nav.appendChild(prev);
            nav.appendChild(el('div', 'cc-month', MONTHS[st.month] + ' ' + st.year));
            nav.appendChild(next);
            var todayBtn = el('button', 'cc-today-btn', 'Today'); todayBtn.type = 'button';
            todayBtn.onclick = function () { var t = todayStr(); viewTo(t); render(); };
            head.appendChild(nav);
            head.appendChild(todayBtn);
            box.appendChild(head);

            // ── summary for the month being shown ──
            var monthKey = st.year + '-' + pad(st.month + 1);
            var monthTotal = 0, monthCounts = {};
            Object.keys(st.byDay).forEach(function (d) {
                if (d.slice(0, 7) !== monthKey) { return; }
                var b = st.byDay[d];
                monthTotal += b.total;
                Object.keys(b.counts).forEach(function (k) { monthCounts[k] = (monthCounts[k] || 0) + b.counts[k]; });
            });
            var summary = el('div', 'cc-summary');
            summary.appendChild(el('span', 'cc-total', plural(monthTotal) + ' this month'));
            statuses.forEach(function (s) {
                var n = monthCounts[s.key] || 0;
                var chip = el('span', 'cc-chip' + (n ? '' : ' cc-chip-zero'));
                var dot = el('i'); dot.style.background = s.color;
                chip.appendChild(dot);
                chip.appendChild(document.createTextNode(s.label + ' ' + n));
                summary.appendChild(chip);
            });
            box.appendChild(summary);

            // ── grid ──
            var grid = el('div', 'cc-grid');
            DOW.forEach(function (n) { grid.appendChild(el('div', 'cc-dow', n)); });
            var firstDow = new Date(st.year, st.month, 1).getDay();
            var daysIn   = new Date(st.year, st.month + 1, 0).getDate();
            var today    = todayStr();
            for (var i = 0; i < firstDow; i++) { grid.appendChild(el('div')); }
            for (var day = 1; day <= daysIn; day++) {
                (function (d) {
                    var b = st.byDay[d] || null;
                    var isSel = (d === st.selected);
                    var cls = 'cc-day';
                    if (b) { cls += ' cc-has cc-heat-' + (b.total >= 6 ? 3 : (b.total >= 3 ? 2 : 1)); }
                    if (d === today) { cls += ' cc-today'; }
                    if (isSel) { cls += ' cc-selected'; }
                    if (inRange(d)) { cls += ' cc-inrange'; }
                    var cell = el('button', cls);
                    cell.type = 'button';
                    cell.title = dayTitle(d, b);
                    cell.setAttribute('aria-label', dayTitle(d, b));
                    cell.appendChild(el('span', 'cc-num', String(+d.slice(8))));
                    if (b) {
                        var pills = el('div', 'cc-pills');
                        var keys = statuses.map(function (s) { return s.key; });
                        Object.keys(b.counts).forEach(function (k) { if (keys.indexOf(k) === -1) { keys.push(k); } });
                        keys.forEach(function (k) {
                            if (!b.counts[k]) { return; }
                            var p = el('span', 'cc-pill', String(b.counts[k]));
                            p.style.background = color(k);
                            pills.appendChild(p);
                        });
                        cell.appendChild(pills);
                    }
                    if (!b && !isSel) { cell.disabled = true; }   // nothing to show for an empty day
                    cell.onclick = function () {
                        if (isSel) { if (opts.onClear) { opts.onClear(); } }
                        else if (opts.onPick) { opts.onPick(d); }
                    };
                    grid.appendChild(cell);
                })(ymd(st.year, st.month, day));
            }
            box.appendChild(grid);

            // ── footer: what the list below is currently showing ──
            var foot = el('div', 'cc-foot');
            var msg = el('span');
            var showClear = false;
            if (st.selected) {
                var sb = st.byDay[st.selected];
                msg.appendChild(document.createTextNode('Showing '));
                msg.appendChild(el('b', '', shortDate(st.selected)));
                msg.appendChild(document.createTextNode(' \u2014 ' + plural(sb ? sb.total : 0)));
                showClear = true;
            } else if (st.from || st.to) {
                msg.appendChild(document.createTextNode('Date filter: '));
                msg.appendChild(el('b', '', (st.from ? shortDate(st.from) : 'start') + ' \u2013 ' + (st.to ? shortDate(st.to) : 'today')));
                showClear = true;
            } else {
                msg.textContent = 'Click a day to see its ' + nounPl + ' in the list below.';
            }
            foot.appendChild(msg);
            if (showClear) {
                var clr = el('button', 'cc-clear', 'Clear'); clr.type = 'button';
                clr.onclick = function () { if (opts.onClear) { opts.onClear(); } };
                foot.appendChild(clr);
            }
            box.appendChild(foot);

            root.appendChild(box);
        }

        function setData(d) {
            d = d || {};
            var prevSel = st.selected;
            st.events   = d.events || [];
            st.selected = isIso(d.selected) ? d.selected : null;
            st.from     = isIso(d.from) ? d.from : null;
            st.to       = isIso(d.to)   ? d.to   : null;
            index();
            if (!st.ready) {
                st.ready = true;
                if (st.selected) { viewTo(st.selected); } else { defaultView(); }
            } else if (st.selected && st.selected !== prevSel) {
                viewTo(st.selected);       // a different day was picked -> show its month
            }
            render();
        }

        return { setData: setData, render: render };
    }

    window.CaseCalendar = { create: create };
})();
</script>
    <?php
}
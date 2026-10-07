<?php
/**
 * includes/notifications.php
 * ----------------------------------------------------------------------
 * "New report" indicators that stay visible while the user is on the page:
 *   1. a red count badge on the Reports link in the sidebar (stays until the report is seen),
 *   2. a big pop-up for every new report / scan / update,
 *   3. the tab title shows the count, and flashes "NEW REPORT" while the tab is in the background.
 *
 * Include it ONCE, right after the header include in includes/layout.php:
 *      <?php include __DIR__ . '/notifications.php'; ?>
 *
 * Troubleshooting:
 *   - open  notifications_feed.php?debug=1  in the browser  -> must show JSON with "ok": true and no query_errors
 *   - press F12, Console, type  casdNotifTest()             -> must show a test alert (proves this file is loaded)
 */
$__cntFeed = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') . '/notifications_feed.php';
?>
<style id="cnt-css">
:root{--cnt-top:20px;--cnt-right:20px}
.cnt-badge{position:absolute;right:14px;top:50%;transform:translateY(-50%);min-width:24px;height:24px;padding:0 7px;border-radius:999px;background:#ef4444;color:#fff;font:900 12px/24px Inter,system-ui,sans-serif;text-align:center;letter-spacing:0;animation:cntPulse 1.6s ease-out infinite;pointer-events:none;z-index:2}
.cnt-badge[hidden]{display:none}
.cnt-badge.cnt-pop{animation:cntPulse 1.6s ease-out infinite,cntBump .5s ease both}
.cnt-toasts{position:fixed;top:calc(var(--cnt-top) + 52px);right:var(--cnt-right);z-index:2147481200;display:flex;flex-direction:column;gap:12px;width:min(92vw,380px);pointer-events:none}
.cnt-toast{pointer-events:auto;position:relative;display:flex;gap:14px;align-items:flex-start;background:#fff;color:#0f172a;padding:16px 16px 14px;border-radius:20px;border:1px solid #e2e8f0;border-left:8px solid var(--c);box-shadow:0 24px 50px -12px rgba(15,23,42,.45);font-family:Inter,system-ui,sans-serif;animation:cntSlide .4s cubic-bezier(.34,1.56,.64,1) both}
.cnt-toast.cnt-out{animation:cntSlideOut .22s ease both}
.cnt-ico{flex:0 0 auto;width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:var(--c-bg);color:var(--c)}
.cnt-ico svg{width:22px;height:22px}
.cnt-txt{min-width:0;flex:1}
.cnt-tag{font-size:9px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:var(--c)}
.cnt-title{font-size:14px;font-weight:900;letter-spacing:-.01em;margin-top:2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.cnt-urg{font-size:8px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:#fff;background:#ef4444;border-radius:999px;padding:3px 7px}
.cnt-body{font-size:12px;font-weight:600;color:#64748b;margin-top:3px;line-height:1.4}
.cnt-go{margin-top:10px;background:#064e3b;color:#fbc02d;border:0;border-radius:10px;padding:8px 14px;font:900 10px Inter,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;cursor:pointer}
.cnt-go:hover{background:#022c22}
.cnt-x{position:absolute;top:10px;right:12px;background:none;border:0;color:#94a3b8;cursor:pointer;font-size:14px;line-height:1;padding:2px}
.cnt-x:hover{color:#0f172a}
.cnt-bar{position:absolute;left:0;right:0;bottom:0;height:3px;background:var(--c);transform-origin:left;animation:cntBar var(--cnt-dur,12s) linear both;border-radius:0 0 12px 0}
/* highlighted report rows on reports.php */
:is(.dr-row,.ph-row).cnt-hl{position:relative;background:linear-gradient(90deg,#fffbeb,#fff) !important;box-shadow:inset 0 0 0 2px #fbc02d,0 8px 22px -10px rgba(251,192,45,.8);animation:cntGlow 1.6s ease-in-out 4}
:is(.dr-row,.ph-row).cnt-hl::after{content:attr(data-cnt-label);position:absolute;top:0;right:16px;background:#10b981;color:#fff;font:900 9px Inter,system-ui,sans-serif;letter-spacing:.14em;padding:3px 10px 4px;border-radius:0 0 10px 10px;box-shadow:0 4px 10px -3px rgba(16,185,129,.6);pointer-events:none}
:is(.dr-row,.ph-row).cnt-hl[data-cnt-label="UPDATED"]::after{background:#3b82f6;box-shadow:0 4px 10px -3px rgba(59,130,246,.6)}
@keyframes cntGlow{0%,100%{box-shadow:inset 0 0 0 2px #fbc02d,0 0 0 0 rgba(251,192,45,.6)}50%{box-shadow:inset 0 0 0 2px #fbc02d,0 0 0 9px rgba(251,192,45,0)}}
@keyframes cntPulse{0%{box-shadow:0 0 0 0 rgba(239,68,68,.65)}70%{box-shadow:0 0 0 12px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}
@keyframes cntBump{0%{transform:translateY(-50%) scale(1)}35%{transform:translateY(-50%) scale(1.45)}100%{transform:translateY(-50%) scale(1)}}
@keyframes cntSlide{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:none}}
@keyframes cntSlideOut{to{opacity:0;transform:translateX(40px)}}
@keyframes cntBar{from{transform:scaleX(1)}to{transform:scaleX(0)}}
@media (prefers-reduced-motion:reduce){.cnt-badge,.cnt-toast,.cnt-bar,:is(.dr-row,.ph-row).cnt-hl{animation:none}}
</style>

<script>
(function () {
    if (window.__casdNotif || window.top !== window) return;   // once per page, never inside iframes
    window.__casdNotif = true;

    var FEED = <?= json_encode($__cntFeed, JSON_UNESCAPED_SLASHES) ?>;
    var KEY = 'casd_notif_v2', INTERVAL = 10000, HIDDEN_EVERY = 3, MAX_ITEMS = 100, MAX_TOASTS = 4, TOAST_MS = 12000, HL_MS = 30 * 60 * 1000, SCROLL_KEY = 'casd_notif_scroll';
    var KINDS = {
        report: { tag: 'Disease report', c: '#f97316', bg: 'rgba(249,115,22,.12)', view: 'disease', p: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
        status: { tag: 'Report update', c: '#3b82f6', bg: 'rgba(59,130,246,.12)', view: 'disease', p: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
        scan:   { tag: 'AI scan',        c: '#8b5cf6', bg: 'rgba(139,92,246,.12)', view: 'scans',   p: 'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z' },
        farm:   { tag: 'Farm report',    c: '#10b981', bg: 'rgba(16,185,129,.12)', view: 'farm',    p: 'M12 21V9m0 0c0-3 2-5 6-5 0 4-2 6-6 6M12 14c0-3-2-5-6-5 0 4 2 6 6 6' }
    };

    var state = load(), busy = false, ownWriteAt = 0, firstPoll = true, ticks = 0, flash = false;
    var baseTitle = document.title.replace(/^(\(\d+\+?\)|\u25CF NEW REPORT)\s*/, '');
    var toasts, badges = [];
    var page = location.pathname.replace(/.*\//, '');
    var curView = page === 'reports.php' ? (new URLSearchParams(location.search).get('view') || 'disease') : null;

    function load() {
        try { var s = JSON.parse(localStorage.getItem(KEY) || 'null'); if (s && Array.isArray(s.items)) return s; } catch (e) {}
        return { cursor: null, items: [] };
    }
    function save() { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function safeLink(l) { return /^[\w./?=&%-]+$/.test(l || '') ? l : 'reports.php'; }
    function unreadItems() { return state.items.filter(function (i) { return !i.read; }); }
    function kindView(i) { var k = KINDS[i.kind]; return k ? k.view : null; }

    // Go to an item's page. If that is the view already open, reload it so the new report is on screen.
    function go(item) {
        var link = safeLink(item.link);
        try { sessionStorage.setItem(SCROLL_KEY, '1'); } catch (e) {}
        if (curView && link.indexOf('view=' + curView) > -1) window.location.reload(); else window.location.href = link;
    }

    // ── sidebar badge ──
    function findReportLinks() {
        var all = Array.prototype.filter.call(document.querySelectorAll('a[href]'), function (a) {
            return !a.closest('#contentBody') && a.pathname.replace(/.*\//, '') === 'reports.php';
        });
        var side = all.filter(function (a) { return a.closest('aside, nav, [class*="sidebar"]'); });
        return side.length ? side : all.slice(0, 1);
    }
    function mountBadges() {
        badges = findReportLinks().map(function (a) {
            if (getComputedStyle(a).position === 'static') a.style.position = 'relative';
            var b = document.createElement('span'); b.className = 'cnt-badge'; b.hidden = true; a.appendChild(b); return b;
        });
    }
    function bump() { badges.forEach(function (b) { b.classList.remove('cnt-pop'); void b.offsetWidth; b.classList.add('cnt-pop'); }); }

    function render() {
        var un = unreadItems(), n = un.length, txt = n > 99 ? '99+' : String(n);
        badges.forEach(function (b) { b.hidden = n === 0; b.textContent = txt; b.title = n + ' new'; });
        document.title = (n ? (flash ? '\u25CF NEW REPORT ' : '(' + txt + ') ') : '') + baseTitle;
    }

    // ── pop-up for every new report ──
    function icon(k) { return '<span class="cnt-ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="' + k.p + '"/></svg></span>'; }
    function toast(item) {
        var k = KINDS[item.kind] || KINDS.report;
        var el = document.createElement('div');
        el.className = 'cnt-toast'; el.setAttribute('role', 'alert');
        el.style.cssText = '--c:' + (item.urgent ? '#ef4444' : k.c) + ';--c-bg:' + k.bg + ';--cnt-dur:' + TOAST_MS + 'ms';
        el.innerHTML = icon(k) + '<div class="cnt-txt"><div class="cnt-tag">' + esc(k.tag) + '</div>' +
            '<div class="cnt-title">' + esc(item.title) + (item.urgent ? '<span class="cnt-urg">High severity</span>' : '') + '</div>' +
            (item.body ? '<div class="cnt-body">' + esc(item.body) + '</div>' : '') +
            '<button type="button" class="cnt-go">View</button></div>' +
            '<button type="button" class="cnt-x" aria-label="Dismiss">\u2715</button><div class="cnt-bar"></div>';
        var timer;
        function gone() { clearTimeout(timer); el.classList.add('cnt-out'); setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 220); }
        el.addEventListener('click', function (e) {
            if (e.target.closest('.cnt-x')) { gone(); return; }     // closing the pop-up keeps the red bar + badge
            markRead(item.id); go(item);
        });
        toasts.appendChild(el);
        while (toasts.children.length > MAX_TOASTS) toasts.removeChild(toasts.firstChild);
        timer = setTimeout(gone, TOAST_MS);
    }
    function announce(fresh) {
        if (!fresh.length) return;
        bump();
        fresh.slice().reverse().forEach(toast);                 // one pop-up per report (oldest first)
    }
    function markRead(id) { state.items.forEach(function (i) { if (i.id === id) i.read = true; }); save(); render(); }

    // Opening a view on reports.php shows what belongs to it, so those items count as seen -- but only at the
    // moment the page opens. Reports that arrive WHILE the page is open keep their indicators until acted on.
    function clearCurrentView() {
        if (!curView) return;
        var changed = false;
        state.items.forEach(function (i) { if (!i.read && kindView(i) === curView) { i.read = true; changed = true; } });
        if (changed) save();
    }

    // ── highlight the new / updated report rows on reports.php (stays until clicked, or 30 min) ──
    function keyOf(i) {
        if (i.key) return i.key;
        var m = /^case:(.+?):(new|pending|verified|resolved|rejected)/.exec(i.id || '');
        return m ? m[1] : null;
    }
    function rowKey(row) {            // disease / scan rows carry data-ref; farm rows (built in JS) carry data-report-id
        return row.getAttribute('data-ref') || (row.dataset && row.dataset.reportId ? 'farm:' + row.dataset.reportId : null);
    }
    var ROW_SEL = '.dr-row[data-ref], .ph-row[data-report-id]';
    function highlightRows() {
        if (!curView) return;
        var map = {}, now = Date.now(), first = null;
        for (var n = state.items.length - 1; n >= 0; n--) {          // oldest -> newest, so the newest label wins
            var it = state.items[n];
            if (!it.hl || now - it.at > HL_MS) continue;
            var k = keyOf(it); if (k) map[k] = it.kind === 'status' ? 'UPDATED' : 'NEW';
        }
        Array.prototype.forEach.call(document.querySelectorAll(ROW_SEL), function (row) {
            var label = map[rowKey(row)];
            if (label) { row.classList.add('cnt-hl'); row.setAttribute('data-cnt-label', label); if (!first) first = row; }
            else if (row.classList.contains('cnt-hl')) { row.classList.remove('cnt-hl'); row.removeAttribute('data-cnt-label'); }
        });
        var want = null; try { want = sessionStorage.getItem(SCROLL_KEY); } catch (e) {}
        if (first && want) {                                          // arrived via "View": bring the row into sight
            try { sessionStorage.removeItem(SCROLL_KEY); } catch (e) {}
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    function watchRows() {
        if (!curView) return;
        var t = null, root = document.getElementById('contentBody') || document.body;
        // the reports list is swapped in place (tabs, filters, live refresh) -> re-apply after every swap
        new MutationObserver(function () { clearTimeout(t); t = setTimeout(highlightRows, 80); }).observe(root, { childList: true, subtree: true });
        setInterval(highlightRows, 60000);                            // lets old highlights expire
        document.addEventListener('click', function (e) {             // opening a report = seen it
            var row = e.target.closest && e.target.closest('.cnt-hl'); if (!row) return;
            var k = rowKey(row), ch = false;
            state = load();
            state.items.forEach(function (i) { if (i.hl && keyOf(i) === k) { i.hl = false; ch = true; } });
            if (ch) save();
            row.classList.remove('cnt-hl'); row.removeAttribute('data-cnt-label');
        }, true);
        highlightRows();
    }

    // ── polling ──
    function poll() {
        if (busy || document.prerendering) return;
        busy = true;
        state = load();                                         // pick up what other tabs already stored
        var url = FEED + (state.cursor ? '?after=' + encodeURIComponent(JSON.stringify(state.cursor)) : '');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })             // expired session -> login page -> not JSON -> ignored
            .then(function (d) {
                if (!d || !d.ok) { console.warn('[notifications] feed answered, but not OK. Open ' + FEED + '?debug=1'); return; }
                var known = {}, fresh = [], now = Date.now();
                var mine = (now - ownWriteAt) < 10000;           // the person's own accept/reject: recorded quietly
                state.items.forEach(function (i) { known[i.id] = 1; });
                (d.events || []).forEach(function (e) {
                    if (known[e.id]) return;
                    known[e.id] = 1;
                    var item = { id: e.id, key: e.key || null, kind: e.kind, title: e.title, body: e.body, link: e.link, urgent: !!e.urgent,
                                 at: now - (e.ago || 0) * 1000, read: !!d.initial || (mine && e.kind === 'status') };
                    item.hl = !item.read;                // quiet / first-load items are not highlighted
                    state.items.push(item);
                    if (!item.read) fresh.push(item);            // every NEW report alerts, even one the person added
                });
                state.items.sort(function (a, b) { return b.at - a.at; });
                state.items = state.items.slice(0, MAX_ITEMS);
                state.cursor = d.cursor;
                if (firstPoll) { firstPoll = false; clearCurrentView(); }
                save(); render(); highlightRows(); announce(fresh);
            })
            .catch(function (err) { console.warn('[notifications] could not reach ' + FEED + ' (' + err + '). Check the file is in the same folder as reports.php.'); })
            .finally(function () { busy = false; });
    }
    function tick() {
        if (document.hidden && (++ticks % HIDDEN_EVERY) !== 0) return;   // background tab: check every ~30 s so it can still flash
        poll();
    }

    // Remember when THIS person last saved something so their own accept/reject isn't announced back to them.
    var _fetch = window.fetch;
    window.fetch = function (input, init) {
        var p = _fetch.apply(this, arguments);
        try { if (init && init.method && String(init.method).toUpperCase() === 'POST') { p.then(function () { ownWriteAt = Date.now(); }, function () {}); } } catch (e) {}
        return p;
    };

    // For checking that everything is wired up: type casdNotifTest() in the browser console (F12).
    window.casdNotifTest = function () {
        var item = { id: 'test:' + Date.now(), key: 'TEST', hl: true, kind: 'report', title: 'Test report', body: 'Late Blight \u00b7 Sample Barangay \u00b7 by Test Farmer',
                     link: 'reports.php?view=disease', urgent: true, at: Date.now(), read: false };
        state = load(); state.items.unshift(item); save(); render(); announce([item]);
    };

    // Highlight test: open reports.php, press F12, type  casdNotifHighlightTest()  -- it says what it finds.
    window.casdNotifHighlightTest = function () {
        var rows = Array.prototype.filter.call(document.querySelectorAll(ROW_SEL), function (r) { return r.offsetParent !== null; });
        console.log('[notifications] page=' + page + ' | reports view=' + curView + ' | visible report rows with a marker=' + rows.length +
                    ' | highlight styles loaded=' + (document.getElementById('cnt-css') ? document.getElementById('cnt-css').textContent.indexOf('cnt-hl') > -1 : false));
        if (!curView) { console.warn('Open the Reports page (reports.php) first.'); return; }
        if (!rows.length) { console.warn('No report rows with a marker found. Replace reports.php with the new version (it adds data-ref to the rows), then reload with Ctrl+F5. On the Farm view, open a tab that has reports.'); return; }
        state = load();
        state.items.unshift({ id: 'hl-test:' + Date.now(), key: rowKey(rows[0]), kind: 'report', title: 'Test', body: '', link: 'reports.php',
                              at: Date.now(), read: true, hl: true });
        save(); highlightRows();
        rows[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        console.log('[notifications] highlighted the first visible row. Click it to clear.');
    };

    function start() {
        toasts = document.createElement('div'); toasts.className = 'cnt-toasts'; document.body.appendChild(toasts);
        mountBadges(); watchRows();
        clearCurrentView(); render(); poll();                   // stored unread items for the view being opened count as seen
        setInterval(tick, INTERVAL);
        setInterval(function () { flash = document.hidden && unreadItems().length > 0 ? !flash : false; render(); }, 1000);   // flashing tab title
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { flash = false; render(); poll(); } });
        window.addEventListener('storage', function (e) { if (e.key === KEY) { state = load(); render(); highlightRows(); } });   // other tab
    }
    function boot() { if (document.prerendering) { document.addEventListener('prerenderingchange', boot, { once: true }); return; } start(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
</script>
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
$__cntBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$__cntFeed = $__cntBase . '/notifications_feed.php';
$__cntChat = $__cntBase . '/chat_feed.php';
// One token per login session. If the browser last saw a different token, this is a NEW LOGIN -> one summary pop-up only.
// (If your logout does not destroy the session, also run  unset($_SESSION['casd_notif_login']);  there.)
$__cntLogin = '';
if (session_status() === PHP_SESSION_ACTIVE) {
    if (empty($_SESSION['casd_notif_login'])) {
        try { $_SESSION['casd_notif_login'] = bin2hex(random_bytes(8)); } catch (Throwable $e) { $_SESSION['casd_notif_login'] = md5(uniqid('', true)); }
    }
    $__cntLogin = (string)$_SESSION['casd_notif_login'];
}
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
/* new chat message from a farmer -- dark bubble, avatar + quoted text, so it never looks like a report alert */
.cnt-chat{pointer-events:auto;position:relative;display:flex;gap:12px;align-items:flex-start;background:#0f172a;color:#fff;padding:14px 16px 16px;border-radius:22px 22px 22px 6px;border:1px solid #1e293b;box-shadow:0 24px 50px -12px rgba(2,6,23,.65);font-family:Inter,system-ui,sans-serif;animation:cntSlide .4s cubic-bezier(.34,1.56,.64,1) both;cursor:pointer;overflow:hidden}
.cnt-chat.cnt-out{animation:cntSlideOut .22s ease both}
.cnt-chat .cc-av{flex:0 0 auto;position:relative;width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#06b6d4,#0e7490);display:flex;align-items:center;justify-content:center;font:900 16px Inter,system-ui,sans-serif;color:#fff}
.cnt-chat .cc-av::after{content:'';position:absolute;right:-2px;bottom:-2px;width:13px;height:13px;border-radius:50%;background:#22d3ee;border:2px solid #0f172a}
.cnt-chat .cc-txt{min-width:0;flex:1}
.cnt-chat .cc-tag{font-size:9px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#22d3ee;display:flex;align-items:center;gap:6px}
.cnt-chat .cc-n{background:#22d3ee;color:#083344;border-radius:999px;padding:1px 7px;font-size:9px;letter-spacing:.04em}
.cnt-chat .cc-name{font-size:14px;font-weight:900;margin-top:2px;padding-right:18px}
.cnt-chat .cc-quote{margin-top:7px;background:rgba(255,255,255,.09);border-radius:14px 14px 14px 4px;padding:8px 11px;font-size:12px;font-weight:600;line-height:1.45;color:#e2e8f0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;white-space:pre-wrap;word-break:break-word}
.cnt-chat .cc-go{margin-top:10px;background:#22d3ee;color:#083344;border:0;border-radius:999px;padding:7px 14px;font:900 10px Inter,system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;cursor:pointer}
.cnt-chat .cc-go:hover{background:#67e8f9}
.cnt-chat .cc-x{position:absolute;top:10px;right:12px;background:none;border:0;color:#64748b;cursor:pointer;font-size:14px;line-height:1;padding:2px}
.cnt-chat .cc-x:hover{color:#fff}
.cnt-chat .cc-bar{position:absolute;left:0;right:0;bottom:0;height:3px;background:#22d3ee;transform-origin:left;animation:cntBar var(--cnt-dur,15s) linear both}
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
@media (prefers-reduced-motion:reduce){.cnt-badge,.cnt-toast,.cnt-chat,.cnt-bar,.cc-bar,:is(.dr-row,.ph-row).cnt-hl{animation:none}}
</style>

<script>
(function () {
    if (window.__casdNotif || window.top !== window) return;   // once per page, never inside iframes
    window.__casdNotif = true;

    var FEED = <?= json_encode($__cntFeed, JSON_UNESCAPED_SLASHES) ?>;
    var CHAT_FEED = <?= json_encode($__cntChat, JSON_UNESCAPED_SLASHES) ?>;
    var LOGIN = <?= json_encode($__cntLogin) ?>;
    var KEY = 'casd_notif_v2', INTERVAL = 10000, HIDDEN_EVERY = 3, MAX_ITEMS = 100, MAX_TOASTS = 4, TOAST_MS = 12000, HL_MS = 7 * 24 * 60 * 60 * 1000, SCROLL_KEY = 'casd_notif_scroll';
    var KINDS = {
        report: { tag: 'Disease report', c: '#f97316', bg: 'rgba(249,115,22,.12)', view: 'disease', p: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
        status: { tag: 'Report update', c: '#3b82f6', bg: 'rgba(59,130,246,.12)', view: 'disease', p: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
        scan:   { tag: 'AI scan',        c: '#8b5cf6', bg: 'rgba(139,92,246,.12)', view: 'scans',   p: 'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z' },
        farm:   { tag: 'Farm report',    c: '#10b981', bg: 'rgba(16,185,129,.12)', view: 'farm',    p: 'M12 21V9m0 0c0-3 2-5 6-5 0 4-2 6-6 6M12 14c0-3-2-5-6-5 0 4 2 6 6 6' },
        digest: { tag: 'Updates',        c: '#f59e0b', bg: 'rgba(245,158,11,.14)', view: 'disease', p: 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9' }
    };

    var state = load(), busy = false, ownWriteAt = 0, firstPoll = true, ticks = 0, flash = false, newLogin = false, chatNewLogin = false;
    var baseTitle = document.title.replace(/^(\(\d+\+?\)|\u25CF NEW REPORT|\u25CF NEW MESSAGE|\uD83D\uDCAC \d+)\s*/, '');
    var toasts, badges = [];
    var page = location.pathname.replace(/.*\//, '');
    var curView = page === 'reports.php' ? (new URLSearchParams(location.search).get('view') || 'disease') : null;

    function load() {
        try { var s = JSON.parse(localStorage.getItem(KEY) || 'null'); if (s && Array.isArray(s.items)) return s; } catch (e) {}
        return { cursor: null, items: [], ids: {} };
    }
    function save() { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function safeLink(l) { return /^[\w./?=&%-]+$/.test(l || '') ? l : 'reports.php'; }
    function unreadItems() { return state.items.filter(function (i) { return !i.read; }); }
    // Red number gone as soon as the person opens Reports (the row highlights are NOT touched -- they stay until clicked).
    function clearAllUnread() {
        state = load(); var ch = false;
        state.items.forEach(function (i) { if (!i.read) { i.read = true; ch = true; } });
        if (ch) { save(); render(); }
    }
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
            var b = document.createElement('span'); b.className = 'cnt-badge'; b.hidden = true; a.appendChild(b); a.addEventListener('click', clearAllUnread); return b;
        });
    }
    function bump() { badges.forEach(function (b) { b.classList.remove('cnt-pop'); void b.offsetWidth; b.classList.add('cnt-pop'); }); }

    function render() {
        var un = unreadItems(), n = un.length, txt = n > 99 ? '99+' : String(n);
        badges.forEach(function (b) { b.hidden = n === 0; b.textContent = txt; b.title = n + ' new'; });
        var m = chatUnreadCount(), prefix = '';
        if (!document.hidden) { m = 0; n = 0; }                 // looking at the tab: plain title (the count only shows while the tab is in the background)
        if (m) prefix = flash ? '\u25CF NEW MESSAGE ' : '\uD83D\uDCAC ' + m + ' ';
        else if (n) prefix = flash ? '\u25CF NEW REPORT ' : '(' + txt + ') ';
        document.title = prefix + baseTitle;
    }

    // ── pop-up for every new report ──
    function icon(k) { return '<span class="cnt-ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="' + k.p + '"/></svg></span>'; }
    function toast(item) {
        var k = KINDS[item.kind] || KINDS.report;
        var el = document.createElement('div');
        el.className = 'cnt-toast'; el.setAttribute('role', 'alert'); el.setAttribute('data-key', item.key || ''); el.setAttribute('data-kind', item.kind || '');
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
    // ONE pop-up that stands for many updates (new login / catching up after being away)
    function digestToast(tag, link, title, body) {
        Array.prototype.forEach.call(document.querySelectorAll('.cnt-toast[data-digest="' + tag + '"]'), function (t) { if (t.parentNode) t.parentNode.removeChild(t); });
        toast({ id: 'digest:' + tag + ':' + Date.now(), key: '', kind: 'digest', title: title, body: body, link: link, urgent: false });
        if (toasts.lastChild) toasts.lastChild.setAttribute('data-digest', tag);
    }
    function digestFresh(fresh) {
        var views = {}; fresh.forEach(function (i) { views[kindView(i) || 'disease'] = 1; });
        var vs = Object.keys(views), link = vs.length === 1 ? 'reports.php?view=' + vs[0] : 'reports.php?view=disease';
        bump();
        digestToast('reports', link, 'Several reports have been updated', fresh.length + ' reports are new or updated');
    }
    function markRead(id) { state.items.forEach(function (i) { if (i.id === id) i.read = true; }); save(); render(); }

    // Opening a view on reports.php shows what belongs to it, so those items count as seen -- but only at the
    // moment the page opens. Reports that arrive WHILE the page is open keep their indicators until acted on.
    function clearCurrentView() {
        if (!curView) return;
        var changed = false;
        state.items.forEach(function (i) { if (!i.read) { i.read = true; changed = true; } });   // any Reports view: the number goes away, highlights stay
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
        if (LOGIN && state.login === LOGIN) newLogin = false;   // another tab already handled this login
        var url = FEED + (state.cursor ? '?after=' + encodeURIComponent(JSON.stringify(state.cursor)) : '');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })             // expired session -> login page -> not JSON -> ignored
            .then(function (d) {
                if (!d || !d.ok) { console.warn('[notifications] feed answered, but not OK. Open ' + FEED + '?debug=1'); return; }
                var known = {}, fresh = [], now = Date.now();
                var mine = (now - ownWriteAt) < 10000;           // the person's own accept/reject: recorded quietly
                var touch = chatLoad().touch || {};              // chats that just got a message: that is NOT a report update
                state.items.forEach(function (i) { known[i.id] = 1; });
                state.ids = state.ids || {};
                Object.keys(state.ids).forEach(function (k) { known[k] = 1; });     // ids announced before, even if trimmed from the list
                (d.events || []).forEach(function (e) {
                    if (known[e.id]) return;
                    known[e.id] = 1; state.ids[e.id] = 1;
                    var item = { id: e.id, key: e.key || null, kind: e.kind, title: e.title, body: e.body, link: e.link, urgent: !!e.urgent,
                                 at: now - (e.ago || 0) * 1000, read: (!!d.initial && !newLogin) || (mine && e.kind === 'status') };   // new login: even the first 7 days stay unread + highlighted
                    if (e.kind === 'status' && touch[e.key] && (now - touch[e.key]) < 120000) item.read = true;   // same moment as a chat message -> stay quiet
                    item.hl = !item.read;                // quiet / first-load items are not highlighted
                    state.items.push(item);
                    var onScreen = !item.read && curView && !document.hidden;
                    if (!item.read) fresh.push(item);            // every NEW report alerts, even one the person added
                    if (onScreen) item.read = true;              // ...but while Reports is open in front of you, the red number never appears (the row highlight stays)
                });
                state.items.sort(function (a, b) { return b.at - a.at; });
                state.items = state.items.slice(0, MAX_ITEMS);
                state.cursor = d.cursor;
                var idKeys = Object.keys(state.ids); if (idKeys.length > 1500) { idKeys.slice(0, idKeys.length - 1000).forEach(function (k) { delete state.ids[k]; }); }
                var wasFirst = firstPoll, wasNewLogin = newLogin;
                if (firstPoll) { firstPoll = false; clearCurrentView(); }
                if (LOGIN) state.login = LOGIN;
                newLogin = false;
                save(); render(); highlightRows();
                // new login / first check on this page: any number of updates -> ONE pop-up. Live trickle: individual pop-ups, but a burst of 4+ is also merged.
                if (fresh.length > ((wasNewLogin || wasFirst) ? 1 : 3)) digestFresh(fresh); else announce(fresh);
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


    // ══ CHAT: new farmer messages ══
    // Every chat is tracked on its own: the browser remembers how many replies it has already seen in EACH chat
    // (seen[chatKey] = count). A chat alerts only when its own count goes up -- so one chat can never re-announce
    // another, a reload or a second tab can never replay old messages, and nothing depends on a shared timestamp.
    // The very first check on a browser only records the current counts (no alerts for old messages).
    var CKEY = 'casd_chat_v1', CHAT_MS = 5000, CHAT_HIDDEN_EVERY = 6, CHAT_TOAST_MS = 15000;
    var cstate = chatLoad(), chatBusy = false, chatTicks = 0, chatPops = {};

    function chatLoad() {
        try { var s = JSON.parse(localStorage.getItem(CKEY) || 'null'); if (s && typeof s.seen === 'object' && s.seen) { s.unread = s.unread || {}; return s; } } catch (e) {}
        return { init: false, seen: {}, unread: {} };
    }
    function chatSave() { try { localStorage.setItem(CKEY, JSON.stringify(cstate)); } catch (e) {} }
    function chatUnreadCount() { return Object.keys(cstate.unread).filter(function (k) { return cstate.unread[k] > 0; }).length; }
    function chatKeyOf(d) { return d.reference_id ? String(d.reference_id) : 'c' + d.case_id; }
    function chatIsOpen(key) {      // the person is looking at this very chat right now
        var m = document.getElementById('messageModal'), d = window._currentViewData;
        return !!(m && !m.classList.contains('hidden') && d && !document.hidden && chatKeyOf(d) === key);
    }
    function chatDrop(key, instant) {
        var p = chatPops[key]; if (!p) return;
        delete chatPops[key]; p.gone(instant);
    }
    function chatClear(key) { chatDrop(key, true); if (cstate.unread[key]) { delete cstate.unread[key]; chatSave(); } render(); }

    function chatToast(a) {
        var c = a.c, key = c.key, total = cstate.unread[key] || a.n;
        chatDrop(key, true);                                      // one pop-up per chat: a newer message replaces the old one
        var last = (c.tail && c.tail.length) ? c.tail[c.tail.length - 1] : '';
        if (last.length > 160) last = last.slice(0, 160) + '\u2026';
        var name = c.name || 'Farmer';
        var el = document.createElement('div');
        el.className = 'cnt-chat'; el.setAttribute('role', 'alert');
        el.style.setProperty('--cnt-dur', CHAT_TOAST_MS + 'ms');
        el.innerHTML = '<span class="cc-av">' + esc(name.charAt(0).toUpperCase()) + '</span><div class="cc-txt">' +
            '<div class="cc-tag">\uD83D\uDCAC New message' + (total > 1 ? '<span class="cc-n">' + total + ' new</span>' : '') + '</div>' +
            '<div class="cc-name">' + esc(name) + '</div>' +
            (last ? '<div class="cc-quote">' + esc(last) + '</div>' : '') +
            '<button type="button" class="cc-go">Open chat</button></div>' +
            '<button type="button" class="cc-x" aria-label="Dismiss">\u2715</button><div class="cc-bar"></div>';
        var timer;
        function gone(instant) {
            clearTimeout(timer);
            if (instant) { if (el.parentNode) el.parentNode.removeChild(el); return; }
            el.classList.add('cnt-out'); setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 220);
        }
        el.addEventListener('click', function (e) {
            if (e.target.closest('.cc-x')) { if (chatPops[key] && chatPops[key].el === el) delete chatPops[key]; gone(); return; }   // closing keeps the unread mark
            var link = safeLink(c.link);
            chatClear(key);
            if (chatIsOpen(key)) return;
            window.location.href = link;
        });
        toasts.appendChild(el);
        while (toasts.children.length > MAX_TOASTS + 2) toasts.removeChild(toasts.firstChild);
        chatPops[key] = { el: el, gone: gone };
        timer = setTimeout(function () { if (chatPops[key] && chatPops[key].el === el) delete chatPops[key]; gone(); }, CHAT_TOAST_MS);
    }

    // A chat message also bumps the case's row in the database. If that produced a report-update item/pop-up for this
    // chat in the last two minutes, take it back: a chat message must only ever show the chat alert.
    function quietStatus(key) {
        var now = Date.now(), changed = false;
        state = load();
        state.items.forEach(function (i) { if (i.kind === 'status' && keyOf(i) === key && now - i.at < 120000 && (!i.read || i.hl)) { i.read = true; i.hl = false; changed = true; } });
        if (changed) save();
        Array.prototype.forEach.call(document.querySelectorAll('.cnt-toast[data-kind="status"]'), function (t) { if (t.getAttribute('data-key') === key && t.parentNode) t.parentNode.removeChild(t); });
        if (changed) { render(); highlightRows(); }
    }
    function chatPoll() {
        if (chatBusy || document.prerendering) return;
        chatBusy = true;
        fetch(CHAT_FEED, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok || !Array.isArray(d.chats)) { console.warn('[notifications] chat feed not OK. Open ' + CHAT_FEED + ' in the browser.'); return; }
                cstate = chatLoad();                               // newest stored state (other tabs may have just announced)
                if (LOGIN && cstate.login === LOGIN) chatNewLogin = false;
                var first = !cstate.init, alerts = [];
                d.chats.forEach(function (c) {
                    var prev = cstate.seen[c.key];
                    if (first) { cstate.seen[c.key] = c.count; return; }          // first run on this browser: just learn the counts
                    if (prev === undefined) prev = 0;                              // a chat that got its first reply
                    if (c.count > prev) {
                        cstate.seen[c.key] = c.count;
                        cstate.touch = cstate.touch || {}; cstate.touch[c.key] = Date.now(); quietStatus(c.key);
                        if (chatIsOpen(c.key)) return;                             // reading it right now
                        cstate.unread[c.key] = (cstate.unread[c.key] || 0) + (c.count - prev);
                        alerts.push({ c: c, n: c.count - prev });
                    } else if (c.count < prev) {                                   // replies removed: follow quietly, never alert
                        cstate.seen[c.key] = c.count;
                        if (cstate.unread[c.key] > c.count) cstate.unread[c.key] = c.count;
                    }
                });
                cstate.init = true;
                var wasCNew = chatNewLogin; if (LOGIN) cstate.login = LOGIN; chatNewLogin = false;
                var tk = cstate.touch || {}; Object.keys(tk).forEach(function (k) { if (Date.now() - tk[k] > 600000) delete tk[k]; });
                chatSave(); render();
                if (alerts.length > (wasCNew ? 1 : 3)) {                        // several chats at once -> one pop-up
                    var tot = 0; alerts.forEach(function (a) { tot += a.n; });
                    bump(); digestToast('chats', 'reports.php?view=disease', 'Several chats have new messages', tot + ' new farmer messages');
                } else if (alerts.length) { bump(); alerts.forEach(chatToast); }
            })
            .catch(function (err) { console.warn('[notifications] could not reach ' + CHAT_FEED + ' (' + err + '). Put chat_feed.php in the same folder as reports.php.'); })
            .finally(function () { chatBusy = false; });
    }
    function chatTick() { if (document.hidden && (++chatTicks % CHAT_HIDDEN_EVERY) !== 0) return; chatPoll(); }

    // review.php calls this when a chat window is opened: that chat counts as read.
    window.casdChatOpened = function (key) { chatClear(key); setTimeout(chatPoll, 500); };

    // Test: F12 -> casdChatTest()  shows a sample message alert.
    window.casdChatTest = function () {
        var c = { key: 'TEST-CHAT', case: 0, name: 'Test Farmer', count: 1, tail: ['Magandang araw po, may bago pong dahon na nanilaw sa taniman ko.'], link: 'reports.php?view=disease' };
        cstate = chatLoad(); cstate.unread['TEST-CHAT'] = 1; chatSave(); render(); chatToast({ c: c, n: 1 });
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
        newLogin = !!LOGIN && state.login !== LOGIN; chatNewLogin = !!LOGIN && cstate.login !== LOGIN;
        mountBadges(); watchRows();
        clearCurrentView(); render(); poll();                   // stored unread items for the view being opened count as seen
        setInterval(tick, INTERVAL);
        chatPoll(); setInterval(chatTick, CHAT_MS);
        setInterval(function () { flash = document.hidden && (unreadItems().length > 0 || chatUnreadCount() > 0) ? !flash : false; render(); }, 1000);   // flashing tab title
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { flash = false; clearCurrentView(); render(); poll(); chatPoll(); } });
        window.addEventListener('storage', function (e) {   // other tab
            if (e.key === KEY) { state = load(); render(); highlightRows(); }
            if (e.key === CKEY) { cstate = chatLoad(); Object.keys(chatPops).forEach(function (k) { if (!cstate.unread[k]) chatDrop(k, true); }); render(); }   // read in another tab -> close here too
        });
    }
    function boot() { if (document.prerendering) { document.addEventListener('prerenderingchange', boot, { once: true }); return; } start(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
</script>
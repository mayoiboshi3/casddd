// Ordinary links retain the browser's navigation, history, and per-page script lifecycle.
(function () {
    'use strict';
    var resetTimer;
    function reset() {
        clearTimeout(resetTimer);
        document.documentElement.classList.remove('site-navigating');
    }
    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        var link = event.target.closest && event.target.closest('#siteSidebar a.nav-link');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        var url = new URL(link.href, location.href);
        if (url.origin !== location.origin) return;
        if (url.href === location.href) {
            event.preventDefault();
            return;
        }
        reset();
        document.documentElement.classList.add('site-navigating');
        // A cancelled navigation or connection failure must never leave a stuck progress bar.
        resetTimer = setTimeout(reset, 10000);
    });
    window.addEventListener('pageshow', reset); // also clears feedback when restored with Back/Forward
})();

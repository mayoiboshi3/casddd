// Keep the three report sections mounted so switching preserves their controls and rows.
(function () {
    'use strict';
    var switcher = document.getElementById('reportViewSwitcher');
    if (!switcher) return;
    function viewOf(url) { return url.searchParams.get('view') || 'disease'; }
    var active = viewOf(new URL(location.href));
    var urls = {};
    switcher.querySelectorAll('a').forEach(function (link) {
        var url = new URL(link.href);
        urls[viewOf(url)] = url.href;
    });
    urls[active] = location.href;

    function show(view) {
        active = view;
        window.casdReportView = view;
        document.querySelectorAll('[data-report-view]').forEach(function (section) {
            section.style.display = section.dataset.reportView === view ? '' : 'none';
        });
        switcher.querySelectorAll('a').forEach(function (link) {
            var selected = viewOf(new URL(link.href)) === view;
            link.classList.toggle('bg-emerald-600', selected);
            link.classList.toggle('border-emerald-600', selected);
            link.classList.toggle('shadow-lg', selected);
            link.classList.toggle('bg-white', !selected);
            link.classList.toggle('border-gray-100', !selected);
            link.classList.toggle('hover:border-emerald-200', !selected);
            link.setAttribute('aria-current', selected ? 'page' : 'false');
            var icon = link.firstElementChild;
            icon.classList.toggle('bg-white/15', selected);
            icon.classList.toggle('bg-emerald-50', !selected);
            var labels = link.querySelectorAll('p');
            labels[0].classList.toggle('text-white', selected);
            labels[0].classList.toggle('text-gray-800', !selected);
            labels[1].classList.toggle('text-white/80', selected);
            labels[1].classList.toggle('text-gray-400', !selected);
        });
        // Re-measure list/calendar layouts after the formerly hidden section is visible.
        if (view === 'disease' && window.drCalendarInit) window.drCalendarInit();
        if (view === 'farm' && window.renderPHRows) window.renderPHRows(true);
        if (view === 'scans' && window.scanSetTab) {
            var scanPanel = document.getElementById('scanPanel');
            var page = document.querySelector('#scanPager .active');
            var pageNumber = page ? Number(page.dataset.page) : 1;
            window.scanSetTab(new URL(location.href).searchParams.get('tab') || scanPanel.dataset.tab, false);
            window.renderScanPage(pageNumber, false);
        }
        window.dispatchEvent(new CustomEvent('casd:report-view', { detail: { view: view } }));
    }
    switcher.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        var next = viewOf(new URL(link.href));
        if (!document.querySelector('[data-report-view="' + next + '"]')) return;
        event.preventDefault();
        if (next === active) return;
        urls[active] = location.href;
        history.pushState({ reportView: next }, '', urls[next]);
        show(next);
    });
    window.addEventListener('popstate', function (event) {
        var next = viewOf(new URL(location.href));
        if (next !== active) {
            // A view switch restores its mounted controls; disease filter history uses its own handler.
            event.stopImmediatePropagation();
            urls[next] = location.href;
            show(next);
        }
    }, true);
    show(active);
})();

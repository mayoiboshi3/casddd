<?php
// Shared by the protected pages. Absolute paths also work when the URL has filters.
$siteNavigationBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
?>
<style id="site-navigation-css">
    @view-transition { navigation: auto; }

    #siteSidebar { view-transition-name: site-sidebar; }
    #siteHeader { view-transition-name: site-header; }
    #contentBody { view-transition-name: site-content; }
    ::view-transition-group(*) { animation-duration:180ms; animation-timing-function:ease-out; }
    ::view-transition-old(root), ::view-transition-new(root),
    ::view-transition-old(site-sidebar), ::view-transition-new(site-sidebar),
    ::view-transition-old(site-header), ::view-transition-new(site-header) { animation:none; }
    ::view-transition-old(site-content) { animation:site-content-out 100ms ease-out both; }
    ::view-transition-new(site-content) { animation:site-content-in 180ms ease-out both; }
    @keyframes site-content-out { to { opacity:0; } }
    @keyframes site-content-in { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:none; } }

    /* Immediate feedback while the destination's server response is loading. */
    html.site-navigating::before {
        content:''; position:fixed; inset:0 auto auto 0; height:3px; width:100%;
        background:linear-gradient(90deg,#059669,#fbc02d,#059669); z-index:2147483500;
        transform-origin:left; animation:site-navigation-progress 8s cubic-bezier(.15,.7,.1,1) forwards;
        pointer-events:none;
    }
    @keyframes site-navigation-progress { from { transform:scaleX(.08); } to { transform:scaleX(.92); } }
    @media (prefers-reduced-motion:reduce) {
        @view-transition { navigation:none; }
        html.site-navigating::before { animation:none; }
    }
</style>
<script type="speculationrules">
{"prefetch":[{"source":"document","where":{"selector_matches":"#siteSidebar a.nav-link"},"eagerness":"moderate"}]}
</script>
<script src="<?= htmlspecialchars($siteNavigationBase . '/includes/site-navigation.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>

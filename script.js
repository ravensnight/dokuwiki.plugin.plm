function loadScript(src) {
    const script = document.createElement('script');
    script.src = src;
    script.type = 'text/javascript';
    document.head.appendChild(script);
}

loadScript(DOKU_BASE + 'lib/plugins/plm/js/manager.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/js/alpine.min.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/js/htmx.min.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/js/partlist.js');

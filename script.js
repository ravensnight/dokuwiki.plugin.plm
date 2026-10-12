function loadScript(src) {
    const script = document.createElement('script');
    script.src = src;
    script.type = 'text/javascript';
    document.head.appendChild(script);
}


// custom scripts
loadScript(DOKU_BASE + 'lib/plugins/plm/js/plm/AppApi.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/js/plm/AppControl.js');

// frameworks
loadScript(DOKU_BASE + 'lib/plugins/plm/js/htmx.min.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/js/alpine.min.js');

// init plm
loadScript(DOKU_BASE + 'lib/plugins/plm/js/plm/init.js');



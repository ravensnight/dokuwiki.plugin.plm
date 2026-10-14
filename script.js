function loadScript(src) {
    const script = document.createElement('script');
    script.src = src;
    script.type = 'text/javascript';
    document.head.appendChild(script);
}


// custom scripts
loadScript(DOKU_BASE + 'lib/plugins/plm/res/js/plm/Parts.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/res/js/plm/Versions.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/res/js/plm/Products.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/res/js/plm/Companies.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/res/js/plm/Masterdata.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/res/js/plm/AppControl.js');

// frameworks
loadScript(DOKU_BASE + 'lib/plugins/plm/res/js/htmx.min.js');
loadScript(DOKU_BASE + 'lib/plugins/plm/res/js/alpine.min.js');

// init plm
loadScript(DOKU_BASE + 'lib/plugins/plm/res/js/plm/init.js');

function loadScript(src) {
    const script = document.createElement('script');
    script.src = DOKU_BASE + 'lib/plugins/plm/res/js/' + src;
    script.type = 'text/javascript';
    document.head.appendChild(script);
}

// init plm
loadScript('plm/init.js');

// custom scripts
loadScript('plm/Api.js');
loadScript('plm/PageFragment.js');
loadScript('plm/ItemContainer.js');
loadScript('plm/Revision.js');
loadScript('plm/Parts.js');
loadScript('plm/Products.js');
loadScript('plm/Companies.js');
loadScript('plm/Masterdata.js');
loadScript('plm/Application.js');
loadScript('plm/BomNode.js');
loadScript('plm/BomControl.js');
loadScript('plm/PlmManager.js');

// frameworks
loadScript('htmx.min.js');
loadScript('alpine.min.js');

document.addEventListener('alpine:init', () => {
    Alpine.data('plmManager', () => new PlmManager());
    Alpine.data('plmCreateBom', (initialConfig) => new BomControl(initialConfig));        
});

document.addEventListener('alpine:init', () => {
    Alpine.data('appContext', () =>
        new AppControl(new AppApi())
    );
});

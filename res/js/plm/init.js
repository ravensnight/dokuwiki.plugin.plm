document.addEventListener('alpine:init', () => {
    Alpine.data('appControl', () =>
        new AppControl()
    );
});

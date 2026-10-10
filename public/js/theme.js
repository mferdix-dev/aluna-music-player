(() => {
    function updateTheme() {
        const hour = new Date().getHours();
        document.documentElement.dataset.theme = hour >= 18 || hour < 6 ? 'dark' : 'light';
    }

    updateTheme();
    window.setInterval(updateTheme, 60_000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) updateTheme();
    });
})();

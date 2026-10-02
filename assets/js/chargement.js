document.addEventListener('DOMContentLoaded', () => {
    const btnToggle = document.getElementById('btnToggle');
    if (btnToggle) {
        btnToggle.addEventListener('click', () => {
            document.body.classList.toggle('menu-collapsed');
        });
    }

    // ✅ Utilisation de la variable globale transmise depuis index.php
    const defaultPage = window.defaultDashboardPage || 'pages/dashboards/dashboard_agent.php';
    const defaultTitle = 'Tableau de bord';
    
    history.replaceState({ url: defaultPage, title: defaultTitle }, defaultTitle, "");
    loadPage(defaultPage, defaultTitle, false);
    
    updateNotifications();
    updateMessageBadge();

    setInterval(updateNotifications, 30000);
    setInterval(updateMessageBadge, 30000);
});

function hideLoader() {
    const $loader = $('#refresh-loader');
    if ($loader.is(':visible')) {
        $loader.fadeOut(300, function() {
            $('body').removeClass('loading');
        });
    }
}

if (document.readyState === 'complete') {
    hideLoader();
} else {
    $(window).on('load', hideLoader);
}
setTimeout(hideLoader, 3000);

setTimeout(function() {
    if ($('#refresh-loader').is(':visible')) {
        $('#refresh-loader').fadeOut(300);
        $('body').removeClass('loading');
        console.warn("Le chargement a été forcé pour débloquer l'affichage.");
    }
}, 5000);
let autoriserSortie = false;

if (window.history.state === null) {
    history.replaceState({ view: 'main' }, "", "");
    history.pushState({ view: 'main' }, "", "");
}

window.onpopstate = function(event) {
    const subPageView = document.getElementById('sub-page-view');
    const dashboardView = document.getElementById('dashboard-view');

    if (subPageView && !subPageView.classList.contains('hidden')) {
        if (typeof hideSubPage === 'function') {
            hideSubPage();
        } else {
            subPageView.classList.add('hidden');
            if (dashboardView) dashboardView.classList.remove('hidden');
        }
    } else {
        if (!autoriserSortie) {
            history.pushState({ view: 'main' }, "", ""); 
            window.ouvrirModalQuitter();
        }
    }
};

window.ouvrirModalQuitter = function() {
    document.getElementById('modal-quitter')?.classList.remove('hidden');
};

window.fermerModalQuitter = function() {
    document.getElementById('modal-quitter')?.classList.add('hidden');
};

window.confirmerQuitter = function() {
    autoriserSortie = true;
    window.location.href = 'auth/logout.php';
};

// GESTION DES ACCORDÉONS / SOUS-MENUS
window.toggleAccordion = function(btn, id) {
    const content = document.getElementById(id);
    if (!content) return;
    
    const isOpening = !content.classList.contains('open');

    // Fermer tous les menus avant d'ouvrir le nouveau
    closeAllMenus();

    if (isOpening) {
        content.classList.add('open');
        content.style.maxHeight = content.scrollHeight + "px"; // Déclenche l'animation CSS
        btn.classList.add('nav-item-active');
        
        const icon = btn.querySelector('.fa-chevron-down');
        if (icon) icon.style.transform = 'rotate(180deg)';
    }
};

window.closeAllMenus = function() {
    document.querySelectorAll('.submenu-content').forEach(el => {
        el.classList.remove('open');
        el.style.maxHeight = null; // Réinitialise la hauteur pour refermer l'accordéon
    });
    document.querySelectorAll('.submenu-btn, .nav-item').forEach(el => {
        el.classList.remove('nav-item-active');
    });
    document.querySelectorAll('.fa-chevron-down').forEach(el => {
        el.style.transform = 'rotate(0deg)';
    });
};

window.handleSubClick = function(url, title) {
    loadPage(url, title);
    closeAllMenus(); // Ferme proprement le sous-menu
};

window.toggleUserMenu = function(e) {
    if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) dropdown.classList.toggle('show');
};

// Clics extérieurs (Dropdowns utilisateurs & notifications)
window.addEventListener('click', (e) => {
    const userDropdown = document.getElementById('userDropdown');
    const userBtn = document.querySelector('button[onclick*="toggleUserMenu"]');
    const photoBtn = document.querySelector('.group.cursor-pointer[onclick*="toggleUserMenu"]');

    if (userDropdown && userDropdown.classList.contains('show')) {
        if (!userDropdown.contains(e.target) && 
            (!userBtn || !userBtn.contains(e.target)) && 
            (!photoBtn || !photoBtn.contains(e.target))) {
            userDropdown.classList.remove('show');
        }
    }

    const notifDropdown = document.getElementById('notif-dropdown');
    const notifBtn = document.querySelector('button[onclick*="toggleNotif"]');

    if (notifDropdown && !notifDropdown.classList.contains('hidden')) {
        if (!notifDropdown.contains(e.target) && (!notifBtn || !notifBtn.contains(e.target))) {
            notifDropdown.classList.add('hidden');
        }
    }
});

// Navigation dynamique SPA
window.loadPage = async function(url, title, push = true) {
    // 1. Ferme immédiatement tous les sous-menus ouverts
    closeAllMenus();

    const loader = $('#refresh-loader');
    if (loader.length) loader.css('display', 'flex').fadeIn('fast');

    const container = document.getElementById('pageContent'); 
    const scrollContainer = document.getElementById('mainContainer'); 
    const titleElement = document.getElementById('page-title');
    
    if (!container) return;
    if (titleElement) titleElement.innerText = title;
    
    const userDropdown = document.getElementById('userDropdown');
    if (userDropdown) userDropdown.classList.remove('show');
    
    if (push) history.pushState({ url: url, title: title }, title, "");

    // Nettoyage DataTables
    if (window.jQuery && $.fn.DataTable) {
        ['#demandesTable', '#tableAgents', '#tableMandat', '#tableBordereau'].forEach(tableId => {
            if ($.fn.DataTable.isDataTable(tableId)) {
                try {
                    $(tableId).DataTable().destroy();
                } catch(e) {
                    console.warn(`Impossible de détruire ${tableId}:`, e);
                }
            }
        });
    }

    container.innerHTML = `
        <div class="flex flex-col items-center justify-center h-64 opacity-20">
            <div class="w-10 h-10 border-4 border-slate-200 border-t-sky-500 rounded-full animate-spin"></div>
            <p class="mt-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Chargement...</p>
        </div>`;

    try {
        const separator = url.includes('?') ? '&' : '?';
        const response = await fetch(`${url}${separator}_t=${Date.now()}`);

        if (response.status === 404) throw new Error('PAGE_ABSENTE');
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        
        const html = await response.text();
        container.innerHTML = html;

        setTimeout(() => {
            const scripts = Array.from(container.querySelectorAll("script"));
            scripts.forEach((oldScript) => {
                try {
                    const newScript = document.createElement("script");
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                        newScript.async = false;
                        document.head.appendChild(newScript);
                    } else if (oldScript.textContent.trim()) {
                        new Function(oldScript.textContent)();
                    }
                } catch (e) {
                    console.warn("Erreur script injecté:", e.message);
                }
            });

            setTimeout(() => {
                if (typeof window.updateNavigation === 'function') window.updateNavigation();
                const nextBtn = document.getElementById('nextBtn');
                if (nextBtn) {
                    nextBtn.style.display = 'inline-flex';
                    nextBtn.style.visibility = 'visible';
                    nextBtn.style.opacity = '1';
                }
            }, 300);

        }, 100);
        
        if (scrollContainer) scrollContainer.scrollTo({ top: 0, behavior: 'smooth' });

    } catch (err) {
        console.error(err);
        container.innerHTML = (err && err.message === 'PAGE_ABSENTE')
            ? `<div class="p-10 text-center">
                 <p class="text-slate-700 font-bold text-lg">Fonctionnalité non disponible</p>
                 <p class="text-slate-500 mt-2 text-sm">Cette page n'est pas encore développée.</p>
               </div>`
            : `<div class="p-10 text-center text-rose-600 font-bold">Erreur de chargement.<br><button onclick="location.reload()" class="underline mt-4">Rafraîchir</button></div>`;
    } finally {
        if (loader.length) loader.fadeOut('fast');
    }
};

window.addEventListener('popstate', function(event) {
    if (event.state && event.state.url) {
        loadPage(event.state.url, event.state.title, false);
    } else if (window.defaultDashboardPage) {
        loadPage(window.defaultDashboardPage, 'Tableau de bord', false);
    }
});
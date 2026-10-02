window.updateNotifications = function() {
    fetch('api/notifications/get_notifications.php')
    .then(response => response.json())
    .then(data => {
        const countTag = document.getElementById('notif-count');
        const listContainer = document.getElementById('notif-list');
        const bellIcon = document.querySelector('#notif-container i.fa-bell');
        if (!countTag || !listContainer) return;
        
        const totalUnread = parseInt(data.count) || 0;
        countTag.style.display = 'flex';
        countTag.innerText = totalUnread;

        const titleSpan = document.querySelector('#notif-dropdown .font-bold span');
        if (titleSpan) {
            titleSpan.innerHTML = `Notifications <span id="read-count-display" class="${totalUnread > 0 ? 'text-rose-500' : 'text-sky-500'} font-bold">(${totalUnread})</span>`;
        }

        if (totalUnread > 0) {
            countTag.classList.remove('bg-sky-500', 'hidden');
            countTag.classList.add('bg-rose-500');
            if (bellIcon) {
                bellIcon.classList.add('text-rose-500', 'animate-pulse');
                bellIcon.classList.remove('text-sky-500');
            }

            const colorStyles = {
                rose: { bg: 'bg-rose-50', border: 'border-rose-200', text: 'text-rose-600', btn: 'text-rose-500' },
                orange: { bg: 'bg-orange-50', border: 'border-orange-200', text: 'text-orange-600', btn: 'text-orange-500' },
                purple: { bg: 'bg-purple-50', border: 'border-purple-200', text: 'text-purple-600', btn: 'text-purple-500' },
                indigo: { bg: 'bg-indigo-50', border: 'border-indigo-200', text: 'text-indigo-600', btn: 'text-indigo-500' },
                emerald: { bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-600', btn: 'text-emerald-500' },
                sky: { bg: 'bg-sky-50', border: 'border-sky-200', text: 'text-sky-600', btn: 'text-sky-500' },
                slate: { bg: 'bg-slate-50', border: 'border-slate-200', text: 'text-slate-600', btn: 'text-slate-500' }
            };

            // Regroupement des alertes présentes dans v_moteur_alertes par agent (pour vérification métier)
            const moteurAlertesParAgent = {};
            (data.all_moteur_items || []).forEach(item => {
                const agentId = item.im;
                if (!moteurAlertesParAgent[agentId]) moteurAlertesParAgent[agentId] = [];
                moteurAlertesParAgent[agentId].push(item.alerte_id || '');
            });

            let html = '';
            (data.items || []).forEach(item => {
                const s = colorStyles[item.couleur_code] || colorStyles.slate;
                const d = item.date_reception_technique ? new Date(item.date_reception_technique) : new Date(); 
                const dateFull = d.toLocaleDateString('fr-FR', {day: '2-digit', month: '2-digit'});

                const alerteId = (item.alerte_id || '').toUpperCase();
                const agentId = item.im || item.id_agent;
                
                // Identifications des types d'alertes
                const estStep = alerteId.includes('STEP');
                const estIntg = alerteId.includes('INTG');
                const estTitu = alerteId.includes('TITU');
                const estAvancement = alerteId.includes('AVANCEMENT') || (!estIntg && !estTitu && !estStep);

                // Récupération de la liste des alertes actives pour cet agent
                const alertesDansMoteur = (moteurAlertesParAgent[agentId] || []).map(code => code.toUpperCase());

                const aIntgDansMoteur = alertesDansMoteur.some(code => code.includes('INTG'));
                const aTituDansMoteur = alertesDansMoteur.some(code => code.includes('TITU'));
                const aAvancementDansMoteur = alertesDansMoteur.some(code => code.includes('AVANCEMENT'));

                let boutonDesactive = false;

                // RÈGLES DE BLOCAGE
                if (estStep) {
                    // Règle 0 : Les STEPs restent TOUJOURS ACTIFS
                    boutonDesactive = false;
                } else if (estIntg && aAvancementDansMoteur) {
                    // Règle 1 : Si INTG et AVANCEMENT existent -> On bloque INTG
                    boutonDesactive = true;
                } else if (estAvancement && aTituDansMoteur) {
                    // Règle 2 : Si TITU et AVANCEMENT existent -> On bloque AVANCEMENT
                    boutonDesactive = true;
                }

                html += `
                    <div class="p-4 border-b ${s.bg} ${s.border} hover:opacity-90 transition-all">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="text-xs font-black ${s.text} uppercase flex items-center gap-2">
                                <i class="${item.icone} text-sm"></i> ${item.titre}
                            </h4>
                            <span class="text-[9px] text-slate-400 font-medium">${dateFull}</span>
                        </div>
                        <p class="text-[13px] text-slate-600 line-clamp-2 mb-2 italic">${item.message}</p>
                        
                        ${boutonDesactive ? `
                            <button type="button" disabled title="Action suspendue (traiter le dossier en priorité)" class="text-[11px] font-bold text-slate-400 opacity-60 cursor-not-allowed flex items-center gap-1 uppercase tracking-wider">
                                <i class="fas fa-lock text-[9px]"></i> Action suspendue
                            </button>
                        ` : `
                            <button type="button" onclick="ouvrirNotifDepuisBouton(this)" data-id="${alerteId}" data-title="Notifications" class="text-[11px] font-bold ${s.btn} hover:underline cursor-pointer flex items-center gap-1 uppercase tracking-wider">
                                Voir le détail <i class="fas fa-arrow-right text-[9px]"></i>
                            </button>
                        `}
                    </div>`;
            });
            listContainer.innerHTML = html;
        } else {
            countTag.classList.remove('bg-rose-500');
            countTag.classList.add('bg-sky-500');
            if (bellIcon) {
                bellIcon.classList.remove('text-rose-500', 'animate-pulse');
                bellIcon.classList.add('text-sky-500');
            }
            listContainer.innerHTML = `
                <div class="p-8 text-center">
                    <i class="fas fa-check-double text-sky-100 text-4xl mb-2"></i>
                    <p class="text-slate-400 text-sm font-medium">Aucune nouvelle alerte</p>
                </div>`;
        }
    }).catch(err => console.error("Erreur notifications:", err));
};

window.toggleNotif = function() {
    const dropdown = document.getElementById('notif-dropdown');
    if (dropdown) dropdown.classList.toggle('hidden');
};

window.ouvrirNotifDepuisBouton = function(btn) {
    const dropdown = document.getElementById('notif-dropdown');
    if (dropdown) dropdown.classList.add('hidden');
    const id = btn.getAttribute('data-id');
    const titre = btn.getAttribute('data-title') || 'Notifications'; 
    redirigerVersDetail(id, titre);
};

window.redirigerVersDetail = function(alerteId, titre) {
    fetch(`api/notifications/mark_read_notification.php?alerte_id=${encodeURIComponent(alerteId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotifications();
            }
            loadPage(`pages/notifications/detail_notification.php?alerte_id=${encodeURIComponent(alerteId)}`, titre);
        })
        .catch(err => {
            console.error("Erreur lors de la mise à jour de la notification:", err);
            loadPage(`pages/notifications/detail_notification.php?alerte_id=${encodeURIComponent(alerteId)}`, titre);
        });
};

window.updateMessageBadge = function() {
    fetch('api/agents/fetch_stats.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('msg-badge');
            const icon = document.getElementById('msg-icon');
            const unreadCount = parseInt(data.unread) || 0;

            if (badge) {
                badge.innerText = unreadCount;
                badge.classList.remove('hidden');
            }

            if (unreadCount > 0) {
                if (badge) badge.classList.replace('bg-sky-500', 'bg-rose-500');
                if (icon) {
                    icon.classList.remove('text-slate-400', 'text-sky-500');
                    icon.classList.add('text-rose-500', 'animate-bounce');
                }
            } else {
                if (badge) badge.classList.replace('bg-rose-500', 'bg-sky-500');
                if (icon) {
                    icon.classList.remove('text-rose-500', 'animate-bounce');
                    icon.classList.add('text-sky-500');
                }
            }
        })
        .catch(err => console.error("Erreur badge:", err));
};
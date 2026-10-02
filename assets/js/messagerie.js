// 1. Déclarations des variables locales au module
let currentType = 'unread';
let allMessages = [];
let messageIdToDelete = null;

// 2. Exportation des fonctions sur l'objet global window

window.switchTab = function(t) {
    currentType = t;
    window.hideRightPanel();
    window.refreshMsgList();
    const tabs = ['btn-unread', 'btn-read', 'btn-sent'];
    tabs.forEach(id => {
        const btn = document.getElementById(id);
        if (id === 'btn-' + t) {
            btn.classList.replace('border-transparent', 'border-sky-600');
            btn.classList.replace('text-slate-400', 'text-sky-600');
        } else {
            btn.classList.replace('border-sky-600', 'border-transparent');
            btn.classList.replace('text-sky-600', 'text-slate-400');
        }
    });
};

window.refreshMsgList = async function() {
    const list = document.getElementById('msg-list');
    if (!list) return;

    try {
        const r = await fetch(`api/messagerie/fetch_message.php?type=${currentType}`);
        
        if (!r.ok) throw new Error(`HTTP Error ${r.status}`);

        const contentType = r.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new TypeError("La réponse reçue n'est pas au format JSON");
        }

        allMessages = await r.json();
        
        if (!allMessages.length) {
            list.innerHTML = '<div class="text-center py-10 text-[10px] font-bold text-slate-300 uppercase">Aucun message</div>';
            return;
        }

        list.innerHTML = allMessages.map((msg, i) => `
            <div onclick="window.displayMessage(${i})" class="group p-5 rounded-[2rem] bg-white border border-gray-50 hover:border-sky-100 hover:shadow-md cursor-pointer transition-all mb-3 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full ${msg.is_read == 0 ? 'bg-sky-50 text-sky-500' : 'bg-slate-50 text-slate-300'} flex items-center justify-center text-xs">
                        <i class="fas ${currentType === 'sent' ? 'fa-paper-plane' : 'fa-envelope'}"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold ${msg.is_read == 0 ? 'text-slate-900' : 'text-slate-500'}">${msg.objet}</h4>
                        <p class="text-[9px] font-bold text-slate-400 uppercase">${msg.nom_exp || 'Utilisateur'} • ${msg.created_at}</p>
                    </div>
                </div>
            </div>
        `).join('');
    } catch (err) {
        console.error("Erreur de chargement :", err);
        list.innerHTML = '<div class="text-center py-10 text-[10px] font-bold text-rose-400 uppercase">Erreur au chargement des messages</div>';
    }
};

window.displayMessage = async function(index) {
    const msg = allMessages[index];
    if (!msg) return;

    if (msg.is_read == 0) {
        try {
            const response = await fetch(`api/messagerie/mark_read.php?id=${msg.id}`);
            const result = await response.json();
            if (result.success) {
                msg.is_read = 1;
                if (typeof window.refreshStats === "function") window.refreshStats();
                if (window.parent && typeof window.parent.updateMessageBadge === "function") {
                    window.parent.updateMessageBadge();
                } else if (typeof window.updateMessageBadge === "function") {
                    window.updateMessageBadge();
                }
            }
        } catch (e) { console.error(e); }
    }

    let loc = "";
    if (msg.nom_zap) loc = `${msg.nom_etablissement} (CISCO ${msg.nom_district})`;
    else if (!msg.nom_etablissement) loc = `CISCO ${msg.nom_district}`;
    else if (!msg.nom_district) loc = `DREN ${msg.nom_region}`;
    else loc = msg.nom_etablissement;

    const rightPanel = document.getElementById('right-panel');
    if (rightPanel) {
        rightPanel.innerHTML = `
            <div class="p-8 border-b border-gray-50 flex justify-between items-start bg-white sticky top-0 z-10">
                <div class="flex items-start gap-4">
                    <button onclick="window.closeAndRefresh()" class="w-10 h-10 mt-1 flex items-center justify-center bg-slate-50 rounded-full text-slate-400 hover:text-sky-600">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div>
                        <h2 class="text-xl font-black text-slate-800 mb-1 leading-tight">${msg.objet}</h2>
                        <p class="text-[11px] font-bold text-sky-600 uppercase">${msg.nom_exp} (${msg.expediteur_im})</p>
                        <p class="text-[10px] text-slate-400 font-medium"><i class="fas fa-map-marker-alt"></i> ${loc}</p>
                    </div>
                </div>
                <button onclick="window.deleteMsg(${msg.id})" class="text-red-300 hover:text-red-500 p-2"><i class="fas fa-trash-alt"></i></button>
            </div>
            <div class="p-8 flex-1 overflow-y-auto bg-slate-50/30">
                <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-gray-100 text-slate-600 text-sm leading-relaxed mb-6">
                    ${(msg.contenu || "").replace(/\n/g, '<br>')}
                </div>
                ${currentType !== 'sent' ? `
                    <div class="flex justify-end">
                        <button onclick="window.showCompose('${msg.expediteur_im}', '${msg.objet}')" class="bg-sky-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg">Répondre</button>
                    </div>
                ` : ''}
            </div>`;
        window.showRightPanel();
    }
};

window.showCompose = function(to = '', subject = '') {
    const template = document.getElementById('template-compose');
    const rightPanel = document.getElementById('right-panel');
    if (!template || !rightPanel) return;

    rightPanel.innerHTML = '';
    rightPanel.appendChild(template.content.cloneNode(true));
    
    const hiddenInput = document.getElementById('dest_im_hidden');
    const selectResp = document.getElementById('select_resp_im');
    const displayAgent = document.getElementById('agent_info_display');

    if (to) {
        hiddenInput.value = to;
        document.getElementById('dest_objet').value = "RE: " + subject;

        if (selectResp && [...selectResp.options].some(opt => opt.value === to)) {
            selectResp.value = to;
        } else if (displayAgent) {
            document.getElementById('zone_liste_responsable').classList.add('hidden');
            document.getElementById('zone_recherche_agent').classList.remove('hidden');
            document.getElementById('search_im').value = to;
            window.findAgent();
        }
    } else {
        window.updateHiddenDest();
    }
    
    document.getElementById('formSendMessage').onsubmit = window.handleSendMessage;
    window.showRightPanel();
};

window.handleSendMessage = async function(e) {
    e.preventDefault();
    if(!document.getElementById('dest_im_hidden').value) {
        alert("Veuillez sélectionner ou trouver un destinataire.");
        return;
    }
    const res = await fetch('api/messagerie/send_message.php', { method: 'POST', body: new FormData(e.target) });
    const data = await res.json();
    if (data.success) {
        window.hideRightPanel();
        window.refreshMsgList();
        window.refreshStats();
        document.getElementById('modalSuccess').classList.remove('hidden');
    }
};

window.showRightPanel = function() {
    const leftColumn = document.getElementById('left-column');
    const rightPanel = document.getElementById('right-panel');
    if (leftColumn && rightPanel) {
        leftColumn.classList.replace('w-full', 'md:w-1/3');
        rightPanel.classList.remove('translate-x-full');
    }
};

window.hideRightPanel = function() {
    const leftColumn = document.getElementById('left-column');
    const rightPanel = document.getElementById('right-panel');
    if (leftColumn && rightPanel) {
        leftColumn.classList.replace('md:w-1/3', 'w-full');
        rightPanel.classList.add('translate-x-full');
    }
};

window.closeAndRefresh = function() { window.hideRightPanel(); window.refreshMsgList(); };
window.closeSuccessModal = function() { document.getElementById('modalSuccess').classList.add('hidden'); };

window.refreshStats = async function() {
    try {
        const r = await fetch('api/agents/fetch_stats.php');
        const s = await r.json();
        document.getElementById('count-unread').innerText = s.unread;
        document.getElementById('count-read').innerText = s.read;
        document.getElementById('count-sent').innerText = s.sent;
    } catch(e) { console.error(e); }
};

window.deleteMsg = function(id) { 
    messageIdToDelete = id;
    document.getElementById('modalConfirmDelete').classList.remove('hidden');
};

window.closeDeleteModal = function() { 
    document.getElementById('modalConfirmDelete').classList.add('hidden');
};

// Initialisation globale au chargement
window.refreshStats();
window.refreshMsgList();

// Événement pour le bouton de confirmation de suppression
const confirmBtn = document.getElementById('confirmDeleteBtn');
if (confirmBtn) {
    confirmBtn.onclick = async () => {
        const r = await fetch(`api/messagerie/delete_message.php?id=${messageIdToDelete}`);
        if ((await r.json()).success) {
            window.closeDeleteModal();
            window.hideRightPanel();
            window.refreshMsgList();
            window.refreshStats();
        }
    };
}
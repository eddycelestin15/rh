<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$userIm = $_SESSION['user_im'];
// Utilisation de type_compte pour distinguer agent/responsable/admin
$userType = trim($_SESSION['user_type'] ?? ''); 
// Utilisation de role_specifique pour les droits étendus
$userRole = trim($_SESSION['user_role'] ?? ''); 

// Récupération de la localité de l'utilisateur connecté
$stmtMe = $pdo->prepare("SELECT code_lieu_affectation FROM utilisateurs WHERE im = ?");
$stmtMe->execute([$userIm]);
$me = $stmtMe->fetch();
$monLieu = trim($me['code_lieu_affectation'] ?? '');

// Définition des flags de droits
$isChefSrvOuDiv = ($userRole === 'chef_service' || $userRole === 'chef_division');
$isAgent = ($userType === 'agent');
$isResponsableSimple = ($userType === 'responsable' && !$isChefSrvOuDiv);

$listeFinalDest = [];
$groupesSpeciaux = [];

if ($isChefSrvOuDiv) {
    // Les chefs voient TOUS les responsables de la base
    $groupesSpeciaux = [
        ['valeur' => 'tous_responsables_region', 'label' => 'Tous les responsables RH dans DREN'],
        ['valeur' => 'tous_chef_division', 'label' => 'Tous les Chef de divisions RH dans CISCO'],
    ];
    $stmt = $pdo->prepare("SELECT im, nom, prenoms, role_specifique FROM utilisateurs 
                           WHERE type_compte = 'responsable' AND im != ? ORDER BY nom ASC");
    $stmt->execute([$userIm]);
    $listeFinalDest = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    // Agents et Responsables simples voient uniquement les responsables de leur LIEU
    $stmt = $pdo->prepare("SELECT im, nom, prenoms, role_specifique FROM utilisateurs 
                           WHERE type_compte = 'responsable' 
                           AND TRIM(code_lieu_affectation) = ? 
                           AND im != ? ORDER BY nom ASC");
    $stmt->execute([$monLieu, $userIm]);
    $listeFinalDest = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div id="modalSuccess" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
    <div class="bg-white p-8 rounded-[2.5rem] shadow-2xl text-center max-w-xs w-full mx-4 border border-gray-100">
        <div class="w-20 h-20 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl shadow-inner">
            <i class="fas fa-check"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 uppercase tracking-tighter mb-2">Message Envoyé</h3>
        <p class="text-sm text-slate-500 mb-6">Votre message a été transmis avec succès.</p>
        <button onclick="closeSuccessModal()" class="w-full py-4 bg-slate-900 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-sky-600 transition-colors">Continuer</button>
    </div>
</div>

<div id="modalConfirmDelete" class="hidden fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
    <div class="bg-white p-8 rounded-[2.5rem] shadow-2xl text-center max-w-xs w-full mx-4 border border-gray-100">
        <div class="w-20 h-20 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl shadow-inner">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 uppercase tracking-tighter mb-2">Supprimer ?</h3>
        <p class="text-sm text-slate-500 mb-6">Voulez-vous vraiment supprimer ce message ?</p>
        <div class="flex gap-3">
            <button onclick="closeDeleteModal()" class="flex-1 py-4 bg-slate-100 text-slate-500 rounded-2xl font-black uppercase text-[10px] tracking-widest">Annuler</button>
            <button id="confirmDeleteBtn" class="flex-1 py-4 bg-red-500 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg">Supprimer</button>
        </div>
    </div>
</div>

<style>
@keyframes pop-in { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.animate-pop-in { animation: pop-in 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
</style>

<div class="flex h-[calc(100vh-120px)] bg-white rounded-[3rem] shadow-sm overflow-hidden border border-gray-100 relative">
    
    <div id="left-column" class="w-full transition-all duration-500 ease-in-out border-r border-transparent flex flex-col bg-white z-10">
        <div class="p-8 border-b border-gray-50 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tighter">Boîte de réception</h2>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Gérez vos communications</p>
            </div>
            <button onclick="showCompose()" class="group flex items-center gap-3 bg-sky-600 text-white px-6 py-3 rounded-2xl shadow-lg hover:bg-sky-700 transition-all">
                <span class="text-[10px] font-black uppercase tracking-widest">Nouveau Message</span>
                <i class="fas fa-paper-plane group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform"></i>
            </button>
        </div>
        
        <div class="flex border-b border-gray-100 bg-white">
            <button onclick="switchTab('unread')" id="btn-unread" class="flex-1 py-4 text-[11px] font-black uppercase tracking-tighter border-b-2 border-sky-600 text-sky-600">Messages Non Lus (<span id="count-unread">0</span>)</button>
            <button onclick="switchTab('read')" id="btn-read" class="flex-1 py-4 text-[11px] font-black uppercase tracking-tighter border-b-2 border-transparent text-slate-400">Messages Lus (<span id="count-read">0</span>)</button>
            <button onclick="switchTab('sent')" id="btn-sent" class="flex-1 py-4 text-[11px] font-black uppercase tracking-tighter border-b-2 border-transparent text-slate-400">Message Envoyés (<span id="count-sent">0</span>)</button>
        </div>

        <div id="msg-list" class="flex-1 overflow-y-auto custom-scrollbar p-8 space-y-4"></div>
    </div>

    <div id="right-panel" class="absolute inset-y-0 right-0 w-full md:w-2/3 bg-white border-l border-gray-100 shadow-2xl transform translate-x-full transition-transform duration-500 ease-in-out z-20 flex flex-col"></div>
</div>

<template id="template-compose">
    <div class="p-8 border-b border-gray-50 flex justify-between items-center bg-white sticky top-0 z-10">
        <h2 class="text-xl font-black text-slate-800 uppercase tracking-tighter" id="form-title">Nouveau Message</h2>
        <button onclick="hideRightPanel()" class="text-slate-400 hover:text-red-500"><i class="fas fa-times"></i></button>
    </div>

    <form id="formSendMessage" class="p-8 space-y-4 overflow-y-auto flex-1">
        
        <?php if ($isResponsableSimple): ?>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Type de destinataire</label>
                <select id="select_type_dest" onchange="toggleDestFields()" class="w-full p-4 bg-slate-50 border-none rounded-2xl text-sm outline-none ring-1 ring-slate-100">
                    <option value="responsable">Un Responsable</option>
                    <option value="agent">Un Agent (Recherche IM)</option>
                </select>
            </div>
        <?php endif; ?>

        <div id="zone_liste_responsable" class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Destinataire</label>
            <select id="select_resp_im" onchange="updateHiddenDest()" class="w-full p-4 bg-slate-50 border-none rounded-2xl text-sm outline-none">
                <option value="">-- Sélectionner --</option>
                <?php if(!empty($groupesSpeciaux)): ?>
                    <optgroup label="GROUPES">
                        <?php foreach($groupesSpeciaux as $g): ?><option value="<?= $g['valeur'] ?>"><?= $g['label'] ?></option><?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
                <optgroup label="INDIVIDUEL">
                    <?php foreach($listeFinalDest as $d): ?>
                        <option value="<?= $d['im'] ?>"><?= strtoupper($d['nom'])." (".str_replace('_',' ',$d['role_specifique']).")" ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>

        <div id="zone_recherche_agent" class="hidden space-y-4">
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2">IM de l'agent</label>
                <div class="flex gap-2">
                    <input type="text" id="search_im" placeholder="Ex: 367058" class="flex-1 p-4 bg-slate-50 border-none rounded-2xl text-sm outline-none">
                    <button type="button" onclick="findAgent()" class="w-14 bg-slate-900 text-white rounded-2xl hover:bg-sky-600 transition-all">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <input type="text" id="agent_info_display" readonly placeholder="Infos de l'agent..." class="w-full p-4 bg-sky-50 border-none rounded-2xl text-[11px] font-bold text-sky-700 outline-none">
        </div>

        <input type="hidden" name="destinataire" id="dest_im_hidden" required>

        <div class="space-y-1 pt-2">
            <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Objet</label>
            <input type="text" name="objet" id="dest_objet" required placeholder="Sujet du message" class="w-full p-4 bg-slate-50 border-none rounded-2xl text-sm outline-none">
        </div>

        <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Message</label>
            <textarea name="message" id="msg_contenu" required rows="5" placeholder="Votre message..." class="w-full p-4 bg-slate-50 border-none rounded-2xl text-sm outline-none resize-none"></textarea>
        </div>

        <button type="submit" class="w-full py-4 bg-sky-600 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg hover:bg-sky-700 transition-all">Envoyer</button>
    </form>
</template>
<script>
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
// Basculement Agent / Responsable (UI)
window.toggleDestFields = function() {
    const typeSelect = document.getElementById('select_type_dest');
    if (!typeSelect) return;
    
    const type = typeSelect.value;
    const zoneAgent = document.getElementById('zone_recherche_agent');
    const zoneResp = document.getElementById('zone_liste_responsable');
    const hiddenInput = document.getElementById('dest_im_hidden');
    
    if (hiddenInput) hiddenInput.value = ""; 

    if (type === 'agent') {
        if (zoneAgent) zoneAgent.classList.remove('hidden');
        if (zoneResp) zoneResp.classList.add('hidden');
    } else {
        if (zoneAgent) zoneAgent.classList.add('hidden');
        if (zoneResp) zoneResp.classList.remove('hidden');
        window.updateHiddenDest();
    }
};

window.updateHiddenDest = function() {
    const sel = document.getElementById('select_resp_im');
    const hiddenInput = document.getElementById('dest_im_hidden');
    if (sel && hiddenInput) hiddenInput.value = sel.value;
};

// Recherche Agent par IM
window.findAgent = async function() {
    const searchInput = document.getElementById('search_im');
    const display = document.getElementById('agent_info_display');
    const hiddenInput = document.getElementById('dest_im_hidden');
    
    if (!searchInput || !searchInput.value) return;
    const im = searchInput.value;
    
    if (display) display.value = "Recherche...";

    try {
        const r = await fetch(`api/messagerie/search_agent.php?im=${im}`);
        const data = await r.json();
        if (data.success) {
            if (display) display.value = data.display;
            if (hiddenInput) hiddenInput.value = data.im;
        } else {
            if (display) display.value = data.message || "Agent non trouvé";
            if (hiddenInput) hiddenInput.value = "";
        }
    } catch(e) { 
        if (display) display.value = "Erreur serveur"; 
        console.error(e);
    }
};
</script>
<script src="assets/js/notifications.js"></script>
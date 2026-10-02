window.histCurrentPage = 1;
window.histRecordsPerPage = 8;
window.histDataRows = [];
window.dossiersAttenteTemporaires = [];

window.fermerModalHistorique = function() {
    const modal = document.getElementById('modal-historique');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.style.display = 'none';
    }
    document.body.style.overflow = ''; 
};

window.voirHistoriqueAgent = function(im, alerte_id) {
    let modal = document.getElementById('modal-historique');
    
    // 1. Si la modale a été supprimée du DOM lors du clic sur "Visualiser les pièces", on la recrée complètement
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modal-historique';
        modal.className = 'fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden';
        document.body.appendChild(modal);
    }

    // 2. Garantir que la structure HTML interne existe toujours
    if (!document.getElementById('modal-historique-body')) {
        modal.innerHTML = `
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden m-4">
                <div id="modal-header-info" class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50"></div>
                <div id="modal-historique-body" class="p-6 overflow-y-auto flex-1"></div>
                <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-between items-center">
                    <div id="modal-footer-pagination"></div>
                    <div id="modal-footer-actions" class="flex items-center gap-3"></div>
                </div>
            </div>
        `;
    }

    const body = document.getElementById('modal-historique-body');
    const header = document.getElementById('modal-header-info');
    const actions = document.getElementById('modal-footer-actions');
    const footerPag = document.getElementById('modal-footer-pagination');

    // Masquer les autres modales éventuelles
    document.getElementById('modalSaisieNumero')?.classList.add('hidden');
    document.getElementById('modalSuccessDOS')?.classList.add('hidden');
    document.getElementById('modalErreurSaisie')?.classList.add('hidden');

    // Réinitialisation des variables globales
    window.histDataRows = [];
    window.histCurrentPage = 1;

    // Réinitialisation de l'affichage avec état de CHARGEMENT
    if (footerPag) footerPag.innerHTML = '';
    if (header) header.innerHTML = '';
    if (actions) actions.innerHTML = '';
    if (body) {
        body.innerHTML = `
            <div class="flex flex-col items-center justify-center py-16 text-slate-400">
                <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
                <span class="text-sm font-medium">Chargement de l'historique...</span>
            </div>`;
    }

    // AFFICHER LA MODALE
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.style.display = 'flex';

    fetch(`api/agents/fetch_data_agent.php?im=${encodeURIComponent(im)}&alerte_id=${encodeURIComponent(alerte_id)}&_t=${Date.now()}`)
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            Swal.fire('Erreur', data.message || data.error || 'Impossible de récupérer les données', 'error');
            return;
        }

        const aUneTitularisation = data.alertes_actives
            ? data.alertes_actives.some(a => (a.alerte_id || '').includes('_TITU'))
            : false;
        const estUnAvancement = (alerte_id || '').includes('_AVANCEMENT') || (alerte_id || '').includes('GROUPE');

        if (aUneTitularisation && estUnAvancement) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            Swal.fire({
                title: '<div class="flex flex-col items-center gap-2"><i class="fas fa-shield-alt text-rose-500 text-5xl mb-2"></i><span class="text-rose-600 uppercase font-black tracking-tighter text-2xl">Action Suspendue</span></div>',
                html: `
                    <div class="mt-4 p-6 bg-rose-50 border border-rose-100 rounded-2xl text-left">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-rose-500 text-white rounded-full flex items-center justify-center shrink-0 shadow-lg shadow-rose-200">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <p class="text-rose-900 font-bold text-base mb-1">Titularisation Obligatoire</p>
                                <p class="text-rose-700/80 text-xs leading-relaxed font-medium">
                                    Conformément aux règles de gestion, un agent stagiaire doit impérativement être titularisé avant toute régularisation d'avancement.
                                </p>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-[12px] text-slate-400 font-bold uppercase tracking-widest italic">Veuillez faire la Titularisation en premier.</p>
                `,
                showConfirmButton: true,
                confirmButtonText: 'J\'ai compris <i class="fas fa-arrow-right ml-2"></i>',
                confirmButtonColor: '#e11d48'
            });
            return;
        }

        window.dossiersAttenteTemporaires = data.dossiers_attente || [];
        header.innerHTML = `
            <div class="flex items-center gap-4">
                <div class="bg-indigo-50 text-indigo-600 w-12 h-12 rounded-2xl flex items-center justify-center shadow-inner"><i class="fas fa-user-tie text-xl"></i></div>
                <div>
                    <h2 class="text-lg font-black text-slate-800 leading-none">${(data.agent && data.agent.nom) || ''} ${(data.agent && data.agent.prenoms) || ''}</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase mt-1 tracking-widest">Matricule : ${im}</p>
                </div>
            </div>`;

        actions.innerHTML = `
            <button type="button" onclick="fermerModalHistorique()" class="bg-slate-100 text-slate-500 px-6 py-3 rounded-xl font-black uppercase text-[10px] hover:bg-slate-200 transition-colors">Fermer</button>
            <button type="button" onclick="ouvrirSaisieNumero('${im}', '${alerte_id}', window.dossiersAttenteTemporaires)" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-black uppercase text-[10px] shadow-lg shadow-indigo-100">Attribuer N° DOS</button>
        `;

        window.histDataRows = data.historique || [];
        renderHistTable();
    })
    .catch(err => {
        console.error("Erreur voirHistoriqueAgent:", err);
        modal.classList.add('hidden');
        modal.style.display = 'none';
        Swal.fire('Erreur', 'Une erreur réseau est survenue lors de la récupération des données.', 'error');
    });
};

window.renderHistTable = function() {
    const body = document.getElementById('modal-historique-body');
    const footerPag = document.getElementById('modal-footer-pagination');
    if (!body) return;

    const start = (window.histCurrentPage - 1) * window.histRecordsPerPage;
    const end = start + window.histRecordsPerPage;
    const rows = window.histDataRows || [];
    const pagedData = rows.slice(start, end);
    const totalPages = Math.ceil(rows.length / window.histRecordsPerPage) || 1;

    let html = `
        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-900 text-white">
                        <th class="p-4 text-[10px] font-bold uppercase tracking-widest text-left">Type Avancement</th>
                        <th class="p-4 text-[10px] font-bold uppercase tracking-widest text-left">Corps</th>
                        <th class="p-4 text-[10px] font-bold uppercase tracking-widest text-left">Grade</th>
                        <th class="p-4 text-[10px] font-bold uppercase tracking-widest text-center">Indice</th>
                        <th class="p-4 text-[10px] font-bold uppercase tracking-widest text-right">Date d'effet</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">`;

    if (pagedData.length === 0) {
        html += `<tr><td colspan="4" class="p-12 text-center text-slate-400 font-medium italic">Aucun avancement trouvé.</td></tr>`;
    }

    pagedData.forEach(h => {
        html += `
            <tr class="hover:bg-slate-50/80 transition-colors">
                <td class="p-4 text-sm font-semibold text-slate-700 uppercase">${h.av_type_avancement || '—'}</td>
                <td class="p-4 text-sm font-semibold text-slate-700 uppercase">${h.av_corps || '—'}</td>
                <td class="p-4 text-sm text-indigo-600 font-medium">${h.av_grade || '—'}</td>
                <td class="p-4 text-sm font-mono font-bold text-slate-900 text-center">${h.av_indice || '—'}</td>
                <td class="p-4 text-sm font-bold text-slate-600 text-right">${h.av_date_effet_fr || '—'}</td>
            </tr>`;
    });

    html += `</tbody></table></div>`;
    body.innerHTML = html;

    if (footerPag) {
        footerPag.innerHTML = `
            <div class="flex gap-1">
                ${Array.from({length: totalPages}, (_, i) => i + 1).map(page => `
                    <button type="button" onclick="changeHistPage(${page})"
                        class="w-7 h-7 rounded flex items-center justify-center text-[10px] font-black transition-all ${page === window.histCurrentPage ? 'bg-slate-900 text-white shadow-md' : 'bg-white text-slate-500 border border-slate-200 hover:border-slate-400'}">
                        ${page}
                    </button>
                `).join('')}
            </div>
            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Page ${window.histCurrentPage}/${totalPages}</span>
        `;
    }
};

window.changeHistPage = function(p) {
    window.histCurrentPage = p;
    renderHistTable();
};
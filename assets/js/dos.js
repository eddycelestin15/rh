/**
 * Attributions des dossiers (DOS) et envois DREN
 */
window.modeTableauDashboard = 'ATTENTE'; 

window.filtrerDemandesDashboard = function(statut) {
    window.modeTableauDashboard = statut;
    const isAttribue = (statut === 'ATTRIBUE');
    const titleElem = document.getElementById('titre_section_demandes') || document.querySelector('.titre-demandes-dos');
    if (titleElem) {
        titleElem.textContent = isAttribue 
            ? "Liste des agents ayant reçu numéros DOS" 
            : "Demandes de numéros DOS en attente";
    }
    if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#demandesTable')) {
        $('#demandesTable').DataTable().ajax.url(`fetch_demandes_dos.php?statut=${statut}`).load();
    } else if (typeof window.loadPage === 'function') {
        window.loadPage(`dashboard_responsable.php?statut=${statut}`, 'Tableau de bord', false);
    }
};

window.ouvrirModalAfficherNumeros = function(im, nomPrenom) {
    const modal = document.getElementById('modalAfficherNumerosDos');
    const tbody = document.getElementById('tbody_afficher_numeros_dos');
    const infoHeader = document.getElementById('modal_afficher_dos_agent_info');

    if (!modal || !tbody) return;

    if (infoHeader) {
        infoHeader.textContent = `Agent : ${nomPrenom} (IM: ${im})`;
    }
    
    tbody.innerHTML = `<tr><td colspan="4" class="p-6 text-center text-slate-400 font-bold"><i class="fas fa-spinner fa-spin mr-2"></i>Chargement...</td></tr>`;
    modal.classList.remove('hidden');

    fetch(`fetch_data_agent.php?action=get_dossiers_attribues&im=${encodeURIComponent(im)}`)
        .then(res => {
            if (!res.ok) throw new Error('Erreur réseau');
            return res.json();
        })
        .then(data => {
            if (!data.success || !Array.isArray(data.dossiers) || data.dossiers.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="p-6 text-center text-slate-400 italic">Aucun numéro DOS enregistré pour cet agent.</td></tr>`;
                return;
            }
            tbody.innerHTML = data.dossiers.map((dos, index) => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-3 font-bold text-slate-500">${index + 1}</td>
                    <td class="p-3 font-semibold text-slate-700">${dos.type_titre || '-'}</td>
                    <td class="p-3 font-medium text-indigo-600">${dos.type_dos || 'standard'}</td>
                    <td class="p-3 font-mono font-black text-emerald-600 text-center bg-emerald-50/50 rounded-lg">${dos.numero_dos || '-'}</td>
                </tr>
            `).join('');
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="4" class="p-6 text-center text-rose-500 font-bold">Erreur de chargement des données.</td></tr>`;
        });
};

window.fermerModalAfficherNumeros = function() {
    const modal = document.getElementById('modalAfficherNumerosDos');
    if (modal) modal.classList.add('hidden');
};

window.ouvrirSaisieNumero = function(im, alerteId, dossiersExistants) {
    const modalHist = document.getElementById('modal-historique');
    if(modalHist) modalHist.classList.add('hidden');

    const container = document.getElementById('container_saisie_numeros');
    const modalSaisie = document.getElementById('modalSaisieNumero');
    
    const btnConfirmer = modalSaisie.querySelector('button[onclick*="validerAttribution"]');
    if(btnConfirmer) {
        btnConfirmer.disabled = false;
        btnConfirmer.innerHTML = '<i class="fas fa-check"></i> Confirmer';
    }

    container.innerHTML = `<div class="py-10 text-center"><i class="fas fa-circle-notch fa-spin text-indigo-600 text-3xl"></i></div>`;
    
    document.getElementById('modal_im_hidden').value = im;
    document.getElementById('modal_alerte_hidden').value = alerteId;
    modalSaisie.classList.remove('hidden');

    const render = (liste) => {
        container.innerHTML = '';
        if(!liste || liste.length === 0) {
            container.innerHTML = `<p class="text-slate-500 text-xs font-bold text-center py-4">Aucun numéro DOS requis pour cet agent.</p>`;
            return;
        }
        liste.forEach((dos) => {
            container.innerHTML += `
                <div class="border-b border-slate-100 pb-3 last:border-0 mb-3">
                    <label class="block mb-1 text-[13px]">
                        <span class="text-slate-600 font-bold uppercase">N° de dossier pour <span class="font-black text-indigo-700">${dos.type_acte}</span> de la grade </span>
                        <span class="text-indigo-600 font-black uppercase">${dos.type_titre} :</span>
                    </label>
                    <div class="relative">
                        <input type="text" 
                            data-type="${dos.type_dos}" 
                            placeholder="EX : DOS0120263567" 
                            class="input-num-dos w-full bg-slate-50 px-4 py-2 rounded-lg border-2 border-slate-100 text-lg font-black uppercase text-slate-700 outline-none focus:border-indigo-500 focus:bg-white transition-all">
                        <i class="fas fa-pencil-alt absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
                    </div>
                </div>`;
        });
    };
    fetch('actions/dossiers/save_dos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'analyser_besoins', im: im, alerte_id: alerteId })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) render(data.types);
        else container.innerHTML = `<p class="text-rose-500 text-xs font-bold text-center py-4">${data.message}</p>`;
    })
    .catch(() => container.innerHTML = "<p class='text-rose-500 text-xs font-bold text-center py-4'>Erreur de connexion au serveur.</p>");
};

window.validerAttribution = function(event) {
    if (event) event.preventDefault();

    const im = document.getElementById('modal_im_hidden').value;
    const alerteId = document.getElementById('modal_alerte_hidden').value;
    const inputs = document.querySelectorAll('.input-num-dos');
    const btn = event.currentTarget || document.querySelector('#modalSaisieNumero button[onclick*="validerAttribution"]');
    
    let numeros = {};
    let valide = true;
    let messageErreur = "";

    inputs.forEach(input => {
        const val = input.value.trim();
        if (!val || val.length !== 14) {
            valide = false;
            input.classList.add('border-rose-500');
            
            if (!val) {
                messageErreur = "Veuillez remplir tous les champs de numéro DOS.";
            } else if (val.length !== 14) {
                messageErreur = `Le numéro DOS doit comporter exactement 14 caractères (actuellement : ${val.length}).`;
            }
        } else {
            input.classList.remove('border-rose-500');
            numeros[input.getAttribute('data-type')] = val;
        }
    });

    if (!valide) {
        const modalErr = document.getElementById('modalErreurSaisie');
        if (modalErr) {
            const txtElement = modalErr.querySelector('p');
            if (txtElement && messageErreur) {
                txtElement.textContent = messageErreur;
            }
            modalErr.classList.remove('hidden');
        } else {
            alert(messageErreur || "Chaque numéro DOS doit comporter exactement 14 caractères.");
        }
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';

    fetch('actions/dossiers/save_dos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'valider_responsable', im: im, alerte_id: alerteId, numeros: numeros })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('modalSaisieNumero')?.classList.add('hidden');
            if (typeof fermerModalHistorique === 'function') fermerModalHistorique();
            document.getElementById('modalSuccessDOS')?.classList.remove('hidden');
        } else {
            Swal.fire('Erreur', data.message || "Erreur lors de la validation", 'error');
        }
    })
    .catch((err) => {
        console.error(err);
        Swal.fire('Erreur', "Erreur de communication avec le serveur", 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmer';
    });
};

window.fermerSuccesEtRafraichir = function() {
    document.getElementById('modalSuccessDOS').classList.add('hidden');
    if (typeof loadPage === 'function') {
        loadPage('pages/dashboards/dashboard_responsable.php', 'Tableau de bord');
    } else {
        window.location.href = 'pages/dashboards/dashboard_responsable.php';
    }
};

window.envoyerVersDrenSeul = function(btn, im, alerteId) {
    Swal.fire({
        title: 'Envoyer la demande ?',
        text: "Cette demande sera transmise au responsable régional (DREN).",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Oui, envoyer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Envoi en cours...', didOpen: () => Swal.showLoading() });

            fetch('actions/dossiers/save_dos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'envoyer_individuel_vers_dren',
                    im: im,
                    alerte_id: alerteId
                })
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    btn.innerHTML = `<i class="fas fa-check-circle"></i> Demande envoyée à DREN`;
                    btn.classList.remove('bg-amber-500', 'hover:bg-amber-600');
                    btn.classList.add('bg-emerald-600', 'cursor-default');
                    btn.disabled = true;

                    Swal.fire({
                        icon: 'success',
                        title: 'Transmis avec succès !',
                        timer: 1600,
                        showConfirmButton: false
                    });

                    setTimeout(() => location.reload(), 1600);
                } else {
                    Swal.fire('Erreur', data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(() => Swal.fire('Erreur', 'Impossible de contacter le serveur', 'error'));
        }
    });
};
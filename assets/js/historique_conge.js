window.openModal = function (id) { 
    const el = document.getElementById(id);
    if (el) el.style.display = 'flex'; 
};

window.closeModal = function (id) { 
    const el = document.getElementById(id);
    if (el) el.style.display = 'none'; 
};

window.openEditModal = function (conge) {
    if (typeof conge === 'string') {
        try { 
            conge = JSON.parse(conge); 
        } catch (e) { 
            console.error('Erreur parsing JSON conge:', e); 
        }
    }

    document.getElementById('edit_id').value = conge.id || '';
    document.getElementById('edit_annee').value = conge.annee || '';
    document.getElementById('edit_num_decision').value = conge.num_decision || '';
    document.getElementById('edit_date_decision').value = conge.date_decision || '';
    document.getElementById('edit_jours_total').value = conge.jours_total || 30;
    
    window.openModal('editCongeModal');
};

window.confirmDelete = function (id) {
    const inputDelete = document.getElementById('idToDelete');
    if (inputDelete) inputDelete.value = id;
    window.openModal('deleteConfirmModal');
};

window.reloadHistoriqueView = function() {
    const urlParams = new URLSearchParams(window.location.search);
    const im = urlParams.get('im') || document.querySelector('input[name="im"]')?.value || '';

    if (im) {
        window.location.href = `index.php?page=historique_conge&im=${im}`;
    } else {
        window.location.reload();
    }
};

window.executeDelete = async function() {
    const idInput = document.getElementById('idToDelete');
    if (!idInput) return;
    
    const id = idInput.value;
    try {
        const res = await fetch('actions/conges/delete_conge.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
        });
        const data = await res.json();
        if (data.status === 'success') {
            window.closeModal('deleteConfirmModal');
            window.reloadHistoriqueView();
        } else {
            alert(data.message || 'Erreur lors de la suppression');
        }
    } catch (e) {
        alert('Erreur réseau ou serveur lors de la suppression.');
    }
};

// Gestionnaire global pour les soumissions de formulaires
document.addEventListener('submit', async function (e) {
    if (!e.target) return;

    if (e.target.id === 'formAddConge') {
        e.preventDefault();
        await submitCongeForm('actions/conges/save_historique_conge.php', new FormData(e.target), 'addCongeModal');
    }

    if (e.target.id === 'formEditConge') {
        e.preventDefault();
        await submitCongeForm('actions/conges/update_historique_conge.php', new FormData(e.target), 'editCongeModal');
    }
});

async function submitCongeForm(url, formData, modalToClose) {
    try {
        const res = await fetch(url, { 
            method: 'POST', 
            body: formData 
        });
        
        const data = await res.json();
        
        if (data.status === 'success') {
            window.closeModal(modalToClose);
            window.openModal('successAddModal');
        } else {
            alert(data.message || 'Erreur lors de l\'enregistrement');
        }
    } catch (err) {
        console.error(err);
        alert('Erreur réseau ou réponse serveur invalide.');
    }
}
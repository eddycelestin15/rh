/**
 * Gestion du formulaire de création de responsable
 */

window.updateRoles = function() {
    const selectNiveau = document.getElementById('selectNiveau');
    const selectRole = document.getElementById('selectRole');
    if (!selectNiveau || !selectRole) return;

    const niveau = selectNiveau.value;
    selectRole.innerHTML = '';

    const filterRegion = document.getElementById('filter_region');
    const filterDistrict = document.getElementById('filter_district');
    const filterCrfrp = document.getElementById('filter_crfrp');

    // Masquer par défaut et réafficher selon la sélection
    if (filterRegion) filterRegion.classList.toggle('hidden', niveau !== 'regional' && niveau !== 'district');
    if (filterDistrict) filterDistrict.classList.toggle('hidden', niveau !== 'district');
    if (filterCrfrp) filterCrfrp.classList.toggle('hidden', niveau !== 'crfrp');

    if (!niveau) {
        selectRole.add(new Option("-- Choisir d'abord un niveau --", ""));
        return;
    }

    const rolesMap = {
        'central': [
            { value: 'chef_service', label: 'Chef de Service RH' },
            { value: 'resp_encadre', label: 'Responsable des Personnels Encadrés' },
            { value: 'resp_non_encadre', label: 'Responsable des Personnels Non-Encadrés' },
            { value: 'resp_retraite', label: 'Responsable des Agents Retraités' },
            { value: 'resp_solde', label: 'Responsable Solde' },
            { value: 'resp_conge', label: 'Responsable Congé' }
        ],
        'regional': [
            { value: 'chef_service', label: 'Chef de Service RH' },
            { value: 'resp_encadre', label: 'Responsable des Personnels Encadrés' },
            { value: 'resp_non_encadre', label: 'Responsable des Personnels Non-Encadrés' },
            { value: 'resp_retraite', label: 'Responsable des Agents Retraités' },
            { value: 'resp_solde', label: 'Responsable Solde' },
            { value: 'resp_conge', label: 'Responsable Congé' }
        ],
        'district': [
            { value: 'chef_division', label: 'Chef de Division RH' },
            { value: 'resp_encadre', label: 'Responsable des Personnels Encadrés' },
            { value: 'resp_non_encadre', label: 'Responsable des Personnels Non-Encadrés' },
            { value: 'resp_retraite', label: 'Responsable des Agents Retraités' },
            { value: 'resp_solde', label: 'Responsable Solde' }, 
            { value: 'resp_conge', label: 'Responsable Congé' }
        ],
        'crfrp': [
            { value: 'resp_personnel_crfrp', label: 'Responsable Personnel CRFRP' }
        ]
    };

    if (rolesMap[niveau]) {
        selectRole.add(new Option("-- Sélectionner un rôle --", ""));
        rolesMap[niveau].forEach(role => {
            selectRole.add(new Option(role.label, role.value));
        });
    }

    if (niveau === 'crfrp') {
        window.fetchData('get_crfrp_all', 'crfrp_id');
    } else if (niveau === 'regional' || niveau === 'district') {
        window.fetchData('get_regions', 'region_id');
    }
};

window.handleRegion = function() {
    const regEl = document.getElementById('region_id');
    const selectNiveau = document.getElementById('selectNiveau');
    if (regEl && selectNiveau && selectNiveau.value === 'district') {
        window.fetchData('get_districts_by_reg&reg_id=' + regEl.value, 'district_id');
    }
};

window.fetchData = function(action, targetId) {
    const el = document.getElementById(targetId);
    if (!el) return;
    
    fetch('api/referentiel/get_loc.php?action=' + action)
    .then(r => r.json())
    .then(data => {
        el.innerHTML = '<option value="">-- Sélectionner --</option>';
        data.forEach(item => el.add(new Option(item.nom, item.id)));
    })
    .catch(err => console.error("Erreur API:", err));
};

window.fermerModaleEtRafraichir = function() {
    const modal = document.getElementById('modalSuccess');
    if (modal) modal.classList.add('hidden');
    
    const form = document.getElementById('formCreerResponsable');
    if (form) form.reset();

    window.updateRoles();

    if (typeof loadPage === 'function') {
        loadPage('pages/admin/creer_responsable.php', 'Gestion des Accès');
    }
};

// Fonction d'initialisation déclenchée à l'injection HTML
window.initFormResponsableEvents = function() {
    const selectNiveau = document.getElementById('selectNiveau');
    const regionId = document.getElementById('region_id');
    const form = document.getElementById('formCreerResponsable');

    if (selectNiveau) {
        selectNiveau.removeEventListener('change', window.updateRoles);
        selectNiveau.addEventListener('change', window.updateRoles);
    }

    if (regionId) {
        regionId.removeEventListener('change', window.handleRegion);
        regionId.addEventListener('change', window.handleRegion);
    }

    if (form) {
        form.onsubmit = async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            try {
                const response = await fetch('actions/compte/save_responsable.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('modalSuccess').classList.remove('hidden');
                } else {
                    alert("Erreur : " + result.message);
                }
            } catch (error) {
                document.getElementById('modalSuccess').classList.remove('hidden');
            }
        };
    }

    // Appliquer l'état initial
    window.updateRoles();
};

// Lancement automatique au chargement direct ou SPA
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initFormResponsableEvents);
} else {
    window.initFormResponsableEvents();
}
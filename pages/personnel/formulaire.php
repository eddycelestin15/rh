<?php 
require_once __DIR__ . '/../../includes/bootstrap.php';
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/check_session.php'; 
?>
<head>
    <?php echo base_tag(); ?>
    <link rel="stylesheet" href="assets/css/formulaire.css">
</head>

<div class="bip-wrapper">
    <div class="tabs-wrapper">
        <div class="tabs" id="tabsBar">
            <div class="tab-link active" id="tab0">État Civil</div>
            <div class="tab-link disabled" id="tab1">Diplômes et compétences</div>
            <div class="tab-link disabled" id="tab2">Situation actuel</div>
            <div class="tab-link disabled" id="tab3">Poste actuel</div>
            <div class="tab-link disabled" id="tab4">Distinctions honorifiques</div>
        </div>
    </div>

    <form id="multiStepForm" enctype="multipart/form-data" novalidate>
        <div id="Step0" class="tab-content active"><?php require_once __DIR__ . '/../sections/etat_civil.php'; ?></div>
        <div id="Step1" class="tab-content"><?php require_once __DIR__ . '/../sections/diplomes.php'; ?></div>
        <div id="Step2" class="tab-content"><?php require_once __DIR__ . '/../sections/situation_admin.php'; ?></div>
        <div id="Step3" class="tab-content"><?php require_once __DIR__ . '/../sections/poste_actuel.php'; ?></div>
        <div id="Step4" class="tab-content"><?php require_once __DIR__ . '/../sections/distinction.php'; ?></div>

        <div class="nav-buttons">
            <button type="button" id="prevBtn" class="px-6 py-2 bg-gray-400 text-white rounded shadow btn-hidden" onclick="changeStep(-1)">
                <i class="fas fa-arrow-left mr-2"></i> Précédent
            </button>
            
            <button type="button" id="nextBtn" class="px-6 py-2 bg-sky-500 text-white rounded shadow font-bold hover:bg-sky-600 transition" onclick="saveAndNext()">
                Enregistrer & Suivant <i class="fas fa-save ml-2"></i>
            </button>
        </div>
    </form>
</div>

<!-- Modales -->
<div id="errorModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <p style="color:#64748b; margin-top:10px;">Vous avez oublié une information obligatoire. Veuillez remplir tous les champs en rouge.</p>
        <button class="modal-btn" onclick="closeErrorModal()">J'ai compris</button>
    </div>
</div>

<div id="successModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-icon" style="color: #22c55e; border-color: #22c55e;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2 style="color: #1e293b; margin-top: 15px; font-size: 1.5rem;">Félicitations !</h2>
        <p style="color:#64748b; margin-top:10px; line-height: 1.6;">
            Toutes vos informations ont été enregistrées avec succès. Votre dossier est désormais complet.
        </p>
        <button class="modal-btn" style="background-color: #22c55e; margin-top: 20px; width: 100%;" onclick="goToDashboard()">
            Retour au Tableau de Bord
        </button>
    </div>
</div>

<script>
// Variables globales
let currentStep = 0;
const totalSteps = 5;
let maxStepReached = 0;

// Affichage des modales
window.showErrorModal = function() {
    const modal = document.getElementById('errorModal');
    if (modal) modal.classList.add('active');
};

window.closeErrorModal = function() {
    const modal = document.getElementById('errorModal');
    if (modal) modal.classList.remove('active');
};

window.showSuccessModal = function() {
    const modal = document.getElementById('successModal');
    if (modal) modal.classList.add('active');
};

window.goToDashboard = function() {
    window.location.href = 'index.php'; 
};

window.updateNavigation = function() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    if (!prevBtn || !nextBtn) return;

    prevBtn.style.visibility = (currentStep === 0) ? 'hidden' : 'visible';

    if (currentStep === totalSteps - 1) {
        nextBtn.innerHTML = 'Enregistrer & Terminer <i class="fas fa-check-double ml-2"></i>';
        nextBtn.style.backgroundColor = "#16a34a";
    } else {
        nextBtn.innerHTML = 'Enregistrer & Suivant <i class="fas fa-save ml-2"></i>';
        nextBtn.style.backgroundColor = "#0ea5e9";
    }

    const tabs = document.querySelectorAll('.tab-link');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach((tab, index) => {
        tab.classList.toggle('active', index === currentStep);
        if (contents[index]) contents[index].classList.toggle('active', index === currentStep);

        if (index <= maxStepReached) {
            tab.classList.remove('disabled');
            tab.onclick = () => { currentStep = index; window.updateNavigation(); };
        } else {
            tab.classList.add('disabled');
            tab.onclick = null;
        }
    });

    // ==========================================
    // FIX : CHARGEMENT DES GRADES À L'AFFICHAGE
    // ==========================================
    if (currentStep === 2) { // Index 2 correspond à la Situation Actuelle
        const corpsSelect = document.getElementById('corps_actuel');
        if (corpsSelect && corpsSelect.value !== "" && typeof window.chargerGrades === 'function') {
            console.log("Tab Situation active : Forçage du chargement des grades");
            window.chargerGrades();
            if (typeof window.chargerGradesIntegration === 'function') window.chargerGradesIntegration();
            if (typeof window.checkIntegrationVisibility === 'function') window.checkIntegrationVisibility();
            if (typeof window.synchroniserSituationAdministrative === 'function') window.synchroniserSituationAdministrative();
        }
    }
};

window.cleanAllHiddenFields = function() {
    document.querySelectorAll('.tab-content').forEach(tab => {
        if (!tab.classList.contains('active')) {
            tab.querySelectorAll('input[required], select[required]').forEach(field => {
                field.required = false;
            });
        }
    });
};

window.saveAndNext = async function() {
    const currentStepDiv = document.getElementById('Step' + currentStep);
    if (!currentStepDiv) return;

    let isValid = true;

    // Validation uniquement des champs visibles de l'onglet actuel
    const requiredFields = currentStepDiv.querySelectorAll('input[required], select[required]');

    requiredFields.forEach(input => {
        if (input.offsetParent === null || getComputedStyle(input).display === 'none') return;

        if (!input.value || input.value.trim() === '') {
            input.style.borderColor = "#ef4444";
            input.style.boxShadow = "0 0 0 3px rgba(239, 68, 68, 0.3)";
            isValid = false;
        } else {
            input.style.borderColor = "#e2e8f0";
            input.style.boxShadow = "none";
        }
    });

    if (!isValid) {
        window.showErrorModal();
        return;
    }

    // Validations spécifiques par étape
    if (currentStep === 0) {
        if (typeof validateCIN === 'function' && !validateCIN()) return;
        if (typeof validateAgeCIN === 'function' && !validateAgeCIN()) return;
    }

    if (currentStep === 2) {
        if (typeof validerDatesSituation === 'function' && !validerDatesSituation()) return;
    }

    // --- AFFICHAGE DU LOADER SUR L'ONGLET SITUATION ADMINISTRATIVE (Step 2) ---
    if (currentStep === 2 && typeof Swal !== 'undefined') {
        let titreLoader = "Traitement en cours...";
        let texteLoader = "Veuillez patienter pendant l'enregistrement...";

        Swal.fire({
            title: titreLoader,
            text: texteLoader,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    // Envoi des données via AJAX
    const formData = new FormData(document.getElementById('multiStepForm'));
    formData.append('step_index', currentStep);

    try {
        const response = await fetch('actions/personnel/save_step.php', { 
            method: 'POST', 
            body: formData 
        });
        const result = await response.json();

        // Fermeture du loader SweetAlert2 s'il était ouvert
        if (typeof Swal !== 'undefined' && Swal.isVisible()) {
            Swal.close();
        }

        if (result.success) {
            if (currentStep === 4) {
                window.showSuccessModal();
            } else {
                currentStep++;
                if (currentStep > maxStepReached) maxStepReached = currentStep;
                window.updateNavigation();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: result.message || 'Erreur serveur inconnue'
                });
            } else {
                alert("Erreur : " + (result.message || "Erreur serveur inconnue"));
            }
        }
    } catch (error) {
        console.error("Erreur AJAX:", error);

        if (typeof Swal !== 'undefined' && Swal.isVisible()) {
            Swal.close();
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Erreur réseau',
                text: 'Une erreur réseau est survenue lors du traitement.'
            });
        } else {
            alert("Erreur de connexion au serveur.");
        }
    }
};

window.changeStep = function(n) {
    currentStep += n;
    if (currentStep < 0) currentStep = 0;
    if (currentStep >= totalSteps) currentStep = totalSteps - 1;
    window.updateNavigation();
};

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    const sidebarDropdowns = window.parent.document.querySelectorAll('.dropdown-active');
    sidebarDropdowns.forEach(el => el.classList.remove('dropdown-active'));
    
    window.updateNavigation();
});
</script>
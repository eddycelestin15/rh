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
        </div>
    </div>

    <form id="multiStepForm"enctype="multipart/form-data">
        <div id="Step0" class="tab-content active"><?php require_once __DIR__ . '/../sections/etat_civil.php'; ?></div>
        <div id="Step1" class="tab-content"><?php require_once __DIR__ . '/../sections/diplomes.php'; ?></div>
        <div id="Step2" class="tab-content"><?php require_once __DIR__ . '/../sections/situation_admin.php'; ?></div>
        <div id="Step3" class="tab-content"><?php require_once __DIR__ . '/../sections/poste_actuel.php'; ?></div>

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
<div id="errorModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <p style="color:#64748b; margin-top:10px;">Vous avez oublier une information obligatoire, veuillez remplir tous les champs colorés en rouge avant de continuer.</p>
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
            Toutes vos informations ont été enregistrées avec succès. Votre dossier est désormais complet et à jour.
        </p>
        <button class="modal-btn" style="background-color: #22c55e; margin-top: 20px; width: 100%;" onclick="goToDashboard()">
            Retour au Tableau de Bord
        </button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // On force la fermeture des menus déroulants de la sidebar pour maximiser l'espace
    const sidebarDropdowns = window.parent.document.querySelectorAll('.dropdown-active');
    sidebarDropdowns.forEach(el => el.classList.remove('dropdown-active'));
});

window.showErrorModal = function() {
    const modal = document.getElementById('errorModal');
    if (modal) modal.classList.add('active');
};

function closeErrorModal() {
    document.getElementById('errorModal').classList.remove('active');
    // Réinitialiser le texte par défaut après fermeture
    setTimeout(() => {
        document.querySelector('#errorModal p').innerText = "Vous avez oublié une information obligatoire...";
        document.querySelector('#errorModal p').style.color = "#64748b";
    }, 300);
}

let currentStep = 0;
const totalSteps = 4;
let maxStepReached = 0;

function updateNavigation() {
    // 1. Gérer la visibilité du bouton précédent
    const prevBtn = document.getElementById('prevBtn');
    
    if (currentStep === 0) {
        prevBtn.classList.add('btn-hidden');
    } else {
        prevBtn.classList.remove('btn-hidden');
    }
    
    // Vérifier si c'est la dernière étape
    const nextBtn = document.getElementById('nextBtn');
    if (currentStep === totalSteps - 1) {
        nextBtn.innerHTML = 'Enregistrer & Terminer <i class="fas fa-check-double ml-2"></i>';
        nextBtn.classList.replace('bg-sky-500', 'bg-green-600');
        nextBtn.classList.replace('hover:bg-sky-600', 'hover:bg-green-700');
    } else {
        nextBtn.innerHTML = 'Enregistrer & Suivant <i class="fas fa-save ml-2"></i>';
        nextBtn.classList.replace('bg-green-600', 'bg-sky-500');
        nextBtn.classList.replace('hover:bg-green-700', 'hover:bg-sky-600');
    }

    const tabs = document.querySelectorAll('.tab-link');
    const contents = document.querySelectorAll('.tab-content');
    tabs.forEach((tab, index) => {
        // 1. Gérer l'état actif
        tab.classList.toggle('active', index === currentStep);
        contents[index].classList.toggle('active', index === currentStep);

        // 2. Rendre les onglets déjà visités cliquables
        if (index <= maxStepReached) {
            tab.classList.remove('disabled');
            tab.style.cursor = 'pointer'; // Curseur main pour indiquer que c'est cliquable
            
            // Ajouter l'événement de clic s'il n'existe pas déjà
            tab.onclick = function() {
                currentStep = index;
                updateNavigation();
                window.scrollTo(0,0);
            };
        } else {
            tab.classList.add('disabled');
            tab.style.cursor = 'not-allowed';
            tab.onclick = null; // Désactiver le clic pour les étapes futures
        }
    });    

    // 2. Mettre à jour l'apparence des onglets
    for (let i = 0; i < totalSteps; i++) {
        const tab = document.getElementById('tab' + i);
        const step = document.getElementById('Step' + i);
        
        if (i === currentStep) {
            tab.classList.add('active');
            tab.classList.remove('disabled');
            if(step) step.classList.add('active');
        } else {
            tab.classList.remove('active');
            if(step) step.classList.remove('active');
        }
    }
}

async function saveAndNext() {
    const currentStepDiv = document.getElementById('Step' + currentStep);
    
    // --- 1. Validation des champs obligatoires vides ---
    const inputs = currentStepDiv.querySelectorAll('input[required], select[required]');
    let isValid = true;

    inputs.forEach(input => {
        const isVisible = input.offsetWidth > 0 || input.offsetHeight > 0;
        if (isVisible && (!input.value || !input.value.trim())) {
            input.style.borderColor = "#ef4444";
            isValid = false;
        } else {
            input.style.borderColor = "#e2e8f0";
        }
    });

    if (!isValid) {
        document.querySelector('#errorModal p').innerText = "Veuillez remplir tous les champs obligatoires.";
        showErrorModal();
        return; 
    }

    // --- 2. Validation Spécifique des Dates (Step 2) ---
    if (currentStep === 2) {
        if (typeof window.validateSituationAdmin === 'function') {
            if (!window.validateSituationAdmin()) {
                // Si la validation retourne false, on s'arrête ici
                // Le message d'erreur est déjà injecté par validateSituationAdmin
                return; 
            }
        }
    }

    // 2. Logique d'enregistrement AJAX
    const formData = new FormData(document.getElementById('multiStepForm'));
    formData.append('step_index', currentStep);

    try {
        const response = await fetch('actions/personnel/save_step.php', {
            method: 'POST',
            body: formData
        });

        // Debug : voir la réponse brute si erreur
        const text = await response.text(); 
        console.log(text); 
        const result = JSON.parse(text);

        if (result.success) {
            if (currentStep === totalSteps - 1) {
                showSuccessModal();
            } else {
                // SINON ON PASSE À LA SUIVANTE
                currentStep++;
                if (currentStep > maxStepReached) {
                    maxStepReached = currentStep;
                }
                updateNavigation();
                window.scrollTo(0,0);
            }
        } else {
            alert("Erreur : " + result.message);
        }
    } catch (error) {
        console.error("Erreur AJAX:", error);
    }
}

function changeStep(n) {
    currentStep += n;
    if (currentStep < 0) currentStep = 0;
    if (currentStep >= totalSteps) currentStep = totalSteps - 1;
    updateNavigation();
}
// Nouvelles fonctions utilitaires
function showSuccessModal() {
    document.getElementById('successModal').classList.add('active');
}

function goToDashboard() {
    window.location.href = 'index.php'; 
}
</script>
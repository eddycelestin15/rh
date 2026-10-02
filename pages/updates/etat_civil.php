<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $im = $_SESSION['user_im'];

    // Récupération des données
    $stmt = $pdo->prepare("SELECT * FROM personnel_etat_civil WHERE im = ?");
    $stmt->execute([$im]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtUser = $pdo->prepare("SELECT telephone, email FROM utilisateurs WHERE im = ?");
    $stmtUser->execute([$im]);
    $u = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // Téléphone
    $tel_brut = !empty($v['num_tel']) ? $v['num_tel'] : ($u['telephone'] ?? '');
    $display_tel = str_replace('+261', '', $tel_brut);

    // E-mail (On vérifie d'abord personnel_etat_civil, puis utilisateurs)
    $display_mail = '';
    if (!empty($v['adress_mail'])) {
        $display_mail = $v['adress_mail'];
    } elseif (!empty($v['email'])) {
        $display_mail = $v['email'];
    } else {
        $display_mail = $u['email'] ?? '';
    }
?>

<style>
    /* Ajout de 2 millimètres (8px) de marge sur les 4 côtés pour éviter le collage */
    .pageContent-inner-wrapper {
        padding: 4mm;
        box-sizing: border-box;
    }

    .form-section-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #0369a1;
        text-transform: uppercase;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        border-bottom: 2px solid #7dd3fc;
        padding-bottom: 8px;
    }
    .form-grid-modern {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 20px;
    }
    .col-span-4 { grid-column: span 4; }
    .col-span-6 { grid-column: span 6; }
    .col-span-8 { grid-column: span 8; }
    .col-span-12 { grid-column: span 12; }
    
    .field-group { display: flex; flex-direction: column; gap: 5px; }
    .field-group label { font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
    .field-group input, .field-group select {
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.9rem;
        transition: border-color 0.2s;
    }
    .field-group input:focus { border-color: #7dd3fc; outline: none; box-shadow: 0 0 0 3px rgba(125, 211, 252, 0.2); }
    .readonly-bg { background-color: #f8fafc; color: #94a3b8; cursor: not-allowed; }
    .required-star { color: #ef4444; margin-left: 2px; }
    
    .pageContent-inner-wrapper {
        padding: 0.5mm;
        box-sizing: border-box;
    }
</style>
<div style="background-color: white; padding: 10px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
<div class="pageContent-inner-wrapper">
    <form id="formEtatCivil" enctype="multipart/form-data">
        <div class="form-section-title">
            <i class="fas fa-id-card mr-2"></i> Informations Identité
        </div>

        <div class="form-grid-modern">
            <div class="field-group col-span-4">
                <label>NOM</label>
                <input type="text" value="<?= $_SESSION['user_nom']; ?>" readonly class="readonly-bg">
            </div>
            <div class="field-group col-span-4">
                <label>PRÉNOMS</label>
                <input type="text" value="<?= $_SESSION['user_prenoms'] ?? ''; ?>" readonly class="readonly-bg">
            </div>
            <div class="field-group col-span-4">
                <label>Matricule (IM)</label>
                <input type="text" value="<?= $im; ?>" readonly class="readonly-bg">
            </div>

            <div class="field-group col-span-4">
                <label>Date de Naissance <span class="required-star">*</span></label>
                <input type="date" name="date_naiss" value="<?= $v['date_naiss'] ?? '' ?>" required>
            </div>
            <div class="field-group col-span-4">
                <label>Lieu de Naissance <span class="required-star">*</span></label>
                <input type="text" name="lieu_naiss" value="<?= htmlspecialchars($v['lieu_naiss'] ?? '') ?>" required placeholder="Ex: Antananarivo">
            </div>
            <div class="field-group col-span-4">
                <label>Sexe <span class="required-star">*</span></label>
                <select name="sexe" required> 
                    <option value="">-- Choisir --</option>
                    <option value="Masculin" <?= ($v['sexe'] ?? '') == 'Masculin' ? 'selected' : '' ?>>Masculin</option>
                    <option value="Féminin" <?= ($v['sexe'] ?? '') == 'Féminin' ? 'selected' : '' ?>>Féminin</option>
                </select>
            </div>

            <div class="field-group col-span-4">
                <label>Numéro CIN (12 chiffres) <span class="required-star">*</span></label>
                <input type="text" name="cin" id="cin" maxlength="12" pattern="[0-9]{12}" value="<?= $v['cin'] ?? '' ?>" required 
                       oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            </div>
            <div class="field-group col-span-4">
                <label>Date CIN <span class="required-star">*</span></label>
                <input type="date" name="date_cin" value="<?= $v['date_cin'] ?? '' ?>" required>
            </div>
            <div class="field-group col-span-4">
                <label>Lieu CIN <span class="required-star">*</span></label>
                <input type="text" name="lieu_cin" value="<?= htmlspecialchars($v['lieu_cin'] ?? '') ?>" required>
            </div>
        </div>
        <br>
        <div class="form-section-title mt-8">
            <i class="fas fa-map-marker-alt mr-2"></i> Coordonnées & Famille
        </div>

        <div class="form-grid-modern">
            <div class="field-group col-span-12">
                <label>Adresse de résidence actuelle <span class="required-star">*</span></label>
                <input type="text" name="adresse" value="<?= htmlspecialchars($v['adresse'] ?? '') ?>" required>
            </div>
            <div class="field-group col-span-4">
                <label>Téléphone <span class="required-star">*</span></label>
                <div class="relative flex items-center border border-slate-300 rounded-lg focus-within:border-blue-500 bg-white overflow-hidden shadow-sm">
                    <span class="pl-3 pr-1 text-slate-400 font-bold text-xs select-none">+261</span>
                    <input type="text" 
                        name="num_tel" 
                        value="<?= htmlspecialchars($display_tel) ?>" 
                        class="w-full py-2.5 pr-3 focus:outline-none text-sm text-slate-800" 
                        required 
                        placeholder="34 xxxxxxx">
                </div>
            </div>

            <div class="field-group col-span-4">
                <label>Whatsapp (pour recevoir notification)</label>
                <div class="relative flex items-center border border-slate-300 rounded-lg focus-within:border-blue-500 bg-white overflow-hidden shadow-sm">
                    <span class="pl-3 pr-1 text-slate-400 font-bold text-xs select-none">+261</span>
                    <input type="text" 
                        name="num_whatsapp" 
                        value="<?= htmlspecialchars(str_replace('+261', '', $v['num_whatsapp'] ?? '')) ?>" 
                        class="w-full py-2.5 pr-3 focus:outline-none text-sm text-slate-800" 
                        placeholder="34 xxxxxxx">
                </div>
            </div>
            <div class="field-group col-span-4">
                <label>Adresse E-mail</label>
                <input type="email" 
                    name="adress_mail" 
                    value="<?= htmlspecialchars($display_mail) ?>" 
                    class="w-full py-2.5 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-blue-500 text-sm text-slate-800 bg-white" 
                    placeholder="agent@education.gov.mg">
            </div>

            <div class="field-group col-span-4">
                <label>Situation familiale <span class="required-star">*</span></label>
                <select name="situation_familiale" id="situation_familiale" required>
                    <option value="">-- Choisir --</option>    
                    <?php 
                    $situations = ["Célibataire", "Marié(e)", "Divorcé(e)", "Veuf(ve)"];
                    foreach($situations as $s): ?>
                        <option value="<?= $s ?>" <?= ($v['situation_familiale'] ?? '') == $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group col-span-4">
                <label>Nombre d'enfant(s) en charge</label>
                <input type="text" name="nbr_enfant" value="<?= htmlspecialchars($v['nbr_enfant'] ?? '0') ?>">
            </div>

            <div class="field-group col-span-4">
                <label>Nombre d'enfant(s) dans bon de caisse</label>
                <input type="number" name="nbr_enfant_bc" min="0" value="<?= htmlspecialchars($v['nbr_enfant_bc'] ?? '0') ?>">
            </div>
        </div>

        <br>
        <div class="form-section-title mt-8">
            <i class="fas fa-exclamation-triangle mr-2"></i> Personne à prévenir en cas de danger
        </div>

        <div class="form-grid-modern">
            <div class="field-group col-span-6">
                <label>Nom <span class="required-star">*</span></label>
                <input type="text" name="urgence_nom" value="<?= htmlspecialchars($v['nom_secours'] ?? '') ?>" required placeholder="Nom de la personne">
            </div>
            <div class="field-group col-span-6">
                <label>Prénoms</label>
                <input type="text" name="urgence_prenoms" value="<?= htmlspecialchars($v['prenoms_secours'] ?? '') ?>" placeholder="Prénoms de la personne">
            </div>
            <div class="field-group col-span-8">
                <label>Adresse <span class="required-star">*</span></label>
                <input type="text" name="urgence_adresse" value="<?= htmlspecialchars($v['adresse_secours'] ?? '') ?>" required placeholder="Adresse exacte de résidence">
            </div>
            <div class="field-group col-span-4">
                <label>Numéro de téléphone <span class="required-star">*</span></label>
                <div class="relative flex items-center border border-slate-300 rounded-lg focus-within:border-blue-500 bg-white overflow-hidden shadow-sm">
                    <span class="pl-3 pr-1 text-slate-400 font-bold text-xs select-none">+261</span>
                    <input type="text" 
                        name="urgence_tel" 
                        value="<?= htmlspecialchars(str_replace('+261', '', $v['tel_secours'] ?? '')) ?>" 
                        class="w-full py-2.5 pr-3 focus:outline-none text-sm text-slate-800" 
                        required 
                        placeholder="34 xxxxxxx">
                </div>
            </div>
        </div>

        <div class="form-grid-modern mt-6">
            <div class="field-group col-span-12">
                <button type="button" onclick="updateEtatCivil()" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200" style="background-color: #0284c7; border: none; border-radius: 6px; padding: 12px 20px; color: white; cursor: pointer;">
                    <i class="fas fa-save mr-1"></i> Mettre à jour
                </button>
            </div>
        </div>
    </form>
</div>
</div>

<div id="cinErrorModal" style="display:none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div style="background: white; padding: 25px; border-radius: 12px; max-width: 450px; width: 90%; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border-top: 5px solid #ef4444;">
        <div style="color: #ef4444; font-size: 3rem; margin-bottom: 15px;">
            <i class="fas fa-id-card"></i>
        </div>
        <h3 id="cinErrorTitle" style="color: #1e293b; font-size: 1.2rem; font-weight: 700; margin-bottom: 10px;">Attention</h3>
        <p id="cinErrorMessage" style="color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 20px;"></p>
        <button onclick="closeCinModal()" style="background: #0369a1; color: white; border: none; padding: 10px 25px; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%;">
            Rectifier l'information
        </button>
    </div>
</div>

<div id="successModal" style="display:none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 16px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="color: #0284c7; font-size: 3.5rem; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 style="color: #0f172a; font-size: 1.3rem; font-weight: 800; margin-bottom: 8px;">Enregistrement réussi !</h3>
        <p style="color: #64748b; font-size: 0.95rem; line-height: 1.4; margin-bottom: 25px;">Les informations de l'état civil ont été mises à jour avec succès.</p>
        <button onclick="closeSuccessModal()" style="background: #0284c7; color: white; border: none; padding: 12px 0; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.3);">
            Fermer
        </button>
    </div>
</div>

<script>
let lastErrorField = null;
let isModalOpen = false; 

function showCinModal(message, fieldToFocus = null) {
    if (isModalOpen) return; 

    const modal = document.getElementById('cinErrorModal');
    const msgContainer = document.getElementById('cinErrorMessage');
    
    if (modal && msgContainer) {
        isModalOpen = true;
        msgContainer.innerText = message;
        lastErrorField = fieldToFocus;
        modal.style.display = 'flex';
    }
}

function closeCinModal() {
    const modal = document.getElementById('cinErrorModal');
    if (modal) {
        modal.style.display = 'none';
        
        setTimeout(() => {
            if (lastErrorField) {
                lastErrorField.style.borderColor = "#ef4444";
                lastErrorField.focus();
            }
            isModalOpen = false; 
        }, 200); 
    }
}

function showSuccessModal() {
    const modal = document.getElementById('successModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeSuccessModal() {
    const modal = document.getElementById('successModal');
    if (modal) {
        modal.style.display = 'none';
        location.reload();
    }
}

function validateCIN() {
    if (isModalOpen) return true; 

    const cinInput = document.getElementById('cin');
    if (!cinInput) return true;

    const cin = cinInput.value;
    const sexeSelect = document.querySelector('select[name="sexe"]');
    const sexe = sexeSelect ? sexeSelect.value : "";
    
    if (cin.length === 0) return true;

    if (cin.length !== 12) {
        showCinModal("Le numéro CIN doit comporter exactement 12 chiffres.", cinInput);
        return false;
    }

    if (sexe === "") {
        showCinModal("Veuillez d'abord sélectionner le sexe de l'agent.", sexeSelect);
        return false;
    }

    const sixiemeChiffre = cin.charAt(5); 
    let attendu = (sexe === "Masculin") ? "1" : "2";

    if (sixiemeChiffre !== attendu) {
        showCinModal("Le 6ème chiffre doit être '" + attendu + "' pour le sexe " + sexe + ".", cinInput);
        return false;
    }

    cinInput.style.borderColor = "#e2e8f0";
    return true;
}

function validateAgeCIN() {
    if (isModalOpen) return true;

    const dateNaissInput = document.querySelector('input[name="date_naiss"]');
    const dateCinInput = document.querySelector('input[name="date_cin"]');
    
    if (!dateNaissInput || !dateCinInput || !dateNaissInput.value || !dateCinInput.value) return true;

    const dateNaiss = new Date(dateNaissInput.value);
    const dateCin = new Date(dateCinInput.value);

    let ageAtCin = dateCin.getFullYear() - dateNaiss.getFullYear();
    const m = dateCin.getMonth() - dateNaiss.getMonth();
    if (m < 0 || (m === 0 && dateCin.getDate() < dateNaiss.getDate())) {
        ageAtCin--;
    }

    if (ageAtCin < 18) {
        showCinModal("L'agent ne peut pas avoir obtenu son CIN avant 18 ans (Âge calculé : " + ageAtCin + " ans).", dateCinInput);
        return false;
    }

    dateCinInput.style.borderColor = "#e2e8f0";
    return true;
}

function initEtatCivilLogic() {
    const cinInput = document.getElementById('cin');
    const dateCinInput = document.querySelector('input[name="date_cin"]');
    const lieuCinInput = document.querySelector('input[name="lieu_cin"]');

    if (cinInput) {
        cinInput.addEventListener('blur', validateCIN);
    }

    if (dateCinInput) {
        dateCinInput.addEventListener('blur', validateAgeCIN);
        dateCinInput.addEventListener('focus', function() {
            if (!validateCIN()) this.blur();
        });
    }

    if (lieuCinInput) {
        lieuCinInput.addEventListener('focus', function() {
            if (!validateAgeCIN()) this.blur();
        });
    }
}

function updateEtatCivil() {
    if (!validateCIN() || !validateAgeCIN()) {
        return;
    }
    
    const form = document.getElementById('formEtatCivil');
    const formData = new FormData(form);
    
    formData.append('step_index', '0');

    fetch('actions/personnel/save_step.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessModal();
        } else {
            alert('Erreur lors de la mise à jour : ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Une erreur réseau est survenue.');
    });
}

initEtatCivilLogic();
window.updateEtatCivil = updateEtatCivil;
window.closeSuccessModal = closeSuccessModal;
window.closeCinModal = closeCinModal;
</script>
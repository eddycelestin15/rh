<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    require_once __DIR__ . '/../../includes/config.php';  
    $im = $_SESSION['user_im'];

    // 1. Récupération de la Situation Actuelle
    $stmtSit = $pdo->prepare("SELECT * FROM personnel_situation_actuelle WHERE im = ?");
    $stmtSit->execute([$im]);
    $sit = $stmtSit->fetch(PDO::FETCH_ASSOC) ?: [];

    // Listes pour les menus
    $statut = $pdo->query("SELECT * FROM statut ORDER BY nom_statut ASC")->fetchAll();
    $code_sanction = $pdo->query("SELECT * FROM code_sanction ORDER BY nom_code_sanction ASC")->fetchAll();
    $corps_complet = $pdo->query("SELECT * FROM ref_corps ORDER BY categorie ASC, libelle_corps ASC")->fetchAll();
?>

<style>
    .form-section-title {
        font-size: 0.9rem; font-weight: 700; color: #0369a1; text-transform: uppercase;
        margin-bottom: 20px; display: flex; align-items: center;
        border-bottom: 2px solid #7dd3fc; padding-bottom: 8px;
    }
    .form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
    .field-group { display: flex; flex-direction: column; gap: 5px; }
    .field-group label { font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
    .field-group input, .field-group select { padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; }
    .readonly-bg { background-color: #f8fafc; cursor: not-allowed; }
    .required-star { color: #ef4444; margin-left: 2px; }
    .conjoint-box { padding: 20px; border-radius: 8px; margin-top: 20px; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
    .form-grid-responsive { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr) !important; 
        gap: 15px; 
        margin-bottom: 30px; 
    }
    @media (min-width: 1024px) {
        .form-grid-responsive { 
            grid-template-columns: repeat(4, 1fr) !important; 
            gap: 20px;
        }
    }
    @media (max-width: 480px) {
        .form-grid-responsive { 
            grid-template-columns: repeat(2, 1fr) !important; 
        }
    }
</style>

<div class="form-section-title">
    <i class="fas fa-university mr-2"></i> Situation actuelle du personnel
</div>

<input type="hidden" id="grade_enregistre" value="<?= htmlspecialchars($sit['grade_actuel'] ?? '') ?>">

<div class="form-grid-responsive">
    <div class="field-group">
        <label>Matricule (IM)</label>
        <input type="text" name="im" value="<?= $im; ?>" readonly class="readonly-bg">
    </div>
    <div class="field-group">
        <label>Date de prise de service<span class="required-star">*</span></label>
        <input type="date" name="date_entree_admin" id="date_entree_admin" value="<?= $sit['date_entree_admin'] ?? '' ?>" required>
    </div>
    <div class="field-group">
        <label>Statut <span class="required-star">*</span></label>
        <select name="statut_actuel" id="statut_actuel" required onchange="handleStatutChange(); chargerGrades(); chargerIndice();">
            <option value="">-- Choisir --</option>
            <?php foreach($statut as $s): ?>
                <option value="<?= htmlspecialchars($s['nom_statut']) ?>" <?= ($sit['statut_actuel'] ?? '') == $s['nom_statut'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nom_statut']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field-group">
        <label>Type d'avancement <span class="required-star">*</span></label>
        <select name="type_avancement_actuel" id="type_avancement_actuel" required onchange="handleStatutChange(); verifierAffichageSituationAvantIntegration();">
            <option value="">-- Choisir --</option>
            <option value="Stagiaire" <?= ($sit['type_avancement_actuel'] ?? '') == 'Stagiaire' ? 'selected' : '' ?>>Stagiaire</option> 
            <option value="Classe" <?= ($sit['type_avancement_actuel'] ?? '') == 'Classe' ? 'selected' : '' ?>>Classe</option> 
            <option value="Echelon" <?= ($sit['type_avancement_actuel'] ?? '') == 'Echelon' ? 'selected' : '' ?>>Echelon</option>
            <option value="Intégration" <?= ($sit['type_avancement_actuel'] ?? '') == 'Intégration' ? 'selected' : '' ?>>Intégration</option>
            <option value="Titularisation" <?= ($sit['type_avancement_actuel'] ?? '') == 'Titularisation' ? 'selected' : '' ?>>Titularisation</option>
        </select>
    </div>
    <div class="field-group">
        <label>Type de l'acte <span class="required-star">*</span></label>
        <select name="type_acte_actuel" id="type_acte_actuel" required onchange="verifierAffichageContratIndetermine();">
            <option value="">-- Choisir --</option> 
            <option value="Contrat" <?= ($sit['type_acte_actuel'] ?? '') == 'Contrat' ? 'selected' : '' ?>>Contrat</option>
            <option value="Avenant" <?= ($sit['type_acte_actuel'] ?? '') == 'Avenant' ? 'selected' : '' ?>>Avenant</option>
            <option value="Arrêté" <?= ($sit['type_acte_actuel'] ?? '') == 'Arrêté' ? 'selected' : '' ?>>Arrêté</option>
        </select>
    </div>
    <div class="field-group">
        <label>N° de l'acte <span class="required-star">*</span></label>
        <input type="text" name="num_acte_actuel" id="num_acte_actuel" value="<?= htmlspecialchars($sit['num_acte_actuel'] ?? '') ?>" required>
    </div>
    <div class="field-group">
        <label>Date de l'acte <span class="required-star">*</span></label>
        <input type="date" name="date_acte_actuel" id="date_acte_actuel" value="<?= $sit['date_acte_actuel'] ?? '' ?>" required>
    </div>

    <div class="field-group">
        <label>Date d'effet <span class="required-star">*</span></label>
        <input type="date" name="date_d_effet_actuel" id="date_d_effet_actuel" value="<?= $sit['date_d_effet_actuel'] ?? '' ?>" required>
    </div>
    <div class="field-group">
        <label>Code Corps <span class="required-star">*</span></label>
        <input type="text" name="code_corps_actuel" id="code_corps_actuel" 
            value="<?= htmlspecialchars($sit['code_corps_actuel'] ?? '') ?>" 
            readonly class="readonly-bg" required>
    </div>
    <div class="field-group">
        <label>Corps Actuel <span class="required-star">*</span></label>
        <select name="corps_actuel" id="corps_actuel" required 
                onchange="mettreAJourChampsAutomatiques(); chargerGrades(); chargerGradesIntegration(); synchroniserSituationAdministrative();">
            <option value="">-- Choisir --</option>
            <?php foreach($corps_complet as $c): ?>
                <?php 
                    $corps_base = trim($c['libelle_corps']);
                    $corps_user = trim($sit['corps_actuel'] ?? '');
                    $selected = ($corps_base === $corps_user) ? 'selected' : '';
                ?>
                <option value="<?= $c['id'] ?>" 
                        data-cat="<?= htmlspecialchars($c['categorie']) ?>" 
                        <?= $selected ?>>
                    <?= htmlspecialchars($c['libelle_corps']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field-group">
        <label>Grade Actuel <span class="required-star">*</span></label>
        <select name="grade_actuel" id="grade_actuel" class="form-control" onchange="chargerIndice()">
            <option value="">-- Choisir le grade --</option>
        </select>
    </div>

    <div class="field-group">
        <label>Catégorie <span class="required-star">*</span></label>
        <input type="text" name="categorie_actuel" id="categorie_actuel" value="<?= htmlspecialchars($sit['categorie_actuel'] ?? '') ?>" readonly class="readonly-bg">
    </div>
    <div class="field-group">
        <label>Indice <span class="required-star">*</span></label>
        <input type="text" name="indice_actuel" id="indice_actuel" value="<?= htmlspecialchars($sit['indice_actuel'] ?? '') ?>" readonly class="readonly-bg">
    </div>
    <div class="field-group">
        <label>Mode de paiement <span class="required-star">*</span></label>
        <select name="mode_paiement" required>
            <option value="">-- Choisir --</option>
            <option value="Virement" <?= ($sit['mode_paiement'] ?? '') == 'Virement' ? 'selected' : '' ?>>Virement</option>
            <option value="Bon de caisse" <?= ($sit['mode_paiement'] ?? '') == 'Bon de caisse' ? 'selected' : '' ?>>Bon de caisse</option>
        </select>
    </div>
    <div class="field-group">
        <label>Chapitre budgétaire <span class="required-star">*</span></label>
        <input type="text" name="chap_budg" value="<?= htmlspecialchars($sit['chap_budg'] ?? '') ?>" required>
    </div>

    <div class="field-group">
        <label>Imputation budgétaire <span class="required-star">*</span></label>
        <select name="imput_budg" required>
            <option value="">-- Choisir --</option>
            <option value="00810110" <?= ($sit['imput_budg'] ?? '') == '00810110' ? 'selected' : '' ?>>00810110</option>
            <option value="00811130" <?= ($sit['imput_budg'] ?? '') == '00811130' ? 'selected' : '' ?>>00811130</option>
        </select>
    </div>
</div>

<div id="modalErrorDates" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md p-4">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden border border-rose-100">
            <div class="bg-rose-50 p-8 text-center">
                <div class="w-20 h-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
                    <i class="fas fa-calendar-times text-3xl"></i>
                </div>
                <h3 class="text-xl font-black text-rose-900 uppercase tracking-tight">Erreur de Chronologie</h3>
                <p class="text-rose-700/80 text-sm mt-2 font-medium">L'ordre des dates est incorrect.</p>
            </div>
            <div class="p-6 space-y-3">
                <div id="errorMessageDates" class="text-sm text-slate-600 bg-slate-50 p-4 rounded-xl border-l-4 border-rose-500 italic text-center"></div>
                <button type="button" onclick="document.getElementById('modalErrorDates').classList.add('hidden')" 
                    class="w-full py-4 bg-slate-900 text-white rounded-2xl font-bold shadow-lg hover:bg-rose-600 transition-all text-xs tracking-widest uppercase">
                    Vérifier mes saisies
                </button>
            </div>
        </div>
    </div>
</div>
<div id="section_contrat_indetermine" style="display: none; margin-top: 30px;">
    <div class="form-section-title">
        <i class="fas fa-file-contract mr-2"></i> Situation du contrat indeterminée :
    </div>
    
    <div class="form-grid-responsive">
        <div class="field-group">
            <label>N° de l'acte <span class="required-star">*</span></label>
            <input type="text" name="num_acte_ind" id="num_acte_ind">
        </div>
        <div class="field-group">
            <label>Date de l'acte <span class="required-star">*</span></label>
            <input type="date" name="date_acte_ind" id="date_acte_ind">
        </div>
        <div class="field-group">
            <label>Corps <span class="required-star">*</span></label>
            <select name="corps_ind" id="corps_ind" onchange="chargerGradesIndetermine();">
                <option value="">-- Choisir --</option>
                <?php foreach($corps_complet as $c): ?>
                    <option value="<?= $c['id'] ?>" data-cat="<?= htmlspecialchars($c['categorie']) ?>">
                        <?= htmlspecialchars($c['libelle_corps']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-group">
            <label>Grade <span class="required-star">*</span></label>
            <select name="grade_ind" id="grade_ind" onchange="chargerIndiceIndetermine();">
                <option value="">-- Choisir le grade --</option>
            </select>
        </div>
        <div class="field-group">
            <label>Indice <span class="required-star">*</span></label>
            <input type="text" name="indice_ind" id="indice_ind" readonly class="readonly-bg">
        </div>
        <div class="field-group">
            <label>Date d'effet <span class="required-star">*</span></label>
            <input type="date" name="date_d_effet_ind" id="date_d_effet_ind">
        </div>
    </div>
</div>
<div id="section_situation_avant_integration" style="display: none; margin-top: 30px;">
    <div class="form-section-title">
        <i class="fas fa-history mr-2"></i> Dernière situation avant intégration :
    </div>
    
    <div class="form-grid-responsive">
        <div class="field-group">
            <label>N° de l'acte <span class="required-star">*</span></label>
            <input type="text" name="num_acte_av_int" id="num_acte_av_int">
        </div>
        <div class="field-group">
            <label>Date de l'acte <span class="required-star">*</span></label>
            <input type="date" name="date_acte_av_int" id="date_acte_av_int">
        </div>
        <div class="field-group">
            <label>Corps <span class="required-star">*</span></label>
            <select name="corps_av_int" id="corps_av_int" onchange="chargerGradesAvantIntegration();">
                <option value="">-- Choisir --</option>
                <?php foreach($corps_complet as $c): ?>
                    <option value="<?= $c['id'] ?>" data-cat="<?= htmlspecialchars($c['categorie']) ?>">
                        <?= htmlspecialchars($c['libelle_corps']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-group">
            <label>Grade <span class="required-star">*</span></label>
            <select name="grade_av_int" id="grade_av_int" onchange="chargerIndiceAvantIntegration();">
                <option value="">-- Choisir le grade --</option>
            </select>
        </div>
        <div class="field-group">
            <label>Indice <span class="required-star">*</span></label>
            <input type="text" name="indice_av_int" id="indice_av_int" readonly class="readonly-bg">
        </div>
        <div class="field-group">
            <label>Date d'effet <span class="required-star">*</span></label>
            <input type="date" name="date_d_effet_av_int" id="date_d_effet_av_int">
        </div>
    </div>
</div>

<script>
// Affichage conditionnel du bloc sous la situation actuelle
function verifierAffichageContratIndetermine() {
    const statut = (document.getElementById('statut_actuel')?.value || '').trim();
    const typeActe = (document.getElementById('type_acte_actuel')?.value || '').trim();
    const blocInd = document.getElementById('section_contrat_indetermine');
    
    if (!blocInd) return;

    console.log('Vérif affichage → statut:', statut, '| typeActe:', typeActe); // debug

    if (statut === "Contractuel EFA" && typeActe === "Avenant") {
        blocInd.style.display = "block";
    } else {
        blocInd.style.display = "none";
    }
}

// Chargement des grades filtrés (seulement ECHELLE pour Catégorie II et III)
function chargerGradesIndetermine() {
    const corpsSelect = document.getElementById('corps_ind');
    const corpsId = corpsSelect.value;
    const selectGrade = document.getElementById('grade_ind');
    const inputIndice = document.getElementById('indice_ind');
    
    if (inputIndice) inputIndice.value = "";
    if (!corpsId || !selectGrade) return;

    const selectedOption = corpsSelect.options[corpsSelect.selectedIndex];
    const cat = selectedOption.getAttribute('data-cat') || "";

    fetch(`api/referentiel/get_grades_par_corps.php?corps_id=${corpsId}`)
    .then(response => response.json())
    .then(data => {
        selectGrade.innerHTML = '<option value="">-- Choisir le grade --</option>';
        data.forEach(grade => {
            const lib = grade.libelle_grade.toUpperCase();
            let afficher = true;

            if (cat === 'II' || cat === 'III') {
                if (!lib.includes("ECHELLE")) {
                    afficher = false;
                }
            }

            if (afficher) {
                const opt = document.createElement('option');
                opt.value = grade.id;
                opt.textContent = grade.libelle_grade;
                selectGrade.appendChild(opt);
            }
        });
    });
}

function chargerIndiceIndetermine() {
    const corpsId = document.getElementById('corps_ind').value;
    const gradeId = document.getElementById('grade_ind').value;
    const inputIndice = document.getElementById('indice_ind');

    if (!corpsId || !gradeId) {
        if (inputIndice) inputIndice.value = "";
        return;
    }

    fetch(`api/referentiel/get_indice.php?corps_id=${corpsId}&grade_type_id=${gradeId}`)
        .then(response => response.json())
        .then(data => {
            if (inputIndice) { inputIndice.value = (data && data.indice) ? data.indice : ""; }
        })
        .catch(err => {
            console.error("Erreur", err);
            if (inputIndice) inputIndice.value = "";
        });
}

// Exposition globale (très important)
window.verifierAffichageContratIndetermine = verifierAffichageContratIndetermine;
window.chargerGradesIndetermine = chargerGradesIndetermine;
window.chargerIndiceIndetermine = chargerIndiceIndetermine;

// Écouteurs
document.addEventListener('DOMContentLoaded', function() {
    const statutElt = document.getElementById('statut_actuel');
    const typeActeElt = document.getElementById('type_acte_actuel');

    if (statutElt) statutElt.addEventListener('change', verifierAffichageContratIndetermine);
    if (typeActeElt) typeActeElt.addEventListener('change', verifierAffichageContratIndetermine);
    
    // Appel initial
    verifierAffichageContratIndetermine();
});
</script>
<script>
    // Gestion du masquage/affichage conditionnel du bloc "Situation avant intégration"
function verifierAffichageSituationAvantIntegration() {
    const typeAvancement = (document.getElementById('type_avancement_actuel')?.value || '').trim();
    const blocInt = document.getElementById('section_situation_avant_integration');
    
    if (!blocInt) return;

    if (typeAvancement === "Intégration") {
        blocInt.style.display = "block";
    } else {
        blocInt.style.display = "none";
    }
}

// Chargement des grades pour la situation avant intégration
function chargerGradesAvantIntegration() {
    const corpsSelect = document.getElementById('corps_av_int');
    const corpsId = corpsSelect ? corpsSelect.value : '';
    const selectGrade = document.getElementById('grade_av_int');
    const inputIndice = document.getElementById('indice_av_int');
    
    if (inputIndice) inputIndice.value = "";
    if (!corpsId || !selectGrade) return;

    const selectedOption = corpsSelect.options[corpsSelect.selectedIndex];
    const cat = selectedOption.getAttribute('data-cat') || "";

    fetch(`api/referentiel/get_grades_par_corps.php?corps_id=${corpsId}`)
    .then(response => response.json())
    .then(data => {
        selectGrade.innerHTML = '<option value="">-- Choisir le grade --</option>';
        data.forEach(grade => {
            const lib = grade.libelle_grade.toUpperCase();
            let afficher = true;

            if (cat === 'II' || cat === 'III') {
                if (!lib.includes("ECHELLE")) {
                    afficher = false;
                }
            }

            if (afficher) {
                const opt = document.createElement('option');
                opt.value = grade.id;
                opt.textContent = grade.libelle_grade;
                selectGrade.appendChild(opt);
            }
        });
    })
    .catch(err => console.error("Erreur lors du chargement des grades avant intégration :", err));
}

// Chargement de l'indice pour la situation avant intégration
function chargerIndiceAvantIntegration() {
    const corpsId = document.getElementById('corps_av_int')?.value;
    const gradeId = document.getElementById('grade_av_int')?.value;
    const inputIndice = document.getElementById('indice_av_int');

    if (!corpsId || !gradeId) {
        if (inputIndice) inputIndice.value = "";
        return;
    }

    fetch(`api/referentiel/get_indice.php?corps_id=${corpsId}&grade_type_id=${gradeId}`)
        .then(response => response.json())
        .then(data => {
            if (inputIndice) { inputIndice.value = (data && data.indice) ? data.indice : ""; }
        })
        .catch(err => {
            console.error("Erreur lors du chargement de l'indice avant intégration :", err);
            if (inputIndice) inputIndice.value = "";
        });
}

// Exposition globale des fonctions
window.verifierAffichageSituationAvantIntegration = verifierAffichageSituationAvantIntegration;
window.chargerGradesAvantIntegration = chargerGradesAvantIntegration;
window.chargerIndiceAvantIntegration = chargerIndiceAvantIntegration;

// Écouteurs d'événements à mettre à jour dans votre DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    const typeAvancementElt = document.getElementById('type_avancement_actuel');

    if (typeAvancementElt) {
        typeAvancementElt.addEventListener('change', verifierAffichageSituationAvantIntegration);
    }
    
    // Appel initial au chargement de la page
    verifierAffichageSituationAvantIntegration();
});
</script>

<script>

function calculerCodeCorps() {
    const statut = document.getElementById('statut_actuel').value;
    const catElt = document.getElementById('categorie_actuel');
    if(!catElt) return;
    const categorie = catElt.value.trim().toUpperCase();
    const gradeSelect = document.getElementById('grade_actuel');
    const inputCodeCorps = document.getElementById('code_corps_actuel');

    if (!gradeSelect.value || !statut || !categorie) return;

    const gradeText = gradeSelect.options[gradeSelect.selectedIndex].text.trim().toUpperCase();
    let code = "";

    switch (categorie) {
        case 'II':
            if (statut === "Contractuel EFA") {
                if (gradeText.includes("ECHELLE III/1°ECHELON") || gradeText.includes("ECHELLE III/2°ECHELON")) {
                    code = "L00A";
                } else {
                    code = "U02C";
                }
            } else if (statut === "Fonctionnaire") {
                code = "C18A";
            }
            break;
        case 'III':
            if (statut === "Contractuel EFA") {
                if (gradeText.includes("ECHELLE IV/1°ECHELON") || gradeText.includes("ECHELLE IV/2°ECHELON")) {
                    code = "K00A";
                } else {
                    code = "U03B";
                }
            } else if (statut === "Fonctionnaire") {
                code = "B18A";
            }
            break;
        case 'IV':
            if (statut === "Contractuel EFA" || (gradeText.includes("STAGIAIRE"))) {
                if (gradeText.includes("STAGIAIRE") || gradeText.includes("2°CLASSE/1°ECHELON")) {
                    code = "J04A";
                } else {
                    code = "U04A";
                }
            } else if (statut === "Fonctionnaire") {
                code = "A18C";
            }
            break;
        case 'V':
            if (statut === "Contractuel EFA" || (gradeText.includes("STAGIAIRE"))) {
                if (gradeText.includes("STAGIAIRE") || gradeText.includes("2°CLASSE/1°ECHELON")) {
                    code = "J05A";
                } else {
                    code = "U05A";
                }
            } else if (statut === "Fonctionnaire") {
                code = "A18D";
            }
            break;
        case 'VI':
            if (statut === "Contractuel EFA" || (gradeText.includes("STAGIAIRE"))) {
                if (gradeText.includes("STAGIAIRE") || gradeText.includes("2°CLASSE/1°ECHELON")) {
                    code = "J06A";
                } else {
                    code = "U06A";
                }
            } else if (statut === "Fonctionnaire") {
                code = "A18B";
            }
            break;
        case 'VIII':
            if (statut === "Contractuel EFA" || (gradeText.includes("STAGIAIRE"))) {
                if (gradeText.includes("STAGIAIRE") || gradeText.includes("2°CLASSE/1°ECHELON")) {
                    code = "J08A";
                } else {
                    code = "U08A";
                }
            } else if (statut === "Fonctionnaire") {
                code = "A18A";
            }
            break;
    }
    inputCodeCorps.value = code;
}

function chargerIndice() {
    const corpsId = document.getElementById('corps_actuel').value;
    const gradeSelect = document.getElementById('grade_actuel');
    const gradeId = gradeSelect.value; 
    const inputIndice = document.getElementById('indice_actuel');

    if (!corpsId || !gradeId || gradeId === "") {
        if(inputIndice) inputIndice.value = "";
        return;
    }
    
    calculerCodeCorps();
    fetch(`api/referentiel/get_indice.php?corps_id=${corpsId}&grade_type_id=${gradeId}`)
        .then(response => response.json())
        .then(data => {
            if (inputIndice) { inputIndice.value = (data && data.indice) ? data.indice : ""; }
        })
        .catch(err => {
            console.error("Erreur", err);
            if(inputIndice) inputIndice.value = "";
        });
}

function chargerGrades() {
    const corpsSelect = document.getElementById('corps_actuel');
    const corpsId = corpsSelect.value;
    const corpsLibelle = corpsSelect.options[corpsSelect.selectedIndex]?.text || "";
    const statut = document.getElementById('statut_actuel').value;
    const typeAvancement = document.getElementById('type_avancement_actuel').value; 
    const selectGrade = document.getElementById('grade_actuel');

    if (!corpsId || !selectGrade) return;

    fetch(`api/referentiel/get_grades_par_corps.php?corps_id=${corpsId}`)
    .then(response => response.json())
    .then(data => {
        selectGrade.innerHTML = '<option value="">-- Choisir le grade --</option>';
        data.forEach(grade => {
            const lib = grade.libelle_grade.toUpperCase();
            let afficher = false;

            const isCatII = corpsLibelle.includes('INSTITUTEURS ET INSTITUTRICES "C"') || corpsLibelle.includes('OPERATEURS');
            const isCatIII = corpsLibelle.includes('INSTITUTEURS ET INSTITUTRICES "B"') || corpsLibelle.includes('ENCADREURS');

            if (isCatII) {
                if (statut === "Contractuel EFA") { if (lib.includes("ECHELLE III")) afficher = true; } 
                else { if (!lib.includes("ECHELLE III")) afficher = true; }
            } else if (isCatIII) {
                if (statut === "Contractuel EFA") { if (lib.includes("ECHELLE IV")) afficher = true; } 
                else { if (!lib.includes("ECHELLE IV")) afficher = true; }
            } else {
                afficher = true;
            }

            if (afficher) {
                const opt = document.createElement('option');
                opt.value = grade.id; 
                opt.textContent = grade.libelle_grade;
                selectGrade.appendChild(opt);
            }
        });

        if (statut === 'Fonctionnaire' && typeAvancement === 'Titularisation') {
            for (let i = 0; i < selectGrade.options.length; i++) {
                if (selectGrade.options[i].text.trim().toUpperCase() === "2°CLASSE/1°ECHELON") {
                    selectGrade.selectedIndex = i;
                    chargerIndice();
                    break;
                }
            }
        }
    });
}

function mettreAJourChampsAutomatiques() {
    const corpsSelect = document.getElementById('corps_actuel');
    const selectedOption = corpsSelect.options[corpsSelect.selectedIndex];
    
    if (selectedOption && selectedOption.value !== "") {
        const cat = selectedOption.getAttribute('data-cat');
        const inputCat = document.getElementById('categorie_actuel');
        if (inputCat) {
            inputCat.value = cat || "";
            const event = new Event('change');
            inputCat.dispatchEvent(event);
        }
        document.getElementById('code_corps_actuel').value = "";
    }
}

function handleStatutChange() {
    const statut = document.getElementById('statut_actuel').value;
    const typeAvancementElt = document.getElementById('type_avancement_actuel'); 
    const typeActeElt = document.getElementById('type_acte_actuel');
    if (!typeAvancementElt || !typeActeElt) return;

    if (statut === "Contractuel EFA") {
        // Filtrer les options d'avancement
        Array.from(typeAvancementElt.options).forEach(opt => {
            const val = opt.value;
            // Inclure "Intégration" si les contractuels peuvent faire une intégration
            opt.style.display = (val === "" || val === "Stagiaire" || val === "Classe" || val === "Echelon" || val === "Intégration") ? 'block' : 'none';
        });

        // Filtrer les types d'acte
        Array.from(typeActeElt.options).forEach(opt => {
            const val = opt.value;
            opt.style.display = (val === "" || val === "Contrat" || val === "Avenant") ? 'block' : 'none';
        });

        if (typeActeElt.value !== "Contrat" && typeActeElt.value !== "Avenant") {
            typeActeElt.value = "Contrat";
        }

        typeActeElt.dispatchEvent(new Event('change'));
    }
    else if (statut === "Fonctionnaire") {
        const avancement = typeAvancementElt.value;
        Array.from(typeAvancementElt.options).forEach(opt => {
            const val = opt.value;
            const allowed = ["", "Stagiaire", "Classe", "Echelon", "Intégration", "Titularisation"];
            opt.style.display = allowed.includes(val) ? 'block' : 'none';
        });
        const isArreteAvancement = ["Stagiaire", "Classe", "Echelon", "Intégration", "Titularisation"].includes(avancement);
        
        if (isArreteAvancement) {
            typeActeElt.value = "Arrêté";
        }

        Array.from(typeActeElt.options).forEach(opt => {
            const val = opt.value;
            if (isArreteAvancement) {
                opt.style.display = (val === "Arrêté") ? 'block' : 'none';
            } else {
                const optionsFonctionnaire = ["", "Arrêté"];
                opt.style.display = optionsFonctionnaire.includes(val) ? 'block' : 'none';
            }
        });
    }

    calculerCodeCorps();

    // Mettre à jour l'affichage des deux sections conditionnelles
    if (typeof verifierAffichageContratIndetermine === 'function') {
        verifierAffichageContratIndetermine();
    }
    if (typeof verifierAffichageSituationAvantIntegration === 'function') {
        verifierAffichageSituationAvantIntegration();
    }
}

function chargerGradesIntegration() {
    const corpsId = document.getElementById('corps_actuel').value;
    const intGradeSelect = document.getElementById('int_grade');

    if (!intGradeSelect) return;
    if (!corpsId) {
        intGradeSelect.innerHTML = '<option value="">-- Choisir le grade --</option>';
        return;
    }

    fetch(`api/referentiel/get_grades_par_corps.php?corps_id=${corpsId}`)
    .then(response => response.json())
    .then(data => {
        intGradeSelect.innerHTML = '<option value="">-- Choisir le grade --</option>';
        data.forEach(grade => {
            const libelle = grade.libelle_grade.toUpperCase();
            if (!libelle.includes("ECHELLE III") && !libelle.includes("ECHELLE IV")) {
                let opt = document.createElement('option');
                opt.value = grade.id;
                opt.text = grade.libelle_grade;
                intGradeSelect.add(opt);
            }
        });
    }).catch(err => console.error(err));
}

function chargerGradesAvantIntegration() {
    const corpsId = document.getElementById('corps_actuel').value;
    const catElt = document.getElementById('categorie_actuel');
    const avGradeSelect = document.getElementById('av_grade_int');
    
    if (!corpsId || !avGradeSelect || !catElt) return;
    const categorie = catElt.value;

    fetch(`api/referentiel/get_grades_par_corps.php?corps_id=${corpsId}`)
    .then(res => res.json())
    .then(data => {
        avGradeSelect.innerHTML = '<option value="">-- Choisir le grade --</option>';
        data.forEach(g => {
            const lib = g.libelle_grade.toUpperCase();
            if (categorie === 'II' || categorie === 'III') {
                if (lib.includes("ECHELLE")) {
                    const opt = document.createElement('option');
                    opt.value = g.id; opt.textContent = g.libelle_grade;
                    avGradeSelect.appendChild(opt);
                }
            } else {
                const opt = document.createElement('option');
                opt.value = g.id; opt.textContent = g.libelle_grade;
                avGradeSelect.appendChild(opt);
            }
        });
    }).catch(err => console.error(err));
}

function chargerIndiceSpecifique(prefix) {
    const corpsId = document.getElementById('corps_actuel').value;
    let gradeElementId = (prefix === 'av') ? 'av_grade_int' : prefix + '_grade';
    let indiceElementId = (prefix === 'av') ? 'av_indice_int' : prefix + '_indice';

    const gradeSelect = document.getElementById(gradeElementId);
    const indiceInput = document.getElementById(indiceElementId);

    if (!gradeSelect || !indiceInput) return;
    const gradeId = gradeSelect.value;

    if (corpsId && gradeId) {
        fetch(`api/referentiel/get_indice.php?corps_id=${corpsId}&grade_id=${gradeId}`)
            .then(res => res.json())
            .then(data => { indiceInput.value = data.indice || ''; })
            .catch(err => console.error(err));
    }
}

function synchroniserSituationAdministrative() {
    const corpsElt = document.getElementById('corps_actuel');
    const corpsNom = corpsElt.options[corpsElt.selectedIndex]?.text || '';
    const dateEntree = document.getElementById('date_entree_admin').value;

    const targetInt = document.getElementById('int_corps_display');
    const targetTit = document.getElementById('tit_corps_display');
    if(targetInt) targetInt.value = corpsNom;
    if(targetTit) targetTit.value = corpsNom;

    if (dateEntree) {
        let d = new Date(dateEntree);
        d.setFullYear(d.getFullYear() + 1);
        const tEffet = document.getElementById('tit_date_effet');
        if(tEffet) tEffet.value = d.toISOString().split('T')[0];
    }

    const corpsId = corpsElt.value;
    if (corpsId) {
        fetch(`api/referentiel/get_grade_titularisation.php?corps_id=${corpsId}`)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const tGrade = document.getElementById('tit_grade');
                    const tIndice = document.getElementById('tit_indice');
                    if(tGrade) tGrade.value = data.grade_id;
                    if(tIndice) tIndice.value = data.indice;
                }
            });
    }
}

// --- 2. ATTACHEMENT DES ÉCOUTEURS COMPLÉMENTAIRES APRÈS CHARGEMENT DU DOM ---
document.addEventListener('DOMContentLoaded', function() {
    const corpsSelect = document.getElementById('corps_actuel');
    const gradeSelect = document.getElementById('grade_actuel');
    const avGradeSelect = document.getElementById('av_grade_int');

    if (corpsSelect && corpsSelect.value !== "") {
        chargerGrades();            
        chargerGradesIntegration(); 
        synchroniserSituationAdministrative();
    }

    if (gradeSelect) { gradeSelect.addEventListener('change', chargerIndice); }
    if (avGradeSelect) { avGradeSelect.addEventListener('change', function() { chargerIndiceSpecifique('av'); }); }

    const champsSource = ['date_acte_actuel', 'date_fin_actuel', 'date_cde_actuel'];
    champsSource.forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.addEventListener('input', synchroniserDatesAvenant); }
    });
});
window.handleStatutChange = handleStatutChange;
window.chargerGrades = chargerGrades;
window.chargerIndice = chargerIndice;
window.mettreAJourChampsAutomatiques = mettreAJourChampsAutomatiques;
window.chargerGradesIntegration = chargerGradesIntegration;
window.synchroniserSituationAdministrative = synchroniserSituationAdministrative;
window.chargerIndiceSpecifique = chargerIndiceSpecifique;
</script>
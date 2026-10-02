<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $im = $_SESSION['user_im'];

    // Récupération de la Situation Actuelle
    $stmtSit = $pdo->prepare("SELECT * FROM personnel_situation_actuelle WHERE im = ?");
    $stmtSit->execute([$im]);
    $sit = $stmtSit->fetch(PDO::FETCH_ASSOC) ?: [];

    // Récupération de la situation Contrat Indéterminé
    $stmtInd = $pdo->prepare("
        SELECT * FROM personnel_avancements 
        WHERE im = ? AND duree = 'indeterminee' 
        ORDER BY id DESC LIMIT 1
    ");
    $stmtInd->execute([$im]);
    $ind = $stmtInd->fetch(PDO::FETCH_ASSOC) ?: [];

    // Récupération de la dernière situation avant Intégration
    $stmtAvInt = $pdo->prepare("
        SELECT av_before.* 
        FROM personnel_avancements av_int
        JOIN personnel_avancements av_before 
        ON av_before.im = av_int.im 
        AND av_before.av_date_effet < av_int.av_date_effet
        WHERE av_int.im = ? 
        AND av_int.av_type_avancement = 'Intégration'
        ORDER BY av_before.av_date_effet DESC 
        LIMIT 1
    ");
    $stmtAvInt->execute([$im]);
    $avInt = $stmtAvInt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Listes pour les menus
    $statut = $pdo->query("SELECT * FROM statut ORDER BY nom_statut ASC")->fetchAll();
    $code_sanction = $pdo->query("SELECT * FROM code_sanction ORDER BY nom_code_sanction ASC")->fetchAll();
    $corps_complet = $pdo->query("SELECT * FROM ref_corps ORDER BY categorie ASC, libelle_corps ASC")->fetchAll();
?>

<link rel="stylesheet" href="assets/css/situation.css">

<div class="update-container">
    <div class="pageContent-inner-wrapper">
        <form id="formSituation" enctype="multipart/form-data">
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
                    <select name="statut_actuel" id="statut_actuel" required onchange="handleStatutChange(); chargerGrades(); chargerIndice(); checkIntegrationVisibility();">
                        <option value="">-- Choisir --</option>
                        <?php foreach($statut as $s): ?>
                            <option value="<?= htmlspecialchars($s['nom_statut']) ?>" <?= ($sit['statut_actuel'] ?? '') == $s['nom_statut'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nom_statut']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-group">
                    <label>Type d'avancement <span class="required-star">*</span></label>
                    <select name="type_avancement_actuel" id="type_avancement_actuel" required onchange="handleStatutChange(); verifierAffichageSituationAvantIntegration(); checkIntegrationVisibility();">
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
                            onchange="mettreAJourChampsAutomatiques(); chargerGrades(); chargerGradesIntegration(); checkIntegrationVisibility(); synchroniserSituationAdministrative();">
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
                    <input type="text" name="categorie_actuel" id="categorie_actuel" value="<?= htmlspecialchars($sit['categorie_actuel'] ?? '') ?>" readonly class="readonly-bg" onchange="checkIntegrationVisibility()">
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

            <!-- Bloc Situation du contrat indeterminée -->
            <div id="section_contrat_indetermine" style="display: none; margin-top: 30px;">
                <div class="form-section-title">
                    <i class="fas fa-file-contract mr-2"></i> Situation du contrat indeterminée :
                </div>
                
                <div class="form-grid-responsive">
                    <div class="field-group">
                        <label>N° de l'acte <span class="required-star">*</span></label>
                        <input type="text" name="num_acte_ind" id="num_acte_ind" 
                            value="<?= htmlspecialchars($ind['av_acte_no'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label>Date de l'acte <span class="required-star">*</span></label>
                        <input type="date" name="date_acte_ind" id="date_acte_ind" 
                            value="<?= htmlspecialchars($ind['av_acte_date'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label>Corps <span class="required-star">*</span></label>
                        <select name="corps_ind" id="corps_ind" onchange="chargerGradesIndetermine();">
                            <option value="">-- Choisir --</option>
                            <?php foreach($corps_complet as $c): ?>
                                <?php 
                                    $selectedCorpsInd = (trim($c['libelle_corps']) === trim($ind['av_corps'] ?? '')) ? 'selected' : '';
                                ?>
                                <option value="<?= $c['id'] ?>" 
                                        data-cat="<?= htmlspecialchars($c['categorie']) ?>"
                                        <?= $selectedCorpsInd ?>>
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
                        <!-- Valeur enregistrée pour pré-sélection JS -->
                        <input type="hidden" id="grade_ind_enregistre" value="<?= htmlspecialchars($ind['av_grade'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label>Indice <span class="required-star">*</span></label>
                        <input type="text" name="indice_ind" id="indice_ind" 
                            value="<?= htmlspecialchars($ind['av_indice'] ?? '') ?>" 
                            readonly class="readonly-bg">
                    </div>
                    <div class="field-group">
                        <label>Date d'effet <span class="required-star">*</span></label>
                        <input type="date" name="date_d_effet_ind" id="date_d_effet_ind" 
                            value="<?= htmlspecialchars($ind['av_date_effet'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Bloc Situation avant intégration -->
            <div id="section_situation_avant_integration" style="display: none; margin-top: 30px;">
                <div class="form-section-title">
                    <i class="fas fa-history mr-2"></i> Dernière situation avant intégration :
                </div>
                
                <div class="form-grid-responsive">
                    <div class="field-group">
                        <label>N° de l'acte <span class="required-star">*</span></label>
                        <input type="text" name="num_acte_av_int" id="num_acte_av_int" 
                            value="<?= htmlspecialchars($avInt['av_acte_no'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label>Date de l'acte <span class="required-star">*</span></label>
                        <input type="date" name="date_acte_av_int" id="date_acte_av_int" 
                            value="<?= htmlspecialchars($avInt['av_acte_date'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label>Corps <span class="required-star">*</span></label>
                        <select name="corps_av_int" id="corps_av_int" onchange="chargerGradesAvantIntegration();">
                            <option value="">-- Choisir --</option>
                            <?php foreach($corps_complet as$c): ?>
                                <?php 
                                    $selectedCorpsAvInt = (trim($c['libelle_corps']) === trim($avInt['av_corps'] ?? '')) ? 'selected' : '';
                                ?>
                                <option value="<?= $c['id'] ?>" 
                                        data-cat="<?= htmlspecialchars($c['categorie']) ?>"
                                        <?= $selectedCorpsAvInt ?>>
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
                        <!-- Champ caché servant à sélectionner automatiquement le grade en JS -->
                        <input type="hidden" id="grade_av_int_enregistre" value="<?= htmlspecialchars($avInt['av_grade'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label>Indice <span class="required-star">*</span></label>
                        <input type="text" name="indice_av_int" id="indice_av_int" 
                            value="<?= htmlspecialchars($avInt['av_indice'] ?? '') ?>" 
                            readonly class="readonly-bg">
                    </div>
                    <div class="field-group">
                        <label>Date d'effet <span class="required-star">*</span></label>
                        <input type="date" name="date_d_effet_av_int" id="date_d_effet_av_int" 
                            value="<?= htmlspecialchars($avInt['av_date_effet'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-grid-modern mt-6">
                <div class="field-group col-span-12">
                    <button type="button" onclick="updateSituation()" class="btn-update-submit">
                        <i class="fas fa-save mr-1"></i> Mettre à jour
                    </button>
                </div>
            </div>
        </form>
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

<div id="modalMettreAJour" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 border border-slate-100">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-sky-100 text-sky-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
                <i class="fas fa-question-circle text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800">Confirmation de mise à jour</h3>
            <p class="text-slate-500 mt-1">Voulez-vous vraiment enregistrer ces modifications ?</p>
        </div>
        
        <div class="flex justify-end gap-3 mt-8">
            <button type="button" onclick="closeUpdateModal()" class="px-5 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium rounded-lg transition-all">
                Annuler
            </button>
            <button type="button" onclick="submitUpdateForm()" class="px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg transition-all shadow">
                Confirmer
            </button>
        </div>
    </div>
</div>

<div id="successModal" class="modal-success-overlay">
    <div class="modal-success-box">
        <div class="modal-success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 class="modal-success-title">Enregistrement réussi !</h3>
        <p class="modal-success-text">Les informations de votre situation ont été mises à jour avec succès.</p>
        <button onclick="closeSuccessModal()" class="modal-success-btn">
            Fermer
        </button>
    </div>
</div>

<script>
function verifierAffichageContratIndetermine() {
    const statut = (document.getElementById('statut_actuel')?.value || '').trim();
    const typeActe = (document.getElementById('type_acte_actuel')?.value || '').trim();
    const blocInd = document.getElementById('section_contrat_indetermine');
    
    if (!blocInd) return;
    if (statut === "Contractuel EFA" && typeActe === "Avenant") {
        blocInd.style.display = "block";
        const corpsInd = document.getElementById('corps_ind');
        if (corpsInd && corpsInd.value) {
            chargerGradesIndetermine();
        }
    } else {
        blocInd.style.display = "none";
    }
}

function chargerGradesIndetermine() {
    const corpsSelect = document.getElementById('corps_ind');
    const corpsId = corpsSelect ? corpsSelect.value : '';
    const selectGrade = document.getElementById('grade_ind');
    const inputIndice = document.getElementById('indice_ind');
    const gradeEnregistre = (document.getElementById('grade_ind_enregistre')?.value || '').trim();
    
    if (inputIndice && !gradeEnregistre) inputIndice.value = "";
    if (!corpsId || !selectGrade) return;

    const selectedOption = corpsSelect.options[corpsSelect.selectedIndex];
    const cat = selectedOption.getAttribute('data-cat') || "";

    const normaliser = (str) => (str || '').toUpperCase().replace(/\s+/g, ' ').trim();

    fetch(`api/referentiel/get_grades_par_corps.php?corps_id=${corpsId}`)
    .then(response => response.json())
    .then(data => {
        selectGrade.innerHTML = '<option value="">-- Choisir le grade --</option>';
        let optionTrouvee = null;

        data.forEach(grade => {
            const lib = (grade.libelle_grade || '').toUpperCase();
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

                if (gradeEnregistre && normaliser(grade.libelle_grade) === normaliser(gradeEnregistre)) {
                    optionTrouvee = opt;
                }
            }
        });

        if (optionTrouvee) {
            optionTrouvee.selected = true;
            if (!document.getElementById('indice_ind').value) {
                chargerIndiceIndetermine();
            }
        }
    });
}

function chargerIndiceIndetermine() {
    const corpsId = document.getElementById('corps_ind')?.value;
    const gradeId = document.getElementById('grade_ind')?.value;
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

function verifierAffichageSituationAvantIntegration() {
    const typeAvancement = (document.getElementById('type_avancement_actuel')?.value || '').trim();
    const blocInt = document.getElementById('section_situation_avant_integration');
    
    if (!blocInt) return;

    // Affiche si le type d'avancement est Intégration
    if (typeAvancement === "Intégration") {
        blocInt.style.display = "block";
        const corpsAvInt = document.getElementById('corps_av_int');
        if (corpsAvInt && corpsAvInt.value) {
            chargerGradesAvantIntegration();
        }
    } else {
        blocInt.style.display = "none";
    }
}

function chargerGradesAvantIntegration() {
    const corpsSelect = document.getElementById('corps_av_int');
    const corpsId = corpsSelect ? corpsSelect.value : '';
    const selectGrade = document.getElementById('grade_av_int');
    const inputIndice = document.getElementById('indice_av_int');
    const gradeEnregistre = (document.getElementById('grade_av_int_enregistre')?.value || '').trim();
    
    if (inputIndice && !gradeEnregistre) inputIndice.value = "";
    if (!corpsId || !selectGrade) return;

    const selectedOption = corpsSelect.options[corpsSelect.selectedIndex];
    const cat = selectedOption.getAttribute('data-cat') || "";

    const normaliser = (str) => (str || '').toUpperCase().replace(/\s+/g, ' ').trim();

    fetch(`api/referentiel/get_grades_par_corps.php?corps_id=${corpsId}`)
    .then(response => response.json())
    .then(data => {
        selectGrade.innerHTML = '<option value="">-- Choisir le grade --</option>';
        let optionTrouvee = null;

        data.forEach(grade => {
            const lib = (grade.libelle_grade || '').toUpperCase();
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

                if (gradeEnregistre && normaliser(grade.libelle_grade) === normaliser(gradeEnregistre)) {
                    optionTrouvee = opt;
                }
            }
        });

        if (optionTrouvee) {
            optionTrouvee.selected = true;
            if (!document.getElementById('indice_av_int').value) {
                chargerIndiceAvantIntegration();
            }
        }
    })
    .catch(err => console.error("Erreur lors du chargement des grades avant intégration :", err));
}

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
    const corpsId = corpsSelect ? corpsSelect.value : '';
    const corpsLibelle = corpsSelect && corpsSelect.selectedIndex >= 0 
        ? (corpsSelect.options[corpsSelect.selectedIndex].text || "") 
        : "";
    const statut = (document.getElementById('statut_actuel')?.value || '').trim();
    const typeAvancement = (document.getElementById('type_avancement_actuel')?.value || '').trim();
    const selectGrade = document.getElementById('grade_actuel');
    const gradeEnregistre = (document.getElementById('grade_enregistre')?.value || '').trim();

    if (!corpsId || !selectGrade) return;

    // Normalisation pour la comparaison (supprime espaces multiples + majuscules)
    const normaliser = (str) => (str || '')
        .toUpperCase()
        .replace(/\s+/g, ' ')
        .replace(/°/g, '°')
        .trim();

    fetch(`api/referentiel/get_grades_par_corps.php?corps_id=${corpsId}`)
        .then(response => response.json())
        .then(data => {
            console.log("Réponse API grades pour corps_id=" + corpsId + " :", data);
            selectGrade.innerHTML = '<option value="">-- Choisir le grade --</option>';

            let optionTrouvee = null;

            data.forEach(grade => {
                const lib = (grade.libelle_grade || '').toUpperCase();
                let afficher = false;

                const isCatII = corpsLibelle.includes('INSTITUTEURS ET INSTITUTRICES "C"') 
                             || corpsLibelle.includes('OPERATEURS');
                const isCatIII = corpsLibelle.includes('INSTITUTEURS ET INSTITUTRICES "B"') 
                              || corpsLibelle.includes('ENCADREURS');

                if (isCatII) {
                    if (statut === "Contractuel EFA") {
                        if (lib.includes("ECHELLE III")) afficher = true;
                    } else {
                        if (!lib.includes("ECHELLE III")) afficher = true;
                    }
                } else if (isCatIII) {
                    if (statut === "Contractuel EFA") {
                        if (lib.includes("ECHELLE IV")) afficher = true;
                    } else {
                        if (!lib.includes("ECHELLE IV")) afficher = true;
                    }
                } else {
                    afficher = true;
                }

                if (afficher) {
                    const opt = document.createElement('option');
                    opt.value = grade.id;
                    opt.textContent = grade.libelle_grade;
                    selectGrade.appendChild(opt);

                    // Vérifie si c’est le grade enregistré
                    if (gradeEnregistre && normaliser(grade.libelle_grade) === normaliser(gradeEnregistre)) {
                        optionTrouvee = opt;
                    }
                }
            });

            // 1. Priorité au grade enregistré
            if (optionTrouvee) {
                optionTrouvee.selected = true;
            }
            // 2. Sinon cas particulier Titularisation
            else if (statut === 'Fonctionnaire' && typeAvancement === 'Titularisation') {
                for (let i = 0; i < selectGrade.options.length; i++) {
                    if (normaliser(selectGrade.options[i].text) === normaliser("2°CLASSE/1°ECHELON")) {
                        selectGrade.selectedIndex = i;
                        break;
                    }
                }
            }

            // Charger l’indice
            chargerIndice();
        })
        .catch(err => {
            console.error("Erreur chargement grades :", err);
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
            inputCat.dispatchEvent(new Event('change'));
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
        Array.from(typeAvancementElt.options).forEach(opt => {
            const val = opt.value;
            opt.style.display = (val === "" || val === "Stagiaire" || val === "Classe" || val === "Echelon" || val === "Intégration") ? 'block' : 'none';
        });
        Array.from(typeActeElt.options).forEach(opt => {
            const val = opt.value;
            opt.style.display = (val === "" || val === "Contrat" || val === "Avenant") ? 'block' : 'none';
        });
        if (typeActeElt.value !== "Contrat" && typeActeElt.value !== "Avenant") {
            typeActeElt.value = "Contrat";
        }
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

function checkIntegrationVisibility() {
    const statut = document.getElementById('statut_actuel').value;
    const catElt = document.getElementById('categorie_actuel');
    const typeAvancement = document.getElementById('type_avancement_actuel').value;
    
    const corpsSelect = document.getElementById('corps_actuel');
    const nomCorpsActuel = corpsSelect.options[corpsSelect.selectedIndex] ? corpsSelect.options[corpsSelect.selectedIndex].text : '';

    const sectionAvant = document.getElementById('section_situation_avant_integration');
    const modal = document.getElementById('modalChoixRecrutement');

    if (sectionAvant) sectionAvant.style.display = 'none';
    if (modal) modal.classList.add('hidden');

    if(!catElt) return;
    const categorie = catElt.value;

    if (statut === 'Fonctionnaire' && typeAvancement === 'Intégration') {
        if (sectionAvant) {
            sectionAvant.style.display = 'block';
            const avCorps = document.getElementById('av_corps');
            if (avCorps) avCorps.value = nomCorpsActuel;
            chargerGradesAvantIntegration();
        }
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

function updateSituation() {
    const form = document.getElementById('formSituation');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

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

    const formData = new FormData(form);
    formData.append('step_index', '2');

    fetch('actions/personnel/save_step.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();

        if (data.success) {
            showSuccessModal();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour : ' + (data.message || 'Erreur inconnue')
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur réseau',
            text: 'Une erreur réseau est survenue.'
        });
    });
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

window.verifierAffichageContratIndetermine = verifierAffichageContratIndetermine;
window.chargerGradesIndetermine = chargerGradesIndetermine;
window.chargerIndiceIndetermine = chargerIndiceIndetermine;
window.verifierAffichageSituationAvantIntegration = verifierAffichageSituationAvantIntegration;
window.chargerGradesAvantIntegration = chargerGradesAvantIntegration;
window.chargerIndiceAvantIntegration = chargerIndiceAvantIntegration;
window.mettreAJourChampsAutomatiques = mettreAJourChampsAutomatiques;
window.handleStatutChange = handleStatutChange;
window.chargerGrades = chargerGrades;
window.chargerIndice = chargerIndice;
window.checkIntegrationVisibility = checkIntegrationVisibility;
window.chargerGradesIntegration = chargerGradesIntegration;
window.synchroniserSituationAdministrative = synchroniserSituationAdministrative;
window.updateSituation = updateSituation; 
window.closeSuccessModal = closeSuccessModal;
window.showSuccessModal = showSuccessModal;

// ============================================
// INITIALISATION (compatible SPA)
// ============================================
function initialiserSituationAdmin() {
    const corpsSelect = document.getElementById('corps_actuel');
    const statutElt = document.getElementById('statut_actuel');
    const typeActeElt = document.getElementById('type_acte_actuel');
    const typeAvancementElt = document.getElementById('type_avancement_actuel');

    if (statutElt) {
        statutElt.addEventListener('change', verifierAffichageContratIndetermine);
        statutElt.addEventListener('change', handleStatutChange);
    }
    if (typeActeElt) {
        typeActeElt.addEventListener('change', verifierAffichageContratIndetermine);
    }
    if (typeAvancementElt) {
        typeAvancementElt.addEventListener('change', verifierAffichageSituationAvantIntegration);
        typeAvancementElt.addEventListener('change', handleStatutChange);
    }

    if (corpsSelect) {
        corpsSelect.addEventListener('change', function() {
            mettreAJourChampsAutomatiques();
            chargerGrades();
            chargerGradesIntegration();
            checkIntegrationVisibility();
            synchroniserSituationAdministrative();
        });
    }

    if (document.getElementById('grade_actuel')) {
        document.getElementById('grade_actuel').addEventListener('change', chargerIndice);
    }

    // Appels initiaux
    if (corpsSelect && corpsSelect.value) {
        chargerGrades();
        chargerGradesIntegration();
        checkIntegrationVisibility();
        synchroniserSituationAdministrative();
    }

    verifierAffichageContratIndetermine();
    verifierAffichageSituationAvantIntegration();
    handleStatutChange();

    const corpsInd = document.getElementById('corps_ind');
    if (corpsInd && corpsInd.value) {
        chargerGradesIndetermine();
    }

    const corpsAvInt = document.getElementById('corps_av_int');
    if (corpsAvInt && corpsAvInt.value) {
        chargerGradesAvantIntegration();
    }
}

// Exécution immédiate (compatible avec le chargement SPA)
if (document.getElementById('formSituation')) {
    initialiserSituationAdmin();
}
</script>
<?php 
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $im = $_SESSION['user_im'];

    // 3. Récupérer les diplômes
    $acad = $pdo->prepare("SELECT * FROM personnel_diplomes WHERE im = ? AND type_diplome = 'acad' LIMIT 3");
    $acad->execute([$im]);
    $diplomes_acad = $acad->fetchAll(PDO::FETCH_ASSOC);

    $pedag = $pdo->prepare("SELECT * FROM personnel_diplomes WHERE im = ? AND type_diplome = 'pedag' LIMIT 3");
    $pedag->execute([$im]);
    $diplomes_pedag = $pedag->fetchAll(PDO::FETCH_ASSOC);

    // 4. Récupérer les compétences
    $comp = $pdo->prepare("SELECT * FROM personnel_competences WHERE im = ?");
    $comp->execute([$im]);
    $pers_comp = $comp->fetch(PDO::FETCH_ASSOC);
?>

<style>
    /* --- DESIGN HARMONISÉ --- */
    :root {
        --primary: #0284c7;
        --primary-hover: #0369a1;
        --danger: #ef4444;
        --bg-light: #f8fafc;
        --border-color: #e2e8f0;
    }

    .diplome-container { padding: 5px 20px 20px 20px; background: white; }
    
    /* Style pour aligner titre à gauche et bouton à droite */
    .form-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #7dd3fc;
        padding-bottom: 8px;
        margin-top: 25px;
        margin-bottom: 20px;
    }

    /* On enlève la bordure du titre h3 car elle est gérée par le parent header */
    .form-section-header h3 {
        font-size: 0.9rem;
        font-weight: 700;
        color: #0369a1;
        text-transform: uppercase;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .pretty-table {
        width: 100%; border-collapse: collapse; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color);
        margin-bottom: 10px;
    }

    .pretty-table th {
        background-color: var(--bg-light); color: #475569; font-size: 0.75rem;
        text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 15px; text-align: left;
    }

    .pretty-table td { padding: 10px; border-bottom: 1px solid var(--border-color); background: #fff; }

    .pretty-table input {
        width: 100%; padding: 8px 10px; border: 1px solid var(--border-color); border-radius: 6px;
        font-size: 0.85rem; transition: all 0.2s; outline: none;
    }

    .pretty-table input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1); }

    /* Nouveau style pour le bouton ajouter réduit pour tenir à droite */
    .btn-add-inline {
        background-color: #f0f9ff;
        color: #0369a1;
        border: 1px dashed #7dd3fc;
        padding: 5px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 700;
        font-size: 0.8rem;
        display: inline-flex; align-items: center; gap: 6px;
        transition: all 0.2s;
    }
    .btn-add-inline:hover { background-color: #e0f2fe; border-style: solid; }

    .btn-remove {
        color: var(--danger); background: #fef2f2; border: 1px solid #fee2e2;
        padding: 6px; border-radius: 6px; cursor: pointer; transition: all 0.2s;
    }
    .btn-remove:hover { background: var(--danger); color: white; }

    .knowledge-section { margin-top: 10px; padding: 10px 0; }
    .checkbox-group { display: flex; flex-wrap: wrap; gap: 25px; margin-bottom: 25px; margin-top: 15px; padding-left: 10px;}
    .checkbox-item { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #475569; cursor: pointer; }
    .langue-row { display: flex; align-items: center; margin-bottom: 15px; gap: 20px; padding-left: 10px;}
    .langue-label { min-width: 80px; font-weight: 600; font-size: 0.9rem; }

    /* Modale */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 2000;
    }
    .modal-content { background: white; padding: 30px; border-radius: 12px; text-align: center; max-width: 400px; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.2); }
    .btn-close-modal { background: #1e293b; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-top: 15px; }
    .pageContent-inner-wrapper {
        padding: 0.5mm;
        box-sizing: border-box;
    }
</style>
<div style="background-color: white; padding: 10px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
<div class="pageContent-inner-wrapper">
    <form id="formDiplome" enctype="multipart/form-data">
        <div class="form-section-header" style="margin-top: 0;">
            <h3><i class="fas fa-graduation-cap"></i> Diplômes Académiques (Les trois (03) plus élevés)</h3>
            <button type="button" class="btn-add-inline" onclick="addRowDiplome('table_acad', 'acad')">
                <i class="fas fa-plus-circle"></i> Ajouter
            </button>
        </div>

        <table class="pretty-table" id="table_acad">
            <thead>
                <tr>
                    <th style="width: 45%;">Nature du Diplôme</th>
                    <th style="width: 35%;">Spécialité</th>
                    <th style="width: 15%;">Année d'obtention</th>
                    <th style="width: 50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($diplomes_acad)): ?>
                    <?php foreach ($diplomes_acad as $da): ?>
                        <tr>
                            <td><input type="text" name="acad_diplome[]" value="<?= htmlspecialchars($da['libelle'] ?? '') ?>" required></td>
                            <td><input type="text" name="acad_specialite[]" value="<?= htmlspecialchars($da['specialite'] ?? '') ?>" required></td>
                            <td><input type="number" name="acad_annee[]" value="<?= htmlspecialchars($da['annee'] ?? '') ?>" min="1960" max="2026" required></td>
                            <td><button type="button" class="btn-remove" onclick="this.closest('tr').remove()"><i class="fas fa-trash"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <script>window.addEventListener('load', () => addRowDiplome('table_acad', 'acad'));</script>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="form-section-header">
            <h3><i class="fas fa-chalkboard-teacher"></i> Diplômes Pédagogiques (Les trois (03) plus élevés)</h3>
            <button type="button" class="btn-add-inline" onclick="addRowDiplome('table_pedag', 'pedag')">
                <i class="fas fa-plus-circle"></i> Ajouter
            </button>
        </div>

        <table class="pretty-table" id="table_pedag">
            <thead>
                <tr>
                    <th style="width: 45%;">Nature du Diplôme</th>
                    <th style="width: 35%;">Spécialité</th>
                    <th style="width: 15%;">Année d'obtention</th>
                    <th style="width: 50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($diplomes_pedag)): ?>
                    <?php foreach ($diplomes_pedag as $dp): ?>
                        <tr>
                            <td><input type="text" name="pedag_diplome[]" value="<?= htmlspecialchars($dp['libelle'] ?? '') ?>" required></td>
                            <td><input type="text" name="pedag_specialite[]" value="<?= htmlspecialchars($dp['specialite'] ?? '') ?>" required></td>
                            <td><input type="number" name="pedag_annee[]" value="<?= htmlspecialchars($dp['annee'] ?? '') ?>" min="1960" max="2026" required></td>
                            <td><button type="button" class="btn-remove" onclick="this.closest('tr').remove()"><i class="fas fa-trash"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <script>window.addEventListener('load', () => addRowDiplome('table_pedag', 'pedag'));</script>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="form-section-header">
            <h3><i class="fas fa-laptop-code"></i> Connaissances en informatique</h3>
        </div>
        <div class="knowledge-section">
            <div class="checkbox-group">
                <label class="checkbox-item"><input type="checkbox" name="info_bureautique" value="1" <?= ($pers_comp && $pers_comp['info_bureautique'] == 1) ? 'checked' : '' ?>> Bureautique</label>
                <label class="checkbox-item"><input type="checkbox" name="info_programmation" value="1" <?= ($pers_comp && $pers_comp['info_programmation'] == 1) ? 'checked' : '' ?>> Programmation</label>
                <label class="checkbox-item"><input type="checkbox" name="info_reseau" value="1" <?= ($pers_comp && $pers_comp['info_reseau'] == 1) ? 'checked' : '' ?>> Réseau</label>
                <label class="checkbox-item">
                    <input type="checkbox" name="info_autres_check" id="info_autres_check" onchange="toggleAutresInfo()" <?= (!empty($pers_comp['info_autres'])) ? 'checked' : ''; ?>> Autres
                </label>
            </div>
            <div id="bloc_autres_info" style="display: none; margin-top: -10px; margin-bottom: 20px; padding-left: 10px;">
                <input type="text" name="info_autres_precision" value="<?= htmlspecialchars($pers_comp['info_autres'] ?? '') ?>" placeholder="Précisez vos autres connaissances..." style="width:100%; padding:8px; border:1px solid #e2e8f0; border-radius:6px;">
            </div>
        </div>
        <div class="form-section-header">
            <h3><i class="fas fa-star"></i> Aptitudes spéciales</h3>
        </div>
        <div class="knowledge-section" style="padding-left: 10px;">
            <input type="text" name="aptitudes_speciales" value="<?= htmlspecialchars($pers_comp['aptitudes_speciales'] ?? '') ?>" placeholder="Ex: Permis de conduire de catégorie A et B, Secourisme, Maintenance d'équipements industriels..." style="width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:6px; font-size:0.9rem; outline: none;">
        </div>

        <div class="form-section-header">
            <h3><i class="fas fa-language"></i> Connaissances linguistiques</h3>
        </div>
        <div class="knowledge-section">
            <div class="langue-row">
                <div class="langue-label">Français :</div>
                <label class="checkbox-item"><input type="radio" name="langue_fr" value="Mauvais" <?= ($pers_comp && $pers_comp['langue_fr'] == 'Mauvais') ? 'checked' : '' ?>> Mauvais</label>
                <label class="checkbox-item"><input type="radio" name="langue_fr" value="Bon" <?= ($pers_comp && $pers_comp['langue_fr'] == 'Bon') ? 'checked' : '' ?>> Bon</label>
                <label class="checkbox-item"><input type="radio" name="langue_fr" value="Excellent" <?= ($pers_comp && $pers_comp['langue_fr'] == 'Excellent') ? 'checked' : '' ?>> Excellent</label>
            </div>
            <div class="langue-row">
                <div class="langue-label">Anglais :</div>
                <label class="checkbox-item"><input type="radio" name="langue_en" value="Mauvais" <?= ($pers_comp && $pers_comp['langue_en'] == 'Mauvais') ? 'checked' : '' ?>> Mauvais</label>
                <label class="checkbox-item"><input type="radio" name="langue_en" value="Bon" <?= ($pers_comp && $pers_comp['langue_en'] == 'Bon') ? 'checked' : '' ?>> Bon</label>
                <label class="checkbox-item"><input type="radio" name="langue_en" value="Excellent" <?= ($pers_comp && $pers_comp['langue_en'] == 'Excellent') ? 'checked' : '' ?>> Excellent</label>
            </div>
            <div class="langue-row">
                <div class="langue-label">Autres :</div>
                <input type="text" name="langue_autres" value="<?= htmlspecialchars($pers_comp['langue_autres'] ?? '') ?>" placeholder="Ex: Espagnol, Allemand..." style="border: none; border-bottom: 2px dotted #cbd5e1; background: transparent; width: 300px; padding: 5px; outline: none;">
            </div>
        </div>
        <div class="form-grid-modern mt-6">
            <div class="field-group col-span-12">
                <button type="button" onclick="updateDiplomes()" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200" style="background-color: #0284c7; border: none; border-radius: 6px; padding: 12px 20px; color: white; cursor: pointer;">
                    <i class="fas fa-save mr-1"></i> Mettre à jour
                </button>
            </div>
        </div>
    </form>
</div>
</div>

<div id="limitModal" class="modal-overlay">
    <div class="modal-content">
        <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--danger); margin-bottom: 15px;"></i>
        <h2 style="color: #1e293b; margin-top: 0;">Limite atteinte</h2>
        <p style="color: #64748b;">Le système ne permet de saisir que les <b>3 diplômes</b> les plus élevés pour chaque catégorie.</p>
        <button class="btn-close-modal" onclick="closeLimitModal()">J'ai compris</button>
    </div>
</div>

<div id="successModal" style="display:none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 16px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="color: #0284c7; font-size: 3.5rem; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 style="color: #0f172a; font-size: 1.3rem; font-weight: 800; margin-bottom: 8px;">Enregistrement réussi !</h3>
        <p style="color: #64748b; font-size: 0.95rem; line-height: 1.4; margin-bottom: 25px;">Les informations concernant votre diplomes ont été mises à jour avec succès.</p>
        <button onclick="closeSuccessModal()" style="background: #0284c7; color: white; border: none; padding: 12px 0; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.3);">
            Fermer
        </button>
    </div>
</div>

<script>
function addRowDiplome(tableId, prefix) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    const rowCount = tbody.querySelectorAll('tr').length;

    if (rowCount < 3) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="${prefix}_diplome[]" placeholder="Ex: Master, Doctorat..." required></td>
            <td><input type="text" name="${prefix}_specialite[]" placeholder="Ex: Gestion, Droit..." required></td>
            <td><input type="number" name="${prefix}_annee[]" placeholder="AAAA" min="1960" max="2026" required></td>
            <td><button type="button" class="btn-remove" onclick="this.closest('tr').remove()"><i class="fas fa-trash"></i></button></td>
        `;
        tbody.appendChild(tr);
    } else {
        document.getElementById('limitModal').style.display = 'flex';
    }
}

function closeLimitModal() {
    document.getElementById('limitModal').style.display = 'none';
}

function toggleAutresInfo() {
    const checkbox = document.getElementById('info_autres_check');
    const blocAutres = document.getElementById('bloc_autres_info');
    blocAutres.style.display = checkbox.checked ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', toggleAutresInfo);

function updateDiplomes() {
    const form = document.getElementById('formDiplome');
    const formData = new FormData(form);
    
    formData.append('step_index', '1');

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
// RENDRE LES FONCTIONS ACCESSIBLES AU HTML (AJOUTER CE BLOC)
window.updateDiplomes = updateDiplomes;
window.showSuccessModal = showSuccessModal;
window.toggleAutresInfo = toggleAutresInfo;
window.closeLimitModal = closeLimitModal;
window.closeSuccessModal = closeSuccessModal;

// Liez les fonctions de gestion des lignes du tableau :
window.addRowDiplome = addRowDiplome;
if (typeof removeRowDiplome !== 'undefined') { window.removeRowDiplome = removeRowDiplome; }
</script>
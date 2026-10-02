<?php
    require_once __DIR__ . '/../../includes/config.php'; 
    require_once __DIR__ . '/../../includes/check_session.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $im = $_GET['im'] ?? $_SESSION['user_im'] ?? '';

    $distinctions = [];
    if ($im) {
        try {
            $stmt =$pdo->prepare("SELECT * FROM personnel_distinctions WHERE im = ? ORDER BY date_acte DESC");
            $stmt->execute([$im]);
            $distinctions =$stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Erreur : " . $e->getMessage());
        }
    }
?>

<!-- Inclusion des styles externes identiques à l'historique des congés -->
<link rel="stylesheet" href="assets/css/historique_conge.css">

<div class="conge-container">
    <div class="form-section-header">
        <h3><i class="fas fa-medal"></i> Distinctions Honorifiques</h3>
        <button type="button" class="btn-add-inline" onclick="window.openModal('addDistModal')">
            <i class="fas fa-plus-circle"></i> Ajouter une distinction
        </button>
    </div>
    
    <table class="pretty-table">
        <thead>
            <tr>
                <th>Type de Grade</th>
                <th>Nature de l'acte</th>
                <th>Nom du Grade</th>
                <th>N° de l'acte</th>
                <th>Date de l'acte</th>
                <th style="text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($distinctions)): ?>
            <tr>
                <td colspan="6" style="text-align: center; color: #94a3b8; padding: 24px;">Aucune distinction enregistrée.</td>
            </tr>
            <?php else: ?>
                <?php foreach ($distinctions as$d): ?>
                <tr>
                    <!-- Type de Grade avec Badge -->
                    <td>
                        <span style="font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem;">
                            <?= htmlspecialchars($d['type_grade']) ?>
                        </span>
                    </td>
                    
                    <!-- Nature de l'acte -->
                    <td style="font-weight: 600; color: #475569;">
                        <?= htmlspecialchars($d['nature_acte']) ?>
                    </td>

                    <!-- Nom du Grade -->
                    <td style="font-weight: 700; color: #1e293b;">
                        <?= htmlspecialchars($d['nom_grade']) ?>
                    </td>

                    <!-- N° de l'acte -->
                    <td style="font-weight: 600;">
                        <?php if (!empty($d['num_acte'])): ?>
                            <?= htmlspecialchars($d['num_acte']) ?>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-style: italic; font-size: 0.85rem;">Non renseigné</span>
                        <?php endif; ?>
                    </td>

                    <!-- Date de l'acte -->
                    <td>
                        <?php if (!empty($d['date_acte'])): ?>
                            <?= date('d/m/Y', strtotime($d['date_acte'])) ?>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-style: italic; font-size: 0.85rem;">Non renseignée</span>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Actions -->
                    <td style="text-align: center; display: flex; gap: 6px; justify-content: center;">
                        <!-- Bouton Éditer -->
                        <button type="button" 
                                onclick="window.openEditModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>)" 
                                title="Modifier la distinction"
                                style="color:#0284c7; background:#e0f2fe; border:1px solid #bae6fd; padding:7px 10px; border-radius:8px; cursor:pointer; transition: all 0.2s;">
                            <i class="fas fa-pen"></i>
                        </button>

                        <!-- Bouton Supprimer -->
                        <button type="button" 
                                onclick="window.confirmDelete(<?= $d['id'] ?>)" 
                                title="Supprimer"
                                style="color:#ef4444; background:#fef2f2; border:1px solid #fee2e2; padding:7px 10px; border-radius:8px; cursor:pointer; transition: all 0.2s;">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- MODALE D'AJOUT -->
<div id="addDistModal" class="mat-dialog-overlay">
    <div class="mat-dialog">
        <div class="modal-header-styled">
            <h4><i class="fas fa-award"></i> Nouvelle Distinction</h4>
            <i class="fas fa-times" style="cursor:pointer; color: #94a3b8; font-size: 1rem;" onclick="window.closeModal('addDistModal')"></i>
        </div>
        <form id="formAddDist" style="padding: 20px;">
            <input type="hidden" name="im" value="<?= htmlspecialchars($im) ?>">

            <div class="mat-form-field">
                <label>Type de Grade</label>
                <select name="type_grade" class="mat-input" required>
                    <option value="Ordre National">Ordre National</option>
                    <option value="Ordre de Mérite">Ordre de Mérite</option>
                    <option value="Brevet d'honneur">Brevet d'honneur</option>
                </select>
            </div>

            <div class="mat-form-field">
                <label>Nature de l'acte</label>
                <select name="nature_acte" class="mat-input" required>
                    <option value="Décret">Décret</option>
                    <option value="Arrêté">Arrêté</option>
                    <option value="Décision">Décision</option>
                </select>
            </div>

            <div class="mat-form-field">
                <label>Nom du Grade</label>
                <select name="nom_grade" class="mat-input" required>
                    <option value="Chevalier">Chevalier</option>
                    <option value="Officier">Officier</option>
                    <option value="Commandeur">Commandeur</option>
                    <option value="Grand-officier">Grand-officier</option>
                    <option value="Grand Croix de 2ème Classe">Grand Croix de 2ème Classe</option>
                </select>
            </div>

            <div class="mat-form-field">
                <label>N° de l'acte</label>
                <input type="text" name="num_acte" class="mat-input" placeholder="Ex: 1234/2024" required>
            </div>

            <div class="mat-form-field">
                <label>Date de l'acte</label>
                <input type="date" name="date_acte" class="mat-input" required>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end;">
                <button type="button" class="btn-action-cancel" onclick="window.closeModal('addDistModal')">Annuler</button>
                <button type="submit" class="btn-action-submit"><i class="fas fa-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODALE D'ÉDITION -->
<div id="editDistModal" class="mat-dialog-overlay">
    <div class="mat-dialog">
        <div class="modal-header-styled">
            <h4><i class="fas fa-edit"></i> Modifier la Distinction</h4>
            <i class="fas fa-times" style="cursor:pointer; color: #94a3b8; font-size: 1rem;" onclick="window.closeModal('editDistModal')"></i>
        </div>
        <form id="formEditDist" method="POST" style="padding: 20px;">
            <!-- Plus besoin de l'ID caché, la recherche se fait sur im + type_grade + nom_grade -->
            <input type="hidden" name="im" value="<?= htmlspecialchars($im) ?>">
            
            <div class="mat-form-field">
                <label>Type de Grade</label>
                <select name="type_grade" id="edit_type_grade" class="mat-input" required>
                    <option value="Ordre National">Ordre National</option>
                    <option value="Ordre de Mérite">Ordre de Mérite</option>
                    <option value="Brevet d'honneur">Brevet d'honneur</option>
                </select>
            </div>

            <div class="mat-form-field">
                <label>Nom du Grade</label>
                <select name="nom_grade" id="edit_nom_grade" class="mat-input" required>
                    <option value="Chevalier">Chevalier</option>
                    <option value="Officier">Officier</option>
                    <option value="Commandeur">Commandeur</option>
                    <option value="Grand-officier">Grand-officier</option>
                    <option value="Grand Croix de 2ème Classe">Grand Croix de 2ème Classe</option>
                </select>
            </div>

            <div class="mat-form-field">
                <label>Nature de l'acte</label>
                <select name="nature_acte" id="edit_nature_acte" class="mat-input" required>
                    <option value="Décret">Décret</option>
                    <option value="Arrêté">Arrêté</option>
                    <option value="Décision">Décision</option>
                </select>
            </div>

            <div class="mat-form-field">
                <label>N° de l'acte</label>
                <input type="text" name="num_acte" id="edit_num_acte" class="mat-input" required>
            </div>

            <div class="mat-form-field">
                <label>Date de l'acte</label>
                <input type="date" name="date_acte" id="edit_date_acte" class="mat-input" required>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end;">
                <button type="button" class="btn-action-cancel" onclick="window.closeModal('editDistModal')">Annuler</button>
                <button type="submit" id="btnSubmitEdit" class="btn-action-submit"><i class="fas fa-save"></i> Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<!-- MODALE DE SUCCÈS -->
<div id="successAddModal" class="mat-dialog-overlay" style="z-index: 2100;">
    <div class="mat-dialog" style="width: 360px; text-align: center; padding: 28px;">
        <div class="modal-confirm-icon icon-success"><i class="fas fa-check-circle"></i></div>
        <h3 style="margin: 0; color: #15803d; text-transform: uppercase; font-size: 0.95rem; font-weight: 800;">Enregistré !</h3>
        <p style="color: #64748b; font-size: 0.85rem; margin: 10px 0 20px;">La distinction a été enregistrée avec succès.</p>
        <button type="button" onclick="window.reloadHistoriqueView()" class="btn-action-submit" style="width:100%; background: #16a34a;">D'accord</button>
    </div>
</div>

<!-- MODALE DE SUPPRESSION -->
<div id="deleteConfirmModal" class="mat-dialog-overlay">
    <div class="mat-dialog" style="width: 360px; text-align: center; padding: 28px;">
        <div class="modal-confirm-icon icon-delete"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 style="margin: 0; color: #991b1b; text-transform: uppercase; font-size: 0.95rem; font-weight: 800;">Supprimer la distinction ?</h3>
        <p style="color: #64748b; font-size: 0.85rem; margin: 10px 0 20px;">Cette action est irréversible. Voulez-vous continuer ?</p>
        <input type="hidden" id="idToDelete">
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn-action-cancel" onclick="window.closeModal('deleteConfirmModal')" style="flex:1;">Annuler</button>
            <button type="button" onclick="window.executeDelete()" class="btn-action-danger" style="flex:1;">Supprimer</button>
        </div>
    </div>
</div>

<script>
    window.openModal = function (id) { 
        const el = document.getElementById(id);
        if (el) el.style.display = 'flex'; 
    };

    window.closeModal = function (id) { 
        const el = document.getElementById(id);
        if (el) el.style.display = 'none'; 
    };

    window.openEditModal = function (distinction) {
        if (typeof distinction === 'string') {
            try { 
                distinction = JSON.parse(distinction); 
            } catch (e) { 
                console.error('Erreur JSON:', e); 
            }
        }

        const elId = document.getElementById('edit_id');
        const elType = document.getElementById('edit_type_grade');
        const elNature = document.getElementById('edit_nature_acte');
        const elNom = document.getElementById('edit_nom_grade');
        const elNum = document.getElementById('edit_num_acte');
        const elDate = document.getElementById('edit_date_acte');

        if (elId) elId.value = distinction.id || '';
        if (elType) elType.value = distinction.type_grade || 'Ordre National';
        if (elNature) elNature.value = distinction.nature_acte || 'Décret';
        if (elNom) elNom.value = distinction.nom_grade || 'Chevalier';
        if (elNum) elNum.value = distinction.num_acte || '';
        if (elDate) elDate.value = distinction.date_acte || '';

        window.openModal('editDistModal');
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
            window.location.href = `index.php?page=historique_distinction&im=${encodeURIComponent(im)}`;
        } else {
            window.location.reload();
        }
    };

    window.executeDelete = async function() {
        const idInput = document.getElementById('idToDelete');
        if (!idInput) return;
        
        const id = idInput.value;
        try {
            const res = await fetch('actions/distinctions/delete_distinction.php', {
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

    async function submitDistForm(url, formData, modalToClose) {
        try {
            const res = await fetch(url, {
                method: 'POST',
                body: formData
            });

            const text = await res.text();
            let data;

            try {
                data = JSON.parse(text);
            } catch (e) {
                alert('Réponse PHP invalide :\n\n' + text);
                return;
            }

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

    document.addEventListener('submit', function (e) {
        if (!e.target) return;

        if (e.target.id === 'formAddDist') {
            e.preventDefault();
            e.stopPropagation();
            submitDistForm('actions/distinctions/save_distinction.php', new FormData(e.target), 'addDistModal');
        }

        if (e.target.id === 'formEditDist') {
            e.preventDefault();
            e.stopPropagation();
            submitDistForm('actions/distinctions/update_distinction.php', new FormData(e.target), 'editDistModal');
        }
    });
</script>
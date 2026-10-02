<?php
    require_once __DIR__ . '/../../includes/config.php'; 
    require_once __DIR__ . '/../../includes/check_session.php';

    $im = $_GET['im'] ?? $_SESSION['user_im'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT * FROM personnel_conges WHERE im = ? ORDER BY annee DESC");
        $stmt->execute([$im]);
        $conges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
?>

<!-- Inclusion des assets externes -->
<link rel="stylesheet" href="assets/css/historique_conge.css">
<div class="conge-container">
    <div class="form-section-header">
        <h3><i class="fas fa-history"></i> Historique des Décisions de Congés</h3>
        <button type="button" class="btn-add-inline" onclick="window.openModal('addCongeModal')">
            <i class="fas fa-plus-circle"></i> Ajouter une décision
        </button>
    </div>
    
    <table class="pretty-table">
        <thead>
            <tr>
                <th>Année</th>
                <th>N° Décision</th>
                <th>Date Décision</th>
                <th style="text-align: center;">Total Jours</th>
                <th style="text-align: center;">Jours Pris</th>
                <th style="text-align: center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($conges)): ?>
            <tr>
                <td colspan="6" style="text-align: center; color: #94a3b8; padding: 24px;">Aucun historique enregistré.</td>
            </tr>
            <?php else: ?>
                <?php foreach ($conges as $c): ?>
                <tr>
                    <td style="font-weight: 700; color: #0369a1;"><?= htmlspecialchars($c['annee']) ?></td>
                    
                    <!-- Numéro de décision -->
                    <td style="font-weight: 600;">
                        <?php if (!empty($c['num_decision'])): ?>
                            <?= htmlspecialchars($c['num_decision']) ?>
                        <?php else: ?>
                            <span style="color: #d97706; background: #fef3c7; padding: 3px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700;">
                                <i class="fas fa-clock"></i> En attente
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Date de décision -->
                    <td>
                        <?php if (!empty($c['date_decision'])): ?>
                            <?= date('d/m/Y', strtotime($c['date_decision'])) ?>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-style: italic; font-size: 0.85rem;">Non renseignée</span>
                        <?php endif; ?>
                    </td>

                    <td style="text-align: center;"><span class="badge-days"><?= floatval($c['jours_total']) ?> jrs</span></td>
                    <td style="text-align: center; font-weight: 700; color: #ef4444;"><?= floatval($c['nbr_jours_pris']) ?> jrs</td>
                    
                    <!-- Actions -->
                    <td style="text-align: center; display: flex; gap: 6px; justify-content: center;">
                        <!-- Bouton Éditer / Renseigner (Échappement ENT_QUOTES sécurisé) -->
                        <button type="button" 
                                onclick="window.openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)" 
                                title="Renseigner / Modifier la décision"
                                style="color:#0284c7; background:#e0f2fe; border:1px solid #bae6fd; padding:7px 10px; border-radius:8px; cursor:pointer; transition: all 0.2s;">
                            <i class="fas fa-pen"></i>
                        </button>

                        <!-- Bouton Supprimer -->
                        <button type="button" 
                                onclick="window.confirmDelete(<?= $c['id'] ?>)" 
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
<div id="addCongeModal" class="mat-dialog-overlay">
    <div class="mat-dialog">
        <div class="modal-header-styled">
            <h4><i class="fas fa-file-signature"></i> Nouvelle Décision</h4>
            <i class="fas fa-times" style="cursor:pointer; color: #94a3b8; font-size: 1rem;" onclick="window.closeModal('addCongeModal')"></i>
        </div>
        <form id="formAddConge" style="padding: 20px;">
            <!-- Matricule de l'agent -->
            <input type="hidden" name="im" value="<?= htmlspecialchars($im) ?>">

            <div class="mat-form-field">
                <label>Année</label>
                <input type="number" name="annee" class="mat-input" value="<?= date('Y') - 1 ?>" min="2000" max="2099" required>
            </div>

            <div class="mat-form-field">
                <label>N° Décision </label>
                <input type="text" name="num_decision" class="mat-input" placeholder="Ex: 066/26/PREF/MNJ/PERS">
            </div>

            <div class="mat-form-field">
                <label>Date Signature </label>
                <input type="date" name="date_decision" class="mat-input">
            </div>

            <div class="mat-form-field">
                <label>Total Jours</label>
                <input type="number" step="0.5" min="0.5" name="jours_total" class="mat-input" value="30" required>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end;">
                <button type="button" class="btn-action-cancel" onclick="window.closeModal('addCongeModal')">Annuler</button>
                <button type="submit" class="btn-action-submit"><i class="fas fa-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODALE D'ÉDITION / MISE À JOUR DE DÉCISION -->
<div id="editCongeModal" class="mat-dialog-overlay">
    <div class="mat-dialog">
        <div class="modal-header-styled">
            <h4><i class="fas fa-edit"></i> Renseigner la Décision de Congé</h4>
            <i class="fas fa-times" style="cursor:pointer; color: #94a3b8; font-size: 1rem;" onclick="window.closeModal('editCongeModal')"></i>
        </div>
        <form id="formEditConge" method="POST" style="padding: 20px;">
            <!-- Cibles IM et Année -->
            <input type="hidden" name="im" value="<?= htmlspecialchars($im) ?>">
            
            <div class="mat-form-field">
                <label>Année</label>
                <!-- readonly permet de transmettre la valeur dans FormData tout en empêchant sa modification -->
                <input type="number" name="annee" id="edit_annee" class="mat-input" readonly style="background-color: #f1f5f9;">
            </div>
            <div class="mat-form-field">
                <label>N° Décision</label>
                <input type="text" name="num_decision" id="edit_num_decision" class="mat-input" placeholder="Ex: 066/26/PREF/MNJ/PERS" required>
            </div>
            <div class="mat-form-field">
                <label>Date Signature</label>
                <input type="date" name="date_decision" id="edit_date_decision" class="mat-input" required>
            </div>
            <div class="mat-form-field">
                <label>Total Jours</label>
                <input type="number" step="0.5" min="0.5" name="jours_total" id="edit_jours_total" class="mat-input" required>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end;">
                <button type="button" class="btn-action-cancel" onclick="window.closeModal('editCongeModal')">Annuler</button>
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
        <p style="color: #64748b; font-size: 0.85rem; margin: 10px 0 20px;">L'historique a été mis à jour avec succès.</p>
        <button type="button" onclick="window.reloadHistoriqueView()" class="btn-action-submit" style="width:100%; background: #16a34a;">D'accord</button>
    </div>
</div>

<!-- MODALE DE SUPPRESSION -->
<div id="deleteConfirmModal" class="mat-dialog-overlay">
    <div class="mat-dialog" style="width: 360px; text-align: center; padding: 28px;">
        <div class="modal-confirm-icon icon-delete"><i class="fas fa-exclamation-triangle"></i></div>
        <h3 style="margin: 0; color: #991b1b; text-transform: uppercase; font-size: 0.95rem; font-weight: 800;">Supprimer la décision ?</h3>
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

    window.openEditModal = function (conge) {
        if (typeof conge === 'string') {
            try { 
                conge = JSON.parse(conge); 
            } catch (e) { 
                console.error('Erreur JSON:', e); 
            }
        }

        // Mise à jour des champs du formulaire s'ils existent
        const elAnnee = document.getElementById('edit_annee');
        const elNumDecision = document.getElementById('edit_num_decision');
        const elDateDecision = document.getElementById('edit_date_decision');
        const elJoursTotal = document.getElementById('edit_jours_total');

        if (elAnnee) elAnnee.value = conge.annee || '';
        if (elNumDecision) elNumDecision.value = conge.num_decision || '';
        if (elDateDecision) elDateDecision.value = conge.date_decision || '';
        if (elJoursTotal) elJoursTotal.value = conge.jours_total || 30;

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
            window.location.href = `index.php?page=historique_conge&im=${encodeURIComponent(im)}`;
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

    // Fonction d'envoi AJAX
    async function submitCongeForm(url, formData, modalToClose) {
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

    // Utilisation de la délégation d'événements globale (Fonctionne même en chargement dynamique AJAX)
    document.addEventListener('submit', function (e) {
        if (!e.target) return;

        if (e.target.id === 'formAddConge') {
            e.preventDefault();
            e.stopPropagation();
            submitCongeForm('actions/conges/save_historique_conge.php', new FormData(e.target), 'addCongeModal');
        }

        if (e.target.id === 'formEditConge') {
            e.preventDefault();
            e.stopPropagation();
            submitCongeForm('actions/conges/update_historique_conge.php', new FormData(e.target), 'editCongeModal');
        }
    });
</script>
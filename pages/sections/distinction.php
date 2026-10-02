<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    require_once __DIR__ . '/../../includes/config.php'; 
    $im =$_SESSION['user_im'];

    $distinctions = [];
    if ($im) {
        $stmt =$pdo->prepare("SELECT * FROM personnel_distinctions WHERE im = ? ORDER BY date_acte DESC");
        $stmt->execute([$im]);
        $distinctions =$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
?>

<!-- Chargement du fichier CSS externe -->
<link rel="stylesheet" href="assets/css/distinction.css">

<script>
// Déclaration globale de la fonction
window.addRowDist = function() {
    const tbody = document.querySelector('#table-dist tbody');
    if (!tbody) return;

    // Si le message "Aucune distinction" existe, on le retire avant d'ajouter une ligne
    const emptyRow = document.getElementById('no-dist-msg');
    if (emptyRow) {
        emptyRow.remove();
    }
    
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="dist_type[]" required>
                <option value="" disabled selected>-- Choisir --</option>
                <option value="Ordre National">Ordre National</option>
                <option value="Ordre de Mérite">Ordre de Mérite</option>
                <option value="Brevet d'honneur">Brevet d'honneur</option>
            </select>
        </td>
        <td>
            <select name="dist_nature[]" required>
                <option value="" disabled selected>-- Choisir --</option>
                <option value="Décret">Décret</option>
                <option value="Arrêté">Arrêté</option>
                <option value="Décision">Décision</option>
            </select>
        </td>
        <td>
            <select name="dist_nom_grade[]" required>
                <option value="" disabled selected>-- Choisir --</option>
                <option value="Chevalier">Chevalier</option>
                <option value="Officier">Officier</option>
                <option value="Commandeur">Commandeur</option>
                <option value="Grand-officier">Grand-officier</option>
                <option value="Grand Croix de 2ème Classe">Grand Croix de 2ème Classe</option>
            </select>
        </td>
        <td><input type="text" name="dist_num[]" placeholder="N°..." required></td>
        <td><input type="date" name="dist_date[]" required></td>
        <td><button type="button" class="btn-remove" onclick="removeRowDist(this)"><i class="fas fa-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
};

// Fonction pour supprimer une ligne et réafficher le message si le tableau redevient vide
window.removeRowDist = function(btn) {
    const tbody = document.querySelector('#table-dist tbody');
    btn.closest('tr').remove();
    if (tbody.querySelectorAll('tr').length === 0) {
        tbody.innerHTML = `<tr id="no-dist-msg"><td colspan="6" style="text-align: center; color: #777;">Aucune distinction ajoutée. Cliquez sur "Ajouter" pour en ajouter une.</td></tr>`;
    }
};
</script>

<div class="dist-container">
    <div class="form-section-header">
        <h3><i class="fas fa-medal"></i> Distinctions Honorifiques</h3>
        <button type="button" class="btn-add-inline" onclick="addRowDist()">
            <i class="fas fa-plus-circle"></i> Ajouter
        </button>
    </div>
    
    <table class="pretty-table" id="table-dist">
        <thead>
            <tr>
                <th style="width: 20%;">Type de Grade</th>
                <th style="width: 18%;">Nature de l'acte</th>
                <th style="width: 22%;">Nom du Grade</th>
                <th style="width: 15%;">N° de l'acte</th>
                <th style="width: 15%;">Date de l'acte</th>
                <th style="width: 50px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($distinctions)): ?>
                <?php foreach ($distinctions as$d): ?>
                    <tr>
                        <td>
                            <select name="dist_type[]" required>
                                <option value="Ordre National" <?= $d['type_grade'] == 'Ordre National' ? 'selected' : '' ?>>Ordre National</option>
                                <option value="Ordre de Mérite" <?= $d['type_grade'] == 'Ordre de Mérite' ? 'selected' : '' ?>>Ordre de Mérite</option>
                                <option value="Brevet d'honneur" <?= $d['type_grade'] == "Brevet d'honneur" ? 'selected' : '' ?>>Brevet d'honneur</option>
                            </select>
                        </td>
                        <td>
                            <select name="dist_nature[]" required>
                                <option value="Décret" <?= $d['nature_acte'] == 'Décret' ? 'selected' : '' ?>>Décret</option>
                                <option value="Arrêté" <?= $d['nature_acte'] == 'Arrêté' ? 'selected' : '' ?>>Arrêté</option>
                                <option value="Décision" <?= $d['nature_acte'] == 'Décision' ? 'selected' : '' ?>>Décision</option>
                            </select>
                        </td>
                        <td>
                            <select name="dist_nom_grade[]" required>
                                <option value="Chevalier" <?= $d['nom_grade'] == 'Chevalier' ? 'selected' : '' ?>>Chevalier</option>
                                <option value="Officier" <?= $d['nom_grade'] == 'Officier' ? 'selected' : '' ?>>Officier</option>
                                <option value="Commandeur" <?= $d['nom_grade'] == 'Commandeur' ? 'selected' : '' ?>>Commandeur</option>
                                <option value="Grand-officier" <?= $d['nom_grade'] == 'Grand-officier' ? 'selected' : '' ?>>Grand-officier</option>
                                <option value="Grand Croix de 2ème Classe" <?= $d['nom_grade'] == 'Grand Croix de 2ème Classe' ? 'selected' : '' ?>>Grand Croix de 2ème Classe</option>
                            </select>
                        </td>
                        <td><input type="text" name="dist_num[]" value="<?= htmlspecialchars($d['num_acte']) ?>" required></td>
                        <td><input type="date" name="dist_date[]" value="<?= $d['date_acte'] ?>" required></td>
                        <td><button type="button" class="btn-remove" onclick="removeRowDist(this)"><i class="fas fa-trash"></i></button></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr id="no-dist-msg">
                    <td colspan="6" style="text-align: center; color: #777;">
                        Aucune distinction ajoutée. Cliquez sur "Ajouter" pour en ajouter une.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
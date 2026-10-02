<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$im = $_SESSION['user_im'] ?? '';
$affectations = [];

if ($im) {
    $stmt = $pdo->prepare("SELECT * FROM personnel_affectations WHERE im = ? ORDER BY date_acte DESC");
    $stmt->execute([$im]);
    $affectations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="mat-container">
    <div class="mat-card">
        <div class="mat-toolbar" style="background: #0284c7;">
            <span class="uppercase font-bold">Aperçu du Renouvellement / Avenant</span>
            <i class="material-icons">description</i>
        </div>

        <div class="p-6">
            <div class="mb-6 p-4 bg-sky-50 border-l-4 border-sky-500 text-sky-700 text-sm">
                <i class="material-icons" style="vertical-align: middle; font-size: 18px;">info</i>
                Le bouton ci-dessous générera un fichier Word contenant l'historique de vos <strong><?php echo count($affectations); ?></strong> affectation(s) enregistrée(s).
            </div>

            <table class="mat-table mb-6">
                <thead>
                    <tr class="mat-header-row">
                        <th class="mat-header-cell">Acte</th>
                        <th class="mat-header-cell">Date</th>
                        <th class="mat-header-cell">Lieu d'affectation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($affectations as $row): ?>
                    <tr class="mat-row">
                        <td class="mat-cell"><?php echo $row['type_acte'] . ' n°' . $row['num_acte']; ?></td>
                        <td class="mat-cell"><?php echo date('d/m/Y', strtotime($row['date_acte'])); ?></td>
                        <td class="mat-cell"><?php echo $row['lieu_affectation']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="flex justify-center mt-8">
                <a href="documents/export_word_logic.php" class="mat-btn mat-btn-primary" style="background: #16a34a; text-decoration: none; display: flex; align-items: center; gap: 10px;">
                    <i class="material-icons">file_download</i>
                    GÉNÉRER LE FICHIER WORD (.DOCX)
                </a>
            </div>
        </div>
    </div>
</div>
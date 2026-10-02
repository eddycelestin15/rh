<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
// Champs obligatoires : sans ce controle les valeurs absentes partaient
// en base sous forme de NULL (et PHP 8 signalait chaque champ manquant).
require_once __DIR__ . '/../../includes/helpers.php';
exiger_champs($_POST, ['im', 'num_dcf', 'date_dcf']);
$im = $_POST['im'];
$num = $_POST['num_dcf'];
$date = $_POST['date_dcf'];

try {
    $pdo->beginTransaction();

    // 1. Mettre à jour les enfants non utilisés
    $stmt1 = $pdo->prepare("UPDATE personnel_enfants SET num_dcf = ?, date_dcf = ? WHERE im_parent = ? AND situation = 'non_utilise'");
    $stmt1->execute([$num, $date, $im]);

    // 2. Récupérer la région de l'agent
    $stmtReg = $pdo->prepare("SELECT nom_region FROM personnel_poste_actuel WHERE im = ?");
    $stmtReg->execute([$im]);
    $region = $stmtReg->fetchColumn();

    if ($region) {
        // 3. Insérer ou mettre à jour la référence régionale
        $stmt3 = $pdo->prepare("INSERT INTO numero_declaration_regional (num_dcf, nom_region) 
                               VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE num_dcf = ?");
        $stmt3->execute([$num, $region, $num]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
<?php
// update_envoi_dren.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    // Utilisation de 'Y-m-d H:i:s' pour inclure Heures:Minutes:Secondes
    $dateHeureNow = date('Y-m-d H:i:s'); 

    try {
        $stmt = $pdo->prepare("UPDATE archives_bordereaux_dren SET date_envoi_bordereau = ? WHERE id = ?");
        $success = $stmt->execute([$dateHeureNow, $id]);
        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
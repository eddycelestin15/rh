<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

if (isset($_POST['id'])) {
    $stmt = $pdo->prepare("DELETE FROM personnel_affectations WHERE id = ?");
    if ($stmt->execute([$_POST['id']])) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Échec de la suppression']);
    }
}
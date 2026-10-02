<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$id = $_GET['id'] ?? 0;
if ($id) {
    $stmt = $pdo->prepare("UPDATE notifications SET est_lu = 1 WHERE id = ?");
    $success = $stmt->execute([$id]);
    echo json_encode(['success' => $success]);
}
?>
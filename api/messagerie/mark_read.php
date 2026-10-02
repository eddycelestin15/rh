<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$id = $_GET['id'] ?? null;
$myIm = $_SESSION['user_im'];

if ($id) {
    // On marque comme lu seulement si le message nous est destiné
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND destinataire_im_ou_role = ?");
    $success = $stmt->execute([$id, $myIm]);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false]);
}
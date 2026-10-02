<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$im = $_SESSION['user_im'];
$role = $_SESSION['user_role'];

// Non lus
$stmt1 = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE (destinataire_im_ou_role = ? OR destinataire_im_ou_role = ?) AND is_read = 0");
$stmt1->execute([$im, $role]);
$unread = $stmt1->fetchColumn();

// Déjà lus
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE (destinataire_im_ou_role = ? OR destinataire_im_ou_role = ?) AND is_read = 1");
$stmt2->execute([$im, $role]);
$read = $stmt2->fetchColumn();

// Envoyés
$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE expediteur_im = ?");
$stmt3->execute([$im]);
$sent = $stmt3->fetchColumn();

echo json_encode([
    'unread' => (int)$unread,
    'read' => (int)$read,
    'sent' => (int)$sent
]);
?>
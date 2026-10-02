<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
// Renvoie le nombre de notifications non lues et les 5 dernières, en JSON.
if (session_status() === PHP_SESSION_NONE) {
}
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

$user_im = $_SESSION['user_im'] ?? null;
if (!$user_im) {
    echo json_encode(['unread_count' => 0, 'items' => []]);
    exit;
}

// 1. Compter les notifications non lues.
//    La colonne est `est_lu` (et non `lu`) — cf. schéma de la table notifications.
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE im = ? AND est_lu = 0");
$stmtCount->execute([$user_im]);
$unreadCount = (int) $stmtCount->fetchColumn();

// 2. Récupérer les 5 dernières pour l'affichage rapide.
//    L'horodatage de la table est `created_at` ; `date_reception_technique`
//    n'existe que dans la vue v_moteur_alertes.
$stmtList = $pdo->prepare("SELECT * FROM notifications WHERE im = ? ORDER BY created_at DESC LIMIT 5");
$stmtList->execute([$user_im]);
$items = $stmtList->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'unread_count' => $unreadCount,
    'items'        => $items,
]);

<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

$id = $_POST['id'] ?? null;
$im = $_POST['im'] ?? null;

if ($id && $im) {
    try {
        // On sécurise la suppression avec l'ID et l'IM de l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM personnel_distinctions WHERE id = ? AND im = ?");
        $success = $stmt->execute([$id, $im]);

        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
}
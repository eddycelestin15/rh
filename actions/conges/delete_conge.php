<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_im'])) {
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit;
}

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $im = $_SESSION['user_im'];

    try {
        // On vérifie l'IM pour être sûr que l'agent ne supprime que ses propres données
        $stmt = $pdo->prepare("DELETE FROM personnel_conges WHERE id = ? AND im = ?");
        $stmt->execute([$id, $im]);

        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID manquant']);
}
?>
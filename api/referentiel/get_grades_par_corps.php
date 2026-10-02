<?php
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

if (empty($_GET['corps_id'])) {
    echo json_encode([]);
    exit;
}

$corps_id = intval($_GET['corps_id']);

try {
    // 1. Récupérer le modele_id du corps
    $stmt = $pdo->prepare("SELECT modele_id FROM ref_corps WHERE id = ?");
    $stmt->execute([$corps_id]);
    $modele_id = $stmt->fetchColumn();

    if ($modele_id) {
        $stmtG = $pdo->prepare("
            SELECT id, libelle_grade 
            FROM ref_grades_types 
            WHERE modele_id = ? 
            ORDER BY id ASC
        ");
        $stmtG->execute([$modele_id]);
        $grades = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($grades ?: []);
        exit;
    }

    // Aucun modele_id → tableau vide
    echo json_encode([]);

} catch (Exception $e) {
    echo json_encode([
        "error"   => true,
        "message" => $e->getMessage()
    ]);
}
exit;
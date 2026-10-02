<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

if ($type === 'corps') {
    $stmt = $pdo->query("SELECT id, libelle_corps, modele_id FROM ref_corps ORDER BY libelle_corps ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
} 

if ($type === 'grades') {
    $stmt = $pdo->query("SELECT id, libelle_grade, modele_id FROM ref_grades_types ORDER BY id ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

echo json_encode([]);
exit;
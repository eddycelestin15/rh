<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';

$im = $_GET['im'] ?? '';
$grade = $_GET['grade'] ?? '';

if ($im && $grade) {
    // On cherche l'avenant correspondant au matricule ET au grade sélectionné
    $stmt = $pdo->prepare("SELECT * FROM personnel_avenant WHERE im = ? AND grade = ? LIMIT 1");
    $stmt->execute([$im, $grade]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($res ?: null);
} else {
    echo json_encode(null);
}
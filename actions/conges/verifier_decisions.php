<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

$im = $_GET['im'] ?? null;

if (!$im) {
    echo json_encode(['annees' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT DISTINCT annee FROM personnel_conges WHERE im = ?");
    $stmt->execute([$im]);
    $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['annees' => array_map('intval', $annees)]);
} catch (Exception $e) {
    echo json_encode(['annees' => []]);
}
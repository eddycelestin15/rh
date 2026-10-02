<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS online_count 
        FROM utilisateurs 
        WHERE derniere_activite >= NOW() - INTERVAL 5 MINUTE
    ");
    $stmt->execute();
    $result = $stmt->fetch();

    $count = (int) ($result['online_count'] ?? 1);

    echo json_encode([
        'success' => true,
        'count'   => $count
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'count'   => 1
    ]);
}
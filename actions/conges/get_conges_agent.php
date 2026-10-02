<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

$im = isset($_GET['im']) ? trim($_GET['im']) : '';

if (empty($im)) {
    echo json_encode(['success' => false, 'message' => 'Matricule manquant']);
    exit;
}

try {
    $stmt =$pdo->prepare("SELECT annee, num_decision, date_decision, jours_total 
                           FROM personnel_conges 
                           WHERE im = :im 
                           ORDER BY annee ASC 
                           LIMIT 3");
    $stmt->execute([':im' =>$im]);
    $conges =$stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $conges]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
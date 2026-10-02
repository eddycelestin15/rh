<?php
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

$im = $_GET['im'] ?? '';
$alerte_id = $_GET['alerte_id'] ?? '';

if (empty($im)) {
    echo json_encode(['success' => false, 'message' => 'IM manquant']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT num_arrete_retraite, date_arrete_retraite 
        FROM arrete_admission_retraite 
        WHERE im = ? AND alerte_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$im, $alerte_id]);
    $arrete = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($arrete) {
        echo json_encode(['success' => true, 'arrete' => $arrete]);
    } else {
        echo json_encode(['success' => true, 'arrete' => null]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
}
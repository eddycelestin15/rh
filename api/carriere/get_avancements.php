<?php
    defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
    require_once __DIR__ . '/../../includes/check_session.php';
    $im = $_GET['im'] ?? '';

    if (empty($im)) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT * FROM bin_agent 
        WHERE im_bin = ? 
        ORDER BY date_acte_bin ASC, numero_acte_bin ASC
    ");
    $stmt->execute([$im]);
    $avancements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($avancements);
?>
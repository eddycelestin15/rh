<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

$alerteId = isset($_GET['alerte_id']) ? trim($_GET['alerte_id']) : null;
$userIm   = isset($_SESSION['user_im']) ? trim($_SESSION['user_im']) : null;

if ($alerteId && $userIm) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO alertes_notifications (im_user, alerte_id, date_lecture) 
            VALUES (:im_user, :alerte_id, NOW())
            ON DUPLICATE KEY UPDATE date_lecture = NOW()
        ");
        $stmt->execute([
            'im_user'   => $userIm,
            'alerte_id' => $alerteId
        ]);

        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
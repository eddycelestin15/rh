<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

$userIm = $_SESSION['user_im'] ?? $_SESSION['im'] ?? '';
$texteSaisi = trim($_POST['message'] ?? '');
$type = trim($_POST['type'] ?? 'message');

if (empty($userIm) || empty($texteSaisi)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Utilisateur non identifié ou message vide.'
    ]);
    exit;
}

try {
    if ($type === 'suggestion') {
        // Enregistrement dans la table dédiée 'suggestions'
        $stmt = $pdo->prepare("INSERT INTO suggestions (sender_im, contenu) VALUES (?, ?)");
        $stmt->execute([$userIm, $texteSaisi]);
    } else {
        // Enregistrement dans le chat standard
        if (!in_array($type, ['message', 'aide'])) {
            $type = 'message';
        }
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_im, message_type, message) VALUES (?, ?, ?)");
        $stmt->execute([$userIm, $type, $texteSaisi]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()
    ]);
}
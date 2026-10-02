<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $msgId = $_GET['id'];
    $userIm = $_SESSION['user_im'];
    $userRole = $_SESSION['user_role'];

    try {
        // Sécurité : On ne supprime que si l'utilisateur est impliqué dans le message
        $stmt = $pdo->prepare("DELETE FROM messages 
                               WHERE id = ? 
                               AND (expediteur_im = ? 
                                    OR destinataire_im_ou_role = ? 
                                    OR destinataire_im_ou_role = ?)");
        
        $stmt->execute([$msgId, $userIm, $userIm, $userRole]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Message non trouvé ou accès refusé']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
}
<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_im'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit;
}

$im = $_SESSION['user_im'];

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
    $target_dir = APP_ROOT . '/images/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $target_file    = $target_dir . $im . ".jpg";
    // Chemin relatif stocke en base et renvoye au JavaScript : il sert d'URL.
    $chemin_relatif = 'images/' . $im . '.jpg';
    
    // Vérification de sécurité
    $check = getimagesize($_FILES["photo"]["tmp_name"]);
    if($check === false) {
        echo json_encode(['success' => false, 'message' => 'Le fichier n\'est pas une image.']);
        exit;
    }

    // 1. Déplacement physique du fichier
    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
        
        // 2. Mise à jour de la base de données pour la persistance
        try {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET photo = ? WHERE im = ?");
            $stmt->execute([$chemin_relatif, $im]);

            echo json_encode(['success' => true, 'path' => $chemin_relatif]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur SQL lors de la mise à jour.']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu.']);
}
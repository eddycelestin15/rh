<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Utilisation de l'IM et du type stockés en session
    $im = $_SESSION['user_im'];
    $user_type = $_SESSION['user_type'] ?? 'agent'; // Récupération du rôle pour la redirection[cite: 18]
    
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    try {
        // Récupérer le mot de passe actuel[cite: 21]
        $stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateurs WHERE im = ?");
        $stmt->execute([$im]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable.']);
            exit;
        }

        // Vérification de l'ancien mot de passe[cite: 21]
        if (!password_verify($old_password, $user['mot_de_passe'])) {
            echo json_encode(['success' => false, 'message' => 'L\'ancien mot de passe est incorrect.']);
            exit;
        }

        // Validation du nouveau mot de passe (minimum 6 caractères)[cite: 21]
        if (empty($new_password) || strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.']);
            exit;
        }

        // Hachage et mise à jour sécurisée[cite: 21]
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE im = ?");
        
        if ($update->execute([$hashed_password, $im])) {
            // Inclusion de user_type pour piloter la redirection JavaScript
            echo json_encode([
                'success' => true,
                'user_type' => $user_type 
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur base de données.']);
    }
}
?>
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

$im = $_SESSION['user_im'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $tel_raw   = trim($_POST['telephone'] ?? '');
    $wa_raw    = trim($_POST['whatsapp'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    
    $old_pass  = $_POST['old_password'] ?? '';
    $new_pass  = $_POST['new_password'] ?? '';

    // Fonction de formatage au format international (+261XXXXXXXXX)
    function formatPhoneMadagascar($phone) {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) return null;
        
        // Si le numéro commence par 261
        if (str_starts_with($clean, '261')) {
            return '+' . $clean;
        }
        
        // Si l'utilisateur a saisi un 0 au début (ex: 0340000000), on le retire
        if (str_starts_with($clean, '0')) {
            $clean = substr($clean, 1);
        }
        
        return '+261' . $clean;
    }

    $telephone = formatPhoneMadagascar($tel_raw);
    $whatsapp  = formatPhoneMadagascar($wa_raw);

    try {
        // 1. Récupération des informations actuelles du compte
        $stmtUser = $pdo->prepare("SELECT mot_de_passe, type_compte FROM utilisateurs WHERE im = ?");
        $stmtUser->execute([$im]);
        $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$currentUser) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable.']);
            exit;
        }

        // 2. Traitement du mot de passe si un nouveau est saisi (facultatif)
        if (!empty($new_pass)) {
            // Vérification du mot de passe actuel
            if (empty($old_pass) || !password_verify($old_pass, $currentUser['mot_de_passe'])) {
                echo json_encode(['success' => false, 'message' => 'Le mot de passe actuel est incorrect.']);
                exit;
            }

            // Hachage du nouveau mot de passe
            $pwd_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $sql = "UPDATE utilisateurs SET telephone = ?, whatsapp = ?, email = ?, mot_de_passe = ? WHERE im = ?";
            $params = [$telephone, $whatsapp, $email, $pwd_hashed, $im];
        } else {
            // Mise à jour uniquement des coordonnées (téléphone, whatsapp, email)
            $sql = "UPDATE utilisateurs SET telephone = ?, whatsapp = ?, email = ? WHERE im = ?";
            $params = [$telephone, $whatsapp, $email, $im];
        }

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            echo json_encode([
                'success'   => true,
                'user_type' => $currentUser['type_compte']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Échec de la mise à jour des données.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
}
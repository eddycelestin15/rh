<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$im = trim($_SESSION['user_im']);
$role = trim($_SESSION['user_role']);
$type = $_GET['type'] ?? 'inbox';

try {
    // Correction : Utilisation de 'prenoms' et 'role_specifique' selon votre schéma
    $sql = "SELECT m.*, 
               p.nom_etablissement, p.nom_zap, p.nom_district, p.nom_region, 
               u.nom as nom_exp, u.prenoms as prenom_exp 
        FROM messages m
        LEFT JOIN personnel_poste_actuel p ON m.expediteur_im = p.im
        LEFT JOIN utilisateurs u ON m.expediteur_im = u.im ";

    // Dans fetch_message.php, modifiez la logique de filtrage :
    if ($type === 'unread') {
        $stmt = $pdo->prepare($sql . " WHERE (m.destinataire_im_ou_role = ? OR m.destinataire_im_ou_role = ?) AND m.is_read = 0 ORDER BY m.created_at DESC");
        $stmt->execute([$im, $role]);
    } elseif ($type === 'read') {
        $stmt = $pdo->prepare($sql . " WHERE (m.destinataire_im_ou_role = ? OR m.destinataire_im_ou_role = ?) AND m.is_read = 1 ORDER BY m.created_at DESC");
        $stmt->execute([$im, $role]);
    } else { // sent
        $stmt = $pdo->prepare($sql . " WHERE m.expediteur_im = ? ORDER BY m.created_at DESC");
        $stmt->execute([$im]);
    }
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($messages);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}
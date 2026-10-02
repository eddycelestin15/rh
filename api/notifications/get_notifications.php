<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';

// Désactiver l'affichage des erreurs directes pour ne pas casser le JSON
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['user_im'])) {
    echo json_encode(['count' => 0, 'count_read' => 0, 'items' => [], 'all_moteur_items' => [], 'error' => 'Session expirée']);
    exit;
}

$userIm = $_SESSION['user_im'];

try {
    // 1. Alertes non lues (pour l'affichage dans la liste)
    $sqlUnread = "SELECT alerte_id, titre, message, couleur_code, icone, date_reception_technique, im 
                  FROM v_moteur_alertes 
                  WHERE im = :im 
                  AND alerte_id NOT LIKE 'HIDDEN_%' 
                  AND TRIM(alerte_id) NOT IN (
                      SELECT TRIM(alerte_id) 
                      FROM alertes_notifications 
                      WHERE TRIM(im_user) = :im_user
                  )
                  ORDER BY date_reception_technique DESC";

    $stmt = $pdo->prepare($sqlUnread);
    $stmt->execute(['im' => $userIm, 'im_user' => $userIm]);
    $unreadAlertes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. TOUTES les alertes encore présentes dans la vue moteur (lues ou non) pour gérer la dépendance métier
    $sqlAllMoteur = "SELECT alerte_id, im 
                     FROM v_moteur_alertes 
                     WHERE im = :im 
                     AND alerte_id NOT LIKE 'HIDDEN_%'";

    $stmtAll = $pdo->prepare($sqlAllMoteur);
    $stmtAll->execute(['im' => $userIm]);
    $allMoteurAlertes = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    // 3. Compter les notifications DÉJÀ LUES
    $sqlCountRead = "SELECT COUNT(*) as total_read 
                     FROM alertes_notifications 
                     WHERE im_user = :im_user";
    
    $stmtRead = $pdo->prepare($sqlCountRead);
    $stmtRead->execute(['im_user' => $userIm]);
    $readData = $stmtRead->fetch(PDO::FETCH_ASSOC);
    $countRead = $readData['total_read'] ?? 0;

    // Réponse JSON complète
    echo json_encode([
        'status'           => 'success',
        'count'            => count($unreadAlertes), 
        'count_read'       => (int)$countRead,       
        'items'            => $unreadAlertes,
        'all_moteur_items' => $allMoteurAlertes
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status'           => 'error',
        'message'          => 'Erreur base de données',
        'count'            => 0,
        'items'            => [],
        'all_moteur_items' => []
    ]);
}
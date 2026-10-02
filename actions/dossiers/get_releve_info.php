<?php
// Désactiver l'affichage HTML direct des erreurs PHP pour préserver la réponse JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../includes/check_session.php';
    require_once __DIR__ . '/../../includes/config.php';

    // Vérification de la connexion à la base de données
    if (!isset($pdo)) {
        throw new Exception("La connexion à la base de données (\$pdo) est introuvable.");
    }

    $im = $_GET['im'] ?? '';
    $annee_courante = date('Y');
    $user_im = $_SESSION['user_im'] ?? '';

    $lieu_direction = '';
    $sigle = 'DREN/VV/CISCO/MNJ'; // Valeur par défaut

    // 1. Récupération du lieu d'affectation si l'IM utilisateur est en session
    if (!empty($user_im)) {
        $stmtLieu = $pdo->prepare("
            SELECT code_lieu_affectation 
            FROM utilisateurs 
            WHERE im = ?
        ");
        $stmtLieu->execute([$user_im]);
        $lieu_direction = $stmtLieu->fetchColumn() ?: '';
    }

    // 2. Recherche du sigle correspondant au lieu
    if (!empty($lieu_direction)) {
        $stmtSigle = $pdo->prepare("
            SELECT libelle_sigle 
            FROM sigle_bordereau 
            WHERE lieu_direction_service = ? 
            LIMIT 1
        ");
        $stmtSigle->execute([$lieu_direction]);
        $sigle_trouve = $stmtSigle->fetchColumn();
        if ($sigle_trouve) {
            $sigle = $sigle_trouve;
        }
    }

    // 3. Calcul du prochain numéro (Filtré par ANNEÉ et par LIEU/DIRECTION/SERVICE)
    if (!empty($lieu_direction)) {
        $stmtNum = $pdo->prepare("
            SELECT MAX(CAST(numero AS UNSIGNED)) 
            FROM releve_service 
            WHERE annee = ? AND lieu_direction_service = ?
        ");
        $stmtNum->execute([$annee_courante, $lieu_direction]);
    } else {
        // Sécurité si lieu_direction est vide
        $stmtNum = $pdo->prepare("
            SELECT MAX(CAST(numero AS UNSIGNED)) 
            FROM releve_service 
            WHERE annee = ? AND (lieu_direction_service IS NULL OR lieu_direction_service = '')
        ");
        $stmtNum->execute([$annee_courante]);
    }

    $maxNum = $stmtNum->fetchColumn();
    
    // Si aucun enregistrement n'existe pour ce lieu cette année, $maxNum sera null/false, donc début à 1 (001)
    $prochain_numero = str_pad(($maxNum ? $maxNum + 1 : 1), 3, '0', STR_PAD_LEFT);

    ob_end_clean();

    echo json_encode([
        'success' => true,
        'sigle' => $sigle,
        'prochain_numero' => $prochain_numero,
        'lieu_direction_service' => $lieu_direction
    ]);
    exit;

} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur SQL : ' . $e->getMessage()
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur Serveur : ' . $e->getMessage()
    ]);
    exit;
}
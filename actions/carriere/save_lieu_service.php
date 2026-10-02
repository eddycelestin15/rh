<?php
// 1. Isolation du flux de sortie pour éviter tout caractère parasite
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/check_session.php';

// Nettoyage du tampon
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

// Récupération des données du corps de la requête
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

$im = trim($data['im'] ?? $_POST['im'] ?? '');
$grade = trim($data['grade'] ?? $data['av_grade'] ?? $_POST['grade'] ?? '');
$lieuService = trim($data['lieu_service'] ?? $data['lieuService'] ?? $_POST['lieu_service'] ?? '');

// Vérification stricte des paramètres requis
if (empty($im) || empty($grade)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Données invalides : "im" ou "grade" manquant',
        'received' => $data 
    ]);
    exit;
}

try {
    if (!isset($pdo)) {
        throw new Exception("Connexion à la base de données indisponible.");
    }

    // Sécurisation de la valeur du lieu de service
    if (empty($lieuService)) {
        echo json_encode([
            'success' => false,
            'message' => 'Le lieu de service ne peut pas être vide.'
        ]);
        exit;
    }

    // Requête tolérante aux espaces et à la casse sur av_grade
    $stmt = $pdo->prepare("
        UPDATE personnel_avancements 
        SET lieu_de_service = ? 
        WHERE im = ? 
        AND LOWER(TRIM(av_grade)) = LOWER(TRIM(?))
    ");
    
    $res = $stmt->execute([$lieuService, $im, $grade]);
    $affected = $stmt->rowCount();

    // On ne valide le succès QUE si au moins une ligne a réellement été modifiée
    if ($res && $affected > 0) {
        echo json_encode([
            'success' => true,
            'affected_rows' => $affected,
            'message' => 'Lieu de service mis à jour avec succès.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'affected_rows' => 0,
            'message' => 'Aucune correspondance trouvée pour le grade "' . $grade . '" et l\'IM ' . $im
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur SQL : ' . $e->getMessage()
    ]);
}
exit;
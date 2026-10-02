<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
// Champs obligatoires : sans ce controle, les champs absents partaient en base
// sous forme de valeurs nulles (et PHP 8 emettait un avertissement par champ).
require_once __DIR__ . '/../../includes/helpers.php';
exiger_champs($_POST, ['type_acte', 'num_acte', 'date_acte', 'fonction', 'lieu_affectation', 'localite']);

try {
    // Un simple INSERT pour créer une nouvelle entrée dans l'historique
    $sql = "INSERT INTO personnel_affectations (im, type_acte, num_acte, date_acte, fonction, lieu_affectation, localite) 
            VALUES (:im, :ta, :na, :da, :fo, :la, :loc)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':im'  => $_SESSION['user_im'],
        ':ta'  => $_POST['type_acte'],
        ':na'  => $_POST['num_acte'],
        ':da'  => $_POST['date_acte'],
        ':fo'  => $_POST['fonction'],
        ':la'  => strtoupper($_POST['lieu_affectation']),
        ':loc' => strtoupper($_POST['localite'])
    ]);
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
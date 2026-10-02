<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json; charset=utf-8');

$im = $_GET['im'] ?? '';

if (empty($im)) {
    echo json_encode(['success' => false, 'message' => 'Le matricule est obligatoire.']);
    exit;
}

try {
    $sql = "SELECT ec.im, ec.nom, ec.prenoms, ec.date_naiss, ec.cin, ec.situation_familiale, ec.sexe,
                   ec.nbr_enfant_bc, psa.mode_paiement, psa.imput_budg, ppa.lieu_de_service
            FROM personnel_etat_civil ec
            LEFT JOIN personnel_poste_actuel ppa ON ec.im = ppa.im
            LEFT JOIN personnel_situation_actuelle psa ON ec.im = psa.im
            WHERE ec.im = ? LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$im]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($agent) {
        echo json_encode([
            'success' => true, 
            'found' => true, 
            'data' => $agent
        ]);
    } else {
        // L'agent n'est pas dans la base, on signale found = false mais success = true
        echo json_encode([
            'success' => true, 
            'found' => false, 
            'im' => $im,
            'message' => 'Nouvel agent (Non trouvé dans la base de données)'
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
exit;
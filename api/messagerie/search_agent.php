<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

$im = trim($_GET['im'] ?? '');

if (!$im) {
    echo json_encode(['success' => false, 'message' => 'IM manquant']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.nom, u.prenoms, u.im, 
               p.nom_etablissement, p.nom_zap, p.nom_district, p.nom_region
        FROM utilisateurs u
        LEFT JOIN personnel_poste_actuel p ON u.im = p.im
        WHERE u.im = ? AND (u.type_compte = 'agent' OR u.role_specifique = 'agent')
    ");
    $stmt->execute([$im]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($agent) {
        $localite = "";
        if (!empty($agent['nom_zap'])) {
            $localite = $agent['nom_etablissement'] . " (CISCO " . $agent['nom_district'] . ")";
        } elseif (empty($agent['nom_zap']) && empty($agent['nom_etablissement'])) {
            $localite = "CISCO " . $agent['nom_district'];
        } elseif (empty($agent['nom_district'])) {
            $localite = "DREN " . $agent['nom_region'];
        } else {
            $localite = $agent['nom_etablissement'];
        }

        $nomComplet = strtoupper($agent['nom']) . " " . $agent['prenoms'] . " (" . $agent['im'] . ")";
        
        echo json_encode([
            'success' => true, 
            'display' => $nomComplet . " - " . $localite,
            'im' => $agent['im']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Agent non trouvé ou accès non autorisé']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la recherche']);
}
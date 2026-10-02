<?php
// 1. Démarrer le tampon pour isoler toute sortie parasite
ob_start();

// 2. Masquer les erreurs HTML directes
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/check_session.php';

// Effacer tout texte/espace envoyé par bootstrap.php
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$im = trim($_GET['im'] ?? '');

if (empty($im)) {
    echo json_encode(new stdClass());
    exit;
}

try {
    if (!isset($pdo)) {
        throw new Exception("La connexion à la base de données (\$pdo) est introuvable.");
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Exécution de la requête
    $stmt = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ? LIMIT 1");
    $stmt->execute([$im]);
    $poste = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poste) {
        echo json_encode(new stdClass());
        exit;
    }

    // Récupération des données du poste
    $typeEtab    = $poste['type_etablissement'] ?? '';
    $nomRegion   = $poste['nom_region'] ?? '';
    $nomDistrict = $poste['nom_district'] ?? '';
    $nomZap      = $poste['nom_zap'] ?? '';
    $nomEtab     = $poste['nom_etablissement'] ?? '';
    $nomDir      = $poste['nom_direction'] ?? '';

    // Construction dynamique du lieu de service
    $lieuService = "";

    if ($typeEtab === 'MEN CENTRAL') {
        $lieuService = !empty($nomDir) ? $nomDir : '-';
    } elseif ($typeEtab === 'DREN') {
        $lieuService = "DREN " . $nomRegion;
    } elseif ($typeEtab === 'CISCO') {
        $lieuService = "CISCO " . $nomDistrict;
    } elseif ($typeEtab === 'CRFRP') {
        $lieuService = !empty($nomEtab) ? $nomEtab : '-';
    } elseif (in_array($typeEtab, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
        $lieuService = "DREN " . $nomRegion . " / CISCO " . $nomDistrict . " / ZAP " . $nomZap . " / " . $nomEtab;
    } else {
        $lieuService = !empty($nomEtab) ? $nomEtab : '-';
    }

    // Ajout de la clé 'lieuService' au tableau de résultat
    $poste['lieuService'] = $lieuService;

    // Renvoi de la réponse JSON avec lieuService inclus
    echo json_encode($poste);

} catch (Exception $e) {
    // En cas d'erreur SQL ou PHP, renvoie un JSON lisible sans status 500
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
exit;
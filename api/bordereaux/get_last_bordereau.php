<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json; charset=utf-8');

$user_im = $_SESSION['user_im'] ?? '';
$type_dos = $_GET['type_dos'] ?? '';
$dest     = $_GET['dest'] ?? '';
$type_bordereau = $_GET['type_bordereau'] ?? '';

if (empty($user_im) || empty($type_dos) || empty($dest)) {
    echo json_encode([
        'next_num' => 1,
        'dernier_complet' => null,
        'sigle' => ''
    ]);
    exit;
}

// Récupération du lieu et du niveau de l'utilisateur
$stmt = $pdo->prepare("SELECT code_lieu_affectation, niveau, role_specifique FROM utilisateurs WHERE im = ?");
$stmt->execute([$user_im]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$lieu   = $user['code_lieu_affectation'] ?? '';
$niveau = $user['niveau'] ?? '';
$role   = $user['role_specifique'] ?? '';

// Mapping destination → table d'archive
$mappingTables = [
    'dren'               => 'archives_bordereaux_dren',
    'augure_dren'        => 'archives_bordereaux_dren',
    'fonction_publique'  => 'archives_bordereaux_fop',
    'augure_fop'         => 'archives_bordereaux_fop',
    'solde_et_pensions'  => 'archives_bordereaux_solde',
    'augure_dsp'         => 'archives_bordereaux_solde',
    'controle_financier' => 'archives_bordereaux_cde',
    'augure_cf'          => 'archives_bordereaux_cde',
    'prefecture'         => 'archives_bordereaux_prefet',
    'drh'                => 'archives_bordereaux_drh',
    'mtefop'             => 'archives_bordereaux_mtefop',
    'primature'          => 'archives_bordereaux_primature'
];

$tableArchive = $mappingTables[$dest] ?? null;

if (!$tableArchive) {
    echo json_encode([
        'next_num' => 1,
        'dernier_complet' => null,
        'sigle' => ''
    ]);
    exit;
}

try {
    // Recherche du dernier numéro dans l'archive
    $sql = "SELECT numero_bordereau, numero_complet, sigle_bordereau 
            FROM $tableArchive 
            WHERE type_demande = ? 
              AND destination = ? 
              AND expediteur = ?";
    
    $params = [$type_dos, $dest, $lieu];

    if (!empty($type_bordereau)) {
        $sql .= " AND type_bordereau = ?";
        $params[] = $type_bordereau;
    }

    $sql .= " ORDER BY numero_bordereau DESC, id DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last) {
        $next_num = (int)$last['numero_bordereau'] + 1;
        $dernier_complet = $last['numero_complet'];
        $sigle = $last['sigle_bordereau'];
    } else {
        $next_num = 1;
        $dernier_complet = null;
        $sigle = '';
    }

    // --- CORRECTION DU FALLBACK POUR LE NIVEAU CENTRAL ---
    if (empty($sigle)) {
        if ($niveau === 'central') {
            // Récupère directement le sigle configuré pour le niveau CENTRAL
            $stmtSigle = $pdo->prepare("SELECT libelle_sigle FROM sigle_bordereau WHERE type_etablissement = 'MEN CENTRAL' LIMIT 1");
            $stmtSigle->execute();
        } else {
            // Recherche standard par lieu d'affectation pour les districts et régions
            $stmtSigle = $pdo->prepare("SELECT libelle_sigle FROM sigle_bordereau WHERE lieu_direction_service = ? LIMIT 1");
            $stmtSigle->execute([$lieu]);
        }
        
        $sigleRow = $stmtSigle->fetch(PDO::FETCH_ASSOC);
        if ($sigleRow) {
            $sigle = $sigleRow['libelle_sigle'];
        }
    }

    echo json_encode([
        'next_num' => $next_num,
        'dernier_complet' => $dernier_complet,
        'sigle' => $sigle
    ]);

} catch (Exception $e) {
    echo json_encode([
        'next_num' => 1,
        'dernier_complet' => null,
        'sigle' => '',
        'error' => $e->getMessage()
    ]);
}
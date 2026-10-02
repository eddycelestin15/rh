<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

// --- 1. RÉCUPÉRATION DES AGENTS POUR LE MODAL ---
if($action == 'get_agents_bordereau_complet') {
    $sql = "SELECT s.*, a.nom, a.prenoms, 
                   pa.nom_etablissement, pa.nom_district,
                   sa.corps_actuel, sa.grade_actuel
            FROM suivi_agents_bordereau s
            LEFT JOIN personnel_etat_civil a ON s.im_agent = a.im
            LEFT JOIN personnel_poste_actuel pa ON s.im_agent = pa.im
            LEFT JOIN personnel_situation_actuelle sa ON s.im_agent = sa.im
            WHERE s.id_bordereau = $id";

    $res = mysqli_query($conn, $sql);
    $data = [];
    if($res) {
        while($row = mysqli_fetch_assoc($res)) { $data[] = $row; }
    }
    echo json_encode($data);
    exit;
}

// --- 2. MISE À JOUR DU STATUT (VALIDE/REJETE) PAR AGENT ---
if($action == 'update_statut_agent') {
    $statut  = mysqli_real_escape_string($conn, $_POST['statut']);
    $motif   = mysqli_real_escape_string($conn, $_POST['motif']);
    $service = $_POST['service'] ?? 'dren';

    $map = [
        'dren'               => ['statut' => 'statut_dren',               'motif' => 'motif_dren'],
        'solde_et_pensions'  => ['statut' => 'statut_solde',              'motif' => 'motif_solde'],
        'controle_financier' => ['statut' => 'statut_controle_financier', 'motif' => 'motif_cde'],
        'prefecture'         => ['statut' => 'statut_prefet',             'motif' => 'motif_prefet']
    ];

    if (!isset($map[$service])) {
        echo json_encode(['success' => false, 'message' => 'Service invalide']);
        exit;
    }

    $colStatut = $map[$service]['statut'];
    $colMotif  = $map[$service]['motif'];

    $sql = "UPDATE suivi_agents_bordereau 
            SET $colStatut = '$statut', $colMotif = '$motif', date_traitement = NOW() 
            WHERE id = $id";

    echo json_encode(['success' => mysqli_query($conn, $sql)]);
    exit;
}

// --- 3. DISTRICT : ENVOI DU BORDEREAU ---
if($action == 'marquer_envoi') {
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    
    // Le district envoie toujours vers la DREN
    $sql = "UPDATE archives_bordereaux_dren SET date_envoi_bordereau = '$date' WHERE id = $id";
    
    if(mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
    exit;
}

// --- 4. RÉGIONAL : RÉCEPTION ET RÉFÉRENCE ---
// --- 4. RÉGIONAL : RÉCEPTION ET RÉFÉRENCE ---
if($action == 'recevoir') {
    $ref_complet = mysqli_real_escape_string($conn, $_POST['reference_complet']);
    $dest = $_POST['dest'] ?? 'dren';

    $mapTables = [
        'dren'               => 'archives_bordereaux_dren',
        'solde_et_pensions'  => 'archives_bordereaux_solde',
        'controle_financier' => 'archives_bordereaux_cde',
        'prefecture'         => 'archives_bordereaux_prefet'
    ];

    $targetTable = $mapTables[$dest] ?? 'archives_bordereaux_dren';

    // 1. Mise à jour de la table archive (Le Bordereau)
    $sqlArchive = "UPDATE $targetTable SET reference_destination = '$ref_complet' WHERE id = $id";
    $resArchive = mysqli_query($conn, $sqlArchive);

    if($resArchive) {
        // 2. Mise à jour de TOUTES les colonnes de référence des agents concernés
        // On propage la référence reçue dans les colonnes de suivi des agents
        $mapRefCol = [
            'dren'               => 'ref_dren',
            'solde_et_pensions'  => 'ref_solde_et_pensions',
            'controle_financier' => 'ref_controle_financier',
            'prefecture'         => 'ref_prefecture'
        ];

        if (isset($mapRefCol[$dest])) {
            $colonneRef = $mapRefCol[$dest];
            // On met à jour la colonne spécifique à la destination pour tous les agents du bordereau
            $sqlSuivi = "UPDATE suivi_agents_bordereau 
                         SET $colonneRef = '$ref_complet' 
                         WHERE id_bordereau = $id AND type_bordereau = (SELECT type_bordereau FROM $targetTable WHERE id = $id LIMIT 1)";
            mysqli_query($conn, $sqlSuivi);
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
    exit;
}
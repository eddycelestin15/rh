<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

$im = $_GET['im'] ?? '';

if (!$im) {
    echo json_encode(['success' => false, 'message' => 'IM manquant']);
    exit;
}

try {
    // 1. Détection du statut de l'agent
    $stmtAgent = $pdo->prepare("SELECT statut_actuel FROM personnel_situation_actuelle WHERE im = ?");
    $stmtAgent->execute([$im]);
    $agent = $stmtAgent->fetch();

    $statut = $agent['statut_actuel'] ?? '';

    // Définition de la logique "Dossier Unique"
    $isModeUnique = ($statut === 'Fonctionnaire' || $statut === 'Contractuel EFA');

    // 2. Requête principale
    $query = "
        SELECT 
            tab_dos.numero_dos,
            tab_dos.alerte_id,
            tab_dos.type_dos,
            tab_dos.type_titre,
            (
                SELECT GROUP_CONCAT(date_reception_technique ORDER BY date_reception_technique ASC SEPARATOR '|')
                FROM v_moteur_alertes 
                WHERE im = tab_dos.im 
                AND alerte_id LIKE 'HIDDEN_%'
            ) AS toutes_les_dates,
            CASE 
                /* 1. Cas RNC - Avenant (Date d'effet actuelle + 1 an) */
                WHEN tab_dos.type_dos LIKE 'avenant%' AND tab_dos.alerte_id LIKE '%_RNC%' 
                    THEN DATE_ADD(tab_dos.date_d_effet_actuel, INTERVAL 1 YEAR)
                
                /* 2. Cas RNC - Contrat (Date d'effet actuelle + 2 ans) */
                WHEN tab_dos.alerte_id LIKE '%_RNC%' 
                    THEN DATE_ADD(tab_dos.date_d_effet_actuel, INTERVAL 2 YEAR)
                
                /* 3. Cas Avancement - Jointure directe */
                WHEN v_direct.date_reception_technique IS NOT NULL 
                    THEN v_direct.date_reception_technique

                /* 4. Cas Avancement - Correspondance par rang */
                ELSE tab_v.date_reception_technique 
            END AS date_reception_technique
        FROM (
            SELECT d.*, sa.date_d_effet_actuel,
                ROW_NUMBER() OVER (ORDER BY d.date_attribution ASC) as rang
            FROM demandes_numeros_dos d
            LEFT JOIN personnel_situation_actuelle sa ON d.im = sa.im
            WHERE d.im = ? AND d.numero_dos IS NOT NULL
        ) AS tab_dos
        LEFT JOIN v_moteur_alertes v_direct ON tab_dos.im = v_direct.im AND tab_dos.alerte_id = v_direct.alerte_id
        LEFT JOIN (
            SELECT date_reception_technique, im, ROW_NUMBER() OVER (ORDER BY date_reception_technique ASC) as rang
            FROM (
                SELECT DISTINCT date_reception_technique, im
                FROM v_moteur_alertes 
                WHERE im = ? 
                AND alerte_id LIKE 'HIDDEN_%'
                AND date_reception_technique IS NOT NULL
            ) AS dates_uniques
        ) AS tab_v ON tab_dos.rang = tab_v.rang AND tab_dos.im = tab_v.im
    ";

    // 3. Assouplissement du filtre pour afficher les dossiers s'ils existent
    if ($isModeUnique) {
        $query .= " ORDER BY tab_dos.numero_dos DESC LIMIT 1"; 
    } else {
        $query .= " ORDER BY tab_dos.numero_dos ASC";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute([$im, $im]);
    $dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si la liste est vide, on tente de récupérer les dossiers sans le filtrage strict
    if (empty($dossiers)) {
        $stmtFallback = $pdo->prepare("SELECT numero_dos, alerte_id, type_dos, type_titre FROM demandes_numeros_dos WHERE im = ? AND numero_dos IS NOT NULL ORDER BY date_attribution DESC LIMIT 1");
        $stmtFallback->execute([$im]);
        $dossierFallback = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($dossierFallback)) {
            $dossiers = $dossierFallback;
        }
    }

    echo json_encode([
        'success' => !empty($dossiers), 
        'dossiers' => $dossiers,
        'mode' => $isModeUnique ? 'unique' : 'liste',
        'statut' => $statut
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
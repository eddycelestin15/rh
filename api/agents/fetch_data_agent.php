<?php
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    if (isset($_GET['action']) && $_GET['action'] === 'get_dossiers_attribues') {
        $im = $_GET['im'] ?? '';
        $id = $_GET['id'] ?? null; // Récupération de l'ID du dossier
        
        if ($id) {
            // Filtrage strict par ID unique et statut ATTRIBUE
            $stmt = $pdo->prepare("
                SELECT id, type_titre, type_dos, numero_dos 
                FROM demandes_numeros_dos 
                WHERE id = ? AND im = ? AND statut = 'ATTRIBUE'
            ");
            $stmt->execute([$id, $im]);
        } else {
            // Option de secours au cas où l'ID n'est pas transmis
            $stmt = $pdo->prepare("
                SELECT id, type_titre, type_dos, numero_dos 
                FROM demandes_numeros_dos 
                WHERE im = ? AND statut = 'ATTRIBUE'
                ORDER BY date_attribution DESC
            ");
            $stmt->execute([$im]);
        }
        
        $dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'dossiers' => $dossiers]);
        exit; 
    }
    
    $im = $_GET['im'] ?? '';
    $alerte_id_concat = $_GET['alerte_id'] ?? '';

    // 1. Infos Agent
    $stmt = $pdo->prepare("SELECT nom, prenoms FROM personnel_etat_civil WHERE im = ?");
    $stmt->execute([$im]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Historique
    $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(av_date_effet, '%d/%m/%Y') as av_date_effet_fr FROM personnel_avancements WHERE im = ? ORDER BY av_date_effet ASC");
    $stmt->execute([$im]);
    $filtered_historique = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Alertes actives (Pour le blocage Titularisation)
    $stmtMoteur = $pdo->prepare("SELECT alerte_id FROM v_moteur_alertes WHERE im = ?");
    $stmtMoteur->execute([$im]);
    $alertes_actives = $stmtMoteur->fetchAll(PDO::FETCH_ASSOC);

    // 4. Dossiers existants (SÉCURISÉ CONTRE LES CHAINES VIDES)
    $dossiers_attente = [];
    $alertes_array = array_filter(explode(',', $alerte_id_concat)); // Filtre les éléments vides

    if (!empty($alertes_array)) {
        $placeholders = implode(',', array_fill(0, count($alertes_array), '?'));
        $stmtD = $pdo->prepare("SELECT type_dos, type_titre FROM demandes_numeros_dos WHERE im = ? AND alerte_id IN ($placeholders) AND statut = 'EN_ATTENTE'");
        $stmtD->execute(array_merge([$im], $alertes_array));
        $dossiers_attente = $stmtD->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'agent' => $agent,
        'historique' => $filtered_historique ?: [],
        'dossiers_attente' => $dossiers_attente,
        'alertes_actives' => $alertes_actives ?: []
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
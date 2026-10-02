<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/check_session.php';

// Nettoyage du tampon
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? '';

try {
    $results = [];

    switch ($type) {
        case 'directions':
            // Récupère la liste des directions (MEN)
            $stmt = $pdo->query("SELECT id, nom_direction FROM ref_directions ORDER BY nom_direction ASC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'regions':
            // Récupère toutes les régions (DREN)
            $stmt = $pdo->query("SELECT id, nom_region FROM ref_regions ORDER BY nom_region ASC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'districts':
            // Récupère les districts (CISCO) filtrés par region_id
            $region_id = filter_input(INPUT_GET, 'region_id', FILTER_VALIDATE_INT);
            if ($region_id) {
                $stmt = $pdo->prepare("SELECT id, nom_district FROM ref_districts WHERE region_id = :region_id ORDER BY nom_district ASC");
                $stmt->execute(['region_id' => $region_id]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'crfrp':
            // Récupère les centres CRFRP filtrés par district_id
            $district_id = filter_input(INPUT_GET, 'district_id', FILTER_VALIDATE_INT);
            if ($district_id) {
                $stmt = $pdo->prepare("SELECT id, nom_crfrp FROM ref_crfrp WHERE district_id = :district_id ORDER BY nom_crfrp ASC");
                $stmt->execute(['district_id' => $district_id]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'zaps':
            // Récupère les ZAP filtrées par district_id
            $district_id = filter_input(INPUT_GET, 'district_id', FILTER_VALIDATE_INT);
            if ($district_id) {
                $stmt = $pdo->prepare("SELECT id, nom_zap FROM ref_zaps WHERE district_id = :district_id ORDER BY nom_zap ASC");
                $stmt->execute(['district_id' => $district_id]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'etablissements':
            // Récupère les établissements filtrés par zap_id et optionnellement par type (Lycée, CEG, EPP, Préscolaire)
            $zap_id = filter_input(INPUT_GET, 'zap_id', FILTER_VALIDATE_INT);
            
            if ($zap_id) {
                $query = "SELECT id, nom_etab FROM ref_etablissements WHERE zap_id = :zap_id";
                $params = ['zap_id' => $zap_id];

                // Filtres dynamiques selon la structure demandée
                if (isset($_GET['is_lycee'])) {
                    $query .= " AND is_lycee = 1";
                } elseif (isset($_GET['is_ceg'])) {
                    $query .= " AND is_ceg = 1";
                } elseif (isset($_GET['is_epp'])) {
                    $query .= " AND is_epp = 1";
                } elseif (isset($_GET['is_prescolaire'])) {
                    $query .= " AND is_prescolaire = 1";
                }

                $query .= " ORDER BY nom_etab ASC";

                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        default:
            $results = [];
            break;
    }

    echo json_encode($results, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données : ' . $e->getMessage()]);
}
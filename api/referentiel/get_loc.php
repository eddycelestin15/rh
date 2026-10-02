<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch($action) {
    case 'get_regions':
        echo json_encode($pdo->query("SELECT id, nom_region as nom FROM ref_regions ORDER BY nom_region")->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_districts':
        $reg_nom = $_GET['reg_nom'] ?? '';
        $type_etablissement = $_GET['type_etablissement'] ?? ''; 

        // ARRÊT UNIQUEMENT SI C'EST STRICTEMENT "DREN"
        if ($type_etablissement === 'DREN') {
            echo json_encode([]);
            break;
        }

        // Pour tous les autres (CISCO, LYCEE, CRFRP, COLLEGE, EPP...), on charge les districts
        if ($type_etablissement === 'CRFRP') {
            $sql = "SELECT DISTINCT d.id, d.nom_district AS nom
                    FROM ref_districts d
                    INNER JOIN ref_regions r ON d.region_id = r.id
                    INNER JOIN ref_crfrp c ON d.id = c.district_id
                    WHERE r.nom_region = ? 
                    ORDER BY d.nom_district ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reg_nom]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $sql = "SELECT d.id, d.nom_district AS nom 
                    FROM ref_districts d
                    INNER JOIN ref_regions r ON d.region_id = r.id
                    WHERE r.nom_region = ? 
                    ORDER BY d.nom_district ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reg_nom]); 
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        break;

    case 'get_zaps':
        $stmt = $pdo->prepare("SELECT z.id, z.nom_zap as nom 
                               FROM ref_zaps z 
                               JOIN ref_districts d ON z.district_id = d.id 
                               WHERE d.nom_district = ? ORDER BY z.nom_zap");
        $stmt->execute([$_GET['dist_nom']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_etabs_par_type':
        $zapNom = $_GET['zap_nom'] ?? ''; 
        $distNom = $_GET['dist_nom'] ?? ''; 
        $typeName = $_GET['type_name'] ?? '';

        // Détermination de la colonne selon le type d'établissement
        $column = "is_epp"; 
        if ($typeName == 'LYCEE') $column = "is_lycee";
        if ($typeName == 'COLLEGE' || $typeName == 'CEG') $column = "is_ceg";
        if ($typeName == 'PRESCOLAIRE') $column = "is_prescolaire";

        // Jointure ajoutée avec ref_districts pour filtrer par ZAP ET par DISTRICT
        $sql = "SELECT e.id, e.nom_etab as nom 
                FROM ref_etablissements e 
                INNER JOIN ref_zaps z ON e.zap_id = z.id 
                INNER JOIN ref_districts d ON z.district_id = d.id 
                WHERE z.nom_zap = ? 
                  AND d.nom_district = ? 
                  AND e.$column = 1 
                ORDER BY e.nom_etab ASC";

        $stmt = $pdo->prepare($sql);
        
        // L'ordre dans le tableau doit correspondre EXACTEMENT aux "?" de la requête SQL
        // 1er "?" = $zapNom, 2ème "?" = $distNom
        $stmt->execute([$zapNom, $distNom]); 
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_crfrp_par_district':
        $distNom = $_GET['dist_nom'] ?? '';
        $sql = "SELECT c.id, c.nom_crfrp AS nom 
                FROM ref_crfrp c
                INNER JOIN ref_districts d ON c.district_id = d.id 
                WHERE d.nom_district = ? 
                ORDER BY c.nom_crfrp";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$distNom]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_fonctions':
        $type = $_GET['type'] ?? ''; 
        $parentId = $_GET['parent_id'] ?? 0;

        $stmt = $pdo->prepare("SELECT id, nom FROM ref_fonctions WHERE rattachement = ? AND parent_id = ? ORDER BY nom");
        $stmt->execute([$type, $parentId]);
        $fonctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $fonctions[] = [
            'id' => 'AUTRE',
            'nom' => 'Autres...'
        ];

        echo json_encode($fonctions);
        break;

    case 'get_crfrp_all':
        // Retourne tous les CRFRP
        $stmt = $pdo->query("SELECT id, nom_crfrp as nom FROM ref_crfrp ORDER BY nom_crfrp");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_districts_by_reg':
        // Retourne les districts selon l'ID de la région
        $regId = $_GET['reg_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id, nom_district as nom FROM ref_districts WHERE region_id = ? ORDER BY nom_district");
        $stmt->execute([$regId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        echo json_encode([]);
        break;
}
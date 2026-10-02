<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch($action) {
    // ==========================================
    //   CAS EXISTANTS (GESTION STRUCTURES / FONCTIONS)
    // ==========================================
    case 'get_ref_directions':
        $stmt = $pdo->query("SELECT id, nom_direction AS nom FROM ref_directions ORDER BY nom_direction ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
    case 'get_directions_men':
        try {
            $stmt = $pdo->query("SELECT id, nom_direction AS nom FROM ref_directions ORDER BY nom_direction ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    case 'get_services_dirmen':
        $id_direction = intval($_GET['id_direction'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, nom_service AS nom FROM ref_services_dirmen WHERE id_direction = ? ORDER BY nom_service ASC");
        $stmt->execute([$id_direction]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
    case 'get_services_men':
        try {
            $id_direction = $_GET['id_direction'] ?? 0;
            
            // Si l'identifiant passé est textuel (nom de la direction), on cherche son ID d'abord
            if (!is_numeric($id_direction) && !empty($id_direction)) {
                $stmtId = $pdo->prepare("SELECT id FROM ref_directions WHERE nom_direction = ?");
                $stmtId->execute([$id_direction]);
                $resId = $stmtId->fetch(PDO::FETCH_ASSOC);
                $id_direction = $resId ? $resId['id'] : 0;
            } else {
                $id_direction = intval($id_direction);
            }

            if ($id_direction > 0) {
                $stmt = $pdo->prepare("SELECT id, nom_service AS nom FROM ref_services_dirmen WHERE id_direction = ? ORDER BY nom_service ASC");
                $stmt->execute([$id_direction]);
            } else {
                $stmt = $pdo->query("SELECT id, nom_service AS nom FROM ref_services_dirmen ORDER BY nom_service ASC");
            }
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    case 'get_services':
        echo json_encode($pdo->query("SELECT id, nom FROM ref_services ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC));
        break;
    case 'get_services_dren':
        try {
            // Requête qui récupère tous les services enregistrés pour la DREN
            $stmt = $pdo->query("SELECT id, nom FROM ref_services ORDER BY nom ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;
    case 'get_services_cisco':
        try {
            $stmt = $pdo->query("SELECT id, nom FROM ref_services ORDER BY nom ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    case 'get_divisions':
        echo json_encode($pdo->query("SELECT id, nom FROM ref_divisions ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC));
        break;
    case 'get_divisions_cisco':
        try {
            $stmt = $pdo->query("SELECT id, nom FROM ref_divisions ORDER BY nom ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    case 'get_fonctions':
        $rat = $_GET['rattachement'] ?? '';
        
        // Si c'est le MEN CENTRAL, on récupère directement les fonctions liées à l'administration centrale
        if ($rat === 'MEN_CENTRAL') {
            $stmt = $pdo->prepare("SELECT id, nom FROM ref_fonctions WHERE rattachement = 'MEN_CENTRAL' ORDER BY nom");
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        }

        // CAS STANDARD DREN ou CISCO (Utilise le filtrage par nom de parent)
        if (isset($_GET['parent_nom']) && !empty($_GET['parent_nom'])) {
            $table_parent = ($rat === 'SERVICE') ? 'ref_services' : 'ref_divisions';
            $sql = "SELECT f.id, f.nom 
                    FROM ref_fonctions f
                    JOIN $table_parent p ON f.parent_id = p.id
                    WHERE f.rattachement = ? AND p.nom = ? ORDER BY f.nom";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$rat, $_GET['parent_nom']]);
        } 
        // AUTRES CAS GÉNÉRAUX (CRFRP, ENSEIGNANT, etc.)
        else {
            $pid = $_GET['parent_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT id, nom FROM ref_fonctions WHERE rattachement = ? AND parent_id = ? ORDER BY nom");
            $stmt->execute([$rat, $pid]);
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_matieres':
        $niv = $_GET['niveau'] ?? '';
        $stmt = $pdo->prepare("SELECT id, nom FROM ref_matieres WHERE niveau = ? ORDER BY nom");
        $stmt->execute([$niv]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // ==========================================
    //   AJOUTS : GESTION DE LA LOCALISATION GÉOGRAPHIQUE
    // ==========================================
    
    // 1. Récupérer toutes les régions
    case 'get_regions':
        // Conforme à ref_regions : utilisation de 'nom_region AS nom'
        $stmt = $pdo->query("SELECT id, nom_region AS nom FROM ref_regions ORDER BY nom_region ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // 2. Récupérer les districts d'une région (avec filtre intelligent pour CRFRP)
    case 'get_districts':
        $reg_nom = $_GET['reg_nom'] ?? '';
        $type_etablissement = $_GET['type_etablissement'] ?? '';

        if ($type_etablissement === 'CRFRP') {
            // Jointure stricte : ref_districts + ref_regions (pour le nom) + ref_crfrp (pour valider la présence du CRFRP)
            $sql = "SELECT DISTINCT d.id, d.nom_district AS nom
                    FROM ref_districts d
                    INNER JOIN ref_regions r ON d.region_id = r.id
                    INNER JOIN ref_crfrp c ON d.id = c.district_id
                    WHERE r.nom_region = ? 
                    ORDER BY d.nom_district ASC";
        } else {
            // Requête standard pour les autres types, filtrée par le nom de la région via une jointure
            $sql = "SELECT d.id, d.nom_district AS nom 
                    FROM ref_districts d
                    INNER JOIN ref_regions r ON d.region_id = r.id
                    WHERE r.nom_region = ? 
                    ORDER BY d.nom_district ASC"; 
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$reg_nom]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // 3. Récupérer l'établissement CRFRP lié au district sélectionné
    case 'get_crfrp_par_district':
        $dist_nom = $_GET['dist_nom'] ?? '';
        
        // Jointure obligatoire car ref_crfrp contient 'district_id' (entier) et non le nom textuel du district
        $sql = "SELECT c.id, c.nom_crfrp AS nom 
                FROM ref_crfrp c
                INNER JOIN ref_districts d ON c.district_id = d.id
                WHERE d.nom_district = ? 
                ORDER BY c.nom_crfrp ASC";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dist_nom]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // 4. Récupérer les ZAP d'un district (Utile pour COLLEGE, LYCEE, EPP)
    case 'get_zaps':
        $dist_nom = $_GET['dist_nom'] ?? '';
        $stmt = $pdo->prepare("SELECT id, nom FROM ref_zaps WHERE district_nom = ? ORDER BY nom ASC");
        $stmt->execute([$dist_nom]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // 5. Récupérer les établissements par type d'une ZAP (Utile pour COLLEGE, LYCEE, EPP)
    case 'get_etabs_par_type':
        $zap_nom = $_GET['zap_nom'] ?? '';
        $type_name = $_GET['type_name'] ?? ''; // ex: 'COLLEGE', 'LYCEE'
        $stmt = $pdo->prepare("SELECT id, nom FROM ref_etablissements WHERE zap_nom = ? AND type = ? ORDER BY nom ASC");
        $stmt->execute([$zap_nom, $type_name]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
}
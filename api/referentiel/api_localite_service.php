<?php
// ====================== CHEMINS CORRIGÉS ======================
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$action = $_POST['action'] ?? '';

// =============================================
// AJOUT : Charger toutes les régions
// =============================================
if ($action === 'get_regions') {
    $stmt = $pdo->query("SELECT id, nom_region FROM ref_regions ORDER BY nom_region ASC");
    $regions = $stmt->fetchAll();

    echo '<option value="">Sélectionner la région...</option>';
    foreach ($regions as $r) {
        echo '<option value="' . htmlspecialchars($r['id']) . '">' . htmlspecialchars($r['nom_region']) . '</option>';
    }
    exit;
}

// =============================================
// AJOUT : Établissements par NOM de District
// =============================================
if ($action === 'get_etablissements_by_district') {
    $district_nom = $_POST['district_nom'] ?? '';
    if (empty($district_nom)) {
        echo '<option value="">Aucun district fourni</option>';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, nom_etab 
        FROM ref_etablissements 
        WHERE district_id IN (SELECT id FROM ref_districts WHERE nom_district = ?)
        ORDER BY nom_etab ASC
    ");
    $stmt->execute([$district_nom]);
    $etabs = $stmt->fetchAll();

    echo '<option value="">Sélectionner l\'établissement...</option>';
    foreach ($etabs as $e) {
        echo '<option value="' . htmlspecialchars($e['id']) . '">' . htmlspecialchars($e['nom_etab']) . '</option>';
    }
    exit;
}

// =============================================
// AJOUT : Communes par NOM de District
// =============================================
if ($action === 'get_communes_by_district') {
    $district_nom = $_POST['district_nom'] ?? '';
    if (empty($district_nom)) {
        echo '<option value="">Aucun district fourni</option>';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, nom_commune 
        FROM ref_communes 
        WHERE district_id IN (SELECT id FROM ref_districts WHERE nom_district = ?)
        ORDER BY nom_commune ASC
    ");
    $stmt->execute([$district_nom]);
    $communes = $stmt->fetchAll();

    echo '<option value="">Sélectionner la commune...</option>';
    foreach ($communes as $c) {
        echo '<option value="' . htmlspecialchars($c['id']) . '">' . htmlspecialchars($c['nom_commune']) . '</option>';
    }
    exit;
}

// =============================================
// 1. ZAPS par District (pour niveau district)
// =============================================
if ($action === 'get_zaps_by_district') {
    $district_nom = $_POST['district_nom'] ?? '';

    if (empty($district_nom)) {
        echo '<option value="">Aucun district fourni</option>';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, nom_zap 
        FROM ref_zaps 
        WHERE district_id IN (
            SELECT id FROM ref_districts WHERE nom_district = ?
        )
        ORDER BY nom_zap ASC
    ");
    $stmt->execute([$district_nom]);
    $zaps = $stmt->fetchAll();

    echo '<option value="">Sélectionner la ZAP...</option>';
    foreach ($zaps as $zap) {
        echo '<option value="' . htmlspecialchars($zap['id']) . '">' . 
             htmlspecialchars($zap['nom_zap']) . '</option>';
    }
}

// =============================================
// 2. CISCOS (Districts) par Région
// =============================================
if ($action === 'get_ciscos_by_region') {
    $region_nom = $_POST['region_nom'] ?? '';

    if (empty($region_nom)) {
        echo '<option value="">Aucune région fournie</option>';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT d.id, d.nom_district 
        FROM ref_districts d
        JOIN ref_regions r ON d.region_id = r.id
        WHERE r.nom_region = ? 
        ORDER BY d.nom_district ASC
    ");
    $stmt->execute([$region_nom]);
    $ciscos = $stmt->fetchAll();

    echo '<option value="">Sélectionner la Cisco...</option>';
    
    if (count($ciscos) > 0) {
        foreach ($ciscos as $cisco) {
            echo '<option value="' . htmlspecialchars($cisco['id']) . '">' . 
                 htmlspecialchars($cisco['nom_district']) . '</option>';
        }
    } else {
        echo '<option value="">Aucune Cisco trouvée pour ' . htmlspecialchars($region_nom) . '</option>';
    }
}

// =============================================
// 3. ZAPS par Cisco (District)
// =============================================
if ($action === 'get_zaps_by_cisco_id') {
    $cisco_id = $_POST['cisco_id'] ?? '';

    if (empty($cisco_id)) {
        echo '<option value="">Aucun ID Cisco fourni</option>';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, nom_zap 
        FROM ref_zaps 
        WHERE district_id = ? 
        ORDER BY nom_zap ASC
    ");
    $stmt->execute([$cisco_id]);
    $zaps = $stmt->fetchAll();

    echo '<option value="">Sélectionner la ZAP...</option>';
    foreach ($zaps as $zap) {
        echo '<option value="' . htmlspecialchars($zap['id']) . '">' . 
             htmlspecialchars($zap['nom_zap']) . '</option>';
    }
}

// =============================================
// 4. Établissements par ZAP + Niveau
// =============================================
if ($action === 'get_etablissements') {
    $zap_id = $_POST['zap_id'] ?? '';
    $niveau = $_POST['niveau'] ?? '';

    if (empty($zap_id) || empty($niveau)) {
        echo '<option value="">Données incomplètes</option>';
        exit;
    }

    $column = '';
    // Mettre en majuscules pour correspondre aux valeurs JavaScript
    switch (strtoupper($niveau)) {
        case 'EEC':
            $column = 'is_eec';
            break;
        case 'PRESCOLAIRE':
            $column = 'is_prescolaire';
            break;
        case 'PRIMAIRE':
            $column = 'is_epp';
            break;
        case 'COLLEGE':
            $column = 'is_ceg';
            break;
        case 'LYCEE':
            $column = 'is_lycee';
            break;
        default:
            echo '<option value="">Niveau non reconnu : ' . htmlspecialchars($niveau) . '</option>';
            exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, nom_etab AS nom_etablissement 
        FROM ref_etablissements 
        WHERE zap_id = ? AND $column = 1 
        ORDER BY nom_etab ASC
    ");
    $stmt->execute([$zap_id]);
    $etablissements = $stmt->fetchAll();

    echo '<option value="">Sélectionner l\'établissement...</option>';
    
    if (count($etablissements) > 0) {
        foreach ($etablissements as $etab) {
            echo '<option value="' . htmlspecialchars($etab['id']) . '">' . 
                 htmlspecialchars($etab['nom_etablissement']) . '</option>';
        }
    } else {
        echo '<option value="">Aucun établissement trouvé pour ce niveau</option>';
    }
}

// =============================================
// COMMUNES PAR ID DE DISTRICT (nouveau - plus précis)
// =============================================
if ($action === 'get_communes_by_district_id') {
    $district_id = $_POST['district_id'] ?? '';

    if (empty($district_id)) {
        echo '<option value="">Aucun district sélectionné</option>';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, nom_commune 
        FROM ref_communes 
        WHERE district_id = ? 
        ORDER BY nom_commune ASC
    ");
    $stmt->execute([$district_id]);
    $communes = $stmt->fetchAll();

    echo '<option value="">Sélectionner la commune...</option>';
    foreach ($communes as $c) {
        echo '<option value="' . htmlspecialchars($c['id']) . '">' . 
             htmlspecialchars($c['nom_commune']) . '</option>';
    }
    exit;
}

// =============================================
// CHARGER DISTRICTS PAR NOM DE RÉGION (pour DREN)
// =============================================
if ($action === 'get_districts_by_region_nom') {
    $region_nom = $_POST['region_nom'] ?? '';

    if (empty($region_nom)) {
        echo '<option value="">Aucune région fournie</option>';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT d.id, d.nom_district 
        FROM ref_districts d
        JOIN ref_regions r ON d.region_id = r.id
        WHERE r.nom_region = ?
        ORDER BY d.nom_district ASC
    ");
    $stmt->execute([$region_nom]);
    $districts = $stmt->fetchAll();

    echo '<option value="">Sélectionner le district...</option>';
    
    foreach ($districts as $d) {
        echo '<option value="' . htmlspecialchars($d['id']) . '">' . 
             htmlspecialchars($d['nom_district']) . '</option>';
    }
    exit;
}
// =============================================
// 5. CORPS
// =============================================
if ($action === 'get_corps') {
    $stmt = $pdo->prepare("SELECT id, libelle_corps FROM ref_corps ORDER BY libelle_corps ASC");
    $stmt->execute();
    $corps = $stmt->fetchAll();

    echo '<option value="">Sélectionner le corps...</option>';
    foreach ($corps as $c) {
        echo '<option value="' . htmlspecialchars($c['id']) . '">' . 
             htmlspecialchars($c['libelle_corps']) . '</option>';
    }
}

if ($action === 'get_grades') {
    $statut        = $_POST['statut'] ?? '';
    $corps_id      = $_POST['corps_id'] ?? null;
    $type_demande  = $_POST['type_demande'] ?? '';
    $type_position = $_POST['type_position'] ?? 'ancien';

    if (!$corps_id) {
        echo '<option value="">Sélectionner le corps d\'abord...</option>';
        exit;
    }

    // Récupérer modele_id + libellé du corps
    $stmtCorps = $pdo->prepare("SELECT modele_id, libelle_corps FROM ref_corps WHERE id = ?");
    $stmtCorps->execute([$corps_id]);
    $corpsInfo = $stmtCorps->fetch(PDO::FETCH_ASSOC);

    if (!$corpsInfo) {
        echo '<option value="">Corps invalide</option>';
        exit;
    }

    $modele_id = $corpsInfo['modele_id'];
    $corpsLib  = $corpsInfo['libelle_corps'];

    $where = ["gt.modele_id = ?"];
    $params = [$modele_id];

    $specialC = ["INSTITUTEURS ET INSTITUTRICES \"C\"", "OPERATEURS"];
    $specialB = ["INSTITUTEURS ET INSTITUTRICES \"B\" ", "ENCADREURS"];

    // ====================== LOGIQUE CORRIGÉE ======================
    $demandesSpeciales = ['renouvellement', 'avenant'];

    if ($type_position === 'nouveau' && !in_array($type_demande, $demandesSpeciales)) {
        // Pour TOUTES les autres demandes (integration, titularisation, avancement, etc.)
        // → Pas d'ECHELLE, même pour Contractuel EFA
        $where[] = "gt.libelle_grade NOT LIKE '%ECHELLE%'";
    }
    elseif ($type_position === 'nouveau' && $statut === 'Contractuel EFA' && in_array($type_demande, $demandesSpeciales)) {
        // Cas spécial : Renouvellement / Avenant + Contractuel EFA
        if (in_array($corpsLib, $specialC)) {
            $where[] = "gt.libelle_grade LIKE '%ECHELLE III%'";
        } elseif (in_array($corpsLib, $specialB)) {
            $where[] = "gt.libelle_grade LIKE '%ECHELLE IV%'";
        } else {
            $where[] = "gt.libelle_grade NOT LIKE '%ECHELLE%'";
        }
    }
    elseif ($statut === 'Contractuel EFA') {
        // Ancien grade ou autres cas Contractuel EFA
        if (in_array($corpsLib, $specialC)) {
            $where[] = "gt.libelle_grade LIKE '%ECHELLE III%'";
        } elseif (in_array($corpsLib, $specialB)) {
            $where[] = "gt.libelle_grade LIKE '%ECHELLE IV%'";
        } else {
            $where[] = "gt.libelle_grade NOT LIKE '%ECHELLE%'";
        }
    } 
    else {
        // Fonctionnaire ou tout autre statut
        $where[] = "gt.libelle_grade NOT LIKE '%ECHELLE%'";
    }
    // ============================================================

    $sql = "
        SELECT DISTINCT gt.id, gt.libelle_grade, gt.code_grade 
        FROM ref_grades_types gt
        WHERE " . implode(" AND ", $where) . "
        ORDER BY gt.libelle_grade ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $grades = $stmt->fetchAll();

    echo '<option value="">Sélectionner le grade...</option>';
    foreach ($grades as $g) {
        echo '<option value="' . htmlspecialchars($g['id']) . '" data-code="' . htmlspecialchars($g['code_grade']) . '">' . 
             htmlspecialchars($g['libelle_grade']) . '</option>';
    }
}

// =============================================
// 7. INDICE
// =============================================
if ($action === 'get_indice') {
    $corps_id      = $_POST['corps_id'] ?? null;
    $grade_type_id = $_POST['grade_type_id'] ?? null;

    if (!$corps_id || !$grade_type_id) {
        echo '0';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT indice 
        FROM ref_grille_indiciaire 
        WHERE corps_id = ? AND grade_type_id = ?
        LIMIT 1
    ");
    $stmt->execute([$corps_id, $grade_type_id]);
    $indice = $stmt->fetchColumn();

    echo $indice ?: '0';
}
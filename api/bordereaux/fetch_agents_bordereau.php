<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php'; 

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    $user_im = $_SESSION['user_im'];
    $stmtUser = $pdo->prepare("SELECT niveau, code_lieu_affectation, role_specifique FROM utilisateurs WHERE im = ?");
    $stmtUser->execute([$user_im]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) throw new Exception("Session utilisateur invalide.");

    $nivResp  = $user['niveau'] ?? '';
    $lieuResp = $user['code_lieu_affectation'] ?? '';

    $page   = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $typeB  = $_POST['typeB'] ?? 'creation_projet';
    $dest   = $_POST['dest'] ?? 'solde_et_pensions';
    $filter = $_POST['filter'] ?? 'tous';
    $search = $_POST['search'] ?? '';

    $start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
    $limit  = $length;
    $offset = $start;
    
    // mappingSelection : définit quelle colonne de la table d. est mise à 1 pour marquer l'ajout au bordereau
    $mappingSelection = [
        'solde_et_pensions'  => ($typeB === 'mandatement') ? 'solde_et_pensions_mandatement' : 'solde_et_pensions',
        'controle_financier' => 'controle_financier',
        'prefecture'         => 'prefecture',
        'fonction_publique'  => 'fonction_publique',
        'dren'               => 'dren'
    ];

    $mappingImpression = [
        'solde_et_pensions'  => ($typeB === 'mandatement') ? 'deja_imprime_mandatement' : 'deja_imprime_solde',
        'controle_financier' => 'deja_imprime_cde',
        'prefecture'         => 'deja_imprime_prefet',
        'fonction_publique'  => 'deja_imprime_fop',
        'dren'               => 'deja_imprime_dren'
    ];

    // 2. Application des colonnes finales
    $colSelection  = $mappingSelection[$dest] ?? 'solde_et_pensions';
    $colImpression = $mappingImpression[$dest] ?? 'deja_imprime_solde';

    // --- CONDITIONS ---
    $conditions = ["d.statut = 'ATTRIBUE'", "d.$colImpression = 0"];
    $params = [];

    // Verrous de validation d'étape (Uniquement pour création_projet)
    if ($typeB !== 'mandatement' && $nivResp !== 'regional') {
        if ($dest == 'solde_et_pensions') {
            $conditions[] = "(s.statut_dren = 'valide' OR 1=1)";
        } elseif ($dest == 'controle_financier') {
            $conditions[] = "(s.statut_solde = 'valide' OR 1=1)";
        } elseif ($dest == 'prefecture') {
            $conditions[] = "(s.statut_controle_financier = 'valide' OR 1=1)";
        }
    }

    // Filtres de types
    if ($filter === 'renouvellement') {
        $conditions[] = "(d.type_dos LIKE 'Contrat1' OR d.type_dos IN ('Avenant1', 'Avenant2'))";
    } elseif ($filter === 'avenant') {
        $conditions[] = "d.type_dos LIKE 'Avenant%'";
    } elseif ($filter === 'avancement_classe') {
        $conditions[] = "d.type_dos = 'Avancement_classe'";
    } elseif ($filter === 'avancement_echelon') {
        $conditions[] = "d.type_dos = 'Avancement_echelon'";
    } elseif ($filter === 'conge_annuel') {
        $conditions[] = "d.type_dos = 'Conge_annuel'";
    } elseif (in_array($filter, ['integration', 'titularisation'])) {
        $conditions[] = "d.type_dos = ?";
        $params[] = ucfirst($filter);
    }

    // Sécurité Géographique
    if ($nivResp === 'regional') {
        $conditions[] = "p.nom_region = ?";
        $params[] = $lieuResp;
    } elseif ($nivResp === 'district') {
        $conditions[] = "p.nom_district = ?";
        $params[] = $lieuResp;
    }

    // Recherche
    if (!empty($search)) {
        $conditions[] = "(d.im LIKE ? OR ec.nom LIKE ? OR ec.prenoms LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam; $params[] = $searchParam; $params[] = $searchParam;
    }

    $whereClause = "WHERE " . implode(" AND ", $conditions);

    // --- EXÉCUTION ---
    $sqlTotal = "SELECT COUNT(DISTINCT d.im) as total 
                 FROM demandes_numeros_dos d
                 LEFT JOIN suivi_agents_bordereau s ON d.im = s.im_agent
                 JOIN personnel_situation_actuelle sa ON d.im = sa.im
                 JOIN personnel_etat_civil ec ON d.im = ec.im
                 JOIN personnel_poste_actuel p ON d.im = p.im
                 $whereClause";

    error_log("SQL Query: " . $sqlTotal);
    error_log("Params: " . json_encode($params));
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute($params);
    $totalAgents = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $totalPages = max(1, ceil($totalAgents / $limit));

    $sql = "SELECT 
                GROUP_CONCAT(d.id) as ids_groupes,
                d.im, ec.nom, ec.prenoms, 
                sa.corps_actuel, sa.grade_actuel,
                p.nom_region, p.nom_district, p.nom_zap, p.nom_etablissement,
                p.type_fonction, p.type_etablissement,
                MAX(CASE WHEN d.$colSelection = 1 THEN 1 ELSE 0 END) as is_added 
            FROM demandes_numeros_dos d
            LEFT JOIN suivi_agents_bordereau s ON d.im = s.im_agent
            JOIN personnel_situation_actuelle sa ON d.im = sa.im
            JOIN personnel_etat_civil ec ON d.im = ec.im
            JOIN personnel_poste_actuel p ON d.im = p.im
            $whereClause 
            GROUP BY d.im
            ORDER BY is_added DESC, d.im ASC 
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Génération du HTML
    $data = [];

    if (count($agents) > 0) {
        $count = $offset + 1;
        foreach($agents as $row) {
            $isAdded = ($row['is_added'] == 1);
            $rowClass = $isAdded ? 'bg-emerald-50/40' : 'hover:bg-slate-50';
            
            $lieuService = $row['nom_etablissement'] ?: '-';
            if($row['type_fonction'] === 'Personnel administratif') {
                if($row['type_etablissement'] === 'DREN') $lieuService = "BUREAU DREN " . $row['nom_region'];
                else if($row['type_etablissement'] === 'CISCO') $lieuService = "BUREAU CISCO " . $row['nom_district'];
            }
            
            // Création des colonnes (td) pour cette ligne
            $ids = $row['ids_groupes'];
            $actionBtn = !$isAdded 
                ? "<button onclick=\"confirmAction('$ids', 'add', '$colSelection')\" class='bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-[9px] font-black'><i class='fas fa-plus mr-1'></i> Ajouter</button>" 
                : "<div class='flex items-center justify-center gap-2'><div class='w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-lg'><i class='fas fa-check'></i></div><button onclick=\"confirmAction('$ids', 'remove', '$colSelection')\" class='w-7 h-7 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-all border border-rose-100'><i class='fas fa-trash-alt'></i></button></div>";

            // On construit la ligne entière en HTML
            $data[] = [
                str_pad($count++, 2, '0', STR_PAD_LEFT), // Colonne 1
                htmlspecialchars($row['nom_region']),    // Colonne 2
                htmlspecialchars($row['nom_district']),  // Colonne 3
                htmlspecialchars($row['nom_zap']),       // Colonne 4
                htmlspecialchars($lieuService),          // Colonne 5
                htmlspecialchars($row['im']),            // Colonne 6
                htmlspecialchars($row['nom'])." ".htmlspecialchars($row['prenoms']), // Colonne 7
                htmlspecialchars($row['corps_actuel']),  // Colonne 8
                $actionBtn                               // Colonne 9 (le bouton)
            ];
        }
    }

    // Réponse au format JSON attendu par DataTables
    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => (int)$totalAgents,
        "recordsFiltered" => (int)$totalAgents,
        "data"            => $data // Les lignes HTML construites
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'total_pages' => 0]);
}
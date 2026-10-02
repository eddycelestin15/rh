<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json; charset=utf-8');
$user_im = $_SESSION['user_im'];
$action = $_POST['action'] ?? '';

if ($action == 'verifier_im') {
    $im = trim($_POST['im'] ?? '');
    $type_demande = trim($_POST['type_demande'] ?? '');

    if (empty($im)) {
        echo json_encode(['success' => false, 'message' => 'Le matricule est obligatoire.']);
        exit;
    }

    // Sélection de la table appropriée selon le type de demande
    if (in_array($type_demande, ['compensatrice', 'installation'])) {
        $stmt = $pdo->prepare("SELECT * FROM acte_formate_retraite WHERE im = ? ORDER BY id DESC LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM acte_formate WHERE im = ? ORDER BY id DESC LIMIT 1");
    }

    $stmt->execute([$im]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agent) {
        echo json_encode(['success' => false, 'message' => 'not_found']);
        exit;
    }

    $stmtPoste = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ? LIMIT 1");
    $stmtPoste->execute([$im]);
    $posteActuel = $stmtPoste->fetch(PDO::FETCH_ASSOC) ?: [];  
    
    $stmt = $pdo->prepare("SELECT niveau, code_lieu_affectation, role_specifique FROM utilisateurs WHERE im = ?");
    $stmt->execute([$user_im]);
    $user = $stmt->fetch();

    $user_niveau = $user['niveau']; 
    $user_lieu   = $user['code_lieu_affectation']; 
    $user_role   = $user['role_specifique'];

    $accesAutorise = false;
    $messageErreur = "Cet agent n'appartient pas à votre localité de responsabilité.";

    // Accès total pour admin
    if ($user_role === 'admin') {
        $accesAutorise = true;
    }
    // resp_solde - DISTRICT
    elseif ($user_role === 'resp_solde' && $user_niveau === 'district') {
        $districtAgent = trim($posteActuel['nom_district'] ?? '');
        $typeEtab      = trim($posteActuel['type_etablissement'] ?? '');
        
        if (!empty($districtAgent) 
            && strcasecmp($districtAgent, $user_lieu) === 0 
            && strcasecmp($typeEtab, 'CRFRP') !== 0) {
            $accesAutorise = true;
        }
    }
    // resp_solde - REGIONAL
    elseif ($user_role === 'resp_solde' && $user_niveau === 'regional') {
        $regionAgent = trim($posteActuel['nom_region'] ?? '');
        $typeEtab    = trim($posteActuel['type_etablissement'] ?? '');
        
        if (!empty($regionAgent) 
            && strcasecmp($regionAgent, $user_lieu) === 0 
            && strcasecmp($typeEtab, 'CRFRP') !== 0) {
            $accesAutorise = true;
        }
    }
    // resp_personnel_crfrp - CRFRP
    elseif ($user_role === 'resp_personnel_crfrp' && $user_niveau === 'crfrp') {
        $etabAgent = trim($posteActuel['nom_etablissement'] ?? '');
        
        if (!empty($etabAgent) && strcasecmp($etabAgent, $user_lieu) === 0) {
            $accesAutorise = true;
        }
    } 
    // resp_solde - CENTRAL
    elseif ($user_role === 'resp_solde' && $user_niveau === 'central') {
        $lieuService = trim($posteActuel['lieu_de_service'] ?? '');
        $typeEtab    = trim($posteActuel['type_etablissement'] ?? '');
        
        if (strcasecmp($typeEtab, 'MEN CENTRAL') === 0 
            || (!empty($lieuService) && strcasecmp($lieuService, $user_lieu) === 0)) {
            $accesAutorise = true;
        }
    }
    else {
        $accesAutorise = false;
        $messageErreur = "Vous n'avez pas les droits pour effectuer un mandatement.";
    }

    if (!$accesAutorise) {
        echo json_encode([
            'success' => false,
            'message' => $messageErreur
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'im'                     => $agent['im'],
            'nom'                    => $agent['nom'],
            'prenoms'                => $agent['prenoms'],
            'cin'                    => $agent['cin'] ?? '',
            'date_cin'               => $agent['date_cin'] ?? '',
            'lieu_cin'               => $agent['lieu_cin'] ?? '',
            'date_naiss'             => $agent['date_naiss'] ?? '',
            'lieu_naiss'             => $agent['lieu_naiss'] ?? '',
            'sexe'                   => $agent['sexe'] ?? '',
            'situation_matrimoniale' => $agent['situation_matrimoniale'] ?? '',
            'nombre_enfant'          => $agent['nombre_enfant'] ?? '',
            'statut_agent'           => $agent['statut_agent'] ?? '',
            'date_entree_admin'      => $agent['date_entree_admin'] ?? '',
            'imput_budg'             => $agent['imput_budg'] ?? '',
            'mode_paiement'          => $agent['mode_paiement'] ?? '',
            'ancien_corps'           => $agent['corps_actuel'] ?? '',
            'ancien_grade'           => $agent['grade_actuel'] ?? '',
            'ancien_indice'          => $agent['indice_actuel'] ?? '',
            'ancien_date_effet'      => $agent['date_d_effet_actuel'] ?? '',
            'type_fonction'          => $posteActuel['type_fonction'] ?? '',
            'type_etablissement'     => $posteActuel['type_etablissement'] ?? '',
            'nom_region'             => $posteActuel['nom_region'] ?? '',
            'nom_district'           => $posteActuel['nom_district'] ?? '',
            'nom_zap'                => $posteActuel['nom_zap'] ?? '',
            'nom_etablissement'      => $posteActuel['nom_etablissement'] ?? '',
            'lieu_de_service'        => $posteActuel['lieu_de_service'] ?? ''
        ]
    ]);
    exit;
}

if ($action == 'list_mandatement') {
    header('Content-Type: text/html; charset=utf-8');

    $stmtUser = $pdo->prepare("SELECT niveau, code_lieu_affectation, role_specifique FROM utilisateurs WHERE im = ?");
    $stmtUser->execute([$user_im]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $user_niveau = strtolower(trim($user['niveau'] ?? ''));
    $user_lieu   = trim($user['code_lieu_affectation'] ?? '');
    $user_role   = trim($user['role_specifique'] ?? '');

    $isSolde  = ($user_role === 'resp_solde');
    $isCRFRP  = ($user_role === 'resp_personnel_crfrp');
    $isAdmin  = ($user_role === 'admin');

    if (!$isSolde && !$isCRFRP && !$isAdmin) {
        echo ""; 
        exit;
    }

    $corpsMap = [];
    $stmtCorps = $pdo->prepare("SELECT libelle_corps, code_corps_ind, code_corps_cont, code_corps_fonc FROM ref_corps");
    $stmtCorps->execute();
    while ($c = $stmtCorps->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($c['code_corps_ind']))  $corpsMap[$c['code_corps_ind']]  = $c['libelle_corps'];
        if (!empty($c['code_corps_cont'])) $corpsMap[$c['code_corps_cont']] = $c['libelle_corps'];
        if (!empty($c['code_corps_fonc'])) $corpsMap[$c['code_corps_fonc']] = $c['libelle_corps'];
    }

    $gradesMap = [];
    $stmtGrades = $pdo->prepare("SELECT code_grade, libelle_grade FROM ref_grades_types");
    $stmtGrades->execute();
    while ($g = $stmtGrades->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($g['code_grade'])) {
            $gradesMap[$g['code_grade']] = $g['libelle_grade'];
        }
    }

    // Inclusion des trois tables : acte_formate, acte_formate_av_cont et acte_formate_retraite
    $sql = "
        SELECT 
            'acte_formate' AS source_table,
            a.id, a.type_demande, a.nom, a.prenoms, a.im, a.statut,
            a.statut AS statut_acte_formate,
            a.ref_mandatement AS ref_mandatement_acte_formate,
            a.corps_actuel, a.grade_actuel, a.code_corps_actuel, a.code_grade_actuel, 
            a.indice_actuel, a.date_d_effet_actuel,
            a.new_corps, a.new_grade, a.new_code_corps, a.new_code_grade, 
            a.new_indice, a.new_date_d_effet,
            p.nom_region, p.nom_district, p.nom_etablissement, p.type_etablissement, p.lieu_de_service
        FROM acte_formate a
        LEFT JOIN personnel_poste_actuel p ON a.im = p.im
        WHERE a.statut = 'en_attente' 
          AND a.type_demande IS NOT NULL

        UNION ALL

        SELECT 
            'acte_formate_av_cont' AS source_table,
            ac.id, ac.type_demande, ac.nom, ac.prenoms, ac.im, ac.statut,
            af.statut AS statut_acte_formate,
            af.ref_mandatement AS ref_mandatement_acte_formate,
            ac.corps_actuel, ac.grade_actuel, ac.code_corps_actuel, ac.code_grade_actuel, 
            ac.indice_actuel, ac.date_d_effet_actuel,
            ac.new_corps, ac.new_grade, ac.new_code_corps, ac.new_code_grade, 
            ac.new_indice, ac.new_date_d_effet,
            p.nom_region, p.nom_district, p.nom_etablissement, p.type_etablissement, p.lieu_de_service
        FROM acte_formate_av_cont ac
        LEFT JOIN acte_formate af ON ac.im = af.im
        LEFT JOIN personnel_poste_actuel p ON ac.im = p.im
        WHERE ac.statut = 'en_attente' 
          AND ac.type_demande IS NOT NULL

        UNION ALL

        SELECT 
            'acte_formate_retraite' AS source_table,
            ar.id, ar.type_demande, ar.nom, ar.prenoms, ar.im, ar.statut,
            ar.statut AS statut_acte_formate,
            ar.ref_mandatement AS ref_mandatement_acte_formate,
            ar.corps_actuel, ar.grade_actuel, ar.code_corps_actuel, ar.code_grade_actuel, 
            ar.indice_actuel, ar.date_d_effet_actuel,
            ar.new_corps, ar.new_grade, ar.new_code_corps, ar.new_code_grade, 
            ar.new_indice, ar.new_date_d_effet,
            p.nom_region, p.nom_district, p.nom_etablissement, p.type_etablissement, p.lieu_de_service
        FROM acte_formate_retraite ar
        LEFT JOIN personnel_poste_actuel p ON ar.im = p.im
        WHERE ar.statut = 'en_attente' 
          AND ar.type_demande IN ('compensatrice', 'installation')
          AND ar.num_acte IS NOT NULL 
          AND TRIM(ar.num_acte) != ''
    ";

    $whereFiltre = "";
    $params = [];

    if ($isAdmin) {
        $whereFiltre = " WHERE 1=1";
    } elseif ($isSolde && $user_niveau === 'district') {
        $whereFiltre = " WHERE nom_district = ? AND (type_etablissement IS NULL OR type_etablissement <> 'CRFRP')";
        $params[] = $user_lieu;
    } elseif ($isSolde && $user_niveau === 'regional') {
        $whereFiltre = " WHERE nom_region = ? AND (type_etablissement IS NULL OR type_etablissement <> 'CRFRP')";
        $params[] = $user_lieu;
    } elseif ($isSolde && $user_niveau === 'central') {
        $whereFiltre = " WHERE (type_etablissement = 'MEN CENTRAL' OR lieu_de_service = ?)";
        $params[] = $user_lieu;
    } elseif ($isCRFRP && $user_niveau === 'crfrp') {
        $whereFiltre = " WHERE nom_etablissement = ?";
        $params[] = $user_lieu;
    } else {
        echo "";
        exit;
    }

    $sqlFinal = "SELECT * FROM ($sql) AS liste $whereFiltre ORDER BY id DESC";

    $stmt = $pdo->prepare($sqlFinal);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $compteur = 1;
    foreach ($data as $row) {
        $jour_effet = 0;
        if (!empty($row['new_date_d_effet'])) {
            $jour_effet = (int)date('d', strtotime($row['new_date_d_effet']));
        }
        
        $libelle_type = $row['type_demande'];
        $is_avenant_contrat = ($row['type_demande'] === 'avenant_avec_contrat');
        $row['libelle_type'] = $libelle_type;
        $row['jour_effet'] = $jour_effet;

        switch($row['type_demande']) {
            case 'renouvellement': $libelle_type = "Renouvellement de contrat"; break;
            case 'avenant_avec_contrat': $libelle_type = "Avenant (Rappel différentiel moins perçu)"; break;
            case 'avancement_classe': $libelle_type = "Avancement de classe"; break;
            case 'avancement_echelon': $libelle_type = "Avancement d'échelon"; break;
            case 'avenant': $libelle_type = "Avenant (Reclassement d'un EFA)"; break;
            case 'integration': $libelle_type = "Intégration"; break;
            case 'titularisation': $libelle_type = "Titularisation"; break;
            case 'compensatrice': $libelle_type = "Compensatrice"; break;
            case 'installation': $libelle_type = "Installation"; break;
        }

        $row['libelle_type'] = $libelle_type;

        $class_libelle = $is_avenant_contrat ? 'text-amber-600 font-extrabold' : 'text-sky-600 font-bold';
        $icone = $is_avenant_contrat ? '<i class="fas fa-file-invoice-dollar mr-1"></i>' : '<i class="fas fa-check-circle mr-1"></i>';

        $est_desactive = false;
        if ($row['source_table'] === 'acte_formate_av_cont') {
            if (empty($row['ref_mandatement_acte_formate'])) {
                $est_desactive = true;
            }
        }

        if ($est_desactive) {
            $class_bouton = 'bg-gray-400 cursor-not-allowed opacity-60 shadow-none';
            $disabled_attr = 'disabled="disabled" title="En attente de validation dans l\'acte formaté"';
        } else {
            $class_bouton = $is_avenant_contrat ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-200' : 'bg-sky-600 hover:bg-sky-700 shadow-sm';
            $disabled_attr = '';
        }

        $row['libelle_corps_actuel'] = isset($corpsMap[$row['code_corps_actuel']]) ? $corpsMap[$row['code_corps_actuel']] : '---';
        $row['libelle_grade_actuel'] = isset($gradesMap[$row['code_grade_actuel']]) ? $gradesMap[$row['code_grade_actuel']] : '---';
        $row['new_libelle_corps'] = isset($corpsMap[$row['new_code_corps']]) ? $corpsMap[$row['new_code_corps']] : '---';
        $row['new_libelle_grade'] = isset($gradesMap[$row['new_code_grade']]) ? $gradesMap[$row['new_code_grade']] : '---';

        $jsonRow = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
        
        echo "<tr>
                <td class='text-center'>$compteur</td>
                <td class='$class_libelle'>$libelle_type</td>
                <td>{$row['nom']} {$row['prenoms']}</td>
                <td class='text-center'>{$row['im']}</td>
                <td>{$row['corps_actuel']}</td>
                <td>{$row['grade_actuel']}</td>
                <td class='text-center'>
                    <button $disabled_attr onclick='ouvrirModaleMandatement($jsonRow)' 
                            class='$class_bouton text-white px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center mx-auto'>
                        $icone Mandater
                    </button>
                </td>
              </tr>";
        $compteur++;
    }
    exit;
}

if ($action == 'sauvegarder_nouvel_agent') {
    $im = trim($_POST['im'] ?? '');

    $swal_type_demande     = $_POST['type_demande'] ?? null;
    $numero_acte           = $_POST['numero_acte'] ?? null;
    $date_acte             = !empty($_POST['date_acte']) ? $_POST['date_acte'] : null;
    $zone_formulaire       = $_POST['zone'] ?? ''; 
    $commune_id            = $_POST['commune_id'] ?? null; 
    $localite_service      = $_POST['localite_service_display'] ?? ''; 
    $statut                = $_POST['statut'] ?? '';
    $mode_paiement         = $_POST['mode_paiement'] ?? '';

    $ancien_corps_id       = $_POST['ancien_corps_id'] ?? null;   
    $ancien_grade_id       = $_POST['ancien_grade_id'] ?? null;   
    $ancien_indice         = !empty($_POST['ancien_indice']) ? (int)$_POST['ancien_indice'] : null;
    $ancien_date_effet     = !empty($_POST['ancien_date_effet']) ? $_POST['ancien_date_effet'] : null;
      
    $nouveau_date_effet    = !empty($_POST['nouveau_date_effet']) ? $_POST['nouveau_date_effet'] : null;
    $nouveau_indice        = !empty($_POST['nouveau_indice']) ? (int)$_POST['nouveau_indice'] : null;

    $numero_visa_finance   = $_POST['numero_visa_finance'] ?? null;
    $date_visa_finance     = !empty($_POST['date_visa_finance']) ? $_POST['date_visa_finance'] : null;
    $numero_visa_cde       = $_POST['numero_visa_cde'] ?? null;
    $date_visa_cde         = !empty($_POST['date_visa_cde']) ? $_POST['date_visa_cde'] : null;
    $nom_signataire        = $_POST['nom_signataire'] ?? null;
    $corps_signataire      = $_POST['corps_signataire'] ?? null;
    $etablissement_id      = $_POST['etablissement_id'] ?? null; 

    $type_fonction         = $_POST['type_fonction'] ?? '';       
    $niveau_demande        = $_POST['niveau_demande'] ?? '';      
    $cisco_selectionne     = $_POST['cisco_selectionne'] ?? '';   
    $zap_selectionnee      = $_POST['zap_selectionnee'] ?? '';    
    $etablissement_nom     = $_POST['etablissement_nom'] ?? '';   

    $user_niveau           = $_SESSION['niveau'] ?? 'regional';                  
    $user_role_specifique  = $_SESSION['role_specifique'] ?? 'resp_solde';       
    $user_code_lieu        = $_SESSION['code_lieu_affectation'] ?? 'INCONNU';     

    if (empty($im) || empty($swal_type_demande)) {
        echo json_encode(['success' => false, 'message' => 'Le matricule (IM) et le type de demande sont obligatoires.']);
        exit;
    }

    try {
        $code_localite = null;

        if (!empty($commune_id)) {
            $stmtCommune = $pdo->prepare("SELECT code_commune, nom_commune FROM ref_communes WHERE id = ? OR nom_commune = ? LIMIT 1");
            $stmtCommune->execute([$commune_id, $commune_id]);
            $communeData = $stmtCommune->fetch(PDO::FETCH_ASSOC);

            if ($communeData) {
                $code_localite = $zone_formulaire . $communeData['code_commune'];
            }
        }

        if ($swal_type_demande === 'renouvellement') {
            $acte = 'CONTRAT';
        } elseif ($swal_type_demande === 'avenant') {
            $acte = 'AVENANT';
        } elseif ($swal_type_demande === 'compensatrice' || $swal_type_demande === 'installation') {
            $acte = 'DECISION';
        } else {
            $acte = 'ARRETE';
        }

        $stmtMvt = $pdo->prepare("SELECT libelle_demande, code_mvt FROM code_mouvement WHERE type_demande = ? LIMIT 1");
        $stmtMvt->execute([$swal_type_demande]);
        $mvt = $stmtMvt->fetch();

        $libelle_demande = $mvt['libelle_demande'] ?? null;
        $code_mvt        = $mvt['code_mvt'] ?? null;

        if ($swal_type_demande === 'avenant') {
            $colonne_corps_actuel = 'code_corps_ind';
        } elseif ($swal_type_demande === 'renouvellement') {
            $colonne_corps_actuel = 'code_corps_cont';
        } else {
            $colonne_corps_actuel = 'code_corps_fonc';
        }
        
        $code_corps_actuel = null;
        if (!empty($ancien_corps_id)) {
            $stmtCorps = $pdo->prepare("SELECT {$colonne_corps_actuel} FROM ref_corps WHERE id = ? OR libelle_corps = ? LIMIT 1");
            $stmtCorps->execute([$ancien_corps_id, $ancien_corps_id]);
            $code_corps_actuel = $stmtCorps->fetchColumn() ?: null;
        }

        $code_grade_actuel = null;
        if (!empty($ancien_grade_id)) {
            $stmtGrade = $pdo->prepare("SELECT code_grade FROM ref_grades_types WHERE id = ? OR libelle_grade = ? LIMIT 1");
            $stmtGrade->execute([$ancien_grade_id, $ancien_grade_id]);
            $code_grade_actuel = $stmtGrade->fetchColumn() ?: null;
        }

        $grades_speciaux = ['A31E', 'A41E', 'ST0E'];
        $grades_speciaux_ind = ['A32E', 'A42E', '2C1E'];
        if ($swal_type_demande === 'avenant') {
            $colonne_new_corps = 'code_corps_ind';
        } elseif ($swal_type_demande === 'renouvellement' && (in_array($code_grade_actuel, $grades_speciaux))) {
            $colonne_new_corps = 'code_corps_cont';
        } elseif ($swal_type_demande === 'renouvellement' && (in_array($code_grade_actuel, $grades_speciaux_ind))) {
            $colonne_new_corps = 'code_corps_ind';
        } else {
            $colonne_new_corps = 'code_corps_fonc';
        }

        $new_corps = null;
        $new_grade = null;
        $nouveau_corps_id = $_POST['nouveau_corps_id'] ?? null;  
        $nouveau_grade_id = $_POST['nouveau_grade_id'] ?? null;
        
        if ($nouveau_corps_id) {
            $stmtC = $pdo->prepare("SELECT libelle_corps FROM ref_corps WHERE id = ?");
            $stmtC->execute([$nouveau_corps_id]);
            $new_corps = $stmtC->fetchColumn() ?: "";
        }
        if ($nouveau_grade_id) {
            $stmtG = $pdo->prepare("SELECT libelle_grade, code_grade FROM ref_grades_types WHERE id = ?");
            $stmtG->execute([$nouveau_grade_id]);
            $grade_data = $stmtG->fetch(PDO::FETCH_ASSOC);        
            if ($grade_data) {
                $new_grade = $grade_data['libelle_grade'];            
            }
        }
        $new_code_corps = null;
        if (!empty($nouveau_corps_id)) {
            $stmtNewCorps = $pdo->prepare("SELECT {$colonne_new_corps} FROM ref_corps WHERE id = ? OR libelle_corps = ? LIMIT 1");
            $stmtNewCorps->execute([$nouveau_corps_id, $nouveau_corps_id]);
            $new_code_corps = $stmtNewCorps->fetchColumn() ?: null;
        }

        $new_code_grade = null;
        if (!empty($nouveau_grade_id)) {
            $stmtNewGrade = $pdo->prepare("SELECT code_grade FROM ref_grades_types WHERE id = ? OR libelle_grade = ? LIMIT 1");
            $stmtNewGrade->execute([$nouveau_grade_id, $nouveau_grade_id]);
            $new_code_grade = $stmtNewGrade->fetchColumn() ?: null;
        }        
        $corps_speciaux_efa = ['L00A', 'U02C', 'K00A', 'U03B'];
        if ($statut === 'Contractuel EFA' && !empty($code_corps_actuel) && in_array($code_corps_actuel, $corps_speciaux_efa)) {
            $ancien_indice = ($ancien_indice ?? 0) + 15;
        }
        if ($statut === 'Contractuel EFA' && !empty($new_code_corps) && in_array($new_code_corps, $corps_speciaux_efa)) {
            $nouveau_indice = ($nouveau_indice ?? 0) + 15;
        }

        // Détermination de la table à mettre à jour selon le type de demande
        $tableCible = in_array($swal_type_demande, ['compensatrice', 'installation']) ? 'acte_formate_retraite' : 'acte_formate';

        $sqlUpdate = "UPDATE {$tableCible} 
                    SET 
                        acte                = :acte, 
                        num_acte            = :num_acte, 
                        date_acte           = :date_acte, 
                        libelle_demande     = :libelle_demande, 
                        code_mvt            = :code_mvt, 
                        mode_paiement       = :mode_paiement,
                        code_localite       = :code_localite, 
                        localite_service    = :localite_service, 
                        date_d_effet_actuel = :date_d_effet_actuel, 
                        indice_actuel       = :indice_actuel, 
                        new_corps           = :new_corps, 
                        new_grade           = :new_grade, 
                        new_code_corps      = :new_code_corps, 
                        new_code_grade      = :new_code_grade, 
                        new_indice          = :new_indice, 
                        new_date_d_effet    = :new_date_d_effet, 
                        num_visa_finance    = :num_visa_finance, 
                        date_visa_finance   = :date_visa_finance, 
                        num_visa_cde        = :num_visa_cde, 
                        date_visa_cde       = :date_visa_cde, 
                        nom_signataire      = :nom_signataire, 
                        corps_signataire    = :corps_signataire,
                        statut              = 'en_attente',
                        date_generation     = NOW()
                    WHERE im = :im 
                        AND type_demande = :type_demande 
                        AND (statut = 'en_attente' OR statut = 'termine')";

        $stmtFinal = $pdo->prepare($sqlUpdate);
        $stmtFinal->execute([
            ':acte'                => $acte,
            ':num_acte'            => $numero_acte,
            ':date_acte'           => $date_acte,
            ':libelle_demande'     => $libelle_demande,
            ':code_mvt'            => $code_mvt,
            ':mode_paiement'       => $mode_paiement,
            ':code_localite'       => $code_localite,       
            ':localite_service'    => $localite_service, 
            ':date_d_effet_actuel' => $ancien_date_effet,
            ':indice_actuel'       => $ancien_indice,
            ':new_corps'           => $new_corps,
            ':new_grade'           => $new_grade,
            ':new_code_corps'      => $new_code_corps,
            ':new_code_grade'      => $new_code_grade,
            ':new_indice'          => $nouveau_indice,
            ':new_date_d_effet'    => $nouveau_date_effet,
            ':num_visa_finance'    => $numero_visa_finance,
            ':date_visa_finance'   => $date_visa_finance,
            ':num_visa_cde'        => $numero_visa_cde,
            ':date_visa_cde'       => $date_visa_cde,
            ':nom_signataire'      => $nom_signataire,
            ':corps_signataire'    => $corps_signataire,
            ':im'                  => $im,
            ':type_demande'        => $swal_type_demande
        ]);

        if ($swal_type_demande === 'renouvellement') {
            $stmtCheck = $pdo->prepare("SELECT corps_actuel, grade_actuel FROM acte_formate WHERE im = ? LIMIT 1");
            $stmtCheck->execute([$im]);
            $agentCurrent = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            $corps_actuel_val = $agentCurrent['corps_actuel'] ?? '';
            $grade_actuel_val = strtoupper(trim($agentCurrent['grade_actuel'] ?? ''));

            if ($corps_actuel_val === $new_corps && ($grade_actuel_val === 'STAGIAIRE' || $grade_actuel_val === '2°CLASSE/1°ECHELON')) {
                
                $stmtAvCont = $pdo->prepare("
                    UPDATE acte_formate_av_cont 
                    SET 
                        code_localite     = :code_localite,
                        localite_service  = :localite_service,
                        new_corps         = :new_corps,
                        new_grade         = :new_grade,
                        new_code_corps    = :new_code_corps,
                        new_code_grade    = :new_code_grade,
                        new_indice        = :new_indice,
                        new_date_d_effet  = DATE_SUB(:new_date_d_effet, INTERVAL 1 YEAR),
                        statut            = 'en_attente',
                        date_generation   = NOW()
                    WHERE im = :im AND (statut = 'en_attente' OR statut = 'termine')
                ");

                $stmtAvCont->execute([
                    ':code_localite'    => $code_localite,
                    ':localite_service' => $localite_service,
                    ':new_corps'        => $new_corps,
                    ':new_grade'        => $new_grade,
                    ':new_code_corps'   => $new_code_corps,
                    ':new_code_grade'   => $new_code_grade,
                    ':new_indice'       => $nouveau_indice,
                    ':new_date_d_effet' => $nouveau_date_effet,
                    ':im'               => $im
                ]);
            }
        }

        $types_exclus = ['renouvellement', 'integration', 'titularisation', 'compensatrice', 'installation'];

        if (!in_array($swal_type_demande, $types_exclus)) {

            $stmtCorpsNew = $pdo->prepare("SELECT id, modele_id, categorie FROM ref_corps WHERE id = ? OR libelle_corps = ? LIMIT 1");
            $stmtCorpsNew->execute([$nouveau_corps_id, $nouveau_corps_id]);
            $corpsNewData = $stmtCorpsNew->fetch(PDO::FETCH_ASSOC);

            if ($corpsNewData) {
                $target_corps_id = $corpsNewData['id'];
                $target_modele_id = $corpsNewData['modele_id'];

                $stmtGActuel = $pdo->prepare("
                    SELECT id, libelle_grade, duree_annees 
                    FROM ref_grades_types 
                    WHERE (code_grade = ? OR libelle_grade = ?) 
                      AND (modele_id = ? OR modele_id IS NULL)
                    ORDER BY id ASC LIMIT 1
                ");
                $stmtGActuel->execute([$code_grade_actuel, $ancien_grade_id, $target_modele_id]);
                $gradeActuelData = $stmtGActuel->fetch(PDO::FETCH_ASSOC);

                $stmtGNouveau = $pdo->prepare("
                    SELECT id, libelle_grade 
                    FROM ref_grades_types 
                    WHERE (code_grade = ? OR libelle_grade = ?) 
                      AND (modele_id = ? OR modele_id IS NULL)
                    ORDER BY id ASC LIMIT 1
                ");
                $stmtGNouveau->execute([$new_code_grade, $nouveau_grade_id, $target_modele_id]);
                $gradeNouveauData = $stmtGNouveau->fetch(PDO::FETCH_ASSOC);

                if ($gradeActuelData && $gradeNouveauData && $gradeActuelData['id'] < $gradeNouveauData['id']) {
                    
                    $stmtClean = $pdo->prepare("DELETE FROM avancement_successif WHERE im = ? AND type_demande = ? AND utilisation = 'utilise'");
                    $stmtClean->execute([$im, $swal_type_demande]);

                    $stmtInter = $pdo->prepare("
                        SELECT id, code_grade, libelle_grade, duree_annees 
                        FROM ref_grades_types 
                        WHERE id > ? AND id < ? 
                          AND (modele_id = ? OR modele_id IS NULL)
                        ORDER BY id ASC
                    ");
                    $stmtInter->execute([$gradeActuelData['id'], $gradeNouveauData['id'], $target_modele_id]);
                    $gradesManquants = $stmtInter->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($gradesManquants)) {
                        $ordre_avancement = 1;
                        $date_effet_courante = $ancien_date_effet;
                        $duree_courante = (int)($gradeActuelData['duree_annees'] ?? 2);

                        $stmtInsertAvancement = $pdo->prepare("
                            INSERT INTO avancement_successif (
                                im,
                                type_demande,
                                ordre_avancement,
                                code_corps,
                                code_grade,
                                indice,
                                date_d_effet,
                                utilisation
                            ) VALUES (
                                :im,
                                :type_demande,
                                :ordre_avancement,
                                :code_corps,
                                :code_grade,
                                :indice,
                                :date_d_effet,
                                'non_utilise'
                            )
                        ");

                        foreach ($gradesManquants as $gInter) {
                            if (!empty($date_effet_courante)) {
                                $date_effet_courante = date('Y-m-d', strtotime("+$duree_courante years", strtotime($date_effet_courante)));
                            } else {
                                $date_effet_courante = null;
                            }

                            $stmtIndice = $pdo->prepare("
                                SELECT indice 
                                FROM ref_grille_indiciaire 
                                WHERE corps_id = ? AND grade_type_id = ? 
                                LIMIT 1
                            ");
                            $stmtIndice->execute([$target_corps_id, $gInter['id']]);
                            $indiceInter = $stmtIndice->fetchColumn();

                            if ($indiceInter === false) {
                                $indiceInter = null;
                            }

                            $stmtInsertAvancement->execute([
                                ':im'               => $im,
                                ':type_demande'      => $swal_type_demande,
                                ':ordre_avancement' => $ordre_avancement,
                                ':code_corps'       => $new_code_corps,
                                ':code_grade'       => $gInter['code_grade'],
                                ':indice'           => $indiceInter,
                                ':date_d_effet'     => $date_effet_courante
                            ]);

                            $duree_courante = (int)($gInter['duree_annees'] ?? 2);
                            $ordre_avancement++;
                        }
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Informations enregistrées avec succès.']);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'valider_avenant_avec_contrat') {
    $im                      = trim($_POST['im'] ?? '');
    $num_acte                = $_POST['num_avenant'] ?? null;
    $date_acte               = !empty($_POST['date_avenant']) ? $_POST['date_avenant'] : null;
    $num_visa_finance        = $_POST['visa_finance_avenant'] ?? null;
    $date_visa_finance      = !empty($_POST['date_finance_avenant']) ? $_POST['date_finance_avenant'] : null;
    $num_visa_cde            = $_POST['visa_cde_avenant'] ?? null;
    $date_visa_cde          = !empty($_POST['date_cde_avenant']) ? $_POST['date_cde_avenant'] : null;
    $nom_signataire          = $_POST['nom_signataire_avenant'] ?? null;
    $corps_signataire        = $_POST['corps_signataire_avenant'] ?? null;

    if (empty($im)) {
        echo json_encode(['success' => false, 'message' => 'Matricule manquant.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE acte_formate_av_cont 
            SET 
                num_acte          = :num_acte,
                date_acte         = :date_acte,
                num_visa_finance  = :num_visa_finance,
                date_visa_finance = :date_visa_finance,
                num_visa_cde      = :num_visa_cde,
                date_visa_cde     = :date_visa_cde,
                nom_signataire    = :nom_signataire,
                corps_signataire  = :corps_signataire,
                statut            = 'valide'
            WHERE im = :im AND statut = 'en_attente'
        ");

        $stmt->execute([
            ':num_acte'          => $num_acte,
            ':date_acte'         => $date_acte,
            ':num_visa_finance'  => $num_visa_finance,
            ':date_visa_finance' => $date_visa_finance,
            ':num_visa_cde'      => $num_visa_cde,
            ':date_visa_cde'     => $date_visa_cde,
            ':nom_signataire'    => $nom_signataire,
            ':corps_signataire'  => $corps_signataire,
            ':im'                => $im
        ]);

        echo json_encode(['success' => true, 'message' => 'Acte mis à jour avec succès.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_acte_formate_av_cont') {
    $im = $_GET['im'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM acte_formate_av_cont WHERE im = ?");
    $stmt->execute([$im]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $data ?: null
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
exit;
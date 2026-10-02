<?php
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
ini_set('display_errors', 0); 
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'Action manquante']);
        exit;
    }

    $action = $data['action'];
    $im = $data['im'] ?? null;
    $alerte_id_groupe = $data['alerte_id'] ?? null; 

    //Détermine les types de dossiers et leurs libellés exacts
    function determinerTypesDossiers($pdo, $im, $alerte_id_groupe) {
        if (!$im || !$alerte_id_groupe) return [];

        if (str_contains($alerte_id_groupe, 'ADMISSION_RETRAITE')) {
            $stmt = $pdo->prepare("SELECT grade_actuel FROM personnel_situation_actuelle WHERE im = ?");
            $stmt->execute([$im]);
            $grade_actuel = $stmt->fetchColumn() ?: 'DEPART A LA RETRAITE';
            
            return [[
                'type'         => 'Admission_retraite',
                'titre'        => $grade_actuel,
                'id_technique' => (string)$alerte_id_groupe
            ]];
        }

        if (str_contains($alerte_id_groupe, 'COMPENSATRICE')) {
            $stmt = $pdo->prepare("SELECT grade_actuel FROM personnel_situation_actuelle WHERE im = ?");
            $stmt->execute([$im]);
            $grade_actuel = $stmt->fetchColumn() ?: 'DEPART A LA RETRAITE';
            
            return [[
                'type'         => 'Compensatrice',
                'titre'        => $grade_actuel,
                'id_technique' => (string)$alerte_id_groupe
            ]];
        }

        if (str_contains($alerte_id_groupe, 'INSTALLATION')) {
            $stmt = $pdo->prepare("SELECT grade_actuel FROM personnel_situation_actuelle WHERE im = ?");
            $stmt->execute([$im]);
            $grade_actuel = $stmt->fetchColumn() ?: 'DEPART A LA RETRAITE';
            
            return [[
                'type'         => 'Installation',
                'titre'        => $grade_actuel,
                'id_technique' => (string)$alerte_id_groupe
            ]];
        }

        $stmt = $pdo->prepare("SELECT statut_actuel, categorie_actuel, code_corps_actuel, grade_actuel, date_d_effet_actuel FROM personnel_situation_actuelle WHERE im = ?");
        $stmt->execute([$im]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) return [];

        $statut = $agent['statut_actuel'] ?? '';
        $categorie = $agent['categorie_actuel'] ?? '';
        $code_corps = $agent['code_corps_actuel'] ?? '';
        $grade_actuel = $agent['grade_actuel'] ?? '';
        
        $grade_suivant = $grade_actuel;

        if (trim(strtoupper($grade_actuel)) === 'STAGIAIRE') {
            $grade_suivant = "2°CLASSE/1°ECHELON";
        } 
        elseif (preg_match('/(\d+)\s*°?\s*ECHELON/i', $grade_actuel, $matches)) {
            $echelon_actuel = (int)$matches[1];
            $echelon_suivant = $echelon_actuel + 1;
            $grade_suivant = preg_replace('/\d+\s*°?\s*ECHELON/i', $echelon_suivant . "°ECHELON", $grade_actuel);
        }

        $results = []; 

        if ($statut === 'Contractuel EFA') {
            if (str_contains($alerte_id_groupe, 'GROUPE') || str_contains($alerte_id_groupe, 'AVANCEMENT')) {
                $sql = "SELECT alerte_id, titre FROM v_moteur_alertes 
                        WHERE im = ? AND alerte_id LIKE 'HIDDEN_%_AVANCEMENT_%'
                        ORDER BY alerte_id ASC";
                $stmtR = $pdo->prepare($sql);
                $stmtR->execute([$im]);
                $alertes_hidden = $stmtR->fetchAll(PDO::FETCH_ASSOC);

                foreach ($alertes_hidden as $row) {
                    if (preg_match('/_AVANCEMENT_(\d+)$/', $row['alerte_id'], $matches)) {
                        $niveau = (int)$matches[1];
                        $idx = (in_array($categorie, ['II', 'III'])) ? ($niveau - 1) : ($niveau + 1);
                        if ($idx > 0) {
                            $results[] = [
                                'type' => 'Avenant' . $idx, 
                                'titre' => $row['titre'],
                                'id_technique' => (string)$row['alerte_id']
                            ];
                        }
                    }
                }
            } 
            elseif (str_contains($alerte_id_groupe, 'RNC')) {
                $n = str_contains($alerte_id_groupe, 'RNC1') ? '1' : '2';
                $first_char = substr($code_corps, 0, 1);

                $results[] = [
                    'type' => 'Contrat' . $n, 
                    'titre' => $grade_suivant, 
                    'id_technique' => (string)$alerte_id_groupe
                ];

                if ($first_char === 'J') {
                    $results[] = [
                        'type' => 'Avenant' . $n, 
                        'titre' => $grade_suivant, 
                        'id_technique' => (string)$alerte_id_groupe
                    ];
                }
            }
            else if (str_contains($alerte_id_groupe, 'INTG')) {
                $stmt_check = $pdo->prepare("SELECT categorie_actuel, grade_actuel, indice_actuel 
                                            FROM personnel_situation_actuelle 
                                            WHERE im = ?");
                $stmt_check->execute([$im]);
                $infos_agent = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($infos_agent) {
                    $cat_agent = trim($infos_agent['categorie_actuel'] ?? '');
                    $indice_actuel = (int)($infos_agent['indice_actuel'] ?? 0);
                    $grade_final = $infos_agent['grade_actuel']; 
                } else {
                    $cat_agent = '';
                    $indice_actuel = 0;
                    $grade_final = "AGENT_INTROUVABLE";
                }

                $table_reference = "";
                if ($cat_agent === 'II') {
                    $table_reference = "ref_grade_cat2";
                } elseif ($cat_agent === 'III') {
                    $table_reference = "ref_grade_cat3";
                }

                if (!empty($table_reference)) {
                    $sql = "SELECT libelle_grade 
                            FROM $table_reference 
                            WHERE indice >= :ind 
                            ORDER BY indice ASC 
                            LIMIT 1";
                    
                    $stmt_int = $pdo->prepare($sql);
                    $stmt_int->execute(['ind' => $indice_actuel]);
                    $res = $stmt_int->fetchColumn();
                    
                    if ($res) {
                        $grade_final = $res;
                    }
                }

                $results[] = [
                    'type' => 'Intégration', 
                    'titre' => $grade_final, 
                    'id_technique' => (string)$alerte_id_groupe
                ];
            }
        } 
        else {
            if (($statut === 'Fonctionnaire') && (str_contains($alerte_id_groupe, 'GROUPE') || str_contains($alerte_id_groupe, 'AVANCEMENT'))) {
                $sqlGrades = "SELECT titre FROM v_moteur_alertes 
                            WHERE im = ? AND alerte_id LIKE 'HIDDEN_%'
                            ORDER BY date_reception_technique ASC";
                $stmtG = $pdo->prepare($sqlGrades);
                $stmtG->execute([$im]);
                $listeGrades = $stmtG->fetchAll(PDO::FETCH_COLUMN);
                $typeAvancement = 'Avancement_echelon';

                if (!empty($listeGrades)) {
                    $titreDetaille = implode(', ', $listeGrades);
                    
                    foreach ($listeGrades as $titre) {
                        $t = strtoupper($titre); 
                        if (
                            str_contains($t, '1°CLASSE/1°ECHELON') || 
                            str_contains($t, 'PRINCIPAL/1°ECHELON') || 
                            str_contains($t, 'CLASSE EXCEPTIONNELLE/1°ECHELON')
                        ) {
                            $typeAvancement = 'Avancement_classe';
                            break; 
                        }
                    }
                } else {
                    $titreDetaille = "Régularisation de carrière (Tous retards)";
                }

                $results[] = [
                    'type' => $typeAvancement, 
                    'titre' => $titreDetaille, 
                    'id_technique' => (string)$alerte_id_groupe
                ];
            }

            if (str_contains($alerte_id_groupe, 'TITU')) {
                $results[] = [
                    'type' => 'Titularisation', 
                    'titre' => '2°CLASSE/1°ECHELON', 
                    'id_technique' => (string)$alerte_id_groupe
                ];
            }
        }
        return $results;
    }

    //Extrait la localisation et détermine dans quels champs DOS
    function obtenirLocalisationAgent($pdo, $im, $alerte_id_groupe) {
        $stmtLoc = $pdo->prepare("SELECT nom_region, nom_district, type_etablissement, nom_etablissement, lieu_de_service FROM personnel_poste_actuel WHERE im = ?");
        $stmtLoc->execute([$im]);
        $loc = $stmtLoc->fetch(PDO::FETCH_ASSOC);

        $stmtSit = $pdo->prepare("SELECT statut_actuel, categorie_actuel FROM personnel_situation_actuelle WHERE im = ?");
        $stmtSit->execute([$im]);
        $sit = $stmtSit->fetch(PDO::FETCH_ASSOC);

        $res = [
            'central'  => null,
            'region'   => null,
            'district' => null,
            'crfrp'    => null
        ];

        if (!$loc) {
            return $res;
        }

        $typeEtab   = strtoupper(trim($loc['type_etablissement'] ?? ''));
        $nomRegion  = !empty($loc['nom_region']) ? $loc['nom_region'] : null;
        $nomDist    = !empty($loc['nom_district']) ? $loc['nom_district'] : null;
        $nomEtab    = !empty($loc['nom_etablissement']) ? $loc['nom_etablissement'] : null;

        $statut     = $sit['statut_actuel'] ?? '';
        $categorie  = strtoupper(trim($sit['categorie_actuel'] ?? ''));

        // Détection des types d'actes
        $isAdmissionRetraite = str_contains($alerte_id_groupe, 'ADMISSION_RETRAITE');
        $isInstallation      = str_contains($alerte_id_groupe, 'INSTALLATION');
        $isCompensatrice     = str_contains($alerte_id_groupe, 'COMPENSATRICE');
        $isRenouvellement    = str_contains($alerte_id_groupe, 'RNC');
        $isAvenant          = str_contains($alerte_id_groupe, 'GROUPE') || str_contains($alerte_id_groupe, 'AVANCEMENT');
        $isAvancement       = str_contains($alerte_id_groupe, 'GROUPE') || str_contains($alerte_id_groupe, 'AVANCEMENT');
        $isIntegration      = str_contains($alerte_id_groupe, 'INTG');
        $isTitularisation   = str_contains($alerte_id_groupe, 'TITU');

        // Établissements de niveau District
        $etabsDistrict = ['CISCO', 'LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'];

        // Agent CRFRP
        if ($typeEtab === 'CRFRP') {
            if ($isAdmissionRetraite || $isInstallation) {
                // Niveau central + Région + CRFRP (Pas de District)
                $res['crfrp']   = $nomEtab;
                $res['region']  = $nomRegion;
                $res['central'] = 'ANTANANARIVO';
            } else {
                // Renouvellement, Avenant, Avancement, Intégration, Titularisation, Compensatrice
                // Niveau régional + CRFRP (Pas de District)
                $res['crfrp']   = $nomEtab;
                $res['region']  = $nomRegion;
            }
        }
        // Agent (CISCO, LYCEE, COLLEGE, PRIMAIRE, PRESOLAIRE)
        elseif (in_array($typeEtab, $etabsDistrict)) {
            // A. Admission Retraite & Installation (resp_retraite)
            if ($isAdmissionRetraite || $isInstallation) {
                $res['district'] = $nomDist;
                $res['region']   = $nomRegion;
                $res['central']  = 'ANTANANARIVO';
            }
            // B. Indemnité Compensatrice (resp_retraite)
            elseif ($isCompensatrice) {
                $res['district'] = $nomDist;
                $res['region']   = $nomRegion;
            }
            // C. Non Encadré (EFA Catégories I, II, III) -> Renouvellement, Avenant, Intégration
            elseif ($statut === 'Contractuel EFA') {
                if ($isRenouvellement || $isAvenant || $isIntegration) {
                    $res['district'] = $nomDist;
                    $res['region']   = $nomRegion;
                }
            }
            // D. Encadré (Fonctionnaires ou Catégories supérieures) -> Avancement, Titularisation
            else {
                if ($isAvancement || $isTitularisation) {
                    $res['district'] = $nomDist;
                    $res['region']   = $nomRegion;
                }
            }
        }
        // Agent DREN
        elseif ($typeEtab === 'DREN') {
            $res['region'] = $nomRegion;
            if ($isAdmissionRetraite || $isInstallation) {
                $res['central'] = 'ANTANANARIVO';
            }
        }
        // Agent MEN CENTRAL
        elseif ($typeEtab === 'MEN CENTRAL') {
            $res['central'] = !empty($loc['lieu_de_service']) ? $loc['lieu_de_service'] : 'ANTANANARIVO';
        }

        return $res;
    }

    switch ($action) {
        case 'demander_numero':
            if (!$im) {
                echo json_encode(['success' => false, 'message' => 'IM manquant']);
                exit;
            }

            $types = determinerTypesDossiers($pdo, $im, $alerte_id_groupe);        
            $loc = obtenirLocalisationAgent($pdo, $im, $alerte_id_groupe);

            if (empty($types)) {
                $types[] = [
                    'id_technique' => (string)$alerte_id_groupe,
                    'type'         => 'DOSSIER',
                    'titre'        => 'DOSSIER ADMINISTRATIF'
                ];
            }

            $pdo->beginTransaction();
            try {
                foreach ($types as $t) {
                    // 1. Insertion / Mise à jour dans la table demandes_numeros_dos
                    $stmt = $pdo->prepare("INSERT INTO demandes_numeros_dos 
                        (im, alerte_id, type_dos, type_titre, statut, date_demande, dos_central, dos_region, dos_district, dos_crfrp) 
                        VALUES (:im, :aid, :typ, :titre, 'EN_ATTENTE', NOW(), :central, :reg, :dist, :crfrp) 
                        ON DUPLICATE KEY UPDATE 
                            date_demande = NOW(), 
                            statut = 'EN_ATTENTE',
                            dos_central = VALUES(dos_central),
                            dos_region = VALUES(dos_region), 
                            dos_district = VALUES(dos_district),
                            dos_crfrp = VALUES(dos_crfrp)");
                    
                    $stmt->execute([
                        'im'      => (string)$im, 
                        'aid'     => (string)$t['id_technique'], 
                        'typ'     => (string)$t['type'], 
                        'titre'   => (string)$t['titre'], 
                        'central' => $loc['central'],
                        'reg'     => $loc['region'], 
                        'dist'    => $loc['district'],
                        'crfrp'   => $loc['crfrp']  
                    ]);

                    // 2. Traitement d'insertion dans la table acte_formate_av_cont (pour avenant_avec_contrat)
                    $alerte_id = (string)$t['id_technique'];
                    $type_titre = (string)$t['titre'];

                    $cond1 = (str_contains($alerte_id, 'RNC1') && $type_titre === '2°CLASSE/1°ECHELON');
                    $cond2 = (str_contains($alerte_id, 'RNC2') && $type_titre === '2°CLASSE/2°ECHELON');

                    if ($cond1 || $cond2) {
                        // A. Récupération des informations du mouvement (type_demande = avenant_avec_contrat)
                        $stmtMvt = $pdo->prepare("SELECT libelle_demande, code_mvt FROM code_mouvement WHERE type_demande = 'avenant_avec_contrat' LIMIT 1");
                        $stmtMvt->execute();
                        $mvt = $stmtMvt->fetch(PDO::FETCH_ASSOC);

                        // B. Récupération des informations d'état civil
                        $stmtCivil = $pdo->prepare("SELECT cin, date_cin, lieu_cin, nom, prenoms, date_naiss, lieu_naiss, situation_familiale, sexe, nbr_enfant_bc FROM personnel_etat_civil WHERE im = ?");
                        $stmtCivil->execute([$im]);
                        $civil = $stmtCivil->fetch(PDO::FETCH_ASSOC);

                        // C. Récupération des informations de la situation actuelle
                        $stmtSit = $pdo->prepare("SELECT imput_budg, mode_paiement, statut_actuel, date_entree_admin, corps_actuel, grade_actuel, code_corps_actuel, date_d_effet_actuel, indice_actuel FROM personnel_situation_actuelle WHERE im = ?");
                        $stmtSit->execute([$im]);
                        $sit = $stmtSit->fetch(PDO::FETCH_ASSOC);

                        // D. Récupération du code_grade à partir de ref_grades_types en associant le corps_actuel et grade_actuel
                        $code_grade_actuel = null;
                        if (!empty($sit['corps_actuel']) && !empty($sit['grade_actuel'])) {
                            $sqlGrade = "SELECT rgt.code_grade 
                                        FROM ref_grades_types rgt
                                        JOIN ref_corps rc ON rgt.modele_id = rc.modele_id
                                        WHERE rc.libelle_corps = ? AND rgt.libelle_grade = ?
                                        LIMIT 1";
                            $stmtGrade = $pdo->prepare($sqlGrade);
                            $stmtGrade->execute([$sit['corps_actuel'], $sit['grade_actuel']]);
                            $code_grade_actuel = $stmtGrade->fetchColumn() ?: null;
                        }

                        // E. Insertion / Mise à jour dans la table acte_formate_av_cont
                        $sqlInsertActe = "INSERT INTO acte_formate_av_cont (
                            type_demande, acte, libelle_demande, code_mvt, cin, date_cin, lieu_cin, im, nom, prenoms, 
                            date_naiss, lieu_naiss, situation_matrimoniale, sexe, nombre_enfant, code_logement, 
                            code_ameublement, code_budget, imput_budg, mode_paiement, statut_agent, date_entree_admin, 
                            corps_actuel, grade_actuel, code_corps_actuel, code_grade_actuel, date_d_effet_actuel, 
                            indice_actuel, statut
                        ) VALUES (
                            'avenant_avec_contrat', 'AVENANT', :libelle_demande, :code_mvt, :cin, :date_cin, :lieu_cin, :im, :nom, :prenoms, 
                            :date_naiss, :lieu_naiss, :situation_matrimoniale, :sexe, :nombre_enfant, '0', 
                            'I', '00', :imput_budg, :mode_paiement, :statut_agent, :date_entree_admin, 
                            :corps_actuel, :grade_actuel, :code_corps_actuel, :code_grade_actuel, :date_d_effet_actuel, 
                            :indice_actuel, 'en_attente'
                        )
                        ON DUPLICATE KEY UPDATE 
                            type_demande = VALUES(type_demande),
                            acte = VALUES(acte),
                            libelle_demande = VALUES(libelle_demande),
                            code_mvt = VALUES(code_mvt),
                            cin = VALUES(cin),
                            date_cin = VALUES(date_cin),
                            lieu_cin = VALUES(lieu_cin),
                            nom = VALUES(nom),
                            prenoms = VALUES(prenoms),
                            date_naiss = VALUES(date_naiss),
                            lieu_naiss = VALUES(lieu_naiss),
                            situation_matrimoniale = VALUES(situation_matrimoniale),
                            sexe = VALUES(sexe),
                            nombre_enfant = VALUES(nombre_enfant),
                            imput_budg = VALUES(imput_budg),
                            mode_paiement = VALUES(mode_paiement),
                            statut_agent = VALUES(statut_agent),
                            date_entree_admin = VALUES(date_entree_admin),
                            corps_actuel = VALUES(corps_actuel),
                            grade_actuel = VALUES(grade_actuel),
                            code_corps_actuel = VALUES(code_corps_actuel),
                            code_grade_actuel = VALUES(code_grade_actuel),
                            date_d_effet_actuel = VALUES(date_d_effet_actuel),
                            indice_actuel = VALUES(indice_actuel),
                            statut = 'en_attente',
                            solde_et_pensions_mandatement = 0,
                            deja_imprime_mandatement = 0,
                            bordereaux_mandatement = NULL,
                            ref_mandatement = NULL,
                            date_reference_bordereau = NULL";

                        $stmtInsert = $pdo->prepare($sqlInsertActe);
                        $stmtInsert->execute([
                            'libelle_demande'        => $mvt['libelle_demande'] ?? null,
                            'code_mvt'               => $mvt['code_mvt'] ?? null,
                            'cin'                    => $civil['cin'] ?? null,
                            'date_cin'               => $civil['date_cin'] ?? null,
                            'lieu_cin'               => $civil['lieu_cin'] ?? null,
                            'im'                     => $im,
                            'nom'                    => $civil['nom'] ?? null,
                            'prenoms'                => $civil['prenoms'] ?? null,
                            'date_naiss'             => $civil['date_naiss'] ?? null,
                            'lieu_naiss'             => $civil['lieu_naiss'] ?? null,
                            'situation_matrimoniale' => $civil['situation_familiale'] ?? null,
                            'sexe'                   => $civil['sexe'] ?? null,
                            'nombre_enfant'          => $civil['nbr_enfant_bc'] ?? 0,
                            'imput_budg'             => $sit['imput_budg'] ?? null,
                            'mode_paiement'          => $sit['mode_paiement'] ?? null,
                            'statut_agent'           => $sit['statut_actuel'] ?? null,
                            'date_entree_admin'      => $sit['date_entree_admin'] ?? null,
                            'corps_actuel'           => $sit['corps_actuel'] ?? null,
                            'grade_actuel'           => $sit['grade_actuel'] ?? null,
                            'code_corps_actuel'      => $sit['code_corps_actuel'] ?? null,
                            'code_grade_actuel'      => $code_grade_actuel,
                            'date_d_effet_actuel'    => $sit['date_d_effet_actuel'] ?? null,
                            'indice_actuel'          => $sit['indice_actuel'] ?? null
                        ]);
                    }

                    // 3. Traitement d'insertion dans la table acte_formate_retraite (pour Compensatrice et Installation)
                    $type_dos_courant = strtolower((string)$t['type']);
                    if ($type_dos_courant === 'compensatrice' || $type_dos_courant === 'installation') {
                        // A. Récupération des informations du mouvement depuis code_mouvement
                        $stmtMvt = $pdo->prepare("SELECT libelle_demande, code_mvt FROM code_mouvement WHERE type_demande = ? LIMIT 1");
                        $stmtMvt->execute([$type_dos_courant]);
                        $mvt = $stmtMvt->fetch(PDO::FETCH_ASSOC);

                        // B. Récupération des informations d'état civil
                        $stmtCivil = $pdo->prepare("SELECT cin, date_cin, lieu_cin, nom, prenoms, date_naiss, lieu_naiss, situation_familiale, sexe, nbr_enfant_bc FROM personnel_etat_civil WHERE im = ?");
                        $stmtCivil->execute([$im]);
                        $civil = $stmtCivil->fetch(PDO::FETCH_ASSOC);

                        // C. Récupération des informations de la situation actuelle
                        $stmtSit = $pdo->prepare("SELECT imput_budg, mode_paiement, statut_actuel, date_entree_admin, corps_actuel, grade_actuel, code_corps_actuel, date_d_effet_actuel, indice_actuel FROM personnel_situation_actuelle WHERE im = ?");
                        $stmtSit->execute([$im]);
                        $sit = $stmtSit->fetch(PDO::FETCH_ASSOC);

                        // D. Récupération du code_grade
                        $code_grade_actuel = null;
                        if (!empty($sit['corps_actuel']) && !empty($sit['grade_actuel'])) {
                            $sqlGrade = "SELECT rgt.code_grade 
                                        FROM ref_grades_types rgt
                                        JOIN ref_corps rc ON rgt.modele_id = rc.modele_id
                                        WHERE rc.libelle_corps = ? AND rgt.libelle_grade = ?
                                        LIMIT 1";
                            $stmtGrade = $pdo->prepare($sqlGrade);
                            $stmtGrade->execute([$sit['corps_actuel'], $sit['grade_actuel']]);
                            $code_grade_actuel = $stmtGrade->fetchColumn() ?: null;
                        }

                        // E. Insertion / Mise à jour dans la table acte_formate_retraite
                        $sqlInsertRetraite = "INSERT INTO acte_formate_retraite (
                            type_demande, acte, libelle_demande, code_mvt, cin, date_cin, lieu_cin, im, nom, prenoms, 
                            date_naiss, lieu_naiss, situation_matrimoniale, sexe, nombre_enfant, code_logement, 
                            code_ameublement, code_budget, imput_budg, mode_paiement, statut_agent, date_entree_admin, 
                            corps_actuel, grade_actuel, code_corps_actuel, code_grade_actuel, date_d_effet_actuel, 
                            indice_actuel, statut
                        ) VALUES (
                            :type_demande, 'DECISION', :libelle_demande, :code_mvt, :cin, :date_cin, :lieu_cin, :im, :nom, :prenoms, 
                            :date_naiss, :lieu_naiss, :situation_matrimoniale, :sexe, :nombre_enfant, '0', 
                            'I', '00', :imput_budg, :mode_paiement, :statut_agent, :date_entree_admin, 
                            :corps_actuel, :grade_actuel, :code_corps_actuel, :code_grade_actuel, :date_d_effet_actuel, 
                            :indice_actuel, 'en_attente'
                        )
                        ON DUPLICATE KEY UPDATE 
                            acte = VALUES(acte),
                            libelle_demande = VALUES(libelle_demande),
                            code_mvt = VALUES(code_mvt),
                            cin = VALUES(cin),
                            date_cin = VALUES(date_cin),
                            lieu_cin = VALUES(lieu_cin),
                            nom = VALUES(nom),
                            prenoms = VALUES(prenoms),
                            date_naiss = VALUES(date_naiss),
                            lieu_naiss = VALUES(lieu_naiss),
                            situation_matrimoniale = VALUES(situation_matrimoniale),
                            sexe = VALUES(sexe),
                            nombre_enfant = VALUES(nombre_enfant),
                            imput_budg = VALUES(imput_budg),
                            mode_paiement = VALUES(mode_paiement),
                            statut_agent = VALUES(statut_agent),
                            date_entree_admin = VALUES(date_entree_admin),
                            corps_actuel = VALUES(corps_actuel),
                            grade_actuel = VALUES(grade_actuel),
                            code_corps_actuel = VALUES(code_corps_actuel),
                            code_grade_actuel = VALUES(code_grade_actuel),
                            date_d_effet_actuel = VALUES(date_d_effet_actuel),
                            indice_actuel = VALUES(indice_actuel),
                            statut = 'en_attente',
                            solde_et_pensions_mandatement = 0,
                            deja_imprime_mandatement = 0,
                            bordereaux_mandatement = NULL,
                            ref_mandatement = NULL,
                            date_reference_bordereau = NULL";

                        $stmtInsertR = $pdo->prepare($sqlInsertRetraite);
                        $stmtInsertR->execute([
                            'type_demande'           => $type_dos_courant,
                            'libelle_demande'        => $mvt['libelle_demande'] ?? null,
                            'code_mvt'               => $mvt['code_mvt'] ?? null,
                            'cin'                    => $civil['cin'] ?? null,
                            'date_cin'               => $civil['date_cin'] ?? null,
                            'lieu_cin'               => $civil['lieu_cin'] ?? null,
                            'im'                     => $im,
                            'nom'                    => $civil['nom'] ?? null,
                            'prenoms'                => $civil['prenoms'] ?? null,
                            'date_naiss'             => $civil['date_naiss'] ?? null,
                            'lieu_naiss'             => $civil['lieu_naiss'] ?? null,
                            'situation_matrimoniale' => $civil['situation_familiale'] ?? null,
                            'sexe'                   => $civil['sexe'] ?? null,
                            'nombre_enfant'          => $civil['nbr_enfant_bc'] ?? 0,
                            'imput_budg'             => $sit['imput_budg'] ?? null,
                            'mode_paiement'          => $sit['mode_paiement'] ?? null,
                            'statut_agent'           => $sit['statut_actuel'] ?? null,
                            'date_entree_admin'      => $sit['date_entree_admin'] ?? null,
                            'corps_actuel'           => $sit['corps_actuel'] ?? null,
                            'grade_actuel'           => $sit['grade_actuel'] ?? null,
                            'code_corps_actuel'      => $sit['code_corps_actuel'] ?? null,
                            'code_grade_actuel'      => $code_grade_actuel,
                            'date_d_effet_actuel'    => $sit['date_d_effet_actuel'] ?? null,
                            'indice_actuel'          => $sit['indice_actuel'] ?? null
                        ]);
                    }
                }
                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'valider_responsable':
            if (!isset($data['numeros'])) {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
                exit;
            }

            $loc = obtenirLocalisationAgent($pdo, $im, $alerte_id_groupe);
            $besoins = determinerTypesDossiers($pdo, $im, $alerte_id_groupe);
            $infosMap = [];
            foreach($besoins as $b) { 
                $infosMap[$b['type']] = ['id_tech' => $b['id_technique'], 'titre' => $b['titre']]; 
            }

            $pdo->beginTransaction();
            try {
                foreach ($data['numeros'] as $type_dos => $num) {
                    if (empty($num)) continue;
                    
                    $id_technique_reel = $infosMap[$type_dos]['id_tech'] ?? $alerte_id_groupe;
                    $titre_final = $infosMap[$type_dos]['titre'] ?? 'DOSSIER ADMINISTRATIF';

                    $stmt1 = $pdo->prepare("INSERT INTO demandes_numeros_dos 
                        (im, alerte_id, type_dos, type_titre, numero_dos, statut, dos_central, dos_region, dos_district, dos_crfrp, date_demande, date_attribution) 
                        VALUES (:im, :aid, :typ, :titre, :num, 'ATTRIBUE', :central, :reg, :dist, :crfrp, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE 
                            numero_dos = VALUES(numero_dos), 
                            statut = 'ATTRIBUE', 
                            date_attribution = NOW(), 
                            type_titre = VALUES(type_titre), 
                            dos_central = VALUES(dos_central),
                            dos_region = VALUES(dos_region), 
                            dos_district = VALUES(dos_district),
                            dos_crfrp = VALUES(dos_crfrp)");

                    $stmt1->execute([
                        'im'      => (string)$im, 
                        'aid'     => (string)$id_technique_reel,
                        'typ'     => (string)$type_dos, 
                        'titre'   => (string)$titre_final, 
                        'num'     => (string)$num,
                        'central' => $loc['central'],
                        'reg'     => $loc['region'],   
                        'dist'    => $loc['district'],
                        'crfrp'   => $loc['crfrp']  
                    ]);

                    $stmt2 = $pdo->prepare("INSERT INTO reponses_dos (im, type_dos, numero_attribue, date_reponse, lu) 
                        VALUES (:im, :typ, :num, NOW(), 0)
                        ON DUPLICATE KEY UPDATE 
                            numero_attribue = VALUES(numero_attribue), 
                            date_reponse = NOW(), 
                            lu = 0");

                    $stmt2->execute([
                        'im'  => (string)$im, 
                        'typ' => (string)$type_dos, 
                        'num' => (string)$num
                    ]);
                }

                $pdo->commit();

                $stmtAgentInfo = $pdo->prepare("SELECT categorie_actuel FROM personnel_situation_actuelle WHERE im = ?");
                $stmtAgentInfo->execute([$im]);
                $agentCat = $stmtAgentInfo->fetchColumn() ?: '';

                echo json_encode([
                    'success' => true,
                    'categorie' => $agentCat,
                    'besoins' => $besoins
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'analyser_besoins':
            $types = determinerTypesDossiers($pdo, $im, $alerte_id_groupe);
            if (empty($types)) {
                echo json_encode(['success' => false, 'message' => 'Aucun dossier détecté.']);
            } else {
                $formatted = array_map(function($t) {
                    $libelle_acte = 'DOSSIER'; 
                    if (str_starts_with($t['type'], 'Avenant')) {
                        $libelle_acte = 'AVENANT';
                    } elseif (str_starts_with($t['type'], 'Contrat')) {
                        $libelle_acte = 'CONTRAT';
                    } elseif ($t['type'] === 'Intégration') {
                        $libelle_acte = 'INTÉGRATION';
                    } elseif ($t['type'] === 'Titularisation') {
                        $libelle_acte = 'TITULARISATION';
                    } elseif ($t['type'] === 'Admission_retraite') {
                        $libelle_acte = 'ADMISSION À LA RETRAITE';
                    } elseif ($t['type'] === 'Compensatrice') {
                        $libelle_acte = 'INDEMNITÉ COMPENSATRICE';
                    } elseif ($t['type'] === 'Installation') {
                        $libelle_acte = "INDEMNITÉ D'INSTALLATION";
                    } elseif (str_contains($t['type'], 'Avancement_classe')) {
                        $libelle_acte = "AVANCEMENT DE CLASSE ET D'ECHELON";
                    } elseif (str_contains($t['type'], 'Avancement_echelon')) {
                        $libelle_acte = "AVANCEMENT D'ECHELON";
                    }
                    return [
                        'type_dos'   => $t['type'], 
                        'type_titre' => $t['titre'],
                        'type_acte'  => $libelle_acte 
                    ];
                }, $types);
                
                echo json_encode(['success' => true, 'types' => $formatted]);
            }
            exit;

        case 'enregistrer_final':
            try {
                $stmt = $pdo->prepare("UPDATE reponses_dos SET lu = 1 WHERE im = ? AND lu = 0");
                $stmt->execute([$im]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue']);
            exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
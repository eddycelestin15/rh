<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'redirect');
require_once __DIR__ . '/../../includes/check_session.php';
    
    $nb_retards = 0;
    $a_des_retards = false;
    $premiere_date_retard = ""; 
    $groupes_projets = [];
    $dernier_p = ['type' => 'Dossier', 'pieces' => ''];

    $im = $_GET['im'] ?? ($_SESSION['user_im'] ?? null);
    $alerte_id = $_GET['alerte_id'] ?? '';

    if (!$im || !$alerte_id) {
        die("Erreur : Paramètres matricule (im=$im) ou Alerte (id=$alerte_id) manquants.");
    }

    // --- RÉCUPÉRATION DU RÔLE SPECIFIQUE EN PHP ---
    $user_id_session = $_SESSION['user_id'] ?? null; 
    $role_specifique = 'agent'; 

    if (!empty($_SESSION['user_im'])) {
        $stmtRole = $pdo->prepare("SELECT role_specifique FROM utilisateurs WHERE im = ?");
        $stmtRole->execute([$_SESSION['user_im']]);
        $role_specifique = $stmtRole->fetchColumn() ?: 'agent';
    }

    $stmt = $pdo->prepare("
        SELECT psa.*, pec.date_naiss, pec.nom, pec.prenoms 
        FROM personnel_situation_actuelle psa
        INNER JOIN personnel_etat_civil pec ON psa.im = pec.im
        WHERE psa.im = ?
    ");
    $stmt->execute([$im]);
    $sit_reelle = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM v_moteur_alertes WHERE alerte_id = ?");
    $stmt->execute([$alerte_id]);
    $notif = $stmt->fetch();

    // --- RÉCUPÉRATION DU STATUT ---
    $stmt_st = $pdo->prepare("
        SELECT statut, numero_dos 
        FROM demandes_numeros_dos 
        WHERE im = ? 
        AND (
            alerte_id = ? 
            OR alerte_id LIKE CONCAT('%', ?, '%_AVANCEMENT%')
        )
        ORDER BY id DESC LIMIT 1
    ");
    $stmt_st->execute([$im, $alerte_id, $im]);
    $suivi_local = $stmt_st->fetch();

    $numero_dos_reel = $suivi_local['numero_dos'] ?? '';
    $statut_reel_db = $suivi_local['statut'] ?? '';

    $est_attribue = (!empty($numero_dos_reel) || $statut_reel_db === 'ATTRIBUE');
    $est_en_attente = (!$est_attribue && $statut_reel_db === 'EN_ATTENTE');

    if ($est_attribue) {
        $statut_dos = 'ATTRIBUE';
    } elseif ($est_en_attente) {
        $statut_dos = 'EN_ATTENTE';
    } else {
        $statut_dos = 'NEUF';
    }

    $peut_visualiser = ($est_attribue && !$a_des_retards); 
    $numAttendu = $numero_dos_reel;
    
    $sqlValide = "SELECT numero_attribue FROM reponses_dos WHERE im = ? ORDER BY id DESC LIMIT 1";
    $stmtValide = $pdo->prepare($sqlValide);
    $stmtValide->execute([$im]);
    $numAttendu = $stmtValide->fetchColumn() ?: '';

    // --- DÉCISION DU BOUTON ---
    $peut_visualiser = (($statut_dos === 'ATTRIBUE' || $statut_dos === 'EN_ATTENTE') && !$a_des_retards); 
    $mode_finaliser = (isset($_GET['mode']) && $_GET['mode'] === 'finaliser');
    
    $mode = $_GET['mode'] ?? '';
    $num_attribue = $_GET['num'] ?? '';
    
    $liste_pieces = $notif['pieces'] ?? '';

    $type = strtoupper($notif['type_key'] ?? '');
    if (empty($type) && strpos($alerte_id, '_') !== false) {
        $parts = explode('_', $alerte_id);
        $type = strtoupper(end($parts));
    }

    $code_corps_prefix = substr($sit_reelle['code_corps_actuel'] ?? '', 0, 1);
    $code_corps_CatII_III = $sit_reelle['code_corps_actuel'];
    $grade = $sit_reelle['grade_actuel'] ?? '';
    $cat = $sit_reelle['categorie_actuel'] ?? '';
    $is_efa = ($sit_reelle['statut_actuel'] === 'Contractuel EFA');
    $is_fonc = ($sit_reelle['statut_actuel'] === 'Fonctionnaire');

    $seuil_atteint = false;
    if (in_array($cat, ['II', 'III']) && !str_contains(strtoupper(trim($grade)), '3°ECHELON')) {
        $seuil_atteint = true;
    } 

    // --- 1. LISTE PIÈCES : RENOUVELLEMENT CONTRAT ---
    $cont = [
        "Demande avec avis du Chef hiérarchique (03)",
        "FICHE N°2 (03)",
        "Note de présentation (03)",
        "Compte rendu de prise de service (03)",
        "Attestation de non interruption de service (03)"
    ];
    $cont[] = "Projet de contrat de travail (06)";
    if ($code_corps_prefix === 'J') { 
        $cont[] = "Projet d’Avenant (05)";   
        if (trim(strtoupper($grade)) === 'STAGIAIRE') {
            $cont[] = "Bulletin Individuel de Notes (03)";
            $cont[] = "Attestation de non jouissance de congé (03)";
        } elseif (trim(strtoupper($grade)) === '2°CLASSE/1°ECHELON') {
            $cont[] = "Photocopie dernier avenant (03)";
        }
    }
    $cont[] = "Photocopie de Contrat de Travail (03)";
    $cont[] = "Certificat de visite médical 57 (01)";
    $cont[] = "Certificat de visite médical 58 (01)";
    $cont[] = "Photocopie CIN (03)";
    $cont[] = "Souche bon de caisse ou avis de credit (03)";
    $pieces_contrat = implode(", ", $cont);

    // --- 2. LISTE PIÈCES : DEMANDE ECHELON ---
    $echelon = ["Demande avec avis du Chef hiérarchique (03)"];
    if ($code_corps_prefix === 'A' || $code_corps_prefix === 'B' || $code_corps_prefix === 'C') { 
        $echelon[] = "Photocopie dernier arrêté d’avancement (03)"; 
        $echelon[] = "Projet d’arrêté (06)";
    } elseif ($code_corps_prefix === 'U'){
        $echelon[] = "Projet d’Avenant (05)";
        $echelon[] = "Photocopie dernier contrat (03)"; 
        if (in_array($cat, ['IV', 'V', 'VI', 'VIII']) || $seuil_atteint) {
            $echelon[] = "Photocopie dernier avenant (03)";
        }
        $echelon[] = "Compte rendu de prise de service (03)";
    }
    $echelon[] = "Certificat administratif (03)";
    $echelon[] = "Attestation de non interruption de service (03)";       
    $echelon[] = "Photocopie CIN (03)";
    $echelon[] = "Souche bon de caisse ou avis de credit (03)";
    $pieces_echelon = implode(", ", $echelon);

    // --- 3. LISTE PIÈCES : DEMANDE CLASSE ---
    $classe = ["Demande avec avis du Chef hiérarchique (03)"];
    if ($is_fonc) {
        $classe[] = "Projet d’arrêté (09)";     
    }
    $classe[] = "Projet de décision (09)";
    $classe[] = "PV CAP (09)";
    if ($is_efa && $code_corps_prefix === 'U') {
        $classe[] = "Projet d’Avenant (05)"; 
        $classe[] = "Photocopie dernier contrat (03)"; 
        $classe[] = "Photocopie dernier avenant (03)";
    }
    if ($is_fonc) {   
        $classe[] = "Photocopie dernier arrêté d’avancement (03)"; 
    }    
    $classe[] = "Bulletin individuel de notes (03)";
    $classe[] = "Certificat administratif (03)"; 
    $classe[] = "Attestation de non interruption de service (03)";    
    $classe[] = "Photocopie CIN (03)";
    $classe[] = "Souche bon de caisse ou avis de credit (03)";
    $pieces_classe = implode(", ", $classe);

    // --- 4. LISTE PIÈCES : DEMANDE INTEGRATION ---
    $integration = [
        "Demande avec avis du Chef hiérarchique (03)",
        "Copies des contrats de travail (1er et dernier contrat) (03)",
        "Photocopie certifiée du diplôme (03)",
        "Arrêté d’équivalence (03)",
        "Fiche d’évaluation (03)",
        "Photocopie dernier Avenant (03)",
        "Attestation de prise de service (03)",
        "Attestation de non interruption de service (03)",
        "Copie CIN certifiée (03)",
        "Acte de naissance (03)",
        "Avis de crédit ou souche bon de caisse (03)"
    ];
    $pieces_integration = implode(", ", $integration);

    // --- 5. LISTE PIÈCES : DEMANDE TITULARISATION ---
    $titularisation = [
        "Demande avec avis du Chef hiérarchique (03)",
        "Projet d’arrêté (06)",
        "PV CAP (03)",
        "Photocopie arrêté de nomination (03)",
        "Bulletin Individuel de Notes (03)",
        "Attestation de prise de service (03)",
        "Attestation de non interruption de service (03)",
        "Certificat administratif (03)",
        "Attestation de non jouissance de congé (03)",
        "Copie CIN certifiée (03)",
        "Avis de crédit ou souche bon de caisse (03)"
    ];
    $pieces_titularisation = implode(", ", $titularisation);

    // --- 6. LISTE PIÈCES : ADMISSION À LA RETRAITE ---
    $admissionRetraite = [
        "Demande avec avis du Chef hiérarchique (03)",
        "Photocopie dernier Avancement (03)",
        "Acte de naissance (03)",
        "Relevé de service (03)",
        "Attestation de non interruption de service (03)",
        "Copie CIN certifiée (03)",
        "Avis de crédit ou souche bon de caisse (03)"
    ];
    $pieces_admissionRetraite = implode(", ", $admissionRetraite);

    // Extraction du jour et du mois de naissance
    $date_naiss = new DateTime($sit_reelle['date_naiss']);
    $jour_mois = $date_naiss->format('m-d'); 

    // Calcul de l'année des 60 ans (année de retraite)
    $annee_retraite = (int)$date_naiss->format('Y') + 60;

    // Condition : Si né le 1er Janvier (01-01)
    if ($jour_mois === '01-01') {
        $annee_ref = $annee_retraite - 1; 
    } else {
        $annee_ref = $annee_retraite;     
    }

    // Construction de la plage des 3 années
    $annees_conge = ($annee_ref - 2) . '-' . ($annee_ref - 1) . '-' . $annee_ref;

    // --- 7. LISTE PIÈCES : INDEMNITÉ COMPENSATRICE ---
    $compensatrice = [
        "Demande de l'agent visée par le Chef hiérarchique (03)",
        "Décision de congé annuel {$annees_conge} (3ex par année) (09)",
        "Projet de décision compensatrice (03)",
        "Etat de droit de congés (03)",
        "Photocopie du dernier avancement (03)",
        "Photocopie de l’arrêté de l’admission à la retraite (03)",
        "Attestation de non interruption de service (03)",
        "Attestation de non jouissance de congé (03)",
        "Photocopie CIN certifiée (03)",
        "Souche bon de caisse ou avis de crédit (03)"
    ];

    $pieces_compensatrice = implode(", ", $compensatrice);

    // --- 8. LISTE PIÈCES : INDEMNITÉ D'INSTALLATION ---
    $installation = [
        "Demande avec avis favorable du Chef hiérarchique (03)",
        "Photocopie certifié de l'admission à la retraite (03)",
        "Photocopie du dernier avancement (03)",
        "Relevé de service (03)",
        "Attestation de validation de service (03)",
        "Photocopie CIN certifiée (03)",
        "Souche bon de caisse ou avis de crédit (03)"
    ];
    $pieces_installation = implode(", ", $installation);

    // --- MISE À JOUR DE LA CONFIGURATION DES PIÈCES ---
    $pieces_config = [
        'TITULARISATION'     => $pieces_titularisation,
        'INTEGRATION'        => $pieces_integration,
        'ECHELON'            => $pieces_echelon,
        'CLASSE'             => $pieces_classe,
        'CONTRAT'            => $pieces_contrat,
        'ADMISSION_RETRAITE' => $pieces_admissionRetraite,
        'COMPENSATRICE'      => $pieces_compensatrice,
        'INSTALLATION'       => $pieces_installation
    ];
    
    $sit_actuelle_commune = [
        'corps'  => $sit_reelle['corps_actuel'],
        'grade'  => $sit_reelle['grade_actuel'],
        'indice' => $sit_reelle['indice_actuel'],
        'date'   => $sit_reelle['date_d_effet_actuel']
    ];

    $type_cle = strtoupper($type); 
    $force_classe = false; 

    // Uniformisation des types de demandes
    if (in_array($type_cle, ['INTG'])) {
        $type_cle = 'INTEGRATION';
    } elseif (in_array($type_cle, ['ADMISSION_RETRAITE'])) {
        $type_cle = 'ADMISSION_RETRAITE';
    } elseif (in_array($type_cle, ['COMPENSATRICE'])) {
        $type_cle = 'COMPENSATRICE';
    } elseif (in_array($type_cle, ['INSTALLATION'])) {
        $type_cle = 'INSTALLATION';
    } elseif (in_array($type_cle, ['TITU'])) {
        $type_cle = 'TITULARISATION';
    } elseif (strpos($alerte_id, '_AVANCEMENT') !== false || $type_cle === 'ECHELON' || $type_cle === 'CLASSE') {
        if (strpos($alerte_id, '_AVANCEMENT') !== false) {
            $titre_notif = mb_strtoupper($notif['titre'] ?? '');
            $contient_1e = (
                stripos($titre_notif, '1°ECHELON') !== false
            );
            if (!$contient_1e) {
                $stmtAll = $pdo->prepare("
                    SELECT titre 
                    FROM v_moteur_alertes 
                    WHERE im = ? 
                    AND alerte_id LIKE '%_AVANCEMENT%'
                ");
                $stmtAll->execute([$im]);
                $tous_titres = $stmtAll->fetchAll(PDO::FETCH_COLUMN);

                foreach ($tous_titres as $t) {
                    $tUp = mb_strtoupper($t ?? '');
                    if (
                        stripos($tUp, '1°ECHELON') !== false
                    ) {
                        $contient_1e = true;
                        break;
                    }
                }
            }

            if (!$contient_1e) {
                $stmtDos = $pdo->prepare("
                    SELECT type_titre 
                    FROM demandes_numeros_dos 
                    WHERE im = ? 
                    AND alerte_id LIKE '%_AVANCEMENT%'
                ");
                $stmtDos->execute([$im]);
                $tous_type_titre = $stmtDos->fetchAll(PDO::FETCH_COLUMN);

                foreach ($tous_type_titre as $tt) {
                    $ttUp = mb_strtoupper($tt ?? '');
                    if (
                        stripos($ttUp, '1°ECHELON') !== false || 
                        stripos($ttUp, '1° ECHELON') !== false || 
                        stripos($ttUp, '1ER ECHELON') !== false
                    ) {
                        $contient_1e = true;
                        break;
                    }
                }
            }

            if ($contient_1e) {
                $type_cle = 'CLASSE';
                $force_classe = true;
            } else {
                $type_cle = 'ECHELON';
            }
        } 
    }

    // 2. Attribution de la liste des pièces
    if (isset($pieces_config[$type_cle])) {
        $liste_pieces = $pieces_config[$type_cle];
    } else {
        $liste_pieces = (!empty($notif['pieces'])) ? $notif['pieces'] : "Liste des pièces non définie";
    }

    // --- LOGIQUE DE BLOCAGE PAR TITULARISATION ---
    $bloque_par_titu = false;
    $id_alerte_titu = "";

    if (strpos($alerte_id, '_AVANCEMENT') !== false) {
        $checkTitu = $pdo->prepare("
            SELECT alerte_id 
            FROM v_moteur_alertes 
            WHERE im = ? 
            AND alerte_id LIKE '%_TITU' 
            LIMIT 1
        ");
        $checkTitu->execute([$im]);
        $titu = $checkTitu->fetch();

        if ($titu) {
            $bloque_par_titu = true;
            $id_alerte_titu = $titu['alerte_id'];
        }
    }

    // --- TITULARISATION ET INTEGRATION ---
    if (in_array($type, ['TITU', 'TITULARISATION', 'INTEGRATION'])) {
        $nomComplet = ($type === 'INTEGRATION') ? 'Intégration' : 'Titularisation';
        $stG = $pdo->prepare("SELECT rg_next.libelle_grade FROM ref_grades_types rg_curr JOIN ref_grades_types rg_next ON rg_next.id = rg_curr.id + 1 WHERE rg_curr.libelle_grade = 'STAGIAIRE' AND rg_curr.modele_id = (SELECT modele_id FROM ref_corps WHERE libelle_corps = ? LIMIT 1)");
        $stG->execute([$sit_reelle['corps_actuel']]);
        $grade_p = $stG->fetchColumn() ?: '2°CLASSE/1°ECHELON';

        $stI = $pdo->prepare("SELECT indice FROM ref_grille_indiciaire rgi JOIN ref_corps rc ON rgi.corps_id = rc.id JOIN ref_grades_types rg ON rgi.grade_type_id = rg.id WHERE rc.libelle_corps = ? AND rg.libelle_grade = ?");
        $stI->execute([$sit_reelle['corps_actuel'], $grade_p]);
        $indice_p = $stI->fetchColumn() ?: '';

        $groupes_projets[] = [
            'type' => "Demande de " . $nomComplet, 
            'label_avant' => "Situation actuelle",
            'label_apres' => "Proposition de " . strtolower($nomComplet),
            'is_contrat' => false,
            'avant' => $sit_actuelle_commune,
            'apres' => [[
                'corps' => $sit_reelle['corps_actuel'], 
                'grade' => $grade_p, 
                'indice' => $indice_p, 
                'date' => date('Y-m-d', strtotime($sit_reelle['date_d_effet_actuel'] . " + 1 year"))
            ]],
            'pieces' => $pieces_config[$type === 'INTEGRATION' ? 'INTEGRATION' : 'TITULARISATION']
        ];
    }
    
    // --- RENOUVELLEMENTS DE CONTRATS ---
    else if (in_array($code_corps_prefix, ['L', 'K', 'J']) && (strpos($alerte_id, '_RNC') !== false)) {
        $date_entree = $sit_reelle['date_entree_admin'];
        $debut_c2 = date('Y-m-d', strtotime($date_entree . " + 2 years"));
        $fin_c1   = date('Y-m-d', strtotime($debut_c2 . " - 1 day"));
        $debut_c3 = date('Y-m-d', strtotime($debut_c2 . " + 2 years"));
        $fin_c2   = date('Y-m-d', strtotime($debut_c3 . " - 1 day"));

        $stG = $pdo->prepare("SELECT rg_next.libelle_grade FROM ref_grades_types rg_curr JOIN ref_grades_types rg_next ON rg_next.id = rg_curr.id + 1 WHERE rg_curr.libelle_grade = ? AND rg_curr.modele_id = rg_next.modele_id");
        $stG->execute([$sit_reelle['grade_actuel']]);
        $grade_p = $stG->fetchColumn() ?: $sit_reelle['grade_actuel'];

        $stI = $pdo->prepare("SELECT indice FROM ref_grille_indiciaire rgi JOIN ref_corps rc ON rgi.corps_id = rc.id JOIN ref_grades_types rg ON rgi.grade_type_id = rg.id WHERE rc.libelle_corps = ? AND rg.libelle_grade = ?");
        $stI->execute([$sit_reelle['corps_actuel'], $grade_p]);
        $indice_p = $stI->fetchColumn() ?: $sit_reelle['indice_actuel'];

        $is_rnc1 = (strpos($alerte_id, '_RNC1') !== false);
        $avant = $sit_actuelle_commune;
        $avant['date_fin'] = $is_rnc1 ? $fin_c1 : $fin_c2;
        $avant['duree_label'] = "24 Mois";

        $groupes_projets[] = [
            'type' => $is_rnc1 ? "Renouvellement : 1er contrat (CDD)" : "Renouvellement : 2eme contrat (CDD)",
            'label_avant' => $is_rnc1 ? "Situation actuelle (1er contrat CDD)" : "Situation actuelle (2Eme contrat CDD)",
            'label_apres' => $is_rnc1 ? "Avancement proposé (2eme Contrat CDD)" : "Avancement proposé (3eme Contrat CDI)",
            'is_contrat' => true,
            'avant' => $avant,
            'apres' => [[
                'corps' => $sit_reelle['corps_actuel'], 
                'grade' => $grade_p, 
                'indice' => $indice_p, 
                'date_effet_avenant' => $is_rnc1 ? date('Y-m-d', strtotime($date_entree . " + 1 year")) : date('Y-m-d', strtotime($debut_c2 . " + 1 year")),
                'date' => $is_rnc1 ? $debut_c2 : $debut_c3, 
                'duree' => $is_rnc1 ? '24 Mois' : 'INDÉTERMINÉE'
            ]],
            'pieces' => $pieces_config['CONTRAT']
        ];
    }

    // --- AVANCEMENT ECHELON / CLASSE ---
    else if ($type === 'ECHELON' || strpos($alerte_id, '_AVANCEMENT') !== false) {
        $cur_corps  = $sit_reelle['corps_actuel'];
        $cur_grade  = $sit_reelle['grade_actuel'];
        $cur_ind    = $sit_reelle['indice_actuel'];
        $cur_date   = $sit_reelle['date_d_effet_actuel']; 
        $cat        = trim($sit_reelle['categorie_actuel']);
        $statut     = $sit_reelle['statut_actuel'];
        $type_av    = $sit_reelle['type_avancement_actuel'];
        $isU        = (strtoupper(substr($sit_reelle['code_corps_actuel'], 0, 1)) === 'U');

        if (trim($cur_grade) === 'STAGIAIRE' || (trim($cur_grade) === '2°CLASSE/1°ECHELON' && $statut !== 'Fonctionnaire')) {
            $stTituGrade = $pdo->prepare("
                SELECT libelle_grade FROM ref_grades_types 
                WHERE libelle_grade LIKE '2%CLASSE%2%ECHELON%' 
                AND modele_id = (SELECT modele_id FROM ref_corps WHERE libelle_corps = ? LIMIT 1) 
                LIMIT 1
            ");
            $stTituGrade->execute([$cur_corps]);
            $grade_post_titu = $stTituGrade->fetchColumn();

            if ($grade_post_titu) {
                $cur_grade = $grade_post_titu; 
                $stI = $pdo->prepare("SELECT rgi.indice FROM ref_grille_indiciaire rgi JOIN ref_corps rc ON rgi.corps_id = rc.id JOIN ref_grades_types rg ON rgi.grade_type_id = rg.id WHERE rc.libelle_corps = ? AND rg.libelle_grade = ?");
                $stI->execute([$cur_corps, $cur_grade]);
                $cur_ind = $stI->fetchColumn() ?: $cur_ind;
                $cur_date = date('Y-m-d', strtotime($cur_date . " + 3 years")); 
            }
        }

        $infosContratInitial = null;
        $titre_prefixe = "Demande d’avancement";

        if ($statut === 'Fonctionnaire' && $type_av === 'Intégration' && in_array($cat, ['IV', 'V', 'VI', 'VIII'])) {
            $sql_reconst = "SELECT SUM(duree_annees) as total_annees 
                            FROM ref_grades_types 
                            WHERE modele_id = (SELECT modele_id FROM ref_grades_types WHERE libelle_grade = ? LIMIT 1)
                            AND id < (SELECT id FROM ref_grades_types WHERE libelle_grade = ? LIMIT 1)";
            
            $stReconst = $pdo->prepare($sql_reconst);
            $stReconst->execute([$cur_grade, $cur_grade]);
            $resReconst = $stReconst->fetch();
            
            $annees_precedentes = (int)($resReconst['total_annees'] ?? 0);

            if (!empty($sit_reelle['date_entree_admin'])) {
                $date_entree = new DateTime($sit_reelle['date_entree_admin']);
                $date_entree->modify("+" . $annees_precedentes . " years");
                $cur_date = $date_entree->format('Y-m-d'); 
            }
        }

        if ($statut === 'Contractuel EFA' && $isU) {
            $titre_prefixe = "Demande d’Avenant";
            if (in_array($cat, ['IV', 'V', 'VI', 'VIII'])) {                
                $cur_date = date('Y-m-d', strtotime('-1 year', strtotime($cur_date)));

                $stAv = $pdo->prepare("SELECT av_grade, av_indice, av_date_effet 
                                    FROM personnel_avancements 
                                    WHERE im = ? AND av_type_acte = 'Avenant' 
                                    ORDER BY av_date_effet DESC LIMIT 1");
                $stAv->execute([$im]);
                $dernierAvenant = $stAv->fetch();

                if ($dernierAvenant && strtotime($dernierAvenant['av_date_effet']) >= strtotime($cur_date)) {
                    $cur_grade = $dernierAvenant['av_grade'];
                    $cur_ind   = $dernierAvenant['av_indice'];
                    $cur_date  = $dernierAvenant['av_date_effet'];
                }
            }
            
            $stC = $pdo->prepare("SELECT av_acte_no, av_acte_date 
                                FROM personnel_avancements 
                                WHERE im = ? AND av_type_acte = 'Contrat' 
                                AND (av_grade LIKE '%2%CLASSE%2%ECHELON%' OR av_grade LIKE '%2/2%') 
                                ORDER BY av_date_effet DESC LIMIT 1");
            $stC->execute([$im]);
            $infosContratInitial = $stC->fetch();
        }

        $sql_etapes = "WITH RECURSIVE chemin AS (
            SELECT rg.id, rg.libelle_grade, rg.duree_annees, CAST(? AS DATE) as d_effet, rg.modele_id, 1 as niv 
            FROM ref_grades_types rg 
            WHERE rg.libelle_grade = ? 
            AND rg.modele_id = (SELECT modele_id FROM ref_corps WHERE libelle_corps = ? LIMIT 1)
            
            UNION ALL 
            
            SELECT rgn.id, rgn.libelle_grade, rgn.duree_annees, DATE_ADD(c.d_effet, INTERVAL c.duree_annees YEAR), rgn.modele_id, c.niv + 1 
            FROM chemin c 
            JOIN ref_grades_types rgn ON rgn.modele_id = c.modele_id AND rgn.id = c.id + 1 
            WHERE DATE_ADD(c.d_effet, INTERVAL c.duree_annees YEAR) <= CURRENT_DATE
        ) 
        SELECT * FROM chemin WHERE niv > 1 ORDER BY niv ASC";

        $stmt_e = $pdo->prepare($sql_etapes);
        $stmt_e->execute([$cur_date, $cur_grade, $cur_corps]);
        $liste_etapes = $stmt_e->fetchAll();

        foreach ($liste_etapes as $index => $etape) {
            $stI = $pdo->prepare("SELECT rgi.indice FROM ref_grille_indiciaire rgi JOIN ref_corps rc ON rgi.corps_id = rc.id WHERE rc.libelle_corps = ? AND rgi.grade_type_id = ?");
            $stI->execute([$cur_corps, $etape['id']]);
            $next_ind = $stI->fetchColumn() ?: 'N/A';

            $classe_actuelle = explode('/', $cur_grade)[0];
            $classe_suivante = explode('/', $etape['libelle_grade'])[0];
            $is_classe = (trim($classe_actuelle) !== trim($classe_suivante));

            if (!empty($force_classe)) {
                $is_classe = true;
            } else {
                $titre_notif = $notif['titre'] ?? '';
                if (strpos($alerte_id, '_AVANCEMENT') !== false && stripos($titre_notif, '1°ECHELON') !== false) {
                    $is_classe = true;
                }
            }

            $groupes_projets[] = [
                'type' => $titre_prefixe . ($is_classe ? " (CLASSE et ECHELON)" : " (ECHELON)"),
                'label_avant' => ($index === 0) ? "DERNIER ACTE ENREGISTRÉ" : "Situation précédente (Régularisée)",
                'label_apres' => ($statut === 'Contractuel EFA') ? "Avenant à régulariser" : "Avancement à régulariser",
                'is_contrat' => false,
                'avant' => ['corps' => $cur_corps, 'grade' => $cur_grade, 'indice' => $cur_ind, 'date' => $cur_date],
                'apres' => [['corps' => $cur_corps, 'grade' => $etape['libelle_grade'], 'indice' => $next_ind, 'date' => $etape['d_effet']]],
                'pieces' => $is_classe ? $pieces_config['CLASSE'] : $pieces_config['ECHELON'],
                'extra_contrat' => $infosContratInitial 
            ];

            $cur_grade = $etape['libelle_grade']; 
            $cur_ind = $next_ind; 
            $cur_date = $etape['d_effet'];
        }
    }
    // --- INTÉGRATION ---
    else if ($type === 'INTG') {        
        $cur_corps  = $sit_reelle['corps_actuel'];
        $cur_grade  = $sit_reelle['grade_actuel'];
        $cur_ind    = (int)$sit_reelle['indice_actuel']; 
        $cur_date   = $sit_reelle['date_d_effet_actuel'];
        $categorie  = $sit_reelle['categorie_actuel']; 

        $sql_etapes = "WITH RECURSIVE chemin AS (
            SELECT rg.id, rg.libelle_grade, rg.duree_annees, CAST(? AS DATE) as d_effet, rg.modele_id, 1 as niv 
            FROM ref_grades_types rg 
            WHERE rg.libelle_grade = ? 
            AND rg.modele_id = (SELECT modele_id FROM ref_corps WHERE libelle_corps = ? LIMIT 1)
            UNION ALL 
            SELECT rgn.id, rgn.libelle_grade, rgn.duree_annees, DATE_ADD(c.d_effet, INTERVAL c.duree_annees YEAR), rgn.modele_id, c.niv + 1 
            FROM chemin c 
            JOIN ref_grades_types rgn ON rgn.modele_id = c.modele_id AND rgn.id = c.id + 1 
            WHERE DATE_ADD(c.d_effet, INTERVAL c.duree_annees YEAR) <= CURRENT_DATE
        ) 
        SELECT * FROM chemin WHERE niv > 1 ORDER BY niv ASC";

        $stmt_e = $pdo->prepare($sql_etapes);
        $stmt_e->execute([$cur_date, $cur_grade, $cur_corps]);
        $liste_etapes = $stmt_e->fetchAll();

        foreach ($liste_etapes as $index => $etape) {
            $stI = $pdo->prepare("SELECT rgi.indice FROM ref_grille_indiciaire rgi JOIN ref_corps rc ON rgi.corps_id = rc.id WHERE rc.libelle_corps = ? AND rgi.grade_type_id = ?");
            $stI->execute([$cur_corps, $etape['id']]);
            $next_ind = $stI->fetchColumn();

            $groupes_projets[] = [
                'type' => "Régularisation préalable (Avancement en retard)",
                'label_avant' => ($index === 0) ? "Situation actuelle" : "Étape précédente",
                'label_apres' => "Avancement à régulariser",
                'is_contrat' => false,
                'avant' => ['corps' => $cur_corps, 'grade' => $cur_grade, 'indice' => $cur_ind, 'date' => $cur_date],
                'apres' => [['corps' => $cur_corps, 'grade' => $etape['libelle_grade'], 'indice' => $next_ind, 'date' => $etape['d_effet']]],
                'pieces' => $pieces_config['ECHELON']
            ];
            $cur_grade = $etape['libelle_grade']; 
            $cur_ind = (int)$next_ind; 
            $cur_date = $etape['d_effet'];
        }

        $grade_integration = $cur_grade;
        $indice_integration = $cur_ind;

        if (in_array($categorie, ['II', 'III'])) {
            $stStag = $pdo->prepare("
                SELECT rg.libelle_grade, rgi.indice 
                FROM ref_grille_indiciaire rgi 
                JOIN ref_corps rc ON rgi.corps_id = rc.id 
                JOIN ref_grades_types rg ON rgi.grade_type_id = rg.id 
                WHERE rc.libelle_corps = ? AND rg.libelle_grade = 'STAGIAIRE'
            ");
            $stStag->execute([$cur_corps]);
            $infos_stagiaire = $stStag->fetch();
            $ind_stag = (int)($infos_stagiaire['indice'] ?? 0);
            $exclusion = ($categorie === 'II') ? 'ECHELLE III%' : 'ECHELLE IV%';

            if ($cur_ind < $ind_stag) {
                $grade_integration = $infos_stagiaire['libelle_grade'];
                $indice_integration = $ind_stag;
            } else {
                $stNext = $pdo->prepare("
                    SELECT rg.libelle_grade, rgi.indice 
                    FROM ref_grille_indiciaire rgi 
                    JOIN ref_grades_types rg ON rgi.grade_type_id = rg.id 
                    JOIN ref_corps rc ON rgi.corps_id = rc.id
                    WHERE rc.libelle_corps = ? 
                    AND rg.libelle_grade NOT LIKE ?   
                    AND rgi.indice >= ?                
                    ORDER BY rgi.indice ASC 
                    LIMIT 1
                ");
                $stNext->execute([$cur_corps, $exclusion, $cur_ind]);
                $res = $stNext->fetch();
                
                if ($res) {
                    $grade_integration = $res['libelle_grade'];
                    $indice_integration = $res['indice'];
                }
            }
        }

        if (!empty($groupes_projets)) {
            foreach ($groupes_projets as $gp) {
                if (isset($gp['type']) && (strpos($gp['type'], 'Régularisation') !== false)) {
                    $nb_retards++;
                    $a_des_retards = true;
                    
                    if (empty($premiere_date_retard) && isset($gp['apres'][0]['date'])) {
                        $premiere_date_retard = ($gp['apres'][0]['date'] !== 'INCONNU') 
                            ? date('d/m/Y', strtotime($gp['apres'][0]['date'])) 
                            : 'date indéterminée';
                    }
                }
            }
        }

        if (strpos($type, 'INT') !== false) {
            $groupes_projets[] = [
                'type' => "DEMANDE D’INTÉGRATION (Passage au Statut Fonctionnaire)",
                'label_avant' => "Situation EFA que doit être à la date du jour",
                'label_apres' => "Situation d'Intégration proposée",
                'is_contrat' => false,
                'is_integration' => true,
                'a_des_retards' => $a_des_retards,
                'avant' => [
                    'corps' => $cur_corps,  
                    'grade' => $cur_grade,  
                    'indice' => $cur_ind,   
                    'date' => $cur_date
                ],
                'apres' => [[
                    'corps'  => $sit_reelle['corps_actuel'] ?? '',
                    'grade'  => $grade_integration ?? '',
                    'indice' => $indice_integration ?? '',
                    'date'   => 'INCONNU'
                ]],
                'pieces' => $pieces_config['INTEGRATION'] ?? 'Copie CIN,Diplôme,Acte de naissance' 
            ];
        }

        $dernier_p = end($groupes_projets);
    }

    // --- DEMANDE DE COMPENSATRICE ---
    else if (strpos($alerte_id, 'COMPENSATRICE') !== false || $type_cle === 'COMPENSATRICE') {
        $groupes_projets[] = [
            'type' => "Demande de décision d'octroi d'indemnité Compensatrice de Congé",
            'label_avant' => "Situation administrative actuelle",
            'label_apres' => "Situation de l'admission à la retraite",
            'is_contrat' => false,
            'avant' => $sit_actuelle_commune,
            'apres' => [[
                'corps'  => $sit_reelle['corps_actuel'],
                'grade'  => $sit_reelle['grade_actuel'],
                'indice' => $sit_reelle['indice_actuel'],
                'date'   => $sit_reelle['date_d_effet_actuel']
            ]],
            'pieces' => $pieces_config['COMPENSATRICE']
        ];
    }

    // --- DEMANDE D'INSTALLATION ---
    else if (strpos($alerte_id, 'INSTALLATION') !== false || $type_cle === 'INSTALLATION') {
        $groupes_projets[] = [
            'type' => "Demande de décision d'Installation",
            'label_avant' => "Situation administrative actuelle",
            'label_apres' => "Situation de l'admission à la retraite",
            'is_contrat' => false,
            'avant' => $sit_actuelle_commune,
            'apres' => [[
                'corps'  => $sit_reelle['corps_actuel'],
                'grade'  => $sit_reelle['grade_actuel'],
                'indice' => $sit_reelle['indice_actuel'],
                'date'   => $sit_reelle['date_d_effet_actuel']
            ]],
            'pieces' => $pieces_config['INSTALLATION']
        ];
    }

    // --- DEMANDE DE RETRAITE ---
    else if (strpos($alerte_id, 'ADMISSION_RETRAITE') !== false || $type_cle === 'ADMISSION_RETRAITE') {
        $date_naissance = new DateTime($sit_reelle['date_naiss']);
        $date_60_ans = clone $date_naissance;
        $date_60_ans->modify('+60 years');
        $date_effet_retraite = $date_60_ans->format('Y-m-d');

        $groupes_projets[] = [
            'type' => "Demande de Projet d'Arrêté d'Admission à la Retraite",
            'label_avant' => "Situation administrative actuelle",
            'label_apres' => "Situation de l'admission à la retraite",
            'is_contrat' => false,
            'avant' => $sit_actuelle_commune,
            'apres' => [[
                'corps'  => $sit_reelle['corps_actuel'],
                'grade'  => $sit_reelle['grade_actuel'],
                'indice' => $sit_reelle['indice_actuel'],
                'date'   => $sit_reelle['date_d_effet_actuel']
            ]],
            'pieces' => $pieces_config['ADMISSION_RETRAITE']
        ];
    }

    if (!empty($groupes_projets)) {
        $dernier_p = end($groupes_projets);
        $_SESSION['dernier_projet_calcule'] = $groupes_projets;
    }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <title>Demande d'Actes RH</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; padding: 0 !important; }
    .page-break { page-break-after: always; box-shadow: none !important; border: 1px solid #eee !important; }
}
</style>
</head>
<body class="bg-slate-50 py-1 px-6">
<div class="max-w-[95%] mx-auto pt-0 px-2">
    <div class="bg-white rounded-[0.5rem] shadow-sm border border-slate-100 p-4 mb-4 flex flex-col md:flex-row justify-between items-center gap-4 no-print">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 bg-slate-900 rounded-xl flex items-center justify-center shadow-lg">
                <i class="fas fa-file-signature text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-black text-slate-900 tracking-tighter uppercase italic leading-none">PROJETS D'ACTES</h2>
                <div class="flex items-center gap-2 mt-1">
                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full text-[10px] font-bold uppercase tracking-widest">
                        Agent IM : <?php echo $im; ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
            <button 
                type="button"
                id="btnDemanderDos"
                class="demander-dos-btn flex items-center justify-center gap-3 px-8 py-4 rounded-2xl shadow-lg transition-all w-full md:w-auto text-white hover:scale-105 bg-indigo-600 <?= ($est_en_attente || $est_attribue) ? 'hidden' : ''; ?>"
                data-im="<?= htmlspecialchars($im ?? ''); ?>"
                data-alerte-id="<?= htmlspecialchars($alerte_id ?? ''); ?>">
                <i class="fas fa-paper-plane text-lg"></i>
                <span>Demander numéro DOS</span>
            </button>
            
            <button 
                type="button"
                id="btnVisualiserPieces"
                class="visualiser-pieces-btn flex items-center justify-center gap-3 px-8 py-4 rounded-2xl shadow-lg transition-all w-full md:w-auto text-white hover:scale-105 bg-emerald-600 <?= ($est_en_attente || $est_attribue) ? '' : 'hidden'; ?>"
                data-type-print="<?= htmlspecialchars($type); ?>"
                data-dos-statut="<?= htmlspecialchars($statut_dos); ?>"
                data-im="<?= htmlspecialchars($im ?? ''); ?>"
                data-alerte-id="<?= htmlspecialchars($alerte_id ?? ''); ?>"
                data-titre="<?= htmlspecialchars($notif['titre'] ?? ''); ?>"
                data-pieces="<?= htmlspecialchars($liste_pieces); ?>"
                data-bloque="<?= ($a_des_retards ? '1' : '0'); ?>"
                data-bloque-titu="<?= ($bloque_par_titu ? '1' : '0'); ?>"
                data-id-titu="<?= $id_alerte_titu; ?>"            
                data-nb-retards="<?= $nb_retards; ?>"
                data-date-retard="<?= htmlspecialchars($premiere_date_retard); ?>"
                data-num-attendu="<?= htmlspecialchars($numAttendu); ?>"
                data-corps="<?= htmlspecialchars($sit_reelle['corps_actuel'] ?? ''); ?>"
                data-role-specifique="<?= htmlspecialchars($role_specifique); ?>">
                <i class="fas fa-eye text-lg"></i>
                <span>Visualiser les pièces</span>
            </button>
        </div>
    </div>
</div>
<div class="max-w-[95%] mx-auto pt-2 px-2">
    <?php foreach ($groupes_projets as $index => $projet): ?>
        <?php 
            $is_mode_int = (strpos($type, 'INT') !== false);
            if ($is_mode_int) {
                if (!isset($projet['is_integration']) || $projet['is_integration'] !== true) {
                    continue; 
                }
            } 
            else {
                if (isset($projet['is_integration']) && $projet['is_integration'] === true) {
                    continue; 
                }
            }
        ?>
        <div class="bg-white rounded-[0.5rem] shadow-xl border border-slate-100 overflow-hidden mb-6 page-break">
            <div class="bg-slate-900 py-3 px-6 text-white flex justify-between items-center">
                <div class="flex items-center gap-2 py-1 px-2"> 
                    <span class="bg-emerald-500 text-white w-8 h-8 flex items-center justify-center rounded-lg font-black italic">
                        <?php echo $index + 1; ?>
                    </span>
                    
                    <h3 class="font-bold uppercase tracking-widest text-sm leading-none">
                        <?php echo $projet['type']; ?>
                    </h3>
                </div>
            </div>

            <div class="p-4 md:p-6 grid md:grid-cols-2 gap-12 relative">
                <div class="hidden md:flex absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-12 h-12 bg-white border border-slate-100 rounded-full items-center justify-center shadow-md z-10">
                    <i class="fas fa-arrow-right text-slate-300"></i>
                </div>

                <div class="space-y-6">
                    <div class="flex items-center gap-3">
                        <span class="w-2 h-6 bg-rose-500 rounded-full"></span>
                        <h4 class="text-xs font-black uppercase tracking-widest text-rose-500"><?php echo $projet['label_avant']; ?></h4>
                    </div>
                    <div class="bg-rose-50/30 p-8 rounded-[2rem] border border-rose-100/50">
                        <?php if ($projet['is_contrat']): ?>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Corps</p>
                                    <p class="font-bold text-slate-700"><?php echo $projet['avant']['corps']; ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Grade actuel</p>
                                    <p class="font-black text-slate-900 text-xl leading-tight"><?php echo $projet['avant']['grade']; ?></p>
                                </div>
                                <div class="flex gap-10 pt-4 border-t border-rose-100/50">
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase">Indice</p>
                                        <p class="text-lg font-black text-rose-600"><?php echo $projet['avant']['indice']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase">Effet</p>
                                        <p class="text-lg font-black text-slate-700"><?php echo date('d/m/Y', strtotime($projet['avant']['date'])); ?></p>
                                    </div>
                                </div>
                                <?php if(isset($projet['avant']['date_fin'])): ?>
                                <div class="p-3 bg-white/60 rounded-xl border border-rose-100">
                                    <p class="text-[9px] font-black text-rose-500 uppercase">Date de fin de contrat</p>
                                    <p class="text-sm font-bold text-rose-700"><?php echo date('d/m/Y', strtotime($projet['avant']['date_fin'])); ?> (<?php echo $projet['avant']['duree_label']; ?>)</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-2 gap-y-6 gap-x-4">
                                <div class="border-r border-rose-100/50 pr-4">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Corps</p>
                                    <p class="font-bold text-slate-700 text-sm leading-tight"><?php echo $projet['avant']['corps']; ?></p>
                                </div>
                                <div class="pl-2">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Grade actuel</p>
                                    <p class="font-black text-slate-900 text-lg leading-tight"><?php echo $projet['avant']['grade']; ?></p>
                                </div>
                                <div class="pt-4 border-t border-rose-100/50 border-r pr-4">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Indice</p>
                                    <p class="text-xl font-black text-rose-600"><?php echo $projet['avant']['indice']; ?></p>
                                </div>
                                <div class="pt-4 border-t border-rose-100/50 pl-2">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Date d'effet</p>
                                    <p class="text-xl font-black text-slate-700"><?php echo date('d/m/Y', strtotime($projet['avant']['date'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($projet['apres'])): ?>
                    <div class="space-y-6">
                        <div class="flex items-center gap-3">
                            <span class="w-2 h-6 bg-emerald-500 rounded-full"></span>
                            <h4 class="text-xs font-black uppercase tracking-widest text-emerald-500"><?php echo $projet['label_apres'] ?? ''; ?></h4>
                        </div>
                        <div class="bg-emerald-50/40 p-8 rounded-[2rem] border border-emerald-100/50">
                            <?php foreach ($projet['apres'] as $av): ?>
                                <?php if ($projet['is_contrat']): ?>
                                    <div class="space-y-4">
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase">Corps</p>
                                            <p class="font-bold text-slate-700"><?php echo $av['corps']; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-emerald-400 uppercase">Grade cible</p>
                                            <p class="font-black text-emerald-900 text-xl leading-tight"><?php echo $av['grade']; ?></p>
                                        </div>
                                        <?php if($code_corps_prefix === 'J'): ?>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="p-3 bg-white/60 rounded-xl border border-emerald-100">
                                                    <p class="text-[9px] font-black text-emerald-500 uppercase">Effet avenant</p>
                                                    <p class="text-sm font-bold text-emerald-700"><?php echo date('d/m/Y', strtotime($av['date_effet_avenant'])); ?></p>
                                                </div>
                                                <div class="p-3 bg-white/60 rounded-xl border border-emerald-100">
                                                    <p class="text-[9px] font-black text-emerald-500 uppercase">Effet contrat</p>
                                                    <p class="text-sm font-bold text-emerald-700"><?php echo date('d/m/Y', strtotime($av['date'])); ?></p>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="p-3 bg-white/60 rounded-xl border border-emerald-100">
                                                <p class="text-[9px] font-black text-emerald-500 uppercase">Date d'effet</p>
                                                <p class="text-sm font-bold text-emerald-700"><?php echo date('d/m/Y', strtotime($av['date'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex gap-10 pt-4 border-t border-emerald-100/50">
                                            <div>
                                                <p class="text-[10px] font-bold text-slate-400 uppercase">Nouvel Indice</p>
                                                <p class="text-lg font-black text-emerald-600"><?php echo $av['indice']; ?></p>
                                            </div>
                                            <div>
                                                <p class="text-[10px] font-bold text-slate-400 uppercase">Durée</p>
                                                <p class="text-lg font-black text-slate-700"><?php echo $av['duree']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-2 gap-y-6 gap-x-4">
                                        <div class="border-r border-emerald-100/50 pr-4">
                                            <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Corps</p>
                                            <p class="font-bold text-slate-700 text-sm leading-tight"><?php echo $av['corps']; ?></p>
                                        </div>
                                        <div class="pl-2">
                                            <p class="text-[10px] font-bold text-emerald-400 uppercase mb-1">Grade cible</p>
                                            <p class="font-black text-emerald-900 text-lg leading-tight"><?php echo $av['grade']; ?></p>
                                        </div>
                                        <div class="pt-4 border-t border-emerald-100/50 border-r pr-4">
                                            <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Nouvel Indice</p>
                                            <p class="text-xl font-black text-emerald-600"><?php echo $av['indice']; ?></p>
                                        </div>
                                        <div class="pt-4 border-t border-emerald-100/50 pl-2">
                                            <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Date d'effet</p>
                                            <p class="text-xl font-black text-slate-700">
                                                <?php 
                                                    if ($av['date'] === 'INCONNU') {
                                                        echo '<span class="text-slate-300 italic text-sm">À déterminer (Après Arrêté)</span>';
                                                    } else {
                                                        echo date('d/m/Y', strtotime($av['date']));
                                                    }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div id="modalSuccessDemande" class="fixed inset-0 z-[200] hidden">
    <div class="absolute inset-0 bg-emerald-900/40 backdrop-blur-sm"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-sm p-10 text-center border-t-8 border-emerald-500">
            <div class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-emerald-100">
                <i class="fas fa-paper-plane text-3xl"></i>
            </div>
            <h4 class="text-xl font-black text-slate-800 mb-2 uppercase">Demande envoyée !</h4>
            <p class="text-slate-500 text-sm font-medium mb-8">Votre demande de numéro de dossier a été transmise au responsable.</p>
            <button onclick="fermerModale()" 
                    class="w-full bg-emerald-500 text-white py-4 rounded-2xl font-black text-xs uppercase hover:bg-emerald-600 transition-all">
                D'accord
            </button>
        </div>
    </div>
</div>
<!-- MODALE DES PIÈCES À FOURNIR -->
<div id="modalPieces" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-md"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all">
        <button onclick="fermerModale()" class="absolute top-6 right-6 z-[110] w-10 h-10 bg-white/10 hover:bg-rose-500 text-white rounded-full flex items-center justify-center transition-all group">
            <i class="fas fa-times text-lg group-hover:scale-110"></i>
        </button>    
        <div id="modalHeader" class="flex items-center gap-4 p-6 bg-slate-900 rounded-t-[3rem]">
                <div id="modalIconContainer" class="h-12 w-12 bg-indigo-500 rounded-2xl flex items-center justify-center shadow-lg">
                    <i id="modalIcon" class="fas fa-folder-open text-white text-xl"></i>
                </div>
                <div class="flex flex-col">
                    <h4 id="modalTitle" class="text-white font-black text-xl uppercase leading-none">Dossier</h4>
                    <p id="modalSubTitle" class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Pièces à fournir</p>
                </div>
            </div>

            <div class="p-8">
                <p class="mb-6 text-xs font-bold text-slate-500 border-l-4 border-indigo-500 pl-3 py-1.5 bg-slate-50 rounded-r-xl">
                    <strong>N.B :</strong> 
                    Les pièces en <span class="text-rose-600 font-extrabold">rouge</span> sont des pièces provenant de la part de l'agent, à joindre au dossier. 
                    Les pièces en <span class="text-emerald-600 font-extrabold">vert</span> sont générées par le système après avoir cliqué sur <span class="text-slate-800"><i class="fas fa-print text-slate-700 mx-0.5"></i> "Imprimer les dossiers"</span>. 
                    Les pièces en <span class="text-indigo-600 font-extrabold">bleu</span> sont des pièces générées uniquement par le responsable après étude de vos dossiers, et celles avec l'icône <i class="fas fa-download text-indigo-600 mx-0.5"></i> sont des pièces que vous pouvez télécharger directement.
                </p>

                <div id="modalContent" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6 max-h-[50vh] overflow-y-auto p-1 custom-scrollbar">
                </div>

                <div class="pt-6 border-t border-slate-100 text-center">
                    <button id="btnImprimerModale" class="group w-full bg-slate-900 hover:bg-emerald-600 text-white font-black py-4 rounded-2xl shadow-xl transition-all flex items-center justify-center gap-4">
                        <i class="fas fa-print text-lg"></i>
                        <span class="uppercase tracking-widest">Imprimer les dossiers</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="modalAdressePrefet" class="fixed inset-0 z-[300] hidden">
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-md"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md p-8">
            <h3 class="text-xl font-black text-slate-900 mb-6 text-center">Adressé à</h3>
            
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-2">Civilité</label>
                    <select id="selectGenre" class="w-full border border-slate-300 rounded-2xl px-4 py-3 focus:outline-none focus:border-emerald-500">
                        <option value="Mr">Monsieur</option>
                        <option value="Mme">Madame</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-2">Destinataire</label>
                    <input id="inputDestinataire" type="text" readonly 
                         class="w-full border border-slate-200 bg-slate-50 rounded-2xl px-4 py-3 text-slate-700">
                </div>
            </div>

            <div class="flex gap-3 mt-8">
                <button onclick="fermerModalAdresse()" 
                    class="flex-1 py-4 rounded-2xl border border-slate-300 font-bold text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
                <button onclick="validerAdresseEtImprimer()" 
                    class="flex-1 py-4 rounded-2xl bg-emerald-600 text-white font-black hover:bg-emerald-700">
                    Valider et Imprimer
                </button>
            </div>
        </div>
    </div>
</div>
<!-- MODALE MEMBRE ET RAPPORTEUR -->
<div id="modalMembreCAP" class="fixed inset-0 z-[350] hidden">
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-md"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-xl p-8 border-t-8 border-indigo-600">
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold">
                        <i class="fas fa-users text-lg"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 uppercase tracking-tight">Membre et Rapporteur</h3>
                </div>
                <button type="button" onclick="fermerModalMembreCAP()" class="text-slate-400 hover:text-rose-500 text-2xl font-bold transition-colors">&times;</button>
            </div>

            <form id="formMembreCAP" onsubmit="validerMembreCAP(event)" class="space-y-4">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <p class="text-xs font-black uppercase text-indigo-600 mb-3 tracking-wider"><i class="fas fa-user-shield mr-1"></i> Informations du Membre</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nom et prénoms du membre</label>
                            <input type="text" id="nom_membre" required placeholder="Ex: RAKOTO Jean" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">IM</label>
                            <input type="text" id="im_membre" required placeholder="Ex: 123456" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <p class="text-xs font-black uppercase text-emerald-600 mb-3 tracking-wider"><i class="fas fa-user-edit mr-1"></i> Informations du Rapporteur</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nom et prénoms du rapporteur</label>
                            <input type="text" id="nom_rapporteur" required placeholder="Ex: RABE Marie" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">IM</label>
                            <input type="text" id="im_rapporteur" required placeholder="Ex: 654321" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="fermerModalMembreCAP()" class="flex-1 py-3.5 rounded-xl border border-slate-300 font-bold text-slate-600 hover:bg-slate-50 transition-all text-xs uppercase">
                        Annuler
                    </button>
                    <button type="submit" class="flex-1 py-3.5 rounded-xl bg-indigo-600 text-white font-black hover:bg-indigo-700 transition-all text-xs uppercase tracking-wider shadow-lg">
                        Valider
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- MODALE NUMÉRO ET SIGLE RELEVÉ DE SERVICE -->
<div id="modalReleveService" class="fixed inset-0 z-[500] hidden">
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-md"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg p-8 border-t-8 border-indigo-600">
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold">
                        <i class="fas fa-file-contract text-lg"></i>
                    </div>
                    <h3 class="text-lg font-black text-slate-900 uppercase tracking-tight">Relevé de Service</h3>
                </div>
                <button type="button" onclick="fermerModalReleveService()" class="text-slate-400 hover:text-rose-500 text-2xl font-bold transition-colors">&times;</button>
            </div>

            <form id="formReleveService"
                data-type-demande="<?= htmlspecialchars($type_cle ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                onsubmit="genererReleveService(event)"
                class="space-y-4">
                <input type="hidden" id="releve_im" value="">
                
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Numéro d'ordre (3 chiffres)</label>
                        <input type="text" id="releve_numero" required placeholder="001" maxlength="10" 
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:outline-none font-bold text-slate-800">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Sigle DREN/CISCO</label>
                        <input type="text" id="releve_sigle" required placeholder="DREN/VV/CISCO/MNJ" 
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:outline-none font-bold text-slate-800">
                    </div>

                    <div class="p-3 bg-indigo-50/50 rounded-xl border border-indigo-100 text-xs font-semibold text-indigo-700">
                        Aperçu du numéro : <br>
                        <span id="aperu_numero_releve" class="font-extrabold text-indigo-900">N°<?= date('Y'); ?>/_____- ...</span>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="fermerModalReleveService()" class="flex-1 py-3.5 rounded-xl border border-slate-300 font-bold text-slate-600 hover:bg-slate-50 transition-all text-xs uppercase">
                        Annuler
                    </button>
                    <button type="submit" class="flex-1 py-3.5 rounded-xl bg-indigo-600 text-white font-black hover:bg-indigo-700 transition-all text-xs uppercase tracking-wider shadow-lg">
                        Télécharger
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function() {
    window.infosActuelles = {}; 
    const modeFinaliserAuto = <?= json_encode($mode_finaliser) ?>;

    window.chargerFormulaireTitularisation = function(im, alerteId) {
        if (typeof window.loadPage === 'function') {
            window.loadPage(`formulaire_demande.php?im=${im}&alerte_id=${alerteId}`);
        } else {
            window.location.href = `formulaire_demande.php?im=${im}&alerte_id=${alerteId}`;
        }
    };

    document.addEventListener('click', function(e) {

        if (e.target.closest('#btnDemanderDos')) {
            const btn = e.target.closest('#btnDemanderDos');
            if (btn) window.envoyerDemandeDos(btn);
            return;
        }

        const btnVisualiser = e.target.closest('#btnVisualiserPieces');
        if (btnVisualiser) {
            e.preventDefault();
            
            window.infosActuelles = {
                roleSpecifique: btnVisualiser.getAttribute('data-role-specifique') || 'agent',
                statut: btnVisualiser.getAttribute('data-dos-statut'),
                type: btnVisualiser.getAttribute('data-type-print'),
                bloque: btnVisualiser.getAttribute('data-bloque') === '1',
                bloqueTitu: btnVisualiser.getAttribute('data-bloque-titu') === '1',
                idTitu: btnVisualiser.getAttribute('data-id-titu'),
                im: btnVisualiser.getAttribute('data-im'),
                alerteId: btnVisualiser.getAttribute('data-alerte-id'),
                nbRetards: btnVisualiser.getAttribute('data-nb-retards') || 0,
                dateRetard: btnVisualiser.getAttribute('data-date-retard') || '',
                numAttendu: btnVisualiser.getAttribute('data-num-attendu'), 
                titre: btnVisualiser.getAttribute('data-titre'),
                piecesStr: btnVisualiser.getAttribute('data-pieces'),
                corps: btnVisualiser.getAttribute('data-corps') || ""
            };

            if (infosActuelles.bloqueTitu) {
                Swal.fire({
                    title: '<span class="text-orange-600 font-black uppercase italic">Titularisation requise</span>',
                    html: `
                        <div class="text-sm text-slate-600 text-left space-y-3 p-2">
                            <div class="bg-orange-50 border-l-4 border-orange-500 p-3 text-orange-700 mb-4 rounded-r-xl">
                                <i class="fas fa-info-circle mr-2 text-lg"></i> <b>Action prioritaire</b>
                            </div>
                            <p class="text-base">Vous ne pouvez pas progresser vers vos <b>avancements</b> tant que votre <b>titularisation</b> n'est pas régularisée.</p>
                            <p class="italic text-xs text-slate-400">Cette étape confirme votre statut définitif dans l'administration.</p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Faire ma titularisation',
                    cancelButtonText: 'Plus tard',
                    confirmButtonColor: '#4f46e5', 
                    cancelButtonColor: '#64748b',
                    customClass: { 
                        popup: 'rounded-[2rem] border-4 border-orange-100 shadow-2xl', 
                        confirmButton: 'rounded-xl font-black uppercase tracking-wider px-6 py-3', 
                        cancelButton: 'rounded-xl font-bold' 
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.chargerFormulaireTitularisation(infosActuelles.im, infosActuelles.idTitu);
                    }
                });
                return;
            }

            if (infosActuelles.bloque) {
                window.ouvrirContenuPieces(true); 
            } else {
                window.ouvrirContenuPieces(false);
                
                if (infosActuelles.statut === 'ATTRIBUE' && !infosActuelles.alerteId.includes('_INTG')) {
                    fetch('actions/dossiers/save_dos.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'enregistrer_final', im: infosActuelles.im, alerte_id: infosActuelles.alerteId })
                    });
                }
            }
        }
    });

    window.envoyerDemandeDos = function(btnElement) {
        btnElement.disabled = true;
        const originalContent = btnElement.innerHTML;
        btnElement.innerHTML = '<i class="fas fa-spinner fa-spin text-lg"></i> <span>Envoi en cours...</span>';

        const imExtrait = btnElement.getAttribute('data-im');
        const alerteIdExtraite = btnElement.getAttribute('data-alerte-id');

        fetch('actions/dossiers/save_dos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'demander_numero', 
                im: imExtrait, 
                alerte_id: alerteIdExtraite 
            })
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("Server HTML output error:", text);
                throw new Error("Le serveur a retourné une réponse non valide (Erreur PHP possible).");
            }
        })
        .then(data => {
            if (data.success) {
                const modalSuccess = document.getElementById('modalSuccessDemande');
                if (modalSuccess) modalSuccess.classList.remove('hidden');

                document.getElementById('btnDemanderDos').classList.add('hidden');
                const btnVisualiser = document.getElementById('btnVisualiserPieces');
                if (btnVisualiser) btnVisualiser.classList.remove('hidden');

                setTimeout(() => {
                    fermerModale();
                }, 2500);
            } else {
                btnElement.disabled = false;
                btnElement.innerHTML = originalContent;
                alert("Erreur : " + (data.message || 'Impossible de demander le numéro'));
            }
        })
        .catch(err => {
            btnElement.disabled = false;
            btnElement.innerHTML = originalContent;
            console.error(err);
            alert(err.message || "Une erreur est survenue lors de l'envoi.");
        });
    };

    window.updateDestinataire = function () {
        const alerteId = infosActuelles.alerteId || '';
        let destinataire = "LE PREFET";
        
        if (alerteId.includes('_INTG') || alerteId.includes('_TITU') || alerteId.includes('_RETRAITE')) {
            destinataire = "LE MINISTRE DE LA FONCTION PUBLIQUE";
        }
        
        const input = document.getElementById('inputDestinataire');
        if (input) input.value = destinataire;
    };

    window.ouvrirContenuPieces = function(isBloque) {
        const modalPieces = document.getElementById('modalPieces');
        const header = document.getElementById('modalHeader');
        const title = document.getElementById('modalTitle');
        const subTitle = document.getElementById('modalSubTitle');
        const iconContainer = document.getElementById('modalIconContainer');
        const icon = document.getElementById('modalIcon');
        const content = document.getElementById('modalContent');
        const btnImp = document.getElementById('btnImprimerModale');
        const nbMessage = document.querySelector('.bg-rose-50.text-slate-500');

        if (isBloque) {
            if(header) header.style.display = "none";
            if(btnImp) btnImp.classList.add('hidden');
            if(nbMessage) nbMessage.classList.add('hidden');
            const btnClose = document.querySelector('button[onclick="fermerModale()"]');
            if(btnClose) {
                btnClose.className = "absolute top-6 right-6 z-[110] w-10 h-10 bg-slate-100 hover:bg-rose-500 text-slate-500 hover:text-white rounded-full flex items-center justify-center transition-all shadow-sm";
            }
            content.className = "p-4 text-center";
            content.innerHTML = `
                    <div class="col-span-full flex flex-col items-center text-center p-2">
                        <div class="w-20 h-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mb-4 shadow-sm">
                            <i class="fas fa-exclamation-triangle text-4xl"></i>
                        </div>

                        <h3 class="text-3xl font-black text-rose-600 mb-2 uppercase italic tracking-tight">
                            Régularisation nécessaire
                        </h3><br>
                        
                        <p class="text-slate-700 mb-4 leading-relaxed text-lg">
                            Vous avez <strong>${infosActuelles.nbRetards} situation(s)</strong> à régulariser depuis le <strong>${infosActuelles.dateRetard}</strong>.<br>
                            Veuillez d'abord régulariser tous ces retards d'avancement avant de faire la demande d'intégration.
                        </p>

                        <div class="bg-slate-50 border border-slate-200 p-4 rounded-[1.5rem] mb-5 w-full">
                            <div class="flex items-start gap-3 text-left">
                                <div class="bg-white p-2 rounded-xl shadow-sm border border-slate-100 flex-shrink-0">
                                    <i class="fas fa-lightbulb text-indigo-500 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-slate-600 text-base leading-snug">
                                        Si la notification n'apparaît plus dans votre boîte de réception, elle a peut-être déjà été consultée. 
                                        Consultez votre <span class="font-bold text-indigo-600 uppercase text-xs tracking-widest">Historique des notifications</span> pour retrouver la liste complète des alertes déjà lues.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center w-full">
                            <button onclick="fermerModale()" 
                                class="group flex items-center gap-3 px-10 py-3 bg-slate-900 text-white rounded-xl font-black text-sm uppercase tracking-[0.2em] hover:bg-indigo-600 transition-all shadow-xl active:scale-95">
                                <span>J'ai compris</span>
                                <i class="fas fa-check-circle text-emerald-400 group-hover:text-white transition-colors"></i>
                            </button>
                        </div>
                    </div>
                `;
        } else {
            if(header) {
                header.style.display = "flex";
                header.style.backgroundColor = ""; 
                header.classList.add('bg-slate-900');
            }

            if(title) {
                title.style.display = "block";
                title.innerHTML = "Liste des pièces à fournir";
            }

            if(iconContainer) {
                iconContainer.style.backgroundColor = "";
                iconContainer.className = "h-12 w-12 bg-indigo-500 rounded-2xl flex items-center justify-center shadow-lg";
            }
            if(icon) icon.className = "fas fa-folder-open text-white text-xl";
            if(btnImp) btnImp.classList.remove('hidden');
            if(nbMessage) nbMessage.classList.remove('hidden');
            
            content.className = "grid grid-cols-1 md:grid-cols-2 gap-3 p-1";
            content.innerHTML = '';
            
            const pieces = infosActuelles.piecesStr ? infosActuelles.piecesStr.split(',') : [];
            const motsClesExternes = ['CIN', 'Diplôme', 'Compte rendu', 'Photocopie', 'dernier', 'Souche', 'Avis de crédit', 'Acte de naissance'];

            const extraireQuantite = (texte) => {
                const match = texte.match(/\((\d+)\)/);
                if (match) {
                    const quantite = match[0];
                    const nomNettoye = texte.replace(/\(\d+\)/, '').trim();
                    return { nom: nomNettoye, quantite: quantite };
                }
                return { nom: texte, quantite: '' };
            };

            const estAgent = (infosActuelles.roleSpecifique === 'agent');
            const estEnAttente = (infosActuelles.statut === 'EN_ATTENTE');
            const estAttribue = (infosActuelles.statut === 'ATTRIBUE');

            pieces.forEach((p, index) => {
                const pTrim = p.trim();
                if (!pTrim) return;
                
                const numSequentiel = index + 1;
                const { nom, quantite } = extraireQuantite(pTrim);
                const pLow = pTrim.toLowerCase();

                const estBIN = pLow.includes('bulletin');
                const estVmed57 = pLow.includes('57');
                const estVmed58 = pLow.includes('58');
                const releveService = pLow.includes('relevé');
                const estProjetOuPV = pLow.includes('projet') || pLow.includes('pv cap');
                const estExterne = motsClesExternes.some(mot => pLow.includes(mot.toLowerCase()));
                
                let estTelechargeable = false;
                let estPieceBleueAgent = false;

                if (estBIN || estVmed57 || estVmed58 || releveService) {
                    estTelechargeable = true;
                } else if (estProjetOuPV) {
                    if (estAgent) {
                        estPieceBleueAgent = true;
                    } else {
                        if (estAttribue) {
                            estTelechargeable = true;
                        } else {
                            estPieceBleueAgent = false;
                        }
                    }
                }
                if (releveService) {
                    if (estAgent) {
                        content.innerHTML += `
                            <div class="flex items-center justify-between gap-3 p-3 rounded-xl border border-indigo-200 bg-indigo-50/50 text-indigo-700 shadow-sm h-full">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-500 text-white font-black text-xs flex items-center justify-center shadow-sm shrink-0">
                                        ${numSequentiel}
                                    </div>
                                    <span class="text-[11px] font-black uppercase leading-tight text-left break-words">
                                        ${nom}
                                    </span>
                                </div>
                                <span class="text-[11px] font-black tracking-wider shrink-0 bg-white/80 px-2 py-0.5 rounded-md ml-2">${quantite}</span>
                            </div>`;
                    } else {
                        content.innerHTML += `
                            <div onclick="ouvrirListeReleveService('${infosActuelles.im}')" 
                                class="flex items-center justify-between gap-3 p-3 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white transition-all cursor-pointer group shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-sm shrink-0 group-hover:bg-white group-hover:text-indigo-600 transition-colors">
                                        ${numSequentiel}
                                    </div>
                                    <span class="text-[11px] font-black uppercase leading-tight text-left break-words">
                                        ${nom} <i class="fas fa-download ml-1 text-xs opacity-80"></i>
                                    </span>
                                </div>
                                <span class="text-[11px] font-black tracking-wider shrink-0 bg-white/80 group-hover:bg-indigo-700 px-2 py-0.5 rounded-md ml-2">${quantite}</span>
                            </div>`;
                    }
                } else if (estTelechargeable) {
                    let mode = '';
                    let page = '';

                    if (estBIN) mode = 'btn';
                    else if (estVmed57) mode = 'vmed57';
                    else if (estVmed58) mode = 'vmed58';
                    else if (pLow.includes('contrat')) mode = 'pct';
                    else if (pLow.includes('avenant')) mode = 'pavenant';
                    else if (pLow.includes('arrêté') || pLow.includes('arrete')) mode = 'parrete';
                    else if (pLow.includes('décision') || pLow.includes('decision')) {
                        if (pLow.includes('compensatrice')) {
                            mode = 'pdecision_compensatrice';
                        } else {
                            mode = 'pdecision';
                        }
                    }
                    else if (pLow.includes('pv cap')) mode = 'pvcap';

                    if (infosActuelles.alerteId.includes('_AVANCEMENT')) {
                        page = 'documents/carriere/generate_avancement.php';
                    } else if (infosActuelles.alerteId.includes('_TITU')) {
                        page = 'documents/carriere/generate_titularisation.php';
                    } else if (infosActuelles.alerteId.includes('_ADMISSION_RETRAITE')) {
                        page = 'documents/carriere/generate_admission_retraite.php';
                    } else if (infosActuelles.alerteId.includes('_COMPENSATRICE')) {
                        page = 'documents/carriere/generate_compensatrice.php';
                    } else if (infosActuelles.alerteId.includes('_INSTALLATION')) {
                        page = 'documents/carriere/generate_installation.php';
                    } else if (infosActuelles.alerteId.includes('_RNC2')) {
                        page = 'documents/carriere/generate_RNC2.php';
                    } else {
                        page = 'documents/carriere/generate_RNC1.php';
                    }

                    if (estBIN) {
                        content.innerHTML += `
                            <div onclick="ouvrirModalBulletin('${infosActuelles.im}')" 
                                class="flex items-center justify-between gap-3 p-3 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white transition-all cursor-pointer group shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-sm shrink-0 group-hover:bg-white group-hover:text-indigo-600 transition-colors">
                                        ${numSequentiel}
                                    </div>
                                    <span class="text-[11px] font-black uppercase leading-tight text-left break-words">
                                        ${nom} <i class="fas fa-download ml-1 text-xs opacity-80"></i>
                                    </span>
                                </div>
                                <span class="text-[11px] font-black tracking-wider shrink-0 bg-white/80 group-hover:bg-indigo-700 px-2 py-0.5 rounded-md ml-2">${quantite}</span>
                            </div>`;
                    } else if (mode === 'pdecision_compensatrice') {
                         const anneeRef = parseInt("<?php echo $annee_ref; ?>", 10);
                         const annee1 = anneeRef - 2;
                         const annee2 = anneeRef - 1;
                         const annee3 = anneeRef;
                    
                         content.innerHTML += `
                             <div onclick="ouvrirModaleSaisieConges('${infosActuelles.im}', '${infosActuelles.alerteId}', '${infosActuelles.corps}', '${annee1}', '${annee2}', '${annee3}')" 
                                 class="flex items-center justify-between gap-3 p-3 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white transition-all cursor-pointer group shadow-sm">
                                 <div class="flex items-center gap-3">
                                     <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-sm shrink-0 group-hover:bg-white group-hover:text-indigo-600 transition-colors">
                                         ${numSequentiel}
                                     </div>
                                     <span class="text-[11px] font-black uppercase leading-tight text-left break-words">
                                         ${nom} <i class="fas fa-download ml-1 text-xs opacity-80"></i>
                                     </span>
                                 </div>
                                 <span class="text-[11px] font-black tracking-wider shrink-0 bg-white/80 group-hover:bg-indigo-700 px-2 py-0.5 rounded-md ml-2">${quantite}</span>
                             </div>`;
                    } else {
                        content.innerHTML += `
                            <a href="${page}?im=${infosActuelles.im}&alerte_id=${infosActuelles.alerteId}&corps=${encodeURIComponent(infosActuelles.corps)}&mode=${mode}" 
                            class="flex items-center justify-between gap-3 p-3 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white transition-all no-underline group shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-sm shrink-0 group-hover:bg-white group-hover:text-indigo-600 transition-colors">
                                        ${numSequentiel}
                                    </div>
                                    <span class="text-[11px] font-black uppercase leading-tight text-left break-words">
                                        ${nom} <i class="fas fa-download ml-1 text-xs opacity-80"></i>
                                    </span>
                                </div>
                                <span class="text-[11px] font-black tracking-wider shrink-0 bg-white/80 group-hover:bg-indigo-700 px-2 py-0.5 rounded-md ml-2">${quantite}</span>
                            </a>`;
                    }
                } else {
                    let bgClass = 'bg-emerald-50';
                    let borderClass = 'border-emerald-200';
                    let textClass = 'text-emerald-700';
                    let numBgColor = 'bg-emerald-600';

                    if (estExterne) {
                        bgClass = 'bg-rose-50';
                        borderClass = 'border-rose-200';
                        textClass = 'text-rose-700';
                        numBgColor = 'bg-rose-600';
                    } else if (estPieceBleueAgent) {
                        bgClass = 'bg-indigo-50/50';
                        borderClass = 'border-indigo-200';
                        textClass = 'text-indigo-700';
                        numBgColor = 'bg-indigo-500';
                    }

                    content.innerHTML += `
                        <div class="flex items-center justify-between gap-3 p-3 rounded-xl border ${borderClass} ${bgClass} ${textClass} shadow-sm h-full">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full ${numBgColor} text-white font-black text-xs flex items-center justify-center shadow-sm shrink-0">
                                    ${numSequentiel}
                                </div>
                                <span class="text-[11px] font-black uppercase leading-tight text-left break-words">${nom}</span>
                            </div>
                            <span class="text-[11px] font-black tracking-wider shrink-0 bg-white/80 px-2 py-0.5 rounded-md ml-2">${quantite}</span>
                        </div>`;
                }
            });
            if (btnImp) {
                btnImp.onclick = function() { 
                    updateDestinataire();                   
                    document.getElementById('modalAdressePrefet').classList.remove('hidden');
                };
            }
        }
        
        if(modalPieces) {
            modalPieces.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    };

    const btnImprimer = document.getElementById('btnImprimerModale');
    if (btnImprimer) {
        btnImprimer.onclick = function() { 
            document.getElementById('modalAdressePrefet').classList.remove('hidden');
        };
    }

    window.genererBulletin = function(im) {
        if (!im) {
            Swal.fire('Erreur', 'Matricule manquant pour la génération.', 'error');
            return;
        }
        const url = `documents/carriere/generate_bin.php?im=${im}`;
        window.open(url, '_blank');
    };

    window.ouvrirModalBulletin = function(im) {
        fetch(`api/carriere/get_avancements.php?im=${im}`)
            .then(r => r.json())
            .then(data => {
                let rows = data.map(a => `
                    <tr class="border-t hover:bg-slate-50">
                        <td class="px-4 py-3">${a.statut || '-'}</td>
                        <td class="px-4 py-3">${a.type_acte_bin || a.av_type_acte || '-'}</td>
                        <td class="px-4 py-3 font-medium">${a.numero_acte_bin || a.av_acte_no || '-'}</td>
                        <td class="px-4 py-3">${a.date_acte_bin ? new Date(a.date_acte_bin).toLocaleDateString('fr-FR') : (a.av_acte_date ? new Date(a.av_acte_date).toLocaleDateString('fr-FR') : '-')}</td>
                        <td class="px-4 py-3">${a.corps_bin || a.av_corps || '-'}</td>
                        <td class="px-4 py-3">${a.grade_bin || a.av_grade || '-'}</td>
                    </tr>
                `).join('');

                let html = `
                    <div id="modalBulletin" class="fixed inset-0 z-[400] flex items-center justify-center bg-slate-900/70 p-4">
                        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-6xl max-h-[92vh] flex flex-col">
                            <div class="bg-slate-900 text-white px-8 py-5 flex justify-between items-center rounded-t-3xl">
                                <h3 class="text-2xl font-black">📋 Avancements Successifs de l'Agent</h3>
                                <button onclick="fermerModalBulletin()" class="text-4xl leading-none hover:text-red-400 transition-colors">&times;</button>
                            </div>
                            
                            <div class="p-8 flex-1 overflow-auto">
                                <button onclick="ouvrirFormAjoutAvancement('${im}')" 
                                        class="mb-6 flex items-center gap-3 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3.5 rounded-2xl font-bold transition-all">
                                    <i class="fas fa-plus"></i> Ajouter un nouvel avancement
                                </button>
                                
                                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                                    <table class="w-full">
                                        <thead class="bg-slate-100 sticky top-0">
                                            <tr>
                                                <th class="px-6 py-4 text-left">Statut</th>
                                                <th class="px-6 py-4 text-left">Type d'Acte</th>
                                                <th class="px-6 py-4 text-left">Numéro</th>
                                                <th class="px-6 py-4 text-left">Date</th>
                                                <th class="px-6 py-4 text-left">Corps</th>
                                                <th class="px-6 py-4 text-left">Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            ${rows || '<tr><td colspan="6" class="text-center py-12 text-slate-400">Aucun avancement enregistré pour cet agent</td></tr>'}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="border-t p-6 flex justify-end gap-4 bg-slate-50 rounded-b-3xl">
                                <button onclick="fermerModalBulletin()" 
                                        class="px-8 py-4 font-bold text-slate-600 hover:bg-slate-100 rounded-2xl">Fermer</button>
                                <button onclick="genererBulletin('${im}')" 
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-4 rounded-2xl font-black flex items-center gap-2">
                                    <i class="fas fa-file-word"></i> Générer le Bulletin Word
                                </button>
                            </div>
                        </div>
                    </div>`;
                
                document.body.insertAdjacentHTML('beforeend', html);
            })
            .catch(() => alert("Erreur de chargement des avancements"));
    };

    window.fermerModalBulletin = function() {
        const modals = document.querySelectorAll('.fixed.inset-0.z-\\[400\\]');
        modals.forEach(m => m.remove());
    };

    window.ouvrirFormAjoutAvancement = function(im) {
        fetch(`api/carriere/get_avancements.php?im=${im}`)
            .then(r => r.json())
            .then(data => {
                let rows = data.map(a => `
                    <tr class="border-t hover:bg-slate-50">
                        <td class="px-4 py-3">${a.statut || '-'}</td>
                        <td class="px-4 py-3">${a.type_acte_bin || a.av_type_acte || '-'}</td>
                        <td class="px-4 py-3 font-medium">${a.numero_acte_bin || a.av_acte_no || '-'}</td>
                        <td class="px-4 py-3">${a.date_acte_bin ? new Date(a.date_acte_bin).toLocaleDateString('fr-FR') : (a.av_acte_date ? new Date(a.av_acte_date).toLocaleDateString('fr-FR') : '-')}</td>
                        <td class="px-4 py-3">${a.corps_bin || a.av_corps || '-'}</td>
                        <td class="px-4 py-3">${a.grade_bin || a.av_grade || '-'}</td>
                    </tr>
                `).join('');

                let formHtml = `
                    <div id="modalFormAvancement" class="fixed inset-0 z-[500] flex items-center justify-center bg-slate-900/80 p-4 backdrop-blur-sm overflow-y-auto">
                        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-5xl p-6 border-t-8 border-emerald-600 max-h-[92vh] flex flex-col my-auto">
                            <div class="flex justify-between items-center mb-6">
                                <h4 class="text-xl font-black text-slate-800 uppercase italic">🆕 Ajouter un Avancement (Agent IM: ${im})</h4>
                                <button onclick="document.getElementById('modalFormAvancement').remove()" class="text-3xl font-bold text-slate-400 hover:text-rose-500 transition-colors">&times;</button>
                            </div>

                            <div class="mb-6 flex-1 overflow-y-auto">
                                <h5 class="text-xs font-black uppercase text-emerald-600 mb-2 tracking-wider"><i class="fas fa-plus-circle mr-1"></i> Formulaire du nouvel avancement</h5>
                                <form id="formNouvelAvancement" onsubmit="enregistrerAvancement(event, '${im}')">
                                    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="text-slate-500 border-b border-slate-200 text-left">
                                                    <th class="pb-3 font-bold uppercase text-[11px] tracking-wider">Statut / Type Acte</th>
                                                    <th class="pb-3 font-bold uppercase text-[11px] tracking-wider">Référence Acte</th>
                                                    <th class="pb-3 font-bold uppercase text-[11px] tracking-wider">Structure Carrière</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="align-top pr-4 space-y-4 w-1/3">
                                                        <div>
                                                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Statut</label>
                                                            <select id="adv_statut" required class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none">
                                                                <option value="Contractuel EFA">Contractuel EFA</option>
                                                                <option value="Fonctionnaire">Fonctionnaire</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Type d'Acte</label>
                                                            <select id="adv_type_acte" required class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none">
                                                                <option value="Contrat">Contrat</option>
                                                                <option value="Avenant">Avenant</option>
                                                                <option value="Arrêté">Arrêté</option>
                                                            </select>
                                                        </div>
                                                    </td>
                                                    
                                                    <td class="align-top pr-4 space-y-4 w-1/3">
                                                        <div>
                                                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Numéro Acte</label>
                                                            <input type="text" id="adv_numero_acte" required placeholder="Ex: 104-MFP/DGFP" class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none">
                                                        </div>
                                                        <div>
                                                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Date Acte</label>
                                                            <input type="date" id="adv_date_acte" required class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none">
                                                        </div>
                                                    </td>
                                                    
                                                    <td class="align-top space-y-4 w-1/3">
                                                        <div>
                                                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Corps</label>
                                                            <select id="adv_corps" required class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none">
                                                                <option value="">Sélectionnez un corps...</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Grade</label>
                                                            <select id="adv_grade" required disabled class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-slate-100 focus:border-emerald-500 outline-none">
                                                                <option value="">Choisir d'abord un corps...</option>
                                                            </select>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="flex justify-end gap-3 mt-6">
                                        <button type="button" onclick="document.getElementById('modalFormAvancement').remove()" class="px-6 py-3 font-bold text-slate-500 hover:bg-slate-100 rounded-xl text-sm transition-all">Annuler</button>
                                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3 rounded-xl font-black text-sm uppercase tracking-wider flex items-center gap-2 shadow-lg transition-all">
                                            <i class="fas fa-save"></i> Enregistrer l'avancement
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>`;

                document.body.insertAdjacentHTML('beforeend', formHtml);

                let dataCorps = [];
                let dataGrades = [];

                Promise.all([
                    fetch('api/dossiers/get_references.php?type=corps').then(r => r.json()),
                    fetch('api/dossiers/get_references.php?type=grades').then(r => r.json())
                ]).then(([corps, grades]) => {
                    dataCorps = corps;
                    dataGrades = grades;

                    const selectCorps = document.getElementById('adv_corps');
                    corps.forEach(c => {
                        let opt = document.createElement('option');
                        opt.value = c.libelle_corps;
                        opt.textContent = c.libelle_corps;
                        opt.setAttribute('data-modele-id', c.modele_id);
                        selectCorps.appendChild(opt);
                    });
                }).catch(err => console.error("Erreur de chargement des référentiels:", err));

                const filtrerGradesMetier = () => {
                    const selectCorps = document.getElementById('adv_corps');
                    const selectGrade = document.getElementById('adv_grade');
                    const statut = document.getElementById('adv_statut').value;
                    
                    const selectedOption = selectCorps.options[selectCorps.selectedIndex];
                    if (!selectedOption || !selectedOption.value) {
                        selectGrade.disabled = true;
                        selectGrade.innerHTML = '<option value="">Choisir d\'abord un corps...</option>';
                        return;
                    }

                    const corpsText = selectedOption.value.toUpperCase();
                    const modeleId = selectedOption.getAttribute('data-modele-id');

                    let gradesFiltres = dataGrades.filter(g => g.modele_id == modeleId);

                    if (corpsText.includes('"C"') || corpsText.includes('OPERATEURS')) {
                        if (statut === 'Contractuel EFA') {
                            gradesFiltres = gradesFiltres.filter(g => g.libelle_grade.toUpperCase().includes('ECHELLE III'));
                        } else if (statut === 'Fonctionnaire') {
                            gradesFiltres = gradesFiltres.filter(g => !g.libelle_grade.toUpperCase().includes('ECHELLE III'));
                        }
                    } else if (corpsText.includes('"B"') || corpsText.includes('ENCADREURS')) {
                        if (statut === 'Contractuel EFA') {
                            gradesFiltres = gradesFiltres.filter(g => g.libelle_grade.toUpperCase().includes('ECHELLE IV'));
                        } else if (statut === 'Fonctionnaire') {
                            gradesFiltres = gradesFiltres.filter(g => !g.libelle_grade.toUpperCase().includes('ECHELLE IV'));
                        }
                    }

                    selectGrade.innerHTML = '<option value="">Sélectionnez un grade cible...</option>';
                    if (gradesFiltres.length > 0) {
                        selectGrade.disabled = false;
                        selectGrade.className = "w-full border border-slate-300 rounded-xl px-3 py-2 bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none";
                        gradesFiltres.forEach(g => {
                            let opt = document.createElement('option');
                            opt.value = g.libelle_grade;
                            opt.textContent = g.libelle_grade;
                            selectGrade.appendChild(opt);
                        });
                    } else {
                        selectGrade.disabled = true;
                        selectGrade.className = "w-full border border-slate-200 rounded-xl px-3 py-2 bg-slate-50 text-slate-400 outline-none";
                        selectGrade.innerHTML = '<option value="">Aucun grade compatible pour ce statut</option>';
                    }
                };

                document.body.addEventListener('change', function(e) {
                    if (e.target && (e.target.id === 'adv_corps' || e.target.id === 'adv_statut')) {
                        filtrerGradesMetier();
                    }
                });
            })
            .catch(() => alert("Erreur lors de la récupération des avancements existants"));
    };

    window.enregistrerAvancement = function(event, im) {
        event.preventDefault();

        const payload = {
            im: im,
            statut: document.getElementById('adv_statut').value,
            type_acte: document.getElementById('adv_type_acte').value,
            numero_acte: document.getElementById('adv_numero_acte').value,
            date_acte: document.getElementById('adv_date_acte').value,
            corps: document.getElementById('adv_corps').value,
            grade: document.getElementById('adv_grade').value
        };

        fetch('actions/carriere/save_avancement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalFormAvancement').remove();
                fermerModalBulletin();
                ouvrirModalBulletin(im);

                Swal.fire({
                    title: 'Enregistré !',
                    text: 'L\'avancement a été sauvegardé avec succès.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Erreur', data.message || 'Impossible d\'enregistrer l\'avancement.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Erreur', 'Un problème réseau est survenu.', 'error');
        });
    };

    window.fermerModalAdresse = function() {
        document.getElementById('modalAdressePrefet').classList.add('hidden');
    };

    window.validerAdresseEtImprimer = function() {
        const genre = document.getElementById('selectGenre').value;
        fermerModalAdresse();
        
        const { type, alerteId, im, corps } = infosActuelles;
        let page = (type === 'INTG') ? 'documents/carriere/generate_integration.php' : 
                (alerteId.includes('_TITU') ? 'documents/carriere/generate_titularisation.php' : 
                (alerteId.includes('_ADMISSION_RETRAITE') ? 'documents/carriere/generate_admission_retraite.php' :
                (alerteId.includes('_COMPENSATRICE') ? 'documents/carriere/generate_compensatrice.php' :
                (alerteId.includes('_INSTALLATION') ? 'documents/carriere/generate_installation.php' : 
                (alerteId.includes('_RNC2') ? 'documents/carriere/generate_RNC2.php' : 'documents/carriere/generate_RNC1.php')))));     
        if (alerteId.includes('_AVANCEMENT')) {
            page = 'documents/carriere/generate_avancement.php';
        }
        
        const baseUrl = `${page}?im=${im}&alerte_id=${alerteId}&corps=${encodeURIComponent(corps)}&genre=${genre}`;

        fetch('actions/dossiers/save_dos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'enregistrer_final', im: im, alerte_id: alerteId })
        });
        window.open(baseUrl, '_blank');
    };
    
    window.fermerModale = function() {
        document.querySelectorAll('[id^="modal"]').forEach(m => m.classList.add('hidden'));
        document.body.style.overflow = 'auto';
    };

    window.addEventListener('DOMContentLoaded', () => {
        if (modeFinaliserAuto) {
            setTimeout(() => {
                const btn = document.getElementById('btnMainAction');
                if(btn) btn.click(); 
            }, 700);
        }
    });

})();
</script>
<script>
    (function() {
        const urlParams = new URLSearchParams(window.location.search);
        const mode = urlParams.get('mode');
        const num = urlParams.get('num');

        if (mode === 'finaliser') {
            console.log("Mode finalisation détecté pour le numéro : " + num);
            setTimeout(() => {
                if (typeof window.ouvrirModale === 'function') {
                    const infos = {
                        type: "<?= substr($alerte_id, -4) === 'INTG' ? 'INTG' : 'RNC' ?>",
                        alerteId: "<?= $alerte_id ?>",
                        im: "<?= $im ?>",
                        corps: "<?= addslashes($sit_reelle['corps_actuel'] ?? '') ?>"
                    };
                    
                    window.ouvrirModale(infos.type, infos.alerteId, infos.im, infos.corps);
                    
                    const inputNum = document.getElementById('num_dossier_saisi');
                    if(inputNum) inputNum.value = num;
                }
            }, 300);
        }
    })();
</script>
<script>
    let urlApresCAP = '';
    window.ouvrirModalMembreCAP = function(urlDestination) {
        urlApresCAP = urlDestination;
        document.getElementById('modalMembreCAP').classList.remove('hidden');
    };

    window.fermerModalMembreCAP = function() {
        document.getElementById('modalMembreCAP').classList.add('hidden');
    };

    window.validerMembreCAP = function(e) {
        e.preventDefault();
        const nom_m = encodeURIComponent(document.getElementById('nom_membre').value.trim());
        const im_m  = encodeURIComponent(document.getElementById('im_membre').value.trim());
        const nom_r = encodeURIComponent(document.getElementById('nom_rapporteur').value.trim());
        const im_r  = encodeURIComponent(document.getElementById('im_rapporteur').value.trim());

        const finalUrl = `${urlApresCAP}&nom_membre=${nom_m}&im_membre=${im_m}&nom_rapporteur=${nom_r}&im_rapporteur=${im_r}`;
        
        fermerModalMembreCAP();
        window.open(finalUrl, '_blank');
    };

    document.body.addEventListener('click', function(e) {
        const linkPVCAP = e.target.closest('a[href*="mode=pvcap"]');
        if (linkPVCAP) {
            e.preventDefault();
            window.ouvrirModalMembreCAP(linkPVCAP.getAttribute('href'));
        }
    });
</script>
<script>
    window.lieuxEnregistresDispo = [];
    window.ouvrirListeReleveService = function(im) {
        if (infosActuelles.roleSpecifique === 'agent') {
            Swal.fire({
                icon: 'error',
                title: 'Accès restreint',
                text: 'Seul le responsable est autorisé à télécharger le relevé de service.'
            });
            return;
        }
        fetch(`api/carriere/get_avancements_releve.php?im=${encodeURIComponent(im)}`)
            .then(response => response.json())
            .then(data => {
                window.lieuxEnregistresDispo = [...new Set(data.map(a => a.lieu_de_service).filter(l => l && l.trim() !== ''))];
                
                let rows = data.map(a => {
                    const grade = a.av_grade || a.grade_bin || '-';
                    const acteNo = a.av_acte_no || a.acte_no || '-';
                    const acteDate = a.av_acte_date || a.acte_date;
                    const dateEffet = a.av_date_effet || a.date_effet;
                    const lieu = a.lieu_de_service || '';

                    const gradeEsc = grade.replace(/'/g, "\\'");
                    const lieuEsc = lieu.replace(/'/g, "\\'");

                    return `
                        <tr class="border-b border-slate-200 hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5 text-left font-bold text-slate-800">${grade}</td>
                            <td class="px-5 py-3.5 text-left font-medium text-slate-600">${acteNo}</td>
                            <td class="px-5 py-3.5 text-left text-slate-600">${acteDate ? new Date(acteDate).toLocaleDateString('fr-FR') : '-'}</td>
                            <td class="px-5 py-3.5 text-left text-slate-600">${dateEffet ? new Date(dateEffet).toLocaleDateString('fr-FR') : '-'}</td>
                            <td class="px-5 py-3.5 text-left">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold ${lieu ? 'text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full' : 'text-slate-400 italic'}">
                                        ${lieu || 'Non défini'}
                                    </span>
                                    <button onclick="gererAffectationGrade('${im}', '${gradeEsc}', '${lieuEsc}')" 
                                            title="Affecter un lieu de service"
                                            class="w-7 h-7 flex-shrink-0 inline-flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white rounded-full transition-all shadow-md">
                                        <i class="fas fa-plus text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');

                let html = `
                    <div id="modalListeReleveService" class="fixed inset-0 z-[400] flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm">
                        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-6xl max-h-[92vh] flex flex-col overflow-hidden">
                            <div class="bg-slate-900 text-white px-8 py-5 flex justify-between items-center">
                                <h3 class="text-2xl font-black">📋 Avancements successifs (Relevé de service)</h3>
                                <button onclick="fermerListeReleveService()" class="text-4xl leading-none text-slate-400 hover:text-red-400 transition-colors">&times;</button>
                            </div>
                            
                            <div class="p-8 flex-1 overflow-auto bg-slate-50/50">
                                <button onclick="ouvrirFormAjoutAvancementReleve('${im}')" 
                                        class="mb-6 flex items-center gap-3 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3.5 rounded-2xl font-bold transition-all shadow-lg hover:shadow-emerald-600/20">
                                    <i class="fas fa-plus"></i> Ajouter un avancement
                                </button>
                                
                                <div class="overflow-hidden rounded-2xl border border-slate-200 shadow-sm bg-white">
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr class="bg-indigo-600 text-white font-bold border-b border-indigo-700">
                                                <th class="px-5 py-4 text-center tracking-wide">Avancements successifs (Grade)</th>
                                                <th class="px-5 py-4 text-center tracking-wide">N° de l'acte</th>
                                                <th class="px-5 py-4 text-center tracking-wide">Date de l'acte</th>
                                                <th class="px-5 py-4 text-center tracking-wide">Date d'effet</th>
                                                <th class="px-5 py-4 text-center tracking-wide">Affectation</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            ${rows || '<tr><td colspan="5" class="text-left py-12 px-5 text-slate-400 italic">Aucun avancement enregistré pour cet agent</td></tr>'}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="border-t p-6 flex justify-end gap-4 bg-white">
                                <button onclick="fermerListeReleveService()" class="px-8 py-4 font-bold text-slate-600 hover:bg-slate-100 rounded-2xl transition-colors">Fermer</button>
                                <button type="button" onclick="ouvrirModalReleveService('${im}')" 
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-4 rounded-2xl font-black flex items-center gap-2 shadow-lg shadow-indigo-600/20 transition-all">
                                    <i class="fas fa-file-word"></i> Télécharger Relevé de Service
                                </button>
                            </div>
                        </div>
                    </div>`;
                
                const ancienne = document.getElementById('modalListeReleveService');
                if (ancienne) {
                    ancienne.remove();
                }

                document.body.insertAdjacentHTML('beforeend', html);
            })
            .catch(err => {
                console.error("Erreur Fetch Réseau :", err);
                Swal.fire('Erreur', 'Impossible de récupérer les avancements de l\'agent.', 'error');
            });
    };

    window.fermerListeReleveService = function() {
        const modal = document.getElementById('modalListeReleveService');

        if (modal) {
            modal.remove();
        }
    };

    // Logique du questionnaire / affectation selon la présence de lieux
    window.gererAffectationGrade = function(im, avGrade, lieuActuel) {
        // 1. Si AUCUN lieu n'est enregistré dans toute la table pour cet agent
        if (window.lieuxEnregistresDispo.length === 0) {
            // Récupérer le lieuService
            fetch(`api/carriere/get_poste_actuel.php?im=${im}`)
                .then(r => r.json())
                .then(res => {
                    const lieuServiceCalcule = res.lieuService || '-';
                    Swal.fire({
                        title: 'Afectation du lieu de service',
                        html: `Est-ce votre lieu de service quand vous aviez le grade <b>${avGrade}</b> ?<br><br><span class="p-2 bg-slate-100 rounded-lg text-indigo-700 font-bold block">${lieuServiceCalcule}</span>`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Oui',
                        cancelButtonText: 'Non',
                        confirmButtonColor: '#059669',
                        cancelButtonColor: '#dc2626'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Enregistrement direct du lieu de service calculé
                            sauvegarderLieuService(im, avGrade, lieuServiceCalcule);
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            // Affichage du formulaire style poste_actuel
                            afficherFormulaireNouveauLieu(im, avGrade);
                        }
                    });
                });
        } 
        // 2. Si au moins un lieu de service existe déjà dans la table personnel_avancements
        else {
            let optionsRadio = window.lieuxEnregistresDispo.map((lieu, index) => `
                <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 hover:bg-indigo-50/50 cursor-pointer text-left my-2">
                    <input type="radio" name="lieu_choisi" value="${lieu}" ${index === 0 ? 'checked' : ''} class="w-4 h-4 text-indigo-600">
                    <span class="text-xs font-bold text-slate-700">${lieu}</span>
                </label>
            `).join('');

            optionsRadio += `
                <label class="flex items-center gap-3 p-3 bg-emerald-50 rounded-xl border border-emerald-200 hover:bg-emerald-100/50 cursor-pointer text-left my-2">
                    <input type="radio" name="lieu_choisi" value="__NOUVEAU__" class="w-4 h-4 text-emerald-600">
                    <span class="text-xs font-black text-emerald-700">+ Saisir un autre lieu de service</span>
                </label>
            `;

            Swal.fire({
                title: `Lieu de service pour : ${avGrade}`,
                html: `<div class="text-left text-sm font-semibold text-slate-600 mb-2">Sélectionnez le lieu correspondant à ce grade :</div>${optionsRadio}`,
                showCancelButton: true,
                confirmButtonText: 'Valider',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#4f46e5',
                preConfirm: () => {
                    const selected = document.querySelector('input[name="lieu_choisi"]:checked');
                    return selected ? selected.value : null;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    if (result.value === '__NOUVEAU__') {
                        afficherFormulaireNouveauLieu(im, avGrade);
                    } else {
                        sauvegarderLieuService(im, avGrade, result.value);
                    }
                }
            });
        }
    };

    window.afficherFormulaireNouveauLieu = function(im, avGrade) {
        const htmlForm = `
            <div class="space-y-4 text-left p-1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type d'affectation</label>
                    <select id="swal_type_lieu" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:border-indigo-500">
                        <option value="">-- Choisir le type --</option>
                        <option value="MEN">MEN</option>
                        <option value="DREN">DREN</option>
                        <option value="CISCO">CISCO</option>
                        <option value="CRFRP">CRFRP</option>
                        <option value="ZAP">ZAP</option>
                        <option value="LYCEE">LYCÉE</option>
                        <option value="COLLEGE">COLLÈGE (CEG)</option>
                        <option value="PRIMAIRE">PRIMAIRE (EPP)</option>
                        <option value="PRESCOLAIRE">PRÉSCOLAIRE</option>
                    </select>
                </div>
                <div id="container_dynamic_selects" class="space-y-3"></div>
            </div>
        `;

        Swal.fire({
            title: `Nouveau lieu de service (${avGrade})`,
            html: htmlForm,
            showCancelButton: true,
            confirmButtonText: 'Enregistrer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#059669',
            didOpen: () => {
                const selectType = document.getElementById('swal_type_lieu');
                const container = document.getElementById('container_dynamic_selects');

                selectType.addEventListener('change', function() {
                    const val = this.value;
                    container.innerHTML = '';
                    if (!val) return;

                    if (val === 'MEN') {
                        container.innerHTML = `
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Direction (MEN)</label>
                                <select id="swal_ref_direction" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">Chargement...</option></select>
                            </div>`;
                        fetch('api/dossiers/get_localite.php?type=directions')
                            .then(r => r.json())
                            .then(data => {
                                const s = document.getElementById('swal_ref_direction');
                                s.innerHTML = '<option value="">-- Sélectionner la direction --</option>';
                                data.forEach(d => {
                                    const nom = d.nom_direction || d.libelle_direction || d.code_direction || d.nom;
                                    s.innerHTML += `<option value="${nom}">${nom}</option>`;
                                });
                            });
                    } else if (val === 'DREN') {
                        container.innerHTML = `
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Région (DREN)</label>
                                <select id="swal_ref_region" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">Chargement...</option></select>
                            </div>`;
                        fetch('api/dossiers/get_localite.php?type=regions')
                            .then(r => r.json())
                            .then(data => {
                                const s = document.getElementById('swal_ref_region');
                                s.innerHTML = '<option value="">-- Sélectionner la région --</option>';
                                data.forEach(reg => {
                                    const nom = reg.nom_region || reg.region || reg.nom;
                                    s.innerHTML += `<option value="${nom}">${nom}</option>`;
                                });
                            });
                    } else if (val === 'CISCO') {
                        container.innerHTML = `
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">DREN (Région)</label>
                                <select id="swal_ref_region" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">Chargement...</option></select>
                            </div>
                            <div id="box_cisco" class="hidden">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CISCO (District)</label>
                                <select id="swal_ref_district" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">-- Sélectionner --</option></select>
                            </div>`;
                        fetch('api/dossiers/get_localite.php?type=regions')
                            .then(r => r.json())
                            .then(data => {
                                const s = document.getElementById('swal_ref_region');
                                s.innerHTML = '<option value="">-- Sélectionner la région --</option>';
                                data.forEach(reg => {
                                    const nom = reg.nom_region || reg.region || reg.nom;
                                    s.innerHTML += `<option value="${nom}" data-id="${reg.id}">${nom}</option>`;
                                });
                                s.addEventListener('change', function() {
                                    const regId = this.options[this.selectedIndex].getAttribute('data-id');
                                    const box = document.getElementById('box_cisco');
                                    const selectDist = document.getElementById('swal_ref_district');
                                    if (!regId) { box.classList.add('hidden'); return; }
                                    box.classList.remove('hidden');
                                    fetch(`api/dossiers/get_localite.php?type=districts&region_id=${regId}`)
                                        .then(r => r.json())
                                        .then(districts => {
                                            selectDist.innerHTML = '<option value="">-- Sélectionner le district --</option>';
                                            districts.forEach(dst => {
                                                const nomD = dst.nom_district || dst.district || dst.nom;
                                                selectDist.innerHTML += `<option value="${nomD}">${nomD}</option>`;
                                            });
                                        });
                                });
                            });
                    } else if (val === 'CRFRP') {
                        container.innerHTML = `
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Région</label>
                                <select id="swal_ref_region" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">Chargement...</option></select>
                            </div>
                            <div id="box_district" class="hidden">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">District</label>
                                <select id="swal_ref_district" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">-- Sélectionner --</option></select>
                            </div>
                            <div id="box_crfrp" class="hidden">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CRFRP</label>
                                <select id="swal_ref_crfrp" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">-- Sélectionner --</option></select>
                            </div>`;
                        fetch('api/dossiers/get_localite.php?type=regions')
                            .then(r => r.json())
                            .then(data => {
                                const sReg = document.getElementById('swal_ref_region');
                                sReg.innerHTML = '<option value="">-- Sélectionner la région --</option>';
                                data.forEach(reg => {
                                    const nom = reg.nom_region || reg.region || reg.nom;
                                    sReg.innerHTML += `<option value="${nom}" data-id="${reg.id}">${nom}</option>`;
                                });
                                sReg.addEventListener('change', function() {
                                    const regId = this.options[this.selectedIndex].getAttribute('data-id');
                                    const boxDst = document.getElementById('box_district');
                                    const boxCrf = document.getElementById('box_crfrp');
                                    const selectDst = document.getElementById('swal_ref_district');
                                    boxCrf.classList.add('hidden');
                                    if (!regId) { boxDst.classList.add('hidden'); return; }
                                    boxDst.classList.remove('hidden');
                                    fetch(`api/dossiers/get_localite.php?type=districts&region_id=${regId}`)
                                        .then(r => r.json())
                                        .then(districts => {
                                            selectDst.innerHTML = '<option value="">-- Sélectionner le district --</option>';
                                            districts.forEach(dst => {
                                                const nomD = dst.nom_district || dst.district || dst.nom;
                                                selectDst.innerHTML += `<option value="${nomD}" data-id="${dst.id}">${nomD}</option>`;
                                            });
                                        });
                                });

                                document.getElementById('swal_ref_district').addEventListener('change', function() {
                                    const dstId = this.options[this.selectedIndex].getAttribute('data-id');
                                    const boxCrf = document.getElementById('box_crfrp');
                                    const selectCrf = document.getElementById('swal_ref_crfrp');
                                    if (!dstId) { boxCrf.classList.add('hidden'); return; }
                                    boxCrf.classList.remove('hidden');
                                    fetch(`api/dossiers/get_localite.php?type=crfrp&district_id=${dstId}`)
                                        .then(r => r.json())
                                        .then(list => {
                                            selectCrf.innerHTML = '<option value="">-- Sélectionner CRFRP --</option>';
                                            list.forEach(c => {
                                                const nomC = c.nom_crfrp || c.libelle_crfrp || c.nom;
                                                selectCrf.innerHTML += `<option value="${nomC}">${nomC}</option>`;
                                            });
                                        });
                                });
                            });
                    } else if (['ZAP', 'LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'].includes(val)) {
                        let fieldFilter = '';
                        if (val === 'LYCEE') fieldFilter = 'is_lycee=1';
                        else if (val === 'COLLEGE') fieldFilter = 'is_ceg=1';
                        else if (val === 'PRIMAIRE') fieldFilter = 'is_epp=1';
                        else if (val === 'PRESCOLAIRE') fieldFilter = 'is_prescolaire=1';

                        container.innerHTML = `
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">DREN (Région)</label>
                                <select id="swal_ref_region" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">Chargement...</option></select>
                            </div>
                            <div id="box_cisco" class="hidden">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">CISCO (District)</label>
                                <select id="swal_ref_district" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">-- Sélectionner --</option></select>
                            </div>
                            <div id="box_zap" class="hidden">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">ZAP</label>
                                <select id="swal_ref_zap" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">-- Sélectionner --</option></select>
                            </div>
                            ${val !== 'ZAP' ? `
                            <div id="box_etab" class="hidden">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Établissement</label>
                                <select id="swal_ref_etab" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white"><option value="">-- Sélectionner --</option></select>
                            </div>` : ''}`;

                        fetch('api/dossiers/get_localite.php?type=regions')
                            .then(r => r.json())
                            .then(data => {
                                const sReg = document.getElementById('swal_ref_region');
                                sReg.innerHTML = '<option value="">-- Sélectionner la région --</option>';
                                data.forEach(reg => {
                                    const nom = reg.nom_region || reg.region || reg.nom;
                                    sReg.innerHTML += `<option value="${nom}" data-id="${reg.id}">${nom}</option>`;
                                });

                                sReg.addEventListener('change', function() {
                                    const regId = this.options[this.selectedIndex].getAttribute('data-id');
                                    const boxCis = document.getElementById('box_cisco');
                                    const boxZap = document.getElementById('box_zap');
                                    const boxEtab = document.getElementById('box_etab');
                                    if (boxZap) boxZap.classList.add('hidden');
                                    if (boxEtab) boxEtab.classList.add('hidden');

                                    if (!regId) { boxCis.classList.add('hidden'); return; }
                                    boxCis.classList.remove('hidden');

                                    fetch(`api/dossiers/get_localite.php?type=districts&region_id=${regId}`)
                                        .then(r => r.json())
                                        .then(districts => {
                                            const sDist = document.getElementById('swal_ref_district');
                                            sDist.innerHTML = '<option value="">-- Sélectionner le district --</option>';
                                            districts.forEach(dst => {
                                                const nomD = dst.nom_district || dst.district || dst.nom;
                                                sDist.innerHTML += `<option value="${nomD}" data-id="${dst.id}">${nomD}</option>`;
                                            });
                                        });
                                });

                                document.getElementById('swal_ref_district').addEventListener('change', function() {
                                    const dstId = this.options[this.selectedIndex].getAttribute('data-id');
                                    const boxZap = document.getElementById('box_zap');
                                    const boxEtab = document.getElementById('box_etab');
                                    if (boxEtab) boxEtab.classList.add('hidden');

                                    if (!dstId) { boxZap.classList.add('hidden'); return; }
                                    boxZap.classList.remove('hidden');

                                    fetch(`api/dossiers/get_localite.php?type=zaps&district_id=${dstId}`)
                                        .then(r => r.json())
                                        .then(zaps => {
                                            const sZap = document.getElementById('swal_ref_zap');
                                            sZap.innerHTML = '<option value="">-- Sélectionner ZAP --</option>';
                                            zaps.forEach(z => {
                                                const nomZ = z.nom_zap || z.zap || z.nom;
                                                sZap.innerHTML += `<option value="${nomZ}" data-id="${z.id}">${nomZ}</option>`;
                                            });
                                        });
                                });

                                if (val !== 'ZAP') {
                                    document.getElementById('swal_ref_zap').addEventListener('change', function() {
                                        const zapId = this.options[this.selectedIndex].getAttribute('data-id');
                                        const boxEtab = document.getElementById('box_etab');
                                        if (!zapId) { boxEtab.classList.add('hidden'); return; }
                                        boxEtab.classList.remove('hidden');

                                        fetch(`api/dossiers/get_localite.php?type=etablissements&zap_id=${zapId}&${fieldFilter}`)
                                            .then(r => r.json())
                                            .then(etabs => {
                                                const sEtab = document.getElementById('swal_ref_etab');
                                                sEtab.innerHTML = '<option value="">-- Sélectionner Établissement --</option>';
                                                etabs.forEach(e => {
                                                    const nomE = e.nom_etab || e.libelle_etablissement || e.nom_etablissement || e.nom;
                                                    sEtab.innerHTML += `<option value="${nomE}">${nomE}</option>`;
                                                });
                                            });
                                    });
                                }
                            });
                    }
                });
            },
            preConfirm: () => {
                const type = document.getElementById('swal_type_lieu').value;
                if (!type) {
                    Swal.showValidationMessage("Veuillez choisir un type d'affectation");
                    return false;
                }

                let finalLieu = "";

                if (type === 'MEN') {
                    const dir = document.getElementById('swal_ref_direction')?.value;
                    if (!dir) { Swal.showValidationMessage("Veuillez choisir une direction"); return false; }
                    finalLieu = dir;
                } else if (type === 'DREN') {
                    const reg = document.getElementById('swal_ref_region')?.value;
                    if (!reg) { Swal.showValidationMessage("Veuillez choisir une région"); return false; }
                    finalLieu = "DREN " + reg;
                } else if (type === 'CISCO') {
                    const reg = document.getElementById('swal_ref_region')?.value;
                    const dst = document.getElementById('swal_ref_district')?.value;
                    if (!reg || !dst) { Swal.showValidationMessage("Veuillez remplir tous les champs (Région / District)"); return false; }
                    finalLieu = "DREN " + reg + " / CISCO " + dst;
                } else if (type === 'CRFRP') {
                    const crf = document.getElementById('swal_ref_crfrp')?.value;
                    if (!crf) { Swal.showValidationMessage("Veuillez choisir le CRFRP"); return false; }
                    finalLieu = crf;
                } else if (type === 'ZAP') {
                    const reg = document.getElementById('swal_ref_region')?.value;
                    const dst = document.getElementById('swal_ref_district')?.value;
                    const zap = document.getElementById('swal_ref_zap')?.value;
                    if (!reg || !dst || !zap) { Swal.showValidationMessage("Veuillez remplir tous les champs (Région / CISCO / ZAP)"); return false; }
                    finalLieu = "DREN " + reg + " / CISCO " + dst + " / ZAP " + zap;
                } else if (['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'].includes(type)) {
                    const reg = document.getElementById('swal_ref_region')?.value;
                    const dst = document.getElementById('swal_ref_district')?.value;
                    const zap = document.getElementById('swal_ref_zap')?.value;
                    const etab = document.getElementById('swal_ref_etab')?.value;
                    if (!reg || !dst || !zap || !etab) { Swal.showValidationMessage("Veuillez remplir tous les champs de l'établissement"); return false; }
                    finalLieu = "DREN " + reg + " / CISCO " + dst + " / ZAP " + zap + " / " + etab;
                }

                return finalLieu;
            }
        }).then((res) => {
            if (res.isConfirmed && res.value) {
                sauvegarderLieuService(im, avGrade, res.value);
            }
        });
    };

    window.sauvegarderLieuService = function(im, avGrade, lieuService) {
        fetch('actions/carriere/save_lieu_service.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                im: im,
                grade: avGrade,           
                lieu_service: lieuService 
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Succès !',
                    text: 'Le lieu de service a été attribué avec succès.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
                if (typeof window.ouvrirListeReleveService === 'function') {
                    window.ouvrirListeReleveService(im);
                }
            }
        })
        .catch(err => {
            console.error("Erreur enregistrement :", err);
            Swal.fire('Erreur', 'Une erreur réseau est survenue.', 'error');
        });
    };

    window.ouvrirFormAjoutAvancementReleve = function(im) {
    let formHtml = `
        <div id="modalFormAjoutAvancement" class="fixed inset-0 z-[500] flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto animate-fade-in">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-4xl p-6 flex flex-col my-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
                    <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-circle-plus text-indigo-600"></i> Nouvel Avancement (Relevé de Service - IM: ${im})
                    </h3>
                    <button type="button" onclick="document.getElementById('modalFormAjoutAvancement').remove()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-all">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <form id="formAvancementModal" class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <input type="hidden" name="action" value="ajouter">
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">N° Matricule (IM)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="text" name="im" value="${im}" readonly class="w-full pl-10 pr-3 py-2.5 border border-slate-200 rounded-lg bg-slate-100 text-slate-500 text-sm font-medium cursor-not-allowed">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Type d'acte <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-file-signature"></i>
                            </span>
                            <select name="av_type_acte" id="modal_av_type_acte" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm appearance-none bg-white">
                                <option value="">-- Sélectionner --</option>
                                <option value="Contrat">Contrat</option>
                                <option value="Avenant">Avenant</option>
                                <option value="Arrêté">Arrêté</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Type d'avancement <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </span>
                            <select name="av_type_avancement" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm appearance-none bg-white">
                                <option value="Stagiaire">Stagiaire</option>
                                <option value="Echelon" selected>Echelon</option>
                                <option value="Classe">Classe</option>
                                <option value="Intégration">Intégration</option>
                                <option value="Titularisation">Titularisation</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">N° de l'acte <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-hashtag"></i>
                            </span>
                            <input type="text" name="av_acte_no" required placeholder="Ex: 4521/MEN/SG/DRH" class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Date de l'acte <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-calendar-day"></i>
                            </span>
                            <input type="date" name="av_acte_date" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Date d'effet <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-calendar-check"></i>
                            </span>
                            <input type="date" name="av_date_effet" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Corps <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-users-gear"></i>
                            </span>
                            <select name="av_corps" id="modal_av_corps" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm appearance-none bg-white">
                                <option value="">-- Chargement... --</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Grade <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-award"></i>
                            </span>
                            <select name="av_grade" id="modal_av_grade" required disabled class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm appearance-none bg-white">
                                <option value="">-- Choisir grade --</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Indice</label>
                        <div class="relative">
                            <input type="text" name="av_indice" id="modal_av_indice" readonly class="w-full pl-10 pr-3 py-2.5 border border-slate-200 rounded-lg bg-slate-100 text-slate-700 font-bold font-mono text-sm cursor-not-allowed">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 md:col-span-3 border-t border-slate-100 pt-4 mt-2">
                        <button type="button" onclick="document.getElementById('modalFormAjoutAvancement').remove()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-sm px-5 py-2.5 rounded-lg transition-all">
                            Annuler
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm px-6 py-2.5 rounded-lg transition-all flex items-center gap-2">
                            <i class="fa-solid fa-check"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>`;

    document.body.insertAdjacentHTML('beforeend', formHtml);

    const corpsModal = document.getElementById('modal_av_corps');
    const apiEndpoint = 'api/carriere/api_avancement_releve.php';

    // 1. Chargement de la liste des corps
    const formDataCorps = new FormData();
    formDataCorps.append('action', 'charger_corps');
    fetch(apiEndpoint, { method: 'POST', body: formDataCorps })
        .then(res => res.json())
        .then(corpsList => {
            corpsModal.innerHTML = '<option value="">-- Choisir corps --</option>';
            if (Array.isArray(corpsList) && corpsList.length > 0) {
                corpsList.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.libelle_corps;
                    opt.setAttribute('data-id', c.id);
                    opt.textContent = c.libelle_corps;
                    corpsModal.appendChild(opt);
                });
            } else {
                corpsModal.innerHTML = '<option value="">Aucun corps trouvé</option>';
            }
        })
        .catch(err => console.error('Erreur chargement corps:', err));

    // 2. Chargement des grades dynamique selon le corps et le type d'acte
    const chargerGradesModal = function() {
        const corpsSelect = document.getElementById('modal_av_corps');
        const selectedOption = corpsSelect.options[corpsSelect.selectedIndex];
        const corpsDbId = selectedOption ? selectedOption.getAttribute('data-id') : null;
        const typeActe = document.getElementById('modal_av_type_acte').value;
        const selectGrade = document.getElementById('modal_av_grade');
        const inputIndice = document.getElementById('modal_av_indice');

        inputIndice.value = '';
        selectGrade.innerHTML = '<option value="">-- Choisir grade --</option>';

        if (!corpsDbId || !typeActe) {
            selectGrade.disabled = true;
            return;
        }

        const formData = new FormData();
        formData.append('action', 'charger_grades');
        formData.append('corps_id', corpsDbId);
        formData.append('type_acte', typeActe);

        fetch(apiEndpoint, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g.libelle_grade;
                        opt.setAttribute('data-grade-id', g.id);
                        opt.textContent = g.libelle_grade;
                        selectGrade.appendChild(opt);
                    });
                    selectGrade.disabled = false;
                } else {
                    selectGrade.innerHTML = '<option value="">Aucun grade disponible</option>';
                    selectGrade.disabled = true;
                }
            })
            .catch(err => console.error('Erreur chargement grades:', err));
    };

    document.getElementById('modal_av_corps').addEventListener('change', chargerGradesModal);
    document.getElementById('modal_av_type_acte').addEventListener('change', chargerGradesModal);

    // 3. Calcul de l'indice lors de la sélection du grade
    document.getElementById('modal_av_grade').addEventListener('change', function() {
        const corpsSelect = document.getElementById('modal_av_corps');
        const selectedOptionCorps = corpsSelect.options[corpsSelect.selectedIndex];
        const corpsDbId = selectedOptionCorps ? selectedOptionCorps.getAttribute('data-id') : null;

        const selectedOptionGrade = this.options[this.selectedIndex];
        const gradeDbId = selectedOptionGrade ? selectedOptionGrade.getAttribute('data-grade-id') : null;
        const inputIndice = document.getElementById('modal_av_indice');

        if (!gradeDbId || !corpsDbId) {
            inputIndice.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('action', 'calculer_indice');
        formData.append('corps_id', corpsDbId);
        formData.append('grade_type_id', gradeDbId);

        fetch(apiEndpoint, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                inputIndice.value = data.indice || '';
            })
            .catch(err => console.error('Erreur calcul indice:', err));
    });

    // 4. Soumission du formulaire
    document.getElementById('formAvancementModal').addEventListener('submit', function(e) {
        e.preventDefault();

        fetch(apiEndpoint, { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('modalFormAjoutAvancement').remove();
                    if (typeof window.ouvrirListeReleveService === 'function') {
                        window.ouvrirListeReleveService(im);
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès !',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire({ icon: 'error', title: 'Erreur', text: 'Une erreur réseau est survenue.' });
            });
    });
};
// Variable globale pour suivre le statut du téléchargement
window.releveDejaTelecharge = false;

window.ouvrirModalReleveService = async function(im) {
    if (!im) {
        Swal.fire('Erreur', 'Matricule manquant.', 'warning');
        return;
    }

    // 1. Vérification si le relevé a déjà été généré dans cette session
    if (window.releveDejaTelecharge) {
        Swal.fire({
            icon: 'info',
            title: 'Information',
            text: 'Vous avez déjà téléchargé le relevé de service, veuillez actualiser la page pour télécharger une deuxième fois.',
            confirmButtonText: 'D\'accord'
        });
        return;
    }

    // 2. Vérification de l'existence des éléments DOM
    const inputIm = document.getElementById('releve_im');
    if (!inputIm) {
        console.error("L'élément #releve_im est introuvable dans le DOM.");
        Swal.fire('Erreur', 'Formulaire introuvable dans la page. Veuillez rafraîchir la page.', 'error');
        return;
    }
    
    inputIm.value = im;
    
    try {
        const response = await fetch(`actions/dossiers/get_releve_info.php?im=${encodeURIComponent(im)}`);
        
        if (!response.ok) {
            throw new Error(`Erreur serveur (${response.status})`);
        }

        const data = await response.json();

        if (data.success) {
            const numero = data.prochain_numero || '';
            const sigle = data.sigle || '';

            const inputNumero = document.getElementById('releve_numero');
            const inputSigle = document.getElementById('releve_sigle');

            if (inputNumero) inputNumero.value = numero;
            if (inputSigle) inputSigle.value = sigle;

            updateApercuReleve(numero, sigle);

            const modal = document.getElementById('modalReleveService');
            if (modal) modal.classList.remove('hidden');
        } else {
            Swal.fire('Erreur', data.error || 'Impossible de charger les données du relevé.', 'error');
        }
    } catch (err) {
        console.error('Erreur d\'initialisation du relevé:', err);
        Swal.fire('Erreur', 'Impossible de joindre le serveur.', 'error');
    }
};

window.fermerModalReleveService = function() {
    const modalReleve = document.getElementById('modalReleveService');
    if (modalReleve) {
        modalReleve.classList.add('hidden');
    }
};

// Fonction de mise à jour de l'aperçu
function updateApercuReleve(numero, sigle) {
    const spanApercu = document.getElementById('apercu_numero_releve') || document.getElementById('aperu_numero_releve');
    if (spanApercu) {
        const annee = new Date().getFullYear();
        const numText = numero ? numero : '_____';
        const sigleText = sigle ? sigle : '...';
        spanApercu.textContent = `N°${annee}/${numText} - ${sigleText}`;
    }
}

window.genererReleveService = async function(event) {
    event.preventDefault();

    const im = document.getElementById('releve_im')?.value;
    const numero = document.getElementById('releve_numero')?.value;
    const sigle = document.getElementById('releve_sigle')?.value;
    const form = document.getElementById('formReleveService');
    const rawType = (form?.getAttribute('data-type-demande') || '').trim().toUpperCase();

    let typeDemande;

    if (rawType === 'INSTALLATION') {
        typeDemande = 'INSTALLATION';
    } else if (rawType === 'ADMISSION_RETRAITE') {
        typeDemande = 'ADMISSION_RETRAITE';
    }

    if (!im || !numero || !sigle) {
        Swal.fire('Attention', 'Veuillez remplir tous les champs requis.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Traitement en cours...',
        text: 'Enregistrement et génération du document',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const saveResponse = await fetch('actions/dossiers/save_releve_service.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ im, numero, sigle, type_demande: typeDemande })
        });

        const saveData = await saveResponse.json();

        if (!saveResponse.ok || !saveData.success) {
            const errorMsg = saveData.error || `Erreur serveur (${saveResponse.status})`;
            Swal.fire('Erreur SQL / PHP', errorMsg, 'error');
            return;
        }
        Swal.close();

        const url = `documents/carriere/generate_releve_service.php?im=${encodeURIComponent(im)}&numero=${encodeURIComponent(numero)}&sigle=${encodeURIComponent(sigle)}&type_demande=${encodeURIComponent(typeDemande)}`;
        window.open(url, '_blank');

        window.releveDejaTelecharge = true;

        if (typeof fermerModalReleveService === 'function') {
            fermerModalReleveService();
        } else {
            const modal = document.getElementById('modalReleveService');
            if (modal) modal.classList.add('hidden');
        }

    } catch (err) {
        console.error('Erreur lors de la génération:', err);
        Swal.fire('Erreur technique', 'Impossible de lire la réponse du serveur.', 'error');
    }
};

window.ouvrirModaleSaisieConges = async function (im, alerteId, corps, annee1, annee2, annee3) {
    // 1. Récupération des données existantes
    let congesExistants = {};
    try {
        const response = await fetch(
            `actions/conges/get_conges_compensatrice.php?im=${encodeURIComponent(im)}&annees=${annee1},${annee2},${annee3}`
        );
        const result = await response.json();
        if (result.success) {
            congesExistants = result.data || {};
        }
    } catch (err) {
        console.warn("Impossible de charger les congés existants :", err);
    }

    // Helpers pour pré-remplir
    const getVal = (annee, champ, defaut = '') => {
        return congesExistants[annee] ? (congesExistants[annee][champ] ?? defaut) : defaut;
    };

    Swal.fire({
        title: '<div class="text-2xl font-black text-slate-800 uppercase tracking-tight italic py-2"><i class="fas fa-file-signature text-indigo-600 mr-2"></i>Saisie des Congés (3 Années)</div>',
        html: `
            <p class="text-sm font-medium text-slate-500 mb-5 text-left border-b border-slate-100 pb-3">
                Veuillez renseigner les informations relatives aux congés pour les trois années consécutives ci-dessous.
            </p>
            <form id="formCongesCompensatrice" class="space-y-3.5 text-left">
                
                <!-- Année 1 -->
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80 shadow-sm space-y-2">
                    <span class="text-xs font-black text-indigo-600 uppercase tracking-wider block">
                        <i class="fas fa-calendar-alt mr-1.5"></i> Année : ${annee1}
                    </span>
                    <input type="hidden" name="annee_1" value="${annee1}">
                    <div class="grid grid-cols-12 gap-2.5 items-center">
                        <div class="col-span-6">
                            <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">N° Décision</label>
                            <input type="text" name="numero_decision_1" required 
                                   value="${getVal(annee1, 'num_decision')}"
                                   placeholder="Ex: 102/PREF/.." 
                                   class="w-full border border-slate-300 rounded-xl px-3 py-1.5 text-sm font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition">
                        </div>
                        <div class="col-span-3">
                            <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">Date Décision</label>
                            <input type="date" name="date_decision_1" required 
                                   value="${getVal(annee1, 'date_decision')}"
                                   class="w-full border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition">
                        </div>
                        <div class="col-span-3">
                            <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">Jours obtenus</label>
                            <input type="number" step="0.5" name="jours_obtenus_1" min="0.5" max="365" required 
                                   value="${getVal(annee1, 'jours_total')}"
                                   placeholder="Ex: 10,5" 
                                   class="w-full border border-slate-300 rounded-xl px-2 py-1.5 text-sm font-bold text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition text-center">
                        </div>
                    </div>
                </div>

                <!-- Année 2 -->
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80 shadow-sm space-y-2">
                    <span class="text-xs font-black text-indigo-600 uppercase tracking-wider block">
                        <i class="fas fa-calendar-alt mr-1.5"></i> Année : ${annee2}
                    </span>
                    <input type="hidden" name="annee_2" value="${annee2}">
                    <div class="grid grid-cols-12 gap-2.5 items-center">
                        <div class="col-span-6">
                            <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">N° Décision</label>
                            <input type="text" name="numero_decision_2" required 
                                   value="${getVal(annee2, 'num_decision')}"
                                   placeholder="Ex: 105/PREF/.." 
                                   class="w-full border border-slate-300 rounded-xl px-3 py-1.5 text-sm font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition">
                        </div>
                        <div class="col-span-3">
                            <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">Date Décision</label>
                            <input type="date" name="date_decision_2" required 
                                   value="${getVal(annee2, 'date_decision')}"
                                   class="w-full border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition">
                        </div>
                        <div class="col-span-3">
                            <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">Jours obtenus</label>
                            <input type="number" step="0.5" name="jours_obtenus_2" min="0.5" max="365" required 
                                   value="${getVal(annee2, 'jours_total')}"
                                   placeholder="Ex: 30" 
                                   class="w-full border border-slate-300 rounded-xl px-2 py-1.5 text-sm font-bold text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition text-center">
                        </div>
                    </div>
                </div>

                <!-- Année 3 -->
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80 shadow-sm space-y-2">
                    <span class="text-xs font-black text-indigo-600 uppercase tracking-wider block">
                        <i class="fas fa-calendar-alt mr-1.5"></i> Année : ${annee3}
                    </span>
                    <input type="hidden" name="annee_3" value="${annee3}">
                    <div class="grid grid-cols-12 gap-2.5 items-center">
                        <div class="col-span-6">
                            <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">N° Décision</label>
                            <input type="text" name="numero_decision_3" required 
                                   value="${getVal(annee3, 'num_decision')}"
                                   placeholder="Ex: 110/PREF/.." 
                                   class="w-full border border-slate-300 rounded-xl px-3 py-1.5 text-sm font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition">
                        </div>
                        <div class="col-span-3">
                            <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">Date Décision</label>
                            <input type="date" name="date_decision_3" required 
                                   value="${getVal(annee3, 'date_decision')}"
                                   class="w-full border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition">
                        </div>
                        <div class="col-span-3">
                            <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">Jours obtenus</label>
                            <input type="number" step="0.5" name="jours_obtenus_3" min="0.5" max="365" required 
                                   value="${getVal(annee3, 'jours_total')}"
                                   placeholder="Ex: 30" 
                                   class="w-full border border-slate-300 rounded-xl px-2 py-1.5 text-sm font-bold text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition text-center">
                        </div>
                    </div>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Suivant <i class="fas fa-arrow-right text-base ml-2"></i>',
        cancelButtonText: '<i class="fas fa-times text-base mr-2"></i> Annuler',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748b',
        customClass: {
            popup: 'rounded-[2.5rem] p-6 max-w-4xl w-full shadow-2xl',
            confirmButton: 'rounded-xl font-black uppercase text-xs px-6 py-3.5 shadow-lg shadow-emerald-600/20',
            cancelButton: 'rounded-xl font-bold text-xs px-6 py-3.5'
        },
        preConfirm: () => {
            const form = document.getElementById('formCongesCompensatrice');
            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }

            const formData = new FormData(form);
            const parseDecimal = (val) => val ? parseFloat(val.toString().replace(',', '.')) : 0;

            const congesData = {
                im: im,
                records: [
                    {
                        annee: formData.get('annee_1'),
                        numero_decision: formData.get('numero_decision_1'),
                        date_decision: formData.get('date_decision_1'),
                        jours_obtenus: parseDecimal(formData.get('jours_obtenus_1'))
                    },
                    {
                        annee: formData.get('annee_2'),
                        numero_decision: formData.get('numero_decision_2'),
                        date_decision: formData.get('date_decision_2'),
                        jours_obtenus: parseDecimal(formData.get('jours_obtenus_2'))
                    },
                    {
                        annee: formData.get('annee_3'),
                        numero_decision: formData.get('numero_decision_3'),
                        date_decision: formData.get('date_decision_3'),
                        jours_obtenus: parseDecimal(formData.get('jours_obtenus_3'))
                    }
                ]
            };

            return fetch('actions/conges/save_conges_compensatrice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(congesData)
            })
            .then(response => {
                if (!response.ok) throw new Error("Erreur réseau lors de la sauvegarde.");
                return response.json();
            })
            .then(data => {
                if (!data.success) throw new Error(data.message || "Erreur d'enregistrement.");
                return true;
            })
            .catch(error => {
                Swal.showValidationMessage(`Erreur: ${error.message}`);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`actions/dossiers/get_arrete_retraite.php?im=${encodeURIComponent(im)}&alerte_id=${encodeURIComponent(alerteId)}`)
                .then(res => res.json())
                .then(data => {
                    const numExistant = data.success && data.arrete ? data.arrete.num_arrete_retraite : '';
                    const dateExistante = data.success && data.arrete ? data.arrete.date_arrete_retraite : '';

                    // 2. Affichage de la modale pré-remplie
                    Swal.fire({
                        title: '<div class="text-xl font-black text-slate-800 uppercase tracking-tight italic py-2"><i class="fas fa-id-card text-indigo-600 mr-2"></i>Arrêté d\'admission à la retraite</div>',
                        html: `
                            <p class="text-xs font-medium text-slate-500 mb-4 text-left border-b border-slate-100 pb-2">
                                Renseignez le numéro et la date de l'arrêté d'admission à la retraite.
                            </p>
                            <form id="formArreteRetraite" class="space-y-4 text-left">
                                <div>
                                    <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">N° de l'Arrêté</label>
                                    <input type="text" id="num_arrete_retraite" name="num_arrete_retraite" value="${numExistant}" required placeholder="Ex: 1234/2026/MEN" 
                                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-black text-slate-600 uppercase mb-1">Date de l'Arrêté</label>
                                    <input type="date" id="date_arrete_retraite" name="date_arrete_retraite" value="${dateExistante}" required 
                                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm font-medium focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition">
                                </div>
                            </form>
                        `,
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-file-word text-base mr-2"></i> Générer le document',
                        cancelButtonText: 'Annuler',
                        confirmButtonColor: '#059669',
                        cancelButtonColor: '#64748b',
                        customClass: {
                            popup: 'rounded-[2rem] p-6 max-w-md w-full shadow-2xl',
                            confirmButton: 'rounded-xl font-black uppercase text-xs px-5 py-3 shadow-lg shadow-emerald-600/20',
                            cancelButton: 'rounded-xl font-bold text-xs px-5 py-3'
                        },
                        preConfirm: async () => {
                            const form = document.getElementById('formArreteRetraite');
                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return false;
                            }

                            const numArrete = document.getElementById('num_arrete_retraite').value;
                            const dateArrete = document.getElementById('date_arrete_retraite').value;

                            Swal.showLoading();

                            try {
                                // Enregistrement / Mise à jour dans la base de données
                                const response = await fetch('actions/dossiers/save_arrete_retraite.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        im: im,
                                        alerte_id: alerteId,
                                        num_arrete_retraite: numArrete,
                                        date_arrete_retraite: dateArrete
                                    })
                                });

                                const resData = await response.json();

                                if (!resData.success) {
                                    Swal.showValidationMessage(resData.message || 'Erreur lors de l\'enregistrement');
                                    return false;
                                }

                                return { numArrete, dateArrete };
                            } catch (error) {
                                Swal.showValidationMessage('Erreur de connexion au serveur');
                                return false;
                            }
                        }
                    }).then((retraiteResult) => {
                        if (retraiteResult.isConfirmed) {
                            const numArrete = encodeURIComponent(retraiteResult.value.numArrete);
                            const dateArrete = encodeURIComponent(retraiteResult.value.dateArrete);

                            window.location.href = `documents/carriere/generate_decision_compensatrice.php?im=${encodeURIComponent(im)}&alerte_id=${encodeURIComponent(alerteId)}&corps=${encodeURIComponent(corps)}&mode=pdecision_compensatrice&num_arrete_retraite=${numArrete}&date_arrete_retraite=${dateArrete}`;
                        }
                    });
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Erreur', 'Impossible de récupérer les informations de l\'arrêté.', 'error');
                });
        }
    });
};

window.redirigerVersDemandeConge = function () {
    if (typeof window.loadPage === 'function') {
        window.loadPage('pages/conges/demande_conge.php', 'Demande de congé');
    } else {
        window.location.href = 'index.php?page=pages/conges/demande_conge.php';
    }
};
</script>
</body>
</html>
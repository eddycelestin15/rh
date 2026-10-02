<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/alertes_notifications.php';
ob_start();
header('Content-Type: application/json');

$im = $_SESSION['user_im'] ?? null;
if (!$im) {
    echo json_encode(['success' => false, 'message' => 'Session expirée ou utilisateur non connecté.']);
    exit;
}

$step = $_POST['step_index'] ?? '';
try {
    $pdo->beginTransaction();
    // Coordonnees facultatives de la fiche agent.
    $tel   = trim($_POST['num_tel'] ?? '');
    $email = trim($_POST['adress_mail'] ?? '');
    
    if (isset($_FILES['photo_personnel']) && $_FILES['photo_personnel']['error'] === 0) {
        $uploadDir = APP_ROOT . '/images/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($_FILES['photo_personnel']['name'], PATHINFO_EXTENSION);
        $fileName = $im . "." . $extension; 
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['photo_personnel']['tmp_name'], $targetPath)) {
            $stmt = $pdo->prepare("UPDATE personnel_etat_civil SET photo_path = ? WHERE im = ?");
            $stmt->execute([$targetPath, $im]);
        }
    }

    // ==========================================
    // STEP 0 : ÉTAT CIVIL
    // ==========================================
    if ($step === "0") {
        if (empty($_POST['date_naiss']) || empty($_POST['cin'])) {
            echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants (Date de naissance, CIN)']);
            exit;
        }
        $tel_saisi = trim($_POST['num_tel'] ?? '');
        $whatsapp_saisi = trim($_POST['num_whatsapp'] ?? '');
        $urgence_tel_saisi = trim($_POST['urgence_tel'] ?? '');
        
        $tel = !empty($tel_saisi) ? "+261" . $tel_saisi : '';
        $whatsapp = !empty($whatsapp_saisi) ? "+261" . $whatsapp_saisi : '';
        $urgence_tel = !empty($urgence_tel_saisi) ? "+261" . $urgence_tel_saisi : ''; 
        $email = $_POST['adress_mail'] ?? ''; 
        $nbr_enfant = $_POST['nbr_enfant_bc'] ?? ''; 
        
        $sql = "INSERT INTO personnel_etat_civil (
                    im, nom, prenoms, date_naiss, lieu_naiss, sexe, cin, date_cin, lieu_cin, 
                    adresse, num_tel, num_whatsapp, adress_mail, situation_familiale, nbr_enfant, nbr_enfant_bc,
                    nom_secours, prenoms_secours, adresse_secours, tel_secours
                ) VALUES (
                    :im, :nom, :prenoms, :dn, :ln, :se, :ci, :dc, :lc, 
                    :ad, :te, :wh, :ma, :si, :nb, :nbc, 
                    :un, :up, :ua, :ut
                ) 
                ON DUPLICATE KEY UPDATE 
                    date_naiss = VALUES(date_naiss), 
                    lieu_naiss = VALUES(lieu_naiss), 
                    sexe = VALUES(sexe), 
                    cin = VALUES(cin), 
                    date_cin = VALUES(date_cin), 
                    lieu_cin = VALUES(lieu_cin), 
                    adresse = VALUES(adresse), 
                    num_tel = VALUES(num_tel), 
                    num_whatsapp = VALUES(num_whatsapp), 
                    adress_mail = VALUES(adress_mail), 
                    situation_familiale = VALUES(situation_familiale), 
                    nbr_enfant = VALUES(nbr_enfant),
                    nbr_enfant_bc = VALUES(nbr_enfant_bc), 
                    nom_secours = VALUES(nom_secours), 
                    prenoms_secours = VALUES(prenoms_secours),
                    adresse_secours = VALUES(adresse_secours), 
                    tel_secours = VALUES(tel_secours)";
        $stmt = $pdo->prepare($sql);        
        $stmt->execute([
            ':im'      => $im,
            ':nom'     => $_SESSION['user_nom'], 
            ':prenoms' => $_SESSION['user_prenoms'], 
            ':dn'      => $_POST['date_naiss'] ?: null,
            ':ln'      => $_POST['lieu_naiss'] ?: null,
            ':se'      => $_POST['sexe'] ?: null,
            ':ci'      => $_POST['cin'] ?: null,
            ':dc'      => $_POST['date_cin'] ?: null,
            ':lc'      => $_POST['lieu_cin'] ?: null,
            ':ad'      => $_POST['adresse'] ?: null,
            ':te'      => $tel,
            ':wh'      => $whatsapp,
            ':ma'      => $email,
            ':si'      => $_POST['situation_familiale'] ?: null,
            ':nb'      => $_POST['nbr_enfant'] ?: null,
            ':nbc'      => $nbr_enfant, 
            ':un'      => $_POST['urgence_nom'] ?? null,
            ':up'      => $_POST['urgence_prenoms'] ?? null,
            ':ua'      => $_POST['urgence_adresse'] ?? null,
            ':ut'      => $urgence_tel
        ]);
        $upd = $pdo->prepare("UPDATE utilisateurs SET telephone = ?, whatsapp = ?, email = ? WHERE im = ?");
        $upd->execute([$tel, $whatsapp, $email, $im]);

        $sql_acte_formate = "INSERT INTO acte_formate (cin, im, nom, prenoms, date_naiss, lieu_naiss, date_cin, lieu_cin, situation_matrimoniale, sexe, nombre_enfant) 
                            VALUES (:cin, :im, :nom, :prenom, :dt_n, :lieu_naiss, :date_cin, :lieu_cin, :sit_fam, :sx, :nb_enfant)
                            ON DUPLICATE KEY UPDATE 
                                cin = VALUES(cin),
                                nom = VALUES(nom),
                                prenoms = VALUES(prenoms),
                                date_naiss = VALUES(date_naiss),
                                lieu_naiss = VALUES(lieu_naiss),
                                date_cin = VALUES(date_cin),
                                lieu_cin = VALUES(lieu_cin),
                                situation_matrimoniale = VALUES(situation_matrimoniale),
                                sexe = VALUES(sexe),
                                nombre_enfant = VALUES(nombre_enfant)";

        $stmt_acte_formate = $pdo->prepare($sql_acte_formate);        
        $stmt_acte_formate->execute([
            ':cin'       => $_POST['cin'] ?: null,
            ':im'        => $im,
            ':nom'       => $_SESSION['user_nom'], 
            ':prenom'    => $_SESSION['user_prenoms'], 
            ':dt_n'      => $_POST['date_naiss'] ?: null,
            ':lieu_naiss'   => $_POST['lieu_naiss'] ?: null,
            ':date_cin'      => $_POST['date_cin'] ?: null,
            ':lieu_cin'   => $_POST['lieu_cin'] ?: null,
            ':sit_fam'   => $_POST['situation_familiale'] ?: null,
            ':sx'        => $_POST['sexe'] ?: null,
            ':nb_enfant' => $nbr_enfant 
        ]);

        $pdo->commit();
        ob_clean();
        echo json_encode(['success' => true]);
        exit;
    } 
    // ==========================================
    // STEP 1 : DIPLÔMES ET COMPÉTENCES
    // ==========================================
    else if ($step === "1") {
        $pdo->prepare("DELETE FROM personnel_diplomes WHERE im = ?")->execute([$im]);
        $sqlDiplome = "INSERT INTO personnel_diplomes (im, type_diplome, libelle, specialite, annee) VALUES (?, ?, ?, ?, ?)";
        $stmtDiplome = $pdo->prepare($sqlDiplome);
        if (!empty($_POST['acad_diplome'])) {
            foreach ($_POST['acad_diplome'] as $key => $val) {
                if (!empty($val)) {
                    $stmtDiplome->execute([
                        $im, 
                        'acad', 
                        $val, 
                        $_POST['acad_specialite'][$key] ?? null, 
                        $_POST['acad_annee'][$key] ?? null
                    ]);
                }
            }
        }

        if (!empty($_POST['pedag_diplome'])) {
            foreach ($_POST['pedag_diplome'] as $key => $val) {
                if (!empty($val)) {
                    $stmtDiplome->execute([
                        $im, 
                        'pedag', 
                        $val, 
                        $_POST['pedag_specialite'][$key] ?? null, 
                        $_POST['pedag_annee'][$key] ?? null
                    ]);
                }
            }
        }

        $info_autres = isset($_POST['info_autres_check']) ? 1 : 0;
        $sql = "UPDATE personnel_competences SET info_autres = :active WHERE im = :im";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':active' => $info_autres,
            ':im' => $im
        ]);

        $sqlComp = "INSERT INTO personnel_competences (
                        im, info_bureautique, info_programmation, info_reseau, info_autres, aptitudes_speciales, 
                        langue_fr, langue_en, langue_autres
                    ) VALUES (
                        :im, :bur, :prog, :res, :aut, :apt, :fr, :en, :l_aut
                    ) ON DUPLICATE KEY UPDATE 
                        info_bureautique=VALUES(info_bureautique), info_programmation=VALUES(info_programmation), 
                        info_reseau=VALUES(info_reseau), info_autres=VALUES(info_autres), aptitudes_speciales=VALUES(aptitudes_speciales), 
                        langue_fr=VALUES(langue_fr), langue_en=VALUES(langue_en), langue_autres=VALUES(langue_autres)";
        
        $pdo->prepare($sqlComp)->execute([
            ':im'    => $im,
            ':bur'   => isset($_POST['info_bureautique']) ? 1 : 0,
            ':prog'  => isset($_POST['info_programmation']) ? 1 : 0,
            ':res'   => isset($_POST['info_reseau']) ? 1 : 0,
            ':aut'   => isset($_POST['info_autres_check']) ? $_POST['info_autres_precision'] : null,
            ':apt'    => $_POST['aptitudes_speciales'] ?? null,
            ':fr'    => $_POST['langue_fr'] ?? null,
            ':en'    => $_POST['langue_en'] ?? null,
            ':l_aut' => $_POST['langue_autres'] ?? null
        ]);

        $pdo->commit();
        ob_clean();
        echo json_encode(['success' => true]);
        exit;
    }
    // ==========================================
    // STEP 2 : SITUATION ADMINISTRATIVE + HISTORIQUE
    // ==========================================
    else if ($step === "2") {
        $im = $_POST['im'] ?? null;
        if (empty($im)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => "Erreur : Le matricule (IM) est manquant."]);
            exit;
        }

        try {
            $corps_id = $_POST['corps_actuel'] ?? null;
            $grade_id = $_POST['grade_actuel'] ?? null;
            $nom_corps = ""; 
            $nom_grade = "";
            $code_grade_pour_acte = ""; 
            
            if ($corps_id) {
                $stmtC = $pdo->prepare("SELECT libelle_corps FROM ref_corps WHERE id = ?");
                $stmtC->execute([$corps_id]);
                $nom_corps = $stmtC->fetchColumn() ?: "";
            }
            if ($grade_id) {
                $stmtG = $pdo->prepare("SELECT libelle_grade, code_grade FROM ref_grades_types WHERE id = ?");
                $stmtG->execute([$grade_id]);
                $grade_data = $stmtG->fetch(PDO::FETCH_ASSOC);        
                if ($grade_data) {
                    $nom_grade = $grade_data['libelle_grade'];            
                    $code_grade_pour_acte = $grade_data['code_grade'];    
                }
            }

            $type_av_choisi = $_POST['type_avancement_actuel'] ?? null;

            $sqlSit = "INSERT INTO personnel_situation_actuelle (
                        im, budget, date_entree_admin, statut_actuel, type_avancement_actuel, 
                        type_acte_actuel, num_acte_actuel, date_acte_actuel, date_d_effet_actuel, 
                        code_corps_actuel, corps_actuel, grade_actuel, categorie_actuel, 
                        indice_actuel, mode_paiement, chap_budg, imput_budg 
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        budget = VALUES(budget), date_entree_admin = VALUES(date_entree_admin), 
                        statut_actuel = VALUES(statut_actuel), type_avancement_actuel = VALUES(type_avancement_actuel),
                        type_acte_actuel = VALUES(type_acte_actuel), num_acte_actuel = VALUES(num_acte_actuel),
                        date_acte_actuel = VALUES(date_acte_actuel), date_d_effet_actuel = VALUES(date_d_effet_actuel),
                        code_corps_actuel = VALUES(code_corps_actuel), corps_actuel = VALUES(corps_actuel), 
                        grade_actuel = VALUES(grade_actuel), categorie_actuel = VALUES(categorie_actuel), 
                        indice_actuel = VALUES(indice_actuel), mode_paiement = VALUES(mode_paiement), 
                        chap_budg = VALUES(chap_budg), imput_budg = VALUES(imput_budg)";

            $pdo->prepare($sqlSit)->execute([
                $im, $_POST['budget'] ?? 'GENERAL', $_POST['date_entree_admin'] ?: null, $_POST['statut_actuel'], 
                $type_av_choisi, $_POST['type_acte_actuel'], $_POST['num_acte_actuel'],
                $_POST['date_acte_actuel'] ?: null, $_POST['date_d_effet_actuel'] ?: null, 
                $_POST['code_corps_actuel'], $nom_corps, $nom_grade, $_POST['categorie_actuel'], 
                $_POST['indice_actuel'], $_POST['mode_paiement'], $_POST['chap_budg'], $_POST['imput_budg']                
            ]);

            $sql_acte = "INSERT INTO acte_formate (
                    im, code_logement, code_ameublement, code_budget, imput_budg, 
                    mode_paiement, statut_agent, date_entree_admin, corps_actuel, grade_actuel, code_corps_actuel, code_grade_actuel, date_d_effet_actuel, indice_actuel 
                ) VALUES (:im, '0', 'I', '00', :imput, :paye, :stat_agent, :dt_entree_admin, 
                        :corps_actuel, :grade_actuel, :code_corps_actuel, :code_grade_actuel, :dt_effet_actuel, :ind_actuel)
                ON DUPLICATE KEY UPDATE 
                    code_logement = '0',
                    code_ameublement = 'I',
                    code_budget = '00',
                    imput_budg = VALUES(imput_budg),
                    mode_paiement = VALUES(mode_paiement),
                    statut_agent = VALUES (statut_agent),
                    date_entree_admin = VALUES(date_entree_admin),
                    corps_actuel = VALUES(corps_actuel),
                    grade_actuel = VALUES(grade_actuel),
                    code_corps_actuel = VALUES(code_corps_actuel),
                    code_grade_actuel = VALUES(code_grade_actuel),
                    date_d_effet_actuel = VALUES(date_d_effet_actuel),
                    indice_actuel = VALUES(indice_actuel)";

            $stmt_acte = $pdo->prepare($sql_acte);
            $stmt_acte->execute([
                ':im'     => $im,
                ':imput'  => $_POST['imput_budg'] ?? null,
                ':paye'   => $_POST['mode_paiement'] ?? null,
                ':stat_agent'   => $_POST['statut_actuel'] ?? null,
                ':dt_entree_admin'  => $_POST['date_entree_admin'] ?: null,
                ':corps_actuel'  => $nom_corps,
                ':grade_actuel'  => $nom_grade,
                ':code_corps_actuel'  =>$_POST['code_corps_actuel'] ?? null,
                ':code_grade_actuel'  => $code_grade_pour_acte,
                ':dt_effet_actuel'  => $_POST['date_d_effet_actuel'] ?: null,  
                ':ind_actuel'   => $_POST['indice_actuel'] ?? null,              
            ]);

            // 4. FONCTION UPSERT HISTORIQUE (avec durée)
            function upsertHistorique($pdo, $im, $type_acte, $type_av, $corps, $grade, $effet, $indice, $num, $date_a, $duree = null) {
                if (empty($grade)) return; 

                // On vérifie si cet acte spécifique pour ce grade existe déjà
                $stmt = $pdo->prepare("SELECT id FROM personnel_avancements WHERE im = ? AND av_grade = ? AND av_type_acte = ?");
                $stmt->execute([$im, $grade, $type_acte]);
                $id = $stmt->fetchColumn();

                if ($id) {
                    $sql = "UPDATE personnel_avancements SET 
                            duree=?, av_type_avancement=?, av_corps=?, av_date_effet=?, av_indice=?, 
                            av_acte_no=?, av_acte_date=? 
                            WHERE id = ?";
                    $pdo->prepare($sql)->execute([$duree, $type_av, $corps, $effet, $indice, $num, $date_a, $id]);
                } else {
                    $sql = "INSERT INTO personnel_avancements (im, duree, av_type_acte, av_type_avancement, av_corps, av_grade, av_date_effet, av_indice, av_acte_no, av_acte_date) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $pdo->prepare($sql)->execute([$im, $duree, $type_acte, $type_av, $corps, $grade, $effet, $indice, $num, $date_a]);
                }
            }

            // Calcul de la durée pour la Situation actuelle
            $statut_actuel    = $_POST['statut_actuel'] ?? '';
            $categorie_actuel = $_POST['categorie_actuel'] ?? '';
            $grade_actuel_txt = strtoupper($nom_grade ?? '');

            $duree_actuel = null;
            if (($categorie_actuel === 'II' || $categorie_actuel === 'III') && $statut_actuel === 'Contractuel EFA') {
                $duree_actuel = '2 ans';
            } else {
                if (strpos($grade_actuel_txt, 'STAGIAIRE') !== false) {
                    $duree_actuel = '1 an';
                } elseif (strpos($grade_actuel_txt, '1°ECHELON') !== false || strpos($grade_actuel_txt, '2°ECHELON') !== false) {
                    $duree_actuel = '2 ans';
                } elseif (strpos($grade_actuel_txt, '3°ECHELON') !== false) {
                    $duree_actuel = '3 ans';
                }
            }

            upsertHistorique(
                $pdo, 
                $im, 
                $_POST['type_acte_actuel'] ?? null, 
                $type_av_choisi, 
                $nom_corps, 
                $nom_grade, 
                $_POST['date_d_effet_actuel'] ?: null, 
                $_POST['indice_actuel'] ?? null, 
                $_POST['num_acte_actuel'] ?? null, 
                $_POST['date_acte_actuel'] ?: null,
                $duree_actuel
            );

            function upsertBinAgent($pdo, $im, $corps, $grade, $type_acte, $num_acte, $date_acte, $statut) {
                if (empty($im) || empty($num_acte)) return;

                $sqlBin = "INSERT INTO bin_agent (im_bin, corps_bin, grade_bin, type_acte_bin, numero_acte_bin, date_acte_bin, statut) 
                        VALUES (:im, :corps, :grade, :type_acte, :num_acte, :date_acte, :statut)
                        ON DUPLICATE KEY UPDATE 
                            corps_bin = VALUES(corps_bin),
                            grade_bin = VALUES(grade_bin),
                            type_acte_bin = VALUES(type_acte_bin),
                            date_acte_bin = VALUES(date_acte_bin),
                            statut = VALUES(statut)";
                
                $stmtBin = $pdo->prepare($sqlBin);
                $stmtBin->execute([
                    ':im'        => $im,
                    ':corps'     => $corps,
                    ':grade'     => $grade,
                    ':type_acte' => $type_acte,
                    ':num_acte'  => $num_acte,
                    ':date_acte' => $date_acte ?: null,
                    ':statut'  => $statut
                ]);
            }
            upsertBinAgent(
                $pdo,
                $im,
                $nom_corps,
                $nom_grade,
                $_POST['type_acte_actuel'] ?? null,
                $_POST['num_acte_actuel'] ?? null,
                $_POST['date_acte_actuel'] ?: null,
                $_POST['statut_actuel'] ?: null
            );

            // Contrat Indéterminé
            $num_acte_ind   = trim($_POST['num_acte_ind'] ?? '');
            $date_acte_ind  = $_POST['date_acte_ind'] ?? null;
            $date_effet_ind = $_POST['date_d_effet_ind'] ?? null;
            $corps_id_ind   = $_POST['corps_ind'] ?? null;
            $grade_id_ind   = $_POST['grade_ind'] ?? null;
            $indice_ind     = $_POST['indice_ind'] ?? null;

            if (!empty($num_acte_ind) && !empty($corps_id_ind) && !empty($grade_id_ind)) {

                // Récupération des libellés
                $nom_corps_ind = "";
                $nom_grade_ind = "";

                $stmtCInd = $pdo->prepare("SELECT libelle_corps FROM ref_corps WHERE id = ?");
                $stmtCInd->execute([$corps_id_ind]);
                $nom_corps_ind = $stmtCInd->fetchColumn() ?: "";

                $stmtGInd = $pdo->prepare("SELECT libelle_grade FROM ref_grades_types WHERE id = ?");
                $stmtGInd->execute([$grade_id_ind]);
                $nom_grade_ind = $stmtGInd->fetchColumn() ?: "";

                // personnel_avancements → durée = "indeterminee"
                upsertHistorique(
                    $pdo,
                    $im,
                    'Contrat',                          // av_type_acte
                    'Echelon',                          // av_type_avancement
                    $nom_corps_ind,                     // av_corps
                    $nom_grade_ind,                     // av_grade
                    $date_effet_ind ?: null,            // av_date_effet
                    $indice_ind,                        // av_indice
                    $num_acte_ind,                      // av_acte_no
                    $date_acte_ind ?: null,             // av_acte_date
                    'indeterminee'                      // duree
                );

                // bin_agent
                upsertBinAgent(
                    $pdo,
                    $im,
                    $nom_corps_ind,                     // corps_bin
                    $nom_grade_ind,                     // grade_bin
                    'Contrat',                          // type_acte_bin
                    $num_acte_ind,                      // numero_acte_bin
                    $date_acte_ind ?: null,             // date_acte_bin
                    'Contractuel EFA'                   // statut
                );
            }

            // Situation avant intégration
            $num_acte_av_int   = trim($_POST['num_acte_av_int'] ?? '');
            $date_acte_av_int  = $_POST['date_acte_av_int'] ?? null;
            $date_effet_av_int = $_POST['date_d_effet_av_int'] ?? null;
            $corps_id_av_int   = $_POST['corps_av_int'] ?? null;
            $grade_id_av_int   = $_POST['grade_av_int'] ?? null;
            $indice_av_int     = $_POST['indice_av_int'] ?? null;

            if (!empty($num_acte_av_int) && !empty($corps_id_av_int) && !empty($grade_id_av_int)) {

                $nom_corps_av_int = "";
                $nom_grade_av_int = "";

                $stmtCAvInt = $pdo->prepare("SELECT libelle_corps FROM ref_corps WHERE id = ?");
                $stmtCAvInt->execute([$corps_id_av_int]);
                $nom_corps_av_int = $stmtCAvInt->fetchColumn() ?: "";

                $stmtGAvInt = $pdo->prepare("SELECT libelle_grade FROM ref_grades_types WHERE id = ?");
                $stmtGAvInt->execute([$grade_id_av_int]);
                $nom_grade_av_int = $stmtGAvInt->fetchColumn() ?: "";

                $grade_txt_av_int = strtoupper($nom_grade_av_int);

                // Condition duree
                $duree_av_int = (strpos($grade_txt_av_int, '2°CLASSE/3°ECHELON') !== false) ? '3 ans' : '2 ans';

                // Condition av_type_acte
                if (
                    strpos($grade_txt_av_int, 'ECHELLE III/3°ECHELON') !== false || 
                    strpos($grade_txt_av_int, 'ECHELLE IV/3°ECHELON') !== false || 
                    strpos($grade_txt_av_int, '2°CLASSE/2°ECHELON') !== false
                ) {
                    $av_type_acte_av_int = 'Contrat';
                } else {
                    $av_type_acte_av_int = 'Avenant';
                }

                // Condition av_type_avancement
                $av_type_avancement_av_int = (strpos($grade_txt_av_int, '1°CLASSE/1°ECHELON') !== false) ? 'Classe' : 'Echelon';

                upsertHistorique(
                    $pdo,
                    $im,
                    $av_type_acte_av_int,               // av_type_acte
                    $av_type_avancement_av_int,         // av_type_avancement
                    $nom_corps_av_int,                  // av_corps
                    $nom_grade_av_int,                  // av_grade
                    $date_effet_av_int ?: null,         // av_date_effet
                    $indice_av_int,                     // av_indice
                    $num_acte_av_int,                   // av_acte_no
                    $date_acte_av_int ?: null,          // av_acte_date
                    $duree_av_int                       // duree
                );
            }

            $pdo->commit();
            declencherAlertesInstantanees($pdo);

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Situation et Historique mis à jour avec succès.']);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
            exit;
        }
    }
    // ==========================================
    // STEP 3 : POSTE ACTUEL ET LOCALISATION
    // ==========================================
    else if ($step === "3") {
        $te_input = $_POST['type_etablissement'] ?? null;
        
        $type_etablissement = $te_input;
        $type_direction     = $_POST['type_direction'] ?? null;
        $nom_direction      = null;
        $nom_service        = null;
        $nom_division       = $_POST['division'] ?? null;
        $nom_region         = $_POST['loc_region'] ?? null;
        $nom_district       = $_POST['loc_district'] ?? null;
        $nom_zap            = $_POST['loc_zap'] ?? null;
        $nom_etablissement  = $_POST['loc_final'] ?? null;
        $lieu_de_service    = null;
        $fonctionFinale     = null;

        // CORRECTION : On accepte 'MEN CENTRAL' ou 'CENTRAL'
        if ($te_input === 'MEN CENTRAL' || $te_input === 'CENTRAL') {
            $type_etablissement = 'MEN CENTRAL'; 
            $type_direction     = 'MEN';
            $nom_region   = 'ANALAMANGA';
            $nom_district = 'ANTANANARIVO RENIVOHITRA';
            
            // 1. Récupération du NOM Réel de la Direction via son ID
            $idDirMen = $_POST['direction_men'] ?? null;
            if (!empty($idDirMen) && is_numeric($idDirMen)) {
                $stmtDir = $pdo->prepare("SELECT nom_direction FROM ref_directions WHERE id = ? LIMIT 1");
                $stmtDir->execute([$idDirMen]);
                $resultDir = $stmtDir->fetch(PDO::FETCH_ASSOC);
                $nom_direction = $resultDir ? $resultDir['nom_direction'] : null;
            } else {
                $nom_direction = !empty($idDirMen) ? $idDirMen : null;
            }

            // 2. Récupération du NOM Réel du Service via son ID
            $idSerMen = $_POST['service_men'] ?? null;
            if (!empty($idSerMen) && is_numeric($idSerMen)) {
                $stmtSer = $pdo->prepare("SELECT nom_service FROM ref_services_dirmen WHERE id = ? LIMIT 1");
                $stmtSer->execute([$idSerMen]);
                $resultSer = $stmtSer->fetch(PDO::FETCH_ASSOC);
                $nom_service = $resultSer ? $resultSer['nom_service'] : null;
            } else {
                $nom_service = !empty($idSerMen) ? $idSerMen : null;
            }

            // Gestion de la fonction pour MEN Central
            $choixMen = $_POST['fonction_men'] ?? '';
            if ($choixMen === 'Direction') {
                $fonctionFinale = $_POST['nom_fonction_direction_men'] ?? null;
            } else {
                $fonctionFinale = $_POST['autre_fonction_saisie'] ?? null;
            }
            
            $lieu_de_service = 'ANTANANARIVO';

        } else {
            $nom_direction = $_POST['direction'] ?? null;
            $nom_service   = $_POST['service'] ?? null;

            if ($te_input === 'DREN') {
                $type_etablissement = 'DREN';
                $type_direction     = 'DREN';
                
                $choixDren = $_POST['fonction_dren'] ?? '';
                
                if ($choixDren === 'Direction') {
                    $fonctionFinale = $_POST['nom_fonction_direction_dren'] ?? null;
                    $nom_direction = $_POST['direction_men'] ?? null;
                    $nom_service   = null;
                } else {
                    $fonctionFinale = $_POST['fonction'] ?? null;
                    $nom_direction  = null;
                    $nom_service    = $_POST['service'] ?? null;
                }
                
                $lieu_de_service = 'DREN ' . ($nom_region ?? '');

            } else if ($te_input === 'CISCO') {
                $type_etablissement = 'CISCO';
                $type_direction     = 'CISCO';
                $choixCisco = $_POST['fonction_cisco'] ?? '';
                $fonctionFinale = ($choixCisco === 'Service') ? ($_POST['nom_fonction_service_cisco'] ?? null) : ($_POST['fonction'] ?? null);
                $lieu_de_service = 'CISCO ' . ($nom_district ?? '');

            } else if ($te_input === 'CRFRP') {
                $type_etablissement = 'CRFRP';
                $type_direction     = 'INFP';
                $fonctionFinale     = $_POST['fonction'] ?? null;
                $lieu_de_service    = $_POST['loc_final'] ?? null;

            } else if (in_array($te_input, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
                $fonctionFinale  = $_POST['fonction'] ?? null;
                $lieu_de_service = 'CISCO ' . ($nom_district ?? '');
            }
        }

        if ($fonctionFinale === 'AUTRE' && !empty($_POST['autre_fonction_saisie'])) {
            $fonctionFinale = trim($_POST['autre_fonction_saisie']);
        }

        // --- REQUÊTE MIS À JOUR AVEC 'ministere' ---
        $sql = "INSERT INTO personnel_poste_actuel (
                    im, ministere, type_fonction, type_etablissement, type_direction, nom_direction,
                    nom_service, nom_division, nom_fonction, nom_matiere, nom_region, nom_district, nom_zap, nom_etablissement, lieu_de_service
                ) VALUES (
                    :im, :min, :tf, :te, :td, :ndir, :ns, :ndiv, :nf, :nm, :nr, :ndi, :nz, :netab, :ls
                ) ON DUPLICATE KEY UPDATE 
                    ministere = VALUES(ministere), type_fonction = VALUES(type_fonction), type_etablissement = VALUES(type_etablissement), 
                    type_direction = VALUES(type_direction), nom_direction = VALUES(nom_direction), nom_service = VALUES(nom_service), 
                    nom_division = VALUES(nom_division), nom_fonction = VALUES(nom_fonction), nom_matiere = VALUES(nom_matiere), 
                    nom_region = VALUES(nom_region), nom_district = VALUES(nom_district), nom_zap = VALUES(nom_zap), 
                    nom_etablissement = VALUES(nom_etablissement), lieu_de_service = VALUES(lieu_de_service)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':im'   => $im,
            ':min'  => 'MEN', 
            ':tf'   => $_POST['type_fonction'] ?? null,
            ':te'   => $type_etablissement,
            ':td'   => $type_direction,
            ':ndir' => $nom_direction, 
            ':ns'   => $nom_service,   
            ':ndiv' => $nom_division,
            ':nf'   => $fonctionFinale,
            ':nm'   => $_POST['nom_matiere'] ?? null,
            ':nr'   => $nom_region,
            ':ndi'  => $nom_district,
            ':nz'   => $nom_zap,
            ':netab'=> $nom_etablissement,
            ':ls'   => $lieu_de_service
        ]);

        $niveau = ($type_etablissement === 'MEN CENTRAL') ? 'central' : null;
        $code_lieu = null;

        if ($type_etablissement === 'MEN CENTRAL') {
            $niveau = 'central'; 
            $code_lieu = 'ANTANANARIVO';
        } else if ($type_etablissement === 'DREN') {
            $niveau = 'regional'; 
            $code_lieu = !empty($nom_region) ? trim($nom_region) : null;
        } else if ($type_etablissement === 'CISCO') {
            $niveau = 'district'; 
            $code_lieu = !empty($nom_district) ? trim($nom_district) : null;
        } else if ($type_etablissement === 'CRFRP') {
            $niveau = 'crfrp'; 
            $code_lieu = !empty($lieu_de_service) ? trim($lieu_de_service) : null;
        } else if (in_array($type_etablissement, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'], true)) {
            $niveau = 'district'; 
            $code_lieu = !empty($nom_district) ? trim($nom_district) : null;
        }

        if (!empty($niveau) && !empty($code_lieu)) {
            $pdo->prepare("UPDATE utilisateurs SET niveau = ?, code_lieu_affectation = ? WHERE im = ?")
                ->execute([$niveau, $code_lieu, $im]);
        }
        
        $nomDistrict = trim($_POST['loc_district'] ?? '');
        $nomRegionAgent = trim($_POST['loc_region'] ?? '');
        $codeLocalite = null;

        if (!empty($nomDistrict)) {
            $stmtD = $pdo->prepare("SELECT code_district FROM ref_districts WHERE TRIM(nom_district) = ? LIMIT 1");
            $stmtD->execute([$nomDistrict]);
            $resD = $stmtD->fetch();
            if ($resD) { $codeLocalite = $resD['code_district']; }
        } 
        
        if (empty($codeLocalite) && !empty($nomRegionAgent)) {
            $stmtR = $pdo->prepare("SELECT code_chef_lieu_region FROM ref_regions WHERE TRIM(nom_region) = ? LIMIT 1");
            $stmtR->execute([$nomRegionAgent]);
            $resR = $stmtR->fetch();
            if ($resR) { $codeLocalite = $resR['code_chef_lieu_region']; }
        }

        if ($codeLocalite !== null) {
            $pdo->prepare("UPDATE acte_formate SET code_localite = ? WHERE im = ?")->execute([$codeLocalite, $im]);
        }
        
        $pdo->commit();
        ob_clean();
        echo json_encode(['success' => true]);
        exit;
    }

    // ==========================================
    // STEP 4 : DISTINCTIONS HONORIFIQUES
    // ==========================================
    else if ($step === "4") {
        $pdo->prepare("DELETE FROM personnel_distinctions WHERE im = ?")->execute([$im]);

        if (!empty($_POST['dist_num']) && is_array($_POST['dist_num'])) {
            $sql = "INSERT INTO personnel_distinctions (
                        im, type_grade, nature_acte, nom_grade, num_acte, date_acte
                    ) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            foreach ($_POST['dist_num'] as $key => $num) {
                if (!empty(trim($num))) {
                    $stmt->execute([
                        $im,
                        $_POST['dist_type'][$key] ?? null,
                        $_POST['dist_nature'][$key] ?? null,
                        $_POST['dist_nom_grade'][$key] ?? null,
                        $num,
                        $_POST['dist_date'][$key] ?? null
                    ]);
                }
            }
        }
        $pdo->commit();
        ob_clean();
        echo json_encode(['success' => true]);
        exit;
    }  
    // ==========================================
    // STEP 6 : CONGÉS
    // ==========================================
    else if ($step === "6") {
        $pdo->prepare("DELETE FROM personnel_conges WHERE im = ?")->execute([$im]);

        if (!empty($_POST['cong_num']) && is_array($_POST['cong_num'])) {
            $sql = "INSERT INTO personnel_conges (im, annee, num_decision, date_decision) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            foreach ($_POST['cong_num'] as $key => $num) {
                if (!empty(trim($num))) {
                    $stmt->execute([
                        $im,
                        $_POST['cong_annee'][$key] ?? null,
                        $num,
                        $_POST['cong_date'][$key] ?? null
                    ]);
                }
            }
        }
        $pdo->commit();
        ob_clean();
        echo json_encode(['success' => true]);
        exit;
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
    exit;
}
?>
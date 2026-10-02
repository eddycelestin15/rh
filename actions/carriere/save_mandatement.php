<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- CAS 1 : INSERTION INITIALE (Clic sur Valider et Continuer) ---
    if ($action === 'initial_save') {
        $im = $_POST['agent_matricule'] ?? '';
        $nom = $_POST['agent_nom'] ?? '';
        $prenoms = $_POST['agent_prenoms'] ?? '';
        $sexe = $_POST['agent_sexe'] ?? '';
        $date_naiss = !empty($_POST['agent_date_naiss']) ? $_POST['agent_date_naiss'] : null;
        $cin = $_POST['agent_cin'] ?? '';
        $situation_matrimoniale = $_POST['agent_matri'] ?? '';
        $nombre_enfant = intval($_POST['agent_enfants'] ?? 0);
        $mode_paiement = $_POST['agent_paiement'] ?? '';
        $imput_budg = $_POST['agent_imputation'] ?? '';
        $type_demande = $_POST['type_demande'] ?? null;

        if (empty($im)) {
            echo json_encode(['success' => false, 'message' => 'Le matricule de l\'agent est requis.']);
            exit;
        }

        try {
            // Correspondance exacte avec les colonnes de votre table acte_formate
            $sql = "INSERT INTO acte_formate (
                        im, type_demande, nom, prenoms, sexe, date_naiss, cin, 
                        situation_matrimoniale, nombre_enfant, mode_paiement, imput_budg, 
                        statut, date_generation
                    ) VALUES (
                        :im, :type_demande, :nom, :prenoms, :sexe, :date_naiss, :cin, 
                        :situation_matrimoniale, :nombre_enfant, :mode_paiement, :imput_budg, 
                        'en_attente', CURRENT_TIMESTAMP
                    )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':im' => $im,
                ':type_demande' => $type_demande,
                ':nom' => $nom,
                ':prenoms' => $prenoms,
                ':sexe' => $sexe,
                ':date_naiss' => $date_naiss,
                ':cin' => $cin,
                ':situation_matrimoniale' => $situation_matrimoniale,
                ':nombre_enfant' => $nombre_enfant,
                ':mode_paiement' => $mode_paiement,
                ':imput_budg' => $imput_budg
            ]);

            // Récupération de l'ID généré pour le renvoyer au JavaScript
            $lastId = $pdo->lastInsertId();

            echo json_encode(['success' => true, 'id' => $lastId]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
            exit;
        }
    }

    // --- CAS 2 : MISE À JOUR FINALE (Clic sur l'Étape 2) ---
    if ($action === 'final_update') {
        $inserted_id = intval($_POST['inserted_id'] ?? 0);
        
        if (!$inserted_id) {
            echo json_encode(['success' => false, 'message' => "ID de ligne manquant pour la mise à jour."]);
            exit;
        }

        // Récupération brute des données POST
        $anc_corps_id = intval($_POST['anc_corps'] ?? 0);
        $anc_grade_id = intval($_POST['anc_grade'] ?? 0);
        $anc_indice   = !empty($_POST['anc_indice']) ? intval($_POST['anc_indice']) : null;
        $date_acte    = !empty($_POST['date_acte']) ? $_POST['date_acte'] : null; // Date d'effet ancienne situation

        $nov_corps_id = intval($_POST['nov_corps'] ?? 0);
        $nov_grade_id = intval($_POST['nov_grade'] ?? 0);
        $nov_indice   = !empty($_POST['nov_indice']) ? intval($_POST['nov_indice']) : null;
        $date_effet   = !empty($_POST['date_effet']) ? $_POST['date_effet'] : null; // Date d'effet nouvelle situation
        $nov_lieu_service = $_POST['nov_lieu_service'] ?? null;

        $num_visa_finance   = $_POST['num_visa_finance'] ?? null;
        $date_visa_finance  = !empty($_POST['date_visa_finance']) ? $_POST['date_visa_finance'] : null;
        $num_visa_cde       = $_POST['num_visa_cde'] ?? null;
        $date_visa_cde      = !empty($_POST['date_visa_cde']) ? $_POST['date_visa_cde'] : null;
        $signataire_acte    = $_POST['signataire_acte'] ?? null;
        $corps_signataire   = $_POST['corps_signataire'] ?? null;

        try {
            // Extraction des libellés réels correspondants aux IDs pour l'Ancienne Situation
            $stmtAnc = $pdo->prepare("SELECT c.libelle_corps, g.code_grade FROM ref_corps c CROSS JOIN ref_grades_types g WHERE c.id = ? AND g.id = ?");
            $stmtAnc->execute([$anc_corps_id, $anc_grade_id]);
            $libellesAnc = $stmtAnc->fetch(PDO::FETCH_ASSOC);

            // Extraction pour la Nouvelle Situation
            $stmtNov = $pdo->prepare("SELECT c.libelle_corps, g.code_grade FROM ref_corps c CROSS JOIN ref_grades_types g WHERE c.id = ? AND g.id = ?");
            $stmtNov->execute([$nov_corps_id, $nov_grade_id]);
            $libellesNov = $stmtNov->fetch(PDO::FETCH_ASSOC);

            $anc_corps_txt = $libellesAnc['libelle_corps'] ?? null;
            $anc_grade_txt = $libellesAnc['code_grade'] ?? null;
            $nov_corps_txt = $libellesNov['libelle_corps'] ?? null;
            $nov_grade_txt = $libellesNov['code_grade'] ?? null;

            // Préparation de la requête SQL de mise à jour globale
            $sql = "UPDATE acte_formate SET 
                        anc_corps = :anc_corps,
                        anc_grade = :anc_grade,
                        anc_indice = :anc_indice,
                        date_acte = :date_acte,
                        
                        nov_corps = :nov_corps,
                        nov_grade = :nov_grade,
                        nov_indice = :nov_indice,
                        date_effet = :date_effet,
                        nov_lieu_service = :nov_lieu_service,
                        
                        num_visa_finance = :num_visa_finance,
                        date_visa_finance = :date_visa_finance,
                        num_visa_cde = :num_visa_cde,
                        date_visa_cde = :date_visa_cde,
                        signataire_acte = :signataire_acte,
                        corps_signataire = :corps_signataire
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':anc_corps'  => $anc_corps_txt,
                ':anc_grade'  => $anc_grade_txt,
                ':anc_indice' => $anc_indice,
                ':date_acte'  => $date_acte,
                
                ':nov_corps'  => $nov_corps_txt,
                ':nov_grade'  => $nov_grade_txt,
                ':nov_indice' => $nov_indice,
                ':date_effet' => $date_effet,
                ':nov_lieu_service' => $nov_lieu_service,
                
                ':num_visa_finance'  => $num_visa_finance,
                ':date_visa_finance' => $date_visa_finance,
                ':num_visa_cde'      => $num_visa_cde,
                ':date_visa_cde'     => $date_visa_cde,
                ':signataire_acte'   => $signataire_acte,
                ':corps_signataire'  => $corps_signataire,
                ':id'                => $inserted_id
            ]);

            echo json_encode(['success' => true, 'message' => "Le mandatement a été créé et enregistré avec succès."]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "Erreur SQL : " . $e->getMessage()]);
            exit;
        }
    }

    // --- CAS 2 : MISE À JOUR FINALE (Formulaire Étape 2 complet) ---
    // Si vous traitez également la soumission finale de l'étape 2 ici :
    $inserted_id = $_POST['inserted_id'] ?? '';
    if (!empty($inserted_id)) {
        $anc_indice = !empty($_POST['anc_indice']) ? intval($_POST['anc_indice']) : null;
        $nov_indice = !empty($_POST['nov_indice']) ? intval($_POST['nov_indice']) : null;
        $anc_corps = $_POST['anc_corps'] ?? null;
        $nov_corps = $_POST['nov_corps'] ?? null;
        $anc_grade = $_POST['anc_grade'] ?? null;
        $nov_grade = $_POST['nov_grade'] ?? null;
        $nov_lieu_service = $_POST['nov_lieu_service'] ?? null;

        try {
            $sql = "UPDATE acte_formate SET 
                        anc_corps = :anc_corps,
                        anc_grade = :anc_grade,
                        anc_indice = :anc_indice,
                        nov_corps = :nov_corps,
                        nov_grade = :nov_grade,
                        nov_indice = :nov_indice,
                        nov_lieu_service = :nov_lieu_service
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':anc_corps' => $anc_corps,
                ':anc_grade' => $anc_grade,
                ':anc_indice' => $anc_indice,
                ':nov_corps' => $nov_corps,
                ':nov_grade' => $nov_grade,
                ':nov_indice' => $nov_indice,
                ':nov_lieu_service' => $nov_lieu_service,
                ':id' => $inserted_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Mandatement finalisé avec succès.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour finale : ' . $e->getMessage()]);
            exit;
        }
    }
}
echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
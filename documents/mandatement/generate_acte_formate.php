<?php
// Tampon de sortie : evite qu'un avertissement PHP ne corrompe le .docx
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php'; 
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

use PhpOffice\PhpWord\TemplateProcessor;

// 1. Récupération des paramètres
$im   = $_GET['im'] ?? '';
$type = $_GET['type'] ?? '';

if (!$im) {
    die("IM manquant.");
}

// 2. Détermination de la table et du fichier Template Word selon le type
switch ($type) {
    case 'compensatrice':
        $tableActe    = 'acte_formate_retraite';
        $templatePath = APP_ROOT . '/pieces/Mandatement/Acte_formate_compensatrice.docx';
        break;

    case 'installation':
        $tableActe    = 'acte_formate_retraite';
        $templatePath = APP_ROOT . '/pieces/Mandatement/Acte_formate_installation.docx';
        break;

    case 'avenant_avec_contrat':
        $tableActe    = 'acte_formate_av_cont';
        $templatePath = APP_ROOT . '/pieces/Mandatement/Acte_formate.docx'; // Ajuster le template si nécessaire
        break;

    default:
        $tableActe    = 'acte_formate';
        $templatePath = APP_ROOT . '/pieces/Mandatement/Acte_formate.docx';
        break;
}

try {
    // 3. Exécution de la requête SQL avec filtre strict sur l'IM ET le type_demande (si applicable)
    if (in_array($type, ['compensatrice', 'installation'])) {
        $sql = "SELECT * FROM $tableActe 
                WHERE im = ? AND type_demande = ? 
                ORDER BY id DESC 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$im, $type]);
    } else {
        $sql = "SELECT * FROM $tableActe 
                WHERE im = ? 
                ORDER BY id DESC 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$im]);
    }

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Aucun enregistrement trouvé dans la table {$tableActe} pour l'IM : " . htmlspecialchars($im) . " (type: " . htmlspecialchars($type) . ")");
    }

    $template = new TemplateProcessor($templatePath);

    // --- LOGIQUE AVANCEMENTS SUCCESSIFS (Tableau) ---
    $stmtAv = $pdo->prepare("SELECT * FROM avancement_successif WHERE im = ? ORDER BY ordre_avancement ASC");
    $stmtAv->execute([$im]);
    $avancements = $stmtAv->fetchAll(PDO::FETCH_ASSOC);

    // --- CHAMPS CLASSIQUES ---
    $template->setValue('acte', $data['acte'] );
    $template->setValue('num_acte', $data['num_acte']);
    $template->setValue('nom_signataire', $data['nom_signataire'] );
    $corps_signataire = !empty($data['corps_signataire']) ? $data['corps_signataire'] : "";
    $template->setValue('corps_signataire', $corps_signataire);
    $libelle_majuscule = mb_strtoupper((string)($data['libelle_demande'] ?? ''), 'UTF-8');
    $template->setValue('type_demande', $libelle_majuscule);

    $code_mvt = trim((string)$data['code_mvt']); 
    $cm = strtoupper($code_mvt);
    for ($i = 1; $i <= 2; $i++) { 
        $char = isset($cm[$i-1]) ? $cm[$i-1] : ''; 
        $template->setValue('cm' . $i, $char);
    }

    $date_acte = $data['date_acte']; 
    $date_clean = "";
    if (!empty($date_acte)) {
        $date_obj = new DateTime($date_acte);
        $date_clean = $date_obj->format('dmY'); 
    }
    for ($i = 1; $i <= 8; $i++) {
        $char = isset($date_clean[$i-1]) ? $date_clean[$i-1] : ''; 
        $template->setValue('dta' . $i, $char);
    }

    $cin = trim((string)$data['cin']); 
    $cin = strtoupper($cin);
    for ($i = 1; $i <= 12; $i++) { 
        $char = isset($cin[$i-1]) ? $cin[$i-1] : ''; 
        $template->setValue('cin' . $i, $char);
    }

    $im = trim((string)$data['im']); 
    $im = strtoupper($im);
    for ($i = 1; $i <= 6; $i++) { 
        $char = isset($im[$i-1]) ? $im[$i-1] : ''; 
        $template->setValue('im' . $i, $char);
    }

    // nettoyerChaine() est fournie par includes/helpers.php

    $nom = trim((string)$data['nom']); 
    $nom = nettoyerChaine($nom); 
    $nom = strtoupper($nom);

    for ($i = 1; $i <= 23; $i++) { 
        $char = isset($nom[$i-1]) ? $nom[$i-1] : ''; 
        $template->setValue('nom' . $i, $char);
    }

    $prenoms = trim((string)$data['prenoms']); 
    $p = nettoyerChaine($prenoms); 
    $p = strtoupper($p);

    for ($i = 1; $i <= 32; $i++) { 
        $char = isset($p[$i-1]) ? $p[$i-1] : ''; 
        $template->setValue('p' . $i, $char);
    }

    $date_naissance = $data['date_naiss']; 
    $date_clean = "";
    if (!empty($date_naissance)) {
        $date_obj = new DateTime($date_naissance);
        $date_clean = $date_obj->format('dmY'); 
    }
    for ($i = 1; $i <= 8; $i++) {
        $char = isset($date_clean[$i-1]) ? $date_clean[$i-1] : ''; 
        $template->setValue('dtn' . $i, $char);
    }

    $sit_mat = $data['situation_matrimoniale']; 
    $lettre_sit = '';

    switch ($sit_mat) {
        case 'Célibataire': $lettre_sit = 'C'; break;
        case 'Marié(e)':    $lettre_sit = 'M'; break;
        case 'Divorcé(e)':  $lettre_sit = 'D'; break;
        case 'Veuf(ve)':    $lettre_sit = 'V'; break;
        default:            $lettre_sit = '';  break;
    }
    $template->setValue('sm', $lettre_sit);

    $sexe_brut = $data['sexe']; 
    $lettre_sexe = '';

    if ($sexe_brut == 'Masculin') {
        $lettre_sexe = 'M';
    } elseif ($sexe_brut == 'Féminin') {
        $lettre_sexe = 'F';
    }
    $template->setValue('sx', $lettre_sexe);

    $nbr_enfant_brut = (string)$data['nombre_enfant']; 
    $nbr_enf_prepare = str_pad($nbr_enfant_brut, 2, '0', STR_PAD_LEFT);
    for ($i = 1; $i <= 2; $i++) {
        $char = isset($nbr_enf_prepare[$i-1]) ? $nbr_enf_prepare[$i-1] : '';
        $template->setValue('nba' . $i, $char);
    }

    // ANCIEN POSITION
    $imputation = trim((string)$data['imput_budg']); 
    $ib = strtoupper($imputation);
    for ($i = 1; $i <= 8; $i++) { 
        $char = isset($ib[$i-1]) ? $ib[$i-1] : ''; 
        $template->setValue('ib' . $i, $char);
    }

    $code_corps_actuel = trim((string)$data['code_corps_actuel']); 
    $cca = strtoupper($code_corps_actuel);
    for ($i = 1; $i <= 4; $i++) { 
        $char = isset($cca[$i-1]) ? $cca[$i-1] : ''; 
        $template->setValue('cca' . $i, $char);
    }

    $code_grade_actuel = trim((string)$data['code_grade_actuel']); 
    $cga = strtoupper($code_grade_actuel);
    for ($i = 1; $i <= 4; $i++) { 
        $char = isset($cga[$i-1]) ? $cga[$i-1] : ''; 
        $template->setValue('cga' . $i, $char);
    }

    $indice_brut = (string)$data['indice_actuel']; 
    $indice_actuel = str_pad($indice_brut, 4, '0', STR_PAD_LEFT);
    for ($i = 1; $i <= 4; $i++) {
        $char = isset($indice_actuel[$i-1]) ? $indice_actuel[$i-1] : '';
        $template->setValue('ia' . $i, $char);
    }

    $code_localite = trim((string)$data['code_localite']); 
    $cd = strtoupper($code_localite);
    for ($i = 1; $i <= 6; $i++) { 
        $char = isset($cd[$i-1]) ? $cd[$i-1] : ''; 
        $template->setValue('cd' . $i, $char);
    }

    $mode_paiement_brut = $data['mode_paiement']; 
    $lettre_mdp = '';

    if ($mode_paiement_brut == 'Virement') {
        $lettre_mdp = '2';
    } elseif ($mode_paiement_brut == 'Bon de caisse') {
        $lettre_mdp = '1';
    }
    $template->setValue('mdp', $lettre_mdp);

    $date_effet_actuel = $data['date_d_effet_actuel']; 
    $date_clean = "";
    if (!empty($date_effet_actuel)) {
        $date_obj = new DateTime($date_effet_actuel);
        $date_clean = $date_obj->format('dmY'); 
    }
    for ($i = 1; $i <= 8; $i++) {
        $char = isset($date_clean[$i-1]) ? $date_clean[$i-1] : ''; 
        $template->setValue('dea' . $i, $char);
    }

    // AVANCEMENT SUCCESSIFS
    $stmtAv = $pdo->prepare("SELECT * FROM avancement_successif WHERE im = ? AND utilisation = 'non_utilise' ORDER BY ordre_avancement ASC LIMIT 4");
    $stmtAv->execute([$im]);
    $listeAvancements = $stmtAv->fetchAll(PDO::FETCH_ASSOC);

    for ($numAv = 1; $numAv <= 4; $numAv++) {
        $donnees = null;
        
        // Recherche de l'avancement spécifique (ex: avancement1, avancement2...)
        foreach ($listeAvancements as $a) {
            $numeroOrdre = intval(preg_replace('/[^0-9]/', '', $a['ordre_avancement']));
            if ($numeroOrdre === $numAv) {
                $donnees = $a;
                break;
            }
        }

        // --- CORPS
        $valCorps = $donnees ? (string)$donnees['code_corps'] : '';
        for ($i = 1; $i <= 4; $i++) {
            $char = isset($valCorps[$i-1]) ? $valCorps[$i-1] : '';
            $template->setValue("cp{$numAv}_{$i}", $char);
        }

        // --- GRADE
        $valGrade = $donnees ? (string)$donnees['code_grade'] : '';
        for ($i = 1; $i <= 4; $i++) {
            $char = isset($valGrade[$i-1]) ? $valGrade[$i-1] : '';
            $template->setValue("gd{$numAv}_{$i}", $char);
        }

        // --- INDICE 
        $valIndice = ($donnees && !empty($donnees['indice'])) ? str_pad($donnees['indice'], 4, '0', STR_PAD_LEFT) : '';
        for ($i = 1; $i <= 4; $i++) {
            $char = isset($valIndice[$i-1]) ? $valIndice[$i-1] : '';
            $template->setValue("id{$numAv}_{$i}", $char);
        }

        // --- DATE D'EFFET
        $valDate = "";
        if ($donnees && !empty($donnees['date_d_effet'])) {
            try {
                $dateObj = new DateTime($donnees['date_d_effet']);
                $valDate = $dateObj->format('dmY'); 
            } catch (Exception $e) {
                $valDate = ""; 
            }
        }
        
        for ($i = 1; $i <= 8; $i++) {
            $char = isset($valDate[$i-1]) ? $valDate[$i-1] : '';
            $template->setValue("dt{$numAv}_{$i}", $char);
        }
    }

    // NOUVELLE POSITION
    $nouveau_code_corps = trim((string)$data['new_code_corps']); 
    $ncc = strtoupper($nouveau_code_corps);
    if ($cca === $ncc) {
        for ($i = 1; $i <= 4; $i++) {
            $template->setValue('ncc' . $i, '');
        }
    } else {
        for ($i = 1; $i <= 4; $i++) { 
            $char = isset($ncc[$i-1]) ? $ncc[$i-1] : ''; 
            $template->setValue('ncc' . $i, $char);
        }
    }

    $nouveau_code_grade = trim((string)$data['new_code_grade']); 
    $ncg = strtoupper($nouveau_code_grade);
    if ($cga === $ncg) {
        for ($i = 1; $i <= 4; $i++) {
            $template->setValue('ncg' . $i, '');
        }
    } else {
        for ($i = 1; $i <= 4; $i++) { 
            $char = isset($ncg[$i-1]) ? $ncg[$i-1] : ''; 
            $template->setValue('ncg' . $i, $char);
        }
    }

    $indice_brut = (string)$data['new_indice']; 
    $new_indice = str_pad($indice_brut, 4, '0', STR_PAD_LEFT);
    if ($indice_actuel === $new_indice) {
        for ($i = 1; $i <= 4; $i++) {
            $template->setValue('ni' . $i, '');
        }
    } else {
        for ($i = 1; $i <= 4; $i++) { 
            $char = isset($new_indice[$i-1]) ? $new_indice[$i-1] : ''; 
            $template->setValue('ni' . $i, $char);
        }
    }

    $nouvelle_date_effet = $data['new_date_d_effet']; 
    $date_clean = "";
    if (!empty($nouvelle_date_effet)) {
        $date_obj = new DateTime($nouvelle_date_effet);
        $date_clean = $date_obj->format('dmY'); 
    }
    for ($i = 1; $i <= 8; $i++) {
        $char = isset($date_clean[$i-1]) ? $date_clean[$i-1] : ''; 
        $template->setValue('nde' . $i, $char);
    }

    // VISA FINANCE ET CDE AVEC SIGNATAIRE
    $num_fin_brut = (string)$data['num_visa_finance']; 
    $num_fin_prepare = str_pad($num_fin_brut, 6, '0', STR_PAD_LEFT);
    for ($i = 1; $i <= 6; $i++) {
        $char = isset($num_fin_prepare[$i-1]) ? $num_fin_prepare[$i-1] : '';
        $template->setValue('nvf' . $i, $char);
    }

    $date_visa_fin = $data['date_visa_finance']; 
    $date_clean = "";
    if (!empty($date_visa_fin)) {
        $date_obj = new DateTime($date_visa_fin);
        $date_clean = $date_obj->format('dmY'); 
    }
    for ($i = 1; $i <= 8; $i++) {
        $char = isset($date_clean[$i-1]) ? $date_clean[$i-1] : ''; 
        $template->setValue('dvf' . $i, $char);
    }

    $num_cde_brut = (string)$data['num_visa_cde']; 
    $num_cde_prepare = str_pad($num_cde_brut, 6, '0', STR_PAD_LEFT);
    for ($i = 1; $i <= 6; $i++) {
        $char = isset($num_cde_prepare[$i-1]) ? $num_cde_prepare[$i-1] : '';
        $template->setValue('nvc' . $i, $char);
    }

    $date_visa_cde = $data['date_visa_cde']; 
    $date_clean = "";
    if (!empty($date_visa_cde)) {
        $date_obj = new DateTime($date_visa_cde);
        $date_clean = $date_obj->format('dmY'); 
    }
    for ($i = 1; $i <= 8; $i++) {
        $char = isset($date_clean[$i-1]) ? $date_clean[$i-1] : ''; 
        $template->setValue('dvc' . $i, $char);
    }

    $updateStmt = $pdo->prepare("UPDATE avancement_successif SET utilisation = 'utilise' WHERE im = ?");
    $updateStmt->execute([$im]);

    $filename = "Acte_formate_" . $im . ".docx";
    // Empeche toute sortie parasite de corrompre le fichier
    vider_tampon_sortie();
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $template->saveAs('php://output');

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
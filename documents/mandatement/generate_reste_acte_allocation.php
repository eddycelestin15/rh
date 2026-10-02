<?php
// Tampon de sortie : evite qu'un avertissement PHP ne corrompe le .docx
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php'; 
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

use PhpOffice\PhpWord\TemplateProcessor;

$im = $_GET['im'] ?? '';

try {
    $queryAgent = "SELECT e.*, s.*, g.code_grade 
               FROM personnel_etat_civil e
               LEFT JOIN personnel_situation_actuelle s ON e.im = s.im
               LEFT JOIN ref_grades_types g ON s.grade_actuel = g.libelle_grade
               WHERE e.im = ?";

    $stmtAgent = $conn->prepare($queryAgent);
    $stmtAgent->bind_param("s", $im);
    $stmtAgent->execute();
    $data = $stmtAgent->get_result()->fetch_assoc();

    if (!$data) {
        die("Agent non trouvé.");
    }

    // 1. Récupérer les 4 premiers enfants (les plus âgés) qui ne sont pas encore 'utilise'
    $query = "SELECT * FROM personnel_enfants 
          WHERE im_parent = ? 
          AND situation = 'non_utilise' 
          ORDER BY date_naiss_enfant ASC 
          LIMIT 4";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $im); 
    $stmt->execute();
    $result = $stmt->get_result();
    $enfants = $result->fetch_all(MYSQLI_ASSOC);

    // Initialiser le template (le fichier doit exister dans votre dossier)
    $template = new TemplateProcessor(APP_ROOT . '/pieces/Allocation_familiale/Acte_formate_reste_allocation.docx');

    // nettoyerChaine() est fournie par includes/helpers.php

    $num_acte = "";
    $date_acte = "";

    if (count($enfants) > 0) {
        $num_acte = $enfants[0]['num_dcf'];
        
        $date_brute = $enfants[0]['date_dcf'];         
        if (!empty($date_brute)) {
            $date_obj = new DateTime($date_brute);           
            $date_acte = $date_obj->format('d/m/Y'); 
            $date_clean = $date_obj->format('dmY'); 
        } else {
            $date_clean = "";
        }
        for ($i = 1; $i <= 8; $i++) {
            $char = isset($date_clean[$i-1]) ? $date_clean[$i-1] : ''; 
            $template->setValue('dta' . $i, $char);
        }
        $template->setValue('num_acte', $num_acte);
    }    

    // CIN (12 cases)
    $cin = trim((string)($data['cin'] ?? '')); 
    $cin = strtoupper($cin);
    for ($i = 1; $i <= 12; $i++) { 
        $char = isset($cin[$i-1]) ? $cin[$i-1] : ''; 
        $template->setValue('cin' . $i, $char);
    }

    // IM (6 cases)
    $im_val = trim((string)($data['im'] ?? '')); 
    $im_val = strtoupper($im_val);
    for ($i = 1; $i <= 6; $i++) { 
        $char = isset($im_val[$i-1]) ? $im_val[$i-1] : ''; 
        $template->setValue('im' . $i, $char);
    }

    // NOM (23 cases)
    $nom = strtoupper(nettoyerChaine(trim((string)($data['nom'] ?? '')))); 
    for ($i = 1; $i <= 23; $i++) { 
        $char = isset($nom[$i-1]) ? $nom[$i-1] : ''; 
        $template->setValue('nom' . $i, $char);
    }

    // PRENOMS (32 cases) - Ici on garde la majuscule comme demandé dans votre snippet
    $p = strtoupper(nettoyerChaine(trim((string)($data['prenoms'] ?? '')))); 
    for ($i = 1; $i <= 32; $i++) { 
        $char = isset($p[$i-1]) ? $p[$i-1] : ''; 
        $template->setValue('p' . $i, $char);
    }

    // DATE DE NAISSANCE AGENT (8 cases)
    $date_naiss = $data['date_naiss'] ?? ''; 
    $date_n_clean = !empty($date_naiss) ? (new DateTime($date_naiss))->format('dmY') : "";
    for ($i = 1; $i <= 8; $i++) {
        $char = isset($date_n_clean[$i-1]) ? $date_n_clean[$i-1] : ''; 
        $template->setValue('dtn' . $i, $char);
    }

    // SITUATION MATRIMONIALE
    $sit_mat = $data['situation_familiale']; 
    $lettre_sit = '';

    switch ($sit_mat) {
        case 'Célibataire': $lettre_sit = 'C'; break;
        case 'Marié(e)':    $lettre_sit = 'M'; break;
        case 'Divorcé(e)':  $lettre_sit = 'D'; break;
        case 'Veuf(ve)':    $lettre_sit = 'V'; break;
        default:            $lettre_sit = '';  break;
    }
    $template->setValue('sm', $lettre_sit);

    // SEXE
    $sexe_brut = $data['sexe'] ?? ''; 
    $template->setValue('sx', ($sexe_brut == 'Masculin' ? 'M' : ($sexe_brut == 'Féminin' ? 'F' : '')));

    // NOMBRE ENFANT (2 cases)
    $nbr_enfant = str_pad((string)($data['nbr_enfant'] ?? 0), 2, '0', STR_PAD_LEFT);
    for ($i = 1; $i <= 2; $i++) {
        $template->setValue('nba' . $i, $nbr_enfant[$i-1]);
    }

    // IMPUTATION BUDGETAIRE (8 cases)
    $ib = strtoupper(trim((string)($data['imput_budg'] ?? '')));
    for ($i = 1; $i <= 8; $i++) { 
        $char = isset($ib[$i-1]) ? $ib[$i-1] : ''; 
        $template->setValue('ib' . $i, $char);
    }

    // CODE CORPS ET GRADE (4 cases chacun)
    $cca = strtoupper(trim((string)($data['code_corps_actuel'] ?? '')));
    for ($i = 1; $i <= 4; $i++) { 
        $template->setValue('cca' . $i, $cca[$i-1] ?? '');
    }

    $code_grade = trim((string)($data['code_grade'] ?? '')); 
    $cga = strtoupper($code_grade);
    for ($i = 1; $i <= 4; $i++) { 
        $char = isset($cga[$i-1]) ? $cga[$i-1] : ''; 
        $template->setValue('cga' . $i, $char);
    }
    // INDICE (4 cases)
    $indice = str_pad((string)($data['indice_actuel'] ?? 0), 4, '0', STR_PAD_LEFT);
    for ($i = 1; $i <= 4; $i++) {
        $template->setValue('ia' . $i, $indice[$i-1]);
    }

    // MODE DE PAIEMENT
    $mdp = $data['mode_paiement'] ?? ''; 
    $template->setValue('mdp', ($mdp == 'Virement' ? '2' : ($mdp == 'Bon de caisse' ? '1' : '')));

    // DATE D'EFFET (8 cases)
    $date_effet = $data['date_d_effet_actuel'] ?? ''; 
    $date_e_clean = !empty($date_effet) ? (new DateTime($date_effet))->format('dmY') : "";
    for ($i = 1; $i <= 8; $i++) {
        $char = isset($date_e_clean[$i-1]) ? $date_e_clean[$i-1] : ''; 
        $template->setValue('dea' . $i, $char);
    }

    // 2. Remplissage des données
    for ($i = 0; $i < 4; $i++) {
        $row = $i + 1;
        if (isset($enfants[$i])) {
            $e = $enfants[$i];
            
            // Numéro de copie (4 cases)
            $num = str_pad($e['num_copie_acte'], 4, '0', STR_PAD_LEFT);
            $template->setValue("na1_$row", $num[0]);
            $template->setValue("na2_$row", $num[1]);
            $template->setValue("na3_$row", $num[2]);
            $template->setValue("na4_$row", $num[3]);

            // Statut BC
            $template->setValue("st_$row", ($e['statut_bc'] == 1 ? 'I' : 'R'));

            // Nom et Prénoms
            $template->setValue("nomc_$row", $e['nom_enfant'] . ' ' . $e['prenoms_enfant']);

            // Date de naissance JJMMAAAA (8 cases)
            $date_str = date('dmY', strtotime($e['date_naiss_enfant']));
            for ($j = 0; $j < 8; $j++) {
                $template->setValue("dt".($j+1)."_$row", $date_str[$j]);
            }

            // Filiation
            $fil_map = ['Légitime' => 'L', 'Reconnaissance' => 'R', 'Naturel' => 'N', 'Adopté' => 'A'];
            $template->setValue("fil_$row", $fil_map[$e['type_filiation']] ?? 'N');

            // 3. Mise à jour du statut en 'utilise'
            $update = $conn->prepare("UPDATE personnel_enfants SET situation = 'utilise', statut_bc = '1' WHERE id = ?");
            $update->bind_param("i", $e['id']); 
            $update->execute();
        } else {
            // Vider les tags si moins de 4 enfants
            $template->setValue("na1_$row", ""); 
            $template->setValue("na2_$row", "");
            $template->setValue("na3_$row", ""); 
            $template->setValue("na4_$row", "");
            $template->setValue("st_$row", ""); 
            $template->setValue("nomc_$row", "");
            for ($j = 1; $j <= 8; $j++) 
                { $template->setValue("dt$j" . "_$row", ""); }
            $template->setValue("fil_$row", "");
        }
    }

    // Empeche toute sortie parasite de corrompre le fichier
    vider_tampon_sortie();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="Acte_formate_allocation.docx"');
    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
?>
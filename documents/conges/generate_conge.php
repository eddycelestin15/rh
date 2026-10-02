<?php
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
use PhpOffice\PhpWord\templateProcessor;

function joursEnLettres($nombre) {
    $unites = [
        0 => "ZÉRO", 1 => "UN", 2 => "DEUX", 3 => "TROIS", 4 => "QUATRE", 
        5 => "CINQ", 6 => "SIX", 7 => "SEPT", 8 => "HUIT", 9 => "NEUF", 
        10 => "DIX", 11 => "ONZE", 12 => "DOUZE", 13 => "TREIZE", 
        14 => "QUATORZE", 15 => "QUINZE", 16 => "SEIZE", 17 => "DIX-SEPT", 
        18 => "DIX-HUIT", 19 => "DIX-NEUF", 20 => "VINGT", 30 => "TRENTE"
    ];
    $nombre_arrondi = floor($nombre);
    $isDemi = ($nombre - $nombre_arrondi) >= 0.5;
    $partie_lettre = $unites[$nombre_arrondi] ?? ($nombre_arrondi > 20 ? "VINGT-" . $unites[$nombre_arrondi - 20] : $nombre_arrondi);
    if ($isDemi) $partie_lettre .= " ET DEMI";
    $chiffre_format = str_replace('.', ',', (string)$nombre);
    return $partie_lettre . " (" . $chiffre_format . ") JOURS";
}

if (isset($_GET['years'], $_GET['im'], $_GET['mode'])) {
    $years_array = explode(',', $_GET['years']);
    sort($years_array);
    $im = $_GET['im'];
    $mode = $_GET['mode'];

    try {
        $stmt = $pdo->prepare("SELECT ec.*, sa.* FROM personnel_etat_civil ec JOIN personnel_situation_actuelle sa ON ec.im = sa.im WHERE ec.im = ?");
        $stmt->execute([$im]);
        $agent = $stmt->fetch();

        if (!$agent) die("Agent introuvable.");

        $dateNais = new DateTime($agent['date_naiss']);
        $dateRetraite = (clone $dateNais)->modify('+60 years');
        $anneeRetraite = (int)$dateRetraite->format('Y');
        $isNeVers = ($dateNais->format('m-d') === '01-01');

        $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
        $stmtPosteActuel->execute([$im]);
        $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);        

        $br = '</w:t><w:br/><w:t>';
        $type_fonction = $PosteActuel['type_fonction'] ?? '';
        $type_etablissement = $PosteActuel['type_etablissement'] ?? '';
        $type_direction = $PosteActuel['type_direction'] ?? '';
        $nom_direction = $PosteActuel['nom_direction'] ?? ''; 
        $nom_service = $PosteActuel['nom_service'] ?? '';
        $nom_division = $PosteActuel['nom_division'] ?? '';
        $nom_region = $PosteActuel['nom_region'] ?? ''; 
        $nom_district = $PosteActuel['nom_district'] ?? ''; 
        $nom_zap = $PosteActuel['nom_zap'] ?? '';
        $nom_etablissement = $PosteActuel['nom_etablissement'] ?? '';
        $sexe = $agent['sexe'] ?? '';
        $situation_familiale = $agent['situation_familiale'] ?? '';

        $chef_lieu_region = '';
        $chef_lieu_district = '';

        if (!empty($nom_region)) {
            $stmtReg = $pdo->prepare("SELECT chef_lieu_region FROM ref_regions WHERE nom_region = :nom_region LIMIT 1");
            $stmtReg->execute(['nom_region' => $nom_region]);
            $region_data = $stmtReg->fetch(PDO::FETCH_ASSOC);
            if ($region_data && !empty($region_data['chef_lieu_region'])) {
                $chef_lieu_region = $region_data['chef_lieu_region'];
            }
        }

        if (!empty($nom_district)) {
            $stmtDist = $pdo->prepare("SELECT chef_lieu_district FROM ref_districts WHERE nom_district = :nom_district LIMIT 1");
            $stmtDist->execute(['nom_district' => $nom_district]);
            $district_data = $stmtDist->fetch(PDO::FETCH_ASSOC);
            if ($district_data && !empty($district_data['chef_lieu_district'])) {
                $chef_lieu_district = $district_data['chef_lieu_district'];
            }
        }

        $nom_crfrp_propre = $nom_etablissement;
        if (stripos($nom_crfrp_propre, 'CRFRP') !== false) {
            $nom_crfrp_propre = trim(preg_replace('/^CRFRP\s+/i', '', $nom_crfrp_propre));
        }
        $nom_crfrp = $nom_crfrp_propre;

        $premiere_lettre = mb_substr(ltrim($chef_lieu_region), 0, 1, 'UTF-8');
        $est_voyelle = preg_match('/^[AEIOUYÀÁÂÃÄÅÆÈÉÊËÌÍÎÏÒÓÔÕÖØÙÚÛÜ]/ui', $premiere_lettre);

        $prefecture_val = $est_voyelle ? "PREFECTURE D'" . mb_strtoupper($chef_lieu_region, 'UTF-8') : "PREFECTURE DE " . mb_strtoupper($chef_lieu_region, 'UTF-8');
        $adresser_a_val = $est_voyelle ? "LE PREFET D'" : "LE PREFET DE ";
        $chef_lieu_region_val = mb_strtoupper($chef_lieu_region, 'UTF-8');

        if ($mode === 'decision') {
            $templateFile = APP_ROOT . '/pieces/Demande_decision_conge/decision2conge.docx';
            if (!file_exists($templateFile)) die("Fichier modélisé introuvable: " . $templateFile);
            
            $template = new templateProcessor($templateFile);
            
            // Gestion Sexe après initialisation de $template
            if ($sexe == "Masculin"){
                $template->setValue('nee_le', "Né le");
                $template->setValue('interessee', "L'intéressé");
            } else {
                $template->setValue('nee_le', "Née le");
                $template->setValue('interessee', "L'intéressée");
            }

            $template->cloneBlock('BLOCK_DECISION', count($years_array), true, true);

            foreach ($years_array as $index => $annee) {
                $p = $index + 1;
                $nb_jours = 30;
                if ($annee == $anneeRetraite && !$isNeVers) {
                    $m = (int)$dateRetraite->format('m');
                    $d = (int)$dateRetraite->format('d');
                    $f = ($d >= 28) ? 2.5 : (($d >= 21) ? 2 : (($d >= 16) ? 1.5 : (($d >= 9) ? 1 : (($d >= 4) ? 0.5 : 0))));
                    $nb_jours = (($m - 1) * 2.5) + $f;
                }

                $template->setValue('annee_dec#' . $p, $annee);
                $template->setValue('jours_dec#' . $p, joursEnLettres($nb_jours));
                $template->setValue('nom_dec#' . $p, htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
                $template->setValue('im_dec#' . $p, $agent['im']);
                $template->setValue('corps_grade_dec#' . $p, htmlspecialchars(($agent['corps_actuel'] ?? '') . ', ' . ($agent['grade_actuel'] ?? '')));
                $template->setValue('chapitre_dec#' . $p, $agent['imput_budg'] ?? '');
                
                if ($p < count($years_array)) {
                    $template->setValue('chapitre_dec#' . $p, ($agent['imput_budg'] ?? '') . '</w:t><w:br w:type="page"/><w:t>');
                }

                if ($type_etablissement == "MEN CENTRAL") {
                    $template->setValue('en_service_dec#' . $p, $nom_direction);
                    $template->setValue('lieu_signature_dec#' . $p, 'Antananarivo');
                } else if ($type_etablissement == "DREN") {
                    $template->setValue('en_service_dec#' . $p, "BUREAU DREN " . $nom_region);
                    $template->setValue('lieu_signature_dec#' . $p, $chef_lieu_region);
                } else if ($type_etablissement == "CISCO") {
                    $template->setValue('en_service_dec#' . $p, "BUREAU CISCO " . $nom_district);
                    $template->setValue('lieu_signature_dec#' . $p, $chef_lieu_region);
                } else if ($type_etablissement == "CRFRP") {
                    $template->setValue('en_service_dec#' . $p, $nom_etablissement);
                    $template->setValue('lieu_signature_dec#' . $p, $chef_lieu_region);
                } else {
                    $template->setValue('en_service_dec#' . $p, $nom_etablissement . " - ZAP " . $nom_zap);
                    $template->setValue('lieu_signature_dec#' . $p, $chef_lieu_region);
                }

                $template->setValue('prefecture_dec#' . $p, $prefecture_val);
                $template->setValue('nom_region_dec#' . $p, $nom_region);
            }
        } 
        else {
            $templateFile = APP_ROOT . '/pieces/Demande_decision_conge/pj_conge.docx';
            if (!file_exists($templateFile)) die("Fichier modélisé introuvable: " . $templateFile);

            $template = new templateProcessor($templateFile);

            // Gestion Sexe après initialisation de $template
            if ($sexe == "Masculin"){
                $template->setValue('nee_le', "Né le");
                $template->setValue('interessee', "L'intéressé");
            } else {
                $template->setValue('nee_le', "Née le");
                $template->setValue('interessee', "L'intéressée");
            }

            $nb_decisions = count($years_array);
            $total_exemplaires_projet = $nb_decisions * 5;

            if ($nb_decisions > 1) {
                $texte_titre = "aux titres des années";
                $temp_years = $years_array;
                $derniere = array_pop($temp_years);
                $liste_annees = implode(', ', $temp_years) . " et " . $derniere;
            } else {
                $texte_titre = "au titre de l'année";
                $liste_annees = $years_array[0];
            }

            if (file_exists(APP_ROOT . '/Logo/Embleme.png')) {
                $template->setImageValue('embleme', ['path' => APP_ROOT . '/Logo/Embleme.png', 'width' => 100, 'height' => 50, 'ratio' => true]);
            }
            if (file_exists(APP_ROOT . '/Logo/Logo_men.png')) {
                $template->setImageValue('logo_men', ['path' => APP_ROOT . '/Logo/Logo_men.png', 'width' => 100, 'height' => 50, 'ratio' => true]);
            }

            $genre_input = $_GET['genre'] ?? 'Mr';
            $genre_maj = ($genre_input === 'Mme') ? 'MADAME' : 'MONSIEUR';
            $genre_min = ($genre_input === 'Mme') ? 'Madame' : 'Monsieur';
            $template->setValue('genre_min', $genre_min);
            $template->setValue('genre_maj', $genre_maj);

            $template->setValue('nb_projet', str_pad($total_exemplaires_projet, 2, '0', STR_PAD_LEFT));
            $template->setValue('titre_annee', $texte_titre); 
            $template->setValue('phrase_titre', $liste_annees);
            $template->setValue('phrase_durant', $liste_annees);
            
            $template->setValue('nom', htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
            $template->setValue('im', $im);
            $template->setValue('corps', $agent['corps_actuel'] ?? '');
            $template->setValue('grade', $agent['grade_actuel'] ?? '');
            $template->setValue('indice', $agent['indice_actuel'] ?? '');
            $template->setValue('chapitre', $agent['imput_budg'] ?? '');
            $template->setValue('date_naiss', !empty($agent['date_naiss']) ? date('d/m/Y', strtotime($agent['date_naiss'])) : '');
            $template->setValue('lieu_naiss', htmlspecialchars($agent['lieu_naiss'] ?? ''));
            $template->setValue('cin', $agent['cin'] ?? '');
            $template->setValue('date_cin', !empty($agent['date_cin']) ? date('d/m/Y', strtotime($agent['date_cin'])) : '');
            $template->setValue('lieu_cin', htmlspecialchars($agent['lieu_cin'] ?? ''));
            $template->setValue('date_entree_admin', !empty($agent['date_entree_admin']) ? date('d/m/Y', strtotime($agent['date_entree_admin'])) : '');
            $template->setValue('date_du_jour', date('d/m/Y'));

            if ($type_etablissement == "MEN CENTRAL") {
                $template->setValue('type_direction', "DIRECTION DES RESSOURCES HUMAINES");
                $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION ADMINISTRATIVE DU PERSONNEL");
                $template->setValue('chef_signataire', "Chef de Service de la Gestion Administrative du Personnel, de la Direction des Ressources Humaines");
                $template->setValue('nom_region', '');
                $template->setValue('nom_district', '');
                $template->setValue('en_service', $nom_direction);
                $template->setValue('lieu_signature', 'Antananarivo');
                $template->setValue('nom_region_dem', 'Analamanga');
                $template->setValue('adresser_a', $adresser_a_val);
                $template->setValue('chef_lieu_region', $chef_lieu_region_val);
            } else if ($type_etablissement == "DREN") {
                $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
                $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
                $template->setValue('chef_signataire', "Chef de Service de la Gestion des Ressources Humaines, de la Direction Régionale de l’Education Nationale de ");
                $template->setValue('nom_region', ucfirst(mb_strtolower($PosteActuel['nom_region'] ?? '')));
                $template->setValue('nom_district', '');
                $template->setValue('en_service', "DREN ".$nom_region);
                $template->setValue('lieu_signature', $chef_lieu_region); 
                $template->setValue('nom_region_dem', $nom_region);
                $template->setValue('adresser_a', $adresser_a_val);
                $template->setValue('chef_lieu_region', $chef_lieu_region_val);
            } else if ($type_etablissement == "CISCO") {
                $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
                $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
                $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
                $template->setValue('nom_region', '');
                $template->setValue('nom_district', $chef_lieu_district);
                $template->setValue('en_service', "CISCO ".$nom_district);
                $template->setValue('lieu_signature', $chef_lieu_district); 
                $template->setValue('nom_region_dem', $nom_region);
                $template->setValue('adresser_a', $adresser_a_val);
                $template->setValue('chef_lieu_region', $chef_lieu_region_val);
            } else if ($type_etablissement == "CRFRP") {
                $template->setValue('type_direction', "DIRECTION GENERALE DE L’INSTITUT NATIONAL" .$br. "DE FORMATION PEDAGOGIQUE");
                $template->setValue('sgrh_cisco_crfrp', "CENTRE REGIONAL DE FORMATION" .$br. "ET DE RECHERCHE PEDAGOGIQUE ".$nom_crfrp);
                $template->setValue('chef_signataire', "Chef de Centre Régional de Formation et de Recherche Pédagogique de ".ucfirst(mb_strtolower($nom_crfrp, 'UTF-8')));
                $template->setValue('nom_region', '');
                $template->setValue('nom_district', '');
                $template->setValue('en_service', $nom_etablissement);
                $template->setValue('lieu_signature', $chef_lieu_district); 
                $template->setValue('nom_region_dem', $nom_region);
                $template->setValue('adresser_a', $adresser_a_val);
                $template->setValue('chef_lieu_region', $chef_lieu_region_val);
            } else {
                $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
                $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
                $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
                $template->setValue('nom_region', '');
                $template->setValue('nom_district', $chef_lieu_district);
                $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
                $template->setValue('lieu_signature', $chef_lieu_district); 
                $template->setValue('nom_region_dem', $nom_region);
                $template->setValue('adresser_a', $adresser_a_val);
                $template->setValue('chef_lieu_region', $chef_lieu_region_val);
            }
        }

        $annees_str = implode('-', $years_array);
        if ($mode === 'decision') {
            $fileName = "Decision_conge_" . $im . "_" . $annees_str . ".docx";
        } else {
            $fileName = "Pieces_demande_conge_" . $im . ".docx";
        }
        
        if (ob_get_length()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $template->saveAs('php://output');
        exit;

    } catch (Exception $e) {
        if (ob_get_length()) ob_end_clean();
        die("Erreur de génération : " . $e->getMessage());
    }
}
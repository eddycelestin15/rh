<?php
// Tampon de sortie : evite qu'un avertissement PHP ne corrompe le .docx
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
use PhpOffice\PhpWord\TemplateProcessor;

if (isset($_GET['im'])) {
    $im = $_GET['im'];

    try {
        $sql = "SELECT ec.*, sa.* FROM personnel_etat_civil ec 
                JOIN personnel_situation_actuelle sa ON ec.im = sa.im 
                WHERE ec.im = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$im]);
        $agent = $stmt->fetch();

        $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
        $stmtPosteActuel->execute([$im]);
        $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            die("Agent introuvable ou situation non renseignée.");
        }

        $templateFile = APP_ROOT . '/pieces/AdmissionRetraite/demande_admission.docx';

        if (!file_exists($templateFile)) erreur_gabarit_absent($templateFile);
        $template = new TemplateProcessor($templateFile);

        $br = '</w:t><w:br/><w:t>';
        $lieu_signature = ucfirst(mb_strtolower($PosteActuel['nom_district'] ?? ''));
        $residant = mb_strtoupper($PosteActuel['nom_district'] ?? '', 'UTF-8');
        $type_fonction = $PosteActuel['type_fonction'];
        $type_etablissement = $PosteActuel['type_etablissement'];
        $type_direction = $PosteActuel['type_direction']; 
        $nom_direction = $PosteActuel['nom_direction'] ?? '';
        $nom_service = $PosteActuel['nom_service'];
        $nom_division = $PosteActuel['nom_division'];
        $nom_region = $PosteActuel['nom_region']; 
        $nom_district = $PosteActuel['nom_district']; 
        $nom_zap = $PosteActuel['nom_zap'];
        $nom_etablissement = $PosteActuel['nom_etablissement'];
        $sexe = $agent['sexe'];
            
        if ($sexe=="Masculin"){
            $template->setValue('nee_le', "Né le");
            $template->setValue('interessee', "L'intéressé");
        } else if ($sexe=="Féminin"){
            $template->setValue('nee_le', "Née le");
            $template->setValue('interessee', "L'intéressée");
        }  

        $ville = 'Mananjary'; 
        if (!empty($nom_region)) {
            $stmt = $pdo->prepare("SELECT chef_lieu_region FROM ref_regions WHERE nom_region = :nom_region LIMIT 1");
            $stmt->execute(['nom_region' => $nom_region]);
            $region_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($region_data && !empty($region_data['chef_lieu_region'])) {
                $ville = $region_data['chef_lieu_region'];
            }
        }

        $nom_crfrp_propre = $PosteActuel['nom_etablissement'] ?? '';
        if (stripos($nom_crfrp_propre, 'CRFRP') !== false) {
            $nom_crfrp_propre = trim(preg_replace('/^CRFRP\s+/i', '', $nom_crfrp_propre));
        }
        $nom_crfrp = $nom_crfrp_propre;

        // Initialisation et récupération des informations régionales et de district
        $chef_lieu_region = '';
        $chef_lieu_district = '';

        // Récupération de chef_lieu_region depuis ref_regions
        if (!empty($nom_region)) {
            $stmtReg = $pdo->prepare("SELECT chef_lieu_region FROM ref_regions WHERE nom_region = :nom_region LIMIT 1");
            $stmtReg->execute(['nom_region' => $nom_region]);
            $region_data = $stmtReg->fetch(PDO::FETCH_ASSOC);
            if ($region_data && !empty($region_data['chef_lieu_region'])) {
                $chef_lieu_region = $region_data['chef_lieu_region'];
            }
        }

        // Récupération de chef_lieu_district depuis ref_districts
        if (!empty($nom_district)) {
            $stmtDist = $pdo->prepare("SELECT chef_lieu_district FROM ref_districts WHERE nom_district = :nom_district LIMIT 1");
            $stmtDist->execute(['nom_district' => $nom_district]);
            $district_data = $stmtDist->fetch(PDO::FETCH_ASSOC);
            if ($district_data && !empty($district_data['chef_lieu_district'])) {
                $chef_lieu_district = $district_data['chef_lieu_district'];
            }
        }

        $chef_lieu_region_val = mb_strtoupper($chef_lieu_region, 'UTF-8');

        if ($type_etablissement =="MEN CENTRAL") {
            $template->setValue('type_direction', "DIRECTION DES RESSOURCES HUMAINES");
            $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION ADMINISTRATIVE DU PERSONNEL");
            $template->setValue('chef_signataire', "Chef de Service de la Gestion Administrative du Personnel, de la Direction des Ressources Humaines");
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', '');
            $template->setValue('en_service', $nom_direction);
            $template->setValue('lieu_signature', 'Antananarivo');
        } else if ($type_etablissement =="DREN") {
            $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
            $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
            $template->setValue('chef_signataire', "Chef de Service de la Gestion des Ressources Humaines, de la Direction Régionale de l’Education Nationale de ");
            $template->setValue('nom_region', ucfirst(mb_strtolower($PosteActuel['nom_region'] ?? '')));
            $template->setValue('nom_district', '');
            $template->setValue('en_service', "DREN ".$nom_region);
            $template->setValue('lieu_signature', ucfirst(mb_strtolower($ville, 'UTF-8')));
        } else if ($type_etablissement =="CISCO") {
            $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
            $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
            $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', $chef_lieu_district);
            $template->setValue('en_service', "CISCO ".$nom_district);
            $template->setValue('lieu_signature', $chef_lieu_district); 
        } else if ($type_etablissement =="CRFRP") {
            $template->setValue('type_direction', "DIRECTION GENERALE DE L’INSTITUT NATIONAL" .$br. "DE FORMATION PEDAGOGIQUE");
            $template->setValue('sgrh_cisco_crfrp', "CENTRE REGIONAL DE FORMATION" .$br. "ET DE RECHERCHE PEDAGOGIQUE ".$nom_crfrp);
            $template->setValue('chef_signataire', "Chef de Centre  Régional de Formation et de Recherche Pédagogique de ".ucfirst(mb_strtolower($nom_crfrp, 'UTF-8')));
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', '');
            $template->setValue('en_service', $nom_etablissement);
            $template->setValue('lieu_signature', $chef_lieu_district); 
        } else if ($type_etablissement == "LYCEE" || $type_etablissement == "COLLEGE") {
            $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
            $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
            $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', $chef_lieu_district);
            $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
            $template->setValue('lieu_signature', $chef_lieu_district); 
        } else if ($type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE") {
            $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
            $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
            $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', $chef_lieu_district);
            $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
            $template->setValue('lieu_signature', $chef_lieu_district); 
        }

        // --- CALCUL DE LA DATE DE RETRAITE (60 ANS) ---
        $dateRetraiteFormatted = '';
        if (!empty($agent['date_naiss'])) {
            $dtNaiss = new DateTime($agent['date_naiss']);
            $dtRetraite = (clone $dtNaiss)->modify('+60 years');
            $dateRetraiteFormatted = $dtRetraite->format('d/m/Y');
        }

        $genre_input = $_GET['genre'] ?? 'Mr';
        $genre_maj = ($genre_input === 'Mme') ? 'MADAME' : 'MONSIEUR';
        $genre_min = ($genre_input === 'Mme') ? 'Madame' : 'Monsieur';
        $template->setValue('genre_min', $genre_min);
        $template->setValue('genre_maj', $genre_maj);
        $template->setValue('nom_complet', htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
        $template->setValue('im', $im);
        $template->setValue('indice', $agent['indice_actuel']);
        $template->setValue('corps', $agent['corps_actuel']);
        $template->setValue('grade', $agent['grade_actuel']);
        $template->setValue('imputation', $agent['imput_budg']);
        $template->setValue('date_naiss', date('d/m/Y', strtotime($agent['date_naiss'])));
        $template->setValue('date_retraite', $dateRetraiteFormatted); 
        $template->setValue('lieu_naiss', $agent['lieu_naiss']);
        $template->setValue('cin', $agent['cin']);
        $template->setValue('date_cin', date('d/m/Y', strtotime($agent['date_cin'])));
        $template->setValue('lieu_cin', $agent['lieu_cin']);
        $template->setValue('indice', $agent['indice_actuel']);
        $template->setValue('date_entree_admin', date('d/m/Y', strtotime($agent['date_entree_admin'])));
        $template->setValue('chapitre', $agent['chap_budg'] ?? '');

        $fileName = "Demande_Admission_Retraite" . $im . ".docx";
        if (ob_get_length()) ob_clean();
        vider_tampon_sortie();
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $template->saveAs('php://output');
        exit;

    } catch (Exception $e) {
        die("Erreur lors de la génération : " . $e->getMessage());
    }
} else {
    die("Numéro matricule (IM) manquant.");
}
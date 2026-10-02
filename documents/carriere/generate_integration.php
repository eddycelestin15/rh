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
        // 1. Récupération des données combinées (État Civil + Situation Actuelle)
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

        $templateFile = APP_ROOT . '/pieces/Integration/Integration.docx';

        if (!file_exists($templateFile)) erreur_gabarit_absent($templateFile);
        $template = new TemplateProcessor($templateFile);

        $br = '</w:t><w:br/><w:t>';
        $lieu_signature = ucfirst(mb_strtolower($PosteActuel['nom_district'] ?? ''));
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
        $situation_familiale = $agent['situation_familiale'];

        if ($type_fonction =="Personnel administratif"){
            $template->setValue('fonction', "PERSONNEL ADMINISTRATIF");
        } else if($type_fonction =="Personnel enseignant" && $type_etablissement =="CRFRP"){
            $template->setValue('au_a_la', "au");
            $template->setValue('fonction', "FORMATEUR");
        }
            
        if ($sexe=="Masculin"){
            $template->setValue('mr_mme_mlle', "Mr");
            $template->setValue('nee_le', "Né le");
            $template->setValue('interessee', "L'intéressé");
            if($type_fonction =="Personnel enseignant" && ($type_etablissement =="LYCEE" || $type_etablissement =="COLLEGE")){
                $template->setValue('au_a_la', "au");    
                $template->setValue('fonction', "ENSEIGNANT");
            } else if($type_fonction =="Personnel enseignant" && ($type_etablissement =="PRIMAIRE")){
                $template->setValue('au_a_la', "à l");    
                $template->setValue('fonction', "ENSEIGNANT");
            } else if($type_fonction =="Personnel enseignant" && ($type_etablissement =="PRESCOLAIRE")){
                $template->setValue('au_a_la', "à l");    
                $template->setValue('fonction', "EDUCATEUR");
            }
        } else if ($sexe=="Féminin"){
            $template->setValue('nee_le', "Née le");
            $template->setValue('interessee', "L'intéressée");
            if($type_fonction =="Personnel enseignant" && ($type_etablissement =="LYCEE" || $type_etablissement =="COLLEGE")){
                $template->setValue('au_a_la', "au");    
                $template->setValue('fonction', "ENSEIGNANTE");
            } else if($type_fonction =="Personnel enseignant" && ($type_etablissement =="PRIMAIRE")){
                $template->setValue('au_a_la', "à l");    
                $template->setValue('fonction', "ENSEIGNANTE");
            } else if($type_fonction =="Personnel enseignant" && ($type_etablissement =="PRESCOLAIRE")){
                $template->setValue('au_a_la', "à l");    
                $template->setValue('fonction', "EDUCATRICE");
            }
        }         

        if ($situation_familiale =="Marié(e)" && $sexe=="Masculin"){
            $template->setValue('sit_familiale', "MARIE");
        } else if ($situation_familiale =="Marié(e)" && $sexe=="Féminin"){
            $template->setValue('sit_familiale', "MARIEE");
            $template->setValue('mr_mme_mlle', "Mme");
        } else if ($situation_familiale =="Célibataire" && $sexe=="Féminin"){
            $template->setValue('sit_familiale', "Mlle");
        }

        $nom_district_clean = trim($nom_district ?? '');
        $nom_district_clean = preg_replace('/\s+[\dIVX]+\s*$/i', '', $nom_district_clean);
        $nom_lower = mb_strtolower($nom_district_clean, 'UTF-8');        
        $nom_lower = preg_replace_callback('/\s+(i{1,3}|iv|v?ii?)\b/i', function($matches) {
            return ' ' . strtoupper($matches[1]);
        }, $nom_lower);
        $lieu_signature = ucfirst($nom_lower);
        $lieu_signature_sans_chiffre = ucfirst(mb_strtolower($nom_district_clean, 'UTF-8'));        

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

        // 1. Initialisation et récupération des informations régionales et de district
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

        $premiere_lettre = mb_substr(ltrim($chef_lieu_region), 0, 1, 'UTF-8');
        $est_voyelle = preg_match('/^[AEIOUYÀÁÂÃÄÅÆÈÉÊËÌÍÎÏÒÓÔÕÖØÙÚÛÜ]/ui', $premiere_lettre);

        $prefecture_val = $est_voyelle ? "PREFECTURE D'" . mb_strtoupper($chef_lieu_region, 'UTF-8') : "PREFECTURE DE " . mb_strtoupper($chef_lieu_region, 'UTF-8');
        $adresser_a_val = $est_voyelle ? "LE PREFET D'" : "LE PREFET DE ";
        $chef_lieu_region_val = mb_strtoupper($chef_lieu_region, 'UTF-8');

        if ($type_etablissement =="MEN CENTRAL") {
            $template->setValue('type_direction', "DIRECTION DES RESSOURCES HUMAINES");
            $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION ADMINISTRATIVE DU PERSONNEL");
            $template->setValue('chef_signataire', "Chef de Service de la Gestion Administrative du Personnel, de la Direction des Ressources Humaines");
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', '');
            $template->setValue('au_a_la', "au");
            $template->setValue('en_service', $nom_direction);
            $template->setValue('lieu_signature', 'Antananarivo');
            $template->setValue('residant', 'Antananarivo');
            $template->setValue('prefecture', '');
            $template->setValue('adresser_a', 'LE DIRECTEUR DES RESSOURCES HUMAINES');
            $template->setValue('chef_lieu_region', '');
            $template->setValue('lieu_destinataire', '= ANTANANARIVO =');
            $template->setValue('titre_signataire', 'Le Directeur');
            $template->setValue('signature_contrat', 'Antananarivo');
        } else if ($type_etablissement =="DREN") {
            $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
            $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
            $template->setValue('chef_signataire', "Chef de Service de la Gestion des Ressources Humaines, de la Direction Régionale de l’Education Nationale de ");
            $template->setValue('nom_region', ucfirst(mb_strtolower($PosteActuel['nom_region'] ?? '')));
            $template->setValue('nom_district', '');
            $template->setValue('au_a_la', "au");
            $template->setValue('en_service', "DREN ".$nom_region);
            $template->setValue('residant', mb_strtoupper($ville, 'UTF-8'));
            $template->setValue('lieu_destinataire', '');
            $template->setValue('titre_signataire', 'Le Préfet');
            $template->setValue('signature_contrat', ucfirst(mb_strtolower($ville, 'UTF-8')));
            $template->setValue('lieu_signature', $chef_lieu_region); 
            $template->setValue('prefecture', $prefecture_val);
            $template->setValue('adresser_a', $adresser_a_val);
            $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        } else if ($type_etablissement =="CISCO") {
            $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
            $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
            $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', $chef_lieu_district);
            $template->setValue('au_a_la', "au ");
            $template->setValue('en_service', "CISCO ".$nom_district);
            $template->setValue('residant', mb_strtoupper($nom_district, 'UTF-8'));
            $template->setValue('lieu_destinataire', '');
            $template->setValue('titre_signataire', 'Le Préfet');
            $template->setValue('signature_contrat', ucfirst(mb_strtolower($ville, 'UTF-8')));
            $template->setValue('lieu_signature', $chef_lieu_district); 
            $template->setValue('prefecture', $prefecture_val);
            $template->setValue('adresser_a', $adresser_a_val);
            $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        } else if ($type_etablissement =="CRFRP") {
            $template->setValue('type_direction', "DIRECTION GENERALE DE L’INSTITUT NATIONAL" .$br. "DE FORMATION PEDAGOGIQUE");
            $template->setValue('sgrh_cisco_crfrp', "CENTRE REGIONAL DE FORMATION" .$br. "ET DE RECHERCHE PEDAGOGIQUE ".$nom_crfrp);
            $template->setValue('chef_signataire', "Chef de Centre  Régional de Formation et de Recherche Pédagogique de ".ucfirst(mb_strtolower($nom_crfrp, 'UTF-8')));
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', '');
            $template->setValue('au_a_la', "au ");
            $template->setValue('en_service', $nom_etablissement);
            $template->setValue('residant', mb_strtoupper($nom_district, 'UTF-8'));
            $template->setValue('lieu_destinataire', '');
            $template->setValue('titre_signataire', 'Le Préfet');
            $template->setValue('signature_contrat', ucfirst(mb_strtolower($ville, 'UTF-8')));
            $template->setValue('lieu_signature', $chef_lieu_district); 
            $template->setValue('prefecture', $prefecture_val);
            $template->setValue('adresser_a', $adresser_a_val);
            $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        } else if ($type_etablissement == "LYCEE" || $type_etablissement == "COLLEGE") {
            $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
            $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
            $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', $chef_lieu_district);
            $template->setValue('au_a_la', "au ");
            $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
            $template->setValue('residant', mb_strtoupper($nom_district, 'UTF-8'));
            $template->setValue('lieu_destinataire', '');
            $template->setValue('titre_signataire', 'Le Préfet');
            $template->setValue('signature_contrat', ucfirst(mb_strtolower($ville, 'UTF-8')));
            $template->setValue('lieu_signature', $chef_lieu_district); 
            $template->setValue('prefecture', $prefecture_val);
            $template->setValue('adresser_a', $adresser_a_val);
            $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        } else if ($type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE") {
            $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
            $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
            $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
            $template->setValue('nom_region', '');
            $template->setValue('nom_district', $chef_lieu_district);
            $template->setValue('au_a_la', "à l'");
            $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
            $template->setValue('residant', mb_strtoupper($nom_district, 'UTF-8'));
            $template->setValue('lieu_destinataire', '');
            $template->setValue('titre_signataire', 'Le Préfet');
            $template->setValue('signature_contrat', ucfirst(mb_strtolower($ville, 'UTF-8')));
            $template->setValue('lieu_signature', $chef_lieu_district); 
            $template->setValue('prefecture', $prefecture_val);
            $template->setValue('adresser_a', $adresser_a_val);
            $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        }

        $template->setImageValue('embleme', [
            'path' => APP_ROOT . '/Logo/Embleme.png', 
            'width' => 100,  
            'height' => 50, 
            'ratio' => true
        ]);
        $template->setImageValue('logo_men', [
            'path' => APP_ROOT . '/Logo/Logo_men.png', 
            'width' => 100,  
            'height' => 50, 
            'ratio' => true
        ]);

        $genre_input = $_GET['genre'] ?? 'Mr';
        $genre_maj = ($genre_input === 'Mme') ? 'MADAME' : 'MONSIEUR';
        $genre_min = ($genre_input === 'Mme') ? 'Madame' : 'Monsieur';
        $template->setValue('genre_min', $genre_min);
        $template->setValue('genre_maj', $genre_maj);

        $template->setValue('nom', htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
        $template->setValue('im', $agent['im']);
        $template->setValue('date_naiss', date('d/m/Y', strtotime($agent['date_naiss'])));
        $template->setValue('lieu_naiss', $agent['lieu_naiss']);
        $template->setValue('cin', $agent['cin']);
        $template->setValue('date_cin', date('d/m/Y', strtotime($agent['date_cin'])));
        $template->setValue('lieu_cin', $agent['lieu_cin']);
        $template->setValue('num_tel', $agent['num_tel']);

        // 4. Remplissage de la Situation EFA (Dernier Avancement)
        $template->setValue('corps_efa', $agent['corps_actuel']);
        $template->setValue('grade_efa', $agent['grade_actuel']);
        $template->setValue('indice_efa', $agent['indice_actuel']);
        $template->setValue('categorie', $agent['categorie_actuel']);
        $template->setValue('chapitre', $agent['chap_budg']);
        $template->setValue('imputation', $agent['imput_budg']);
        
        // Dates clés
        $template->setValue('date_entree_admin', date('d/m/Y', strtotime($agent['date_entree_admin'])));
        $template->setValue('date_effet_actuel', date('d/m/Y', strtotime($agent['date_d_effet_actuel'])));
        
        // Références de l'acte actuel (Dernier avancement/contrat)
        $template->setValue('num_acte', $agent['num_acte_actuel']);
        $template->setValue('date_acte', date('d/m/Y', strtotime($agent['date_acte_actuel'])));        

        // 6. Génération et téléchargement
        $fileName = "Integration_" . $im . ".docx";
        if (ob_get_length()) ob_clean();
        // Empeche toute sortie parasite de corrompre le fichier
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
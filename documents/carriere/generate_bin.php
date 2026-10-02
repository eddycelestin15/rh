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
        $stmt = $pdo->prepare("SELECT ec.*, sa.* FROM personnel_etat_civil ec JOIN personnel_situation_actuelle sa ON ec.im = sa.im WHERE ec.im = ?");
        $stmt->execute([$im]);
        $agent = $stmt->fetch();

        if (!$agent) die("Agent introuvable.");

        $stmtDiplome = $pdo->prepare(" SELECT libelle, specialite FROM personnel_diplomes WHERE im = ? ORDER BY annee DESC LIMIT 1");
        $stmtDiplome->execute([$im]);
        $diplome_plus_recent = $stmtDiplome->fetch(PDO::FETCH_ASSOC);
        
        $stmtCompetence = $pdo->prepare("SELECT aptitudes_speciales FROM personnel_competences WHERE im = ?");
        $stmtCompetence->execute([$im]);
        $aptitude_speciale = $stmtCompetence->fetchColumn() ?: '-';

        $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
        $stmtPosteActuel->execute([$im]);
        $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

        $stmtAvancements = $pdo->prepare("SELECT * FROM bin_agent WHERE im_bin = ? ORDER BY date_acte_bin ASC");
        $stmtAvancements->execute([$im]);
        $avancements = $stmtAvancements->fetchAll(PDO::FETCH_ASSOC);

        $stmtDistinctions = $pdo->prepare("SELECT * FROM personnel_distinctions WHERE im = ? ORDER BY date_acte ASC");
        $stmtDistinctions->execute([$im]);
        $distinctions = $stmtDistinctions->fetchAll(PDO::FETCH_ASSOC);

        $template = new TemplateProcessor(APP_ROOT . '/pieces/rectoVerso/BIN.docx');            

        $premier_avancement = '';
        $avancements_successifs = '';
        $br = '</w:t><w:br/><w:t>'; 

        if (!empty($avancements)) {
            $lignes = [];
            foreach ($avancements as $av) {
                $date_acte = date('d/m/Y', strtotime($av['date_acte_bin']));
                $lignes[] = htmlspecialchars($av['corps_bin'] . ", " . $av['grade_bin'] . ", " . $av['type_acte_bin'] . " N° " . $av['numero_acte_bin'] . " du " . $date_acte);
            }
            $premier_avancement = array_shift($lignes);
            if (!empty($lignes)) {
                $avancements_successifs = implode($br, $lignes);
            } else {
                $avancements_successifs = '-'; 
            }
        } else {
            $premier_avancement = "-";
            $avancements_successifs = "-";
        }

        $premier_distinction = '';
        $autres_distinctions = '';
        if (!empty($distinctions)) {
            $lignes = [];
            foreach ($distinctions as $dist) {
                $date_acte = date('d/m/Y', strtotime($dist['date_acte']));
                $lignes[] = htmlspecialchars($dist['nom_grade'] . " de l'" . $dist['type_grade'] . ", Decret N°" . $dist['num_acte'] . " du " . $date_acte);
            }
            $premier_distinction = array_shift($lignes);
            if (!empty($lignes)) {
                $autres_distinctions = implode($br, $lignes);
            } else {
                $autres_distinctions = '-'; 
            }
        } else {
            $premier_distinction = "-";
            $autres_distinctions = "-";
        }

        $br = '</w:t><w:br/><w:t>';
        $type_fonction = $PosteActuel['type_fonction'];
        $type_etablissement = $PosteActuel['type_etablissement'];
        $type_direction = $PosteActuel['type_direction'];
        $nom_direction = $PosteActuel['nom_direction']; 
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
            $template->setValue('fonction', "FORMATEUR");
        }
            
        if ($sexe=="Masculin"){
            $template->setValue('mr_mme_mlle', "Mr ");
            $template->setValue('nee_le', "Né le");
            $template->setValue('interessee', "L'intéressé");
            if($type_fonction =="Personnel enseignant" && ($type_etablissement =="LYCEE" || $type_etablissement =="COLLEGE")){
                $template->setValue('fonction', "ENSEIGNANT");
            } else if($type_fonction =="Personnel enseignant" && ($type_etablissement =="PRIMAIRE")){
                $template->setValue('fonction', "ENSEIGNANT");
            } else if($type_fonction =="Personnel enseignant" && ($type_etablissement =="PRESCOLAIRE")){
                $template->setValue('fonction', "EDUCATEUR");
            }
        } else if ($sexe=="Féminin"){
            $template->setValue('nee_le', "Née le");
            $template->setValue('interessee', "L'intéressée");
            if($type_fonction =="Personnel enseignant" && ($type_etablissement =="LYCEE" || $type_etablissement =="COLLEGE")){
                $template->setValue('fonction', "ENSEIGNANTE");
            } else if($type_fonction =="Personnel enseignant" && ($type_etablissement =="PRIMAIRE")){
                $template->setValue('fonction', "ENSEIGNANTE");
            } else if($type_fonction =="Personnel enseignant" && ($type_etablissement =="PRESCOLAIRE")){
                $template->setValue('fonction', "EDUCATRICE");
            }
        }         

        if ($situation_familiale =="Marié(e)" && $sexe=="Masculin"){
            $template->setValue('sit_familiale', "MARIE");
        } else if ($situation_familiale =="Marié(e)" && $sexe=="Féminin"){
            $template->setValue('sit_familiale', "MARIEE");
            $template->setValue('mr_mme_mlle', "Mme ");
        } else if ($situation_familiale =="Célibataire" && $sexe=="Féminin"){
            $template->setValue('sit_familiale', "CELIBATAIRE");
            $template->setValue('mr_mme_mlle', "Mlle ");
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
            $template->setValue('au_a_la', "au");
            $template->setValue('en_service', $nom_direction);
        } else if ($type_etablissement =="DREN") {
            $template->setValue('au_a_la', "au");
            $template->setValue('en_service', "DREN ".$nom_region);
        } else if ($type_etablissement =="CISCO") {
            $template->setValue('au_a_la', "au ");
            $template->setValue('en_service', "CISCO ".$nom_district);
        } else if ($type_etablissement =="CRFRP") {
            $template->setValue('au_a_la', "au ");
            $template->setValue('en_service', $nom_etablissement);
        } else if ($type_etablissement == "LYCEE" || $type_etablissement == "COLLEGE") {
            $template->setValue('au_a_la', "au ");
            $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
        } else if ($type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE") {
            $template->setValue('au_a_la', "à l'");
            $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
        }

        $template->setValue('annee', date('Y'));
        $template->setValue('nom', htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
        $template->setValue('im', $im);
        $template->setValue('date_naiss', date('d/m/Y', strtotime($agent['date_naiss'])));
        $template->setValue('lieu_naiss', $agent['lieu_naiss']);
        $template->setValue('situation_familiale', $agent['situation_familiale']);
        $template->setValue('nbr_enfant', $agent['nbr_enfant']);
        $template->setValue('adresse', $agent['adresse']);
        $template->setValue('corps_actuel', $agent['corps_actuel']);
        $template->setValue('grade_actuel', $agent['grade_actuel']);
        $template->setValue('date_d_effet_actuel', date('d/m/Y', strtotime($agent['date_d_effet_actuel'])));
        $template->setValue('num_acte_actuel', $agent['num_acte_actuel']);
        $template->setValue('date_acte_actuel', date('d/m/Y', strtotime($agent['date_acte_actuel'])));
        $template->setValue('date_entree_admin', date('d/m/Y', strtotime($agent['date_entree_admin'])));
        $template->setValue('premier_avancement', $premier_avancement);
        $template->setValue('avancements_successifs', $avancements_successifs);
        $template->setValue('diplomes', htmlspecialchars($diplome_plus_recent['libelle'] ?? '-'));
        $template->setValue('specialites', htmlspecialchars($diplome_plus_recent['specialite'] ?? '-'));
        $template->setValue('premier_distinction', $premier_distinction);
        $template->setValue('autres_distinctions', $autres_distinctions);
        $template->setValue('aptitude_speciale', htmlspecialchars($aptitude_speciale));
        $template->setValue('nom_complet_secours', htmlspecialchars($agent['nom_secours'] . ' ' . $agent['prenoms_secours']) ?? '-');
        $template->setValue('adresse_secours', $agent['adresse_secours'] ?? '-');
        $template->setValue('tel_secours', $agent['tel_secours'] ?? '-');

        $fileName = "BIN_" . $im . ".docx";

        if (ob_get_length()) ob_clean();
        // Empeche toute sortie parasite de corrompre le fichier
        vider_tampon_sortie();
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $template->saveAs('php://output');
        exit;

    } catch (Exception $e) {
        die("Erreur : " . $e->getMessage());
    }
?>
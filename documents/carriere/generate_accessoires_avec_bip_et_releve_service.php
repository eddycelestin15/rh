<?php
// Tampon de sortie : evite qu'un avertissement PHP ne corrompe le .docx
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

use PhpOffice\PhpWord\TemplateProcessor;

$im = $_SESSION['user_im'];
$type_doc = $_POST['type_document'] ?? '';

if (empty($type_doc)) {
    die("Veuillez choisir un type de document.");
}

try {
    // 1. Définition du template et du nom de fichier selon le choix
    $templateFile = '';
    $outputName = '';

    switch ($type_doc) {
        case 'certificat_administratif':
            $templateFile = APP_ROOT . '/pieces/Accessoires/certificat_administratif.docx';
            $outputName = "Certificat_Admin_$im.docx";
            break;
        
        case 'bip':
            $templateFile = APP_ROOT . '/pieces/Accessoires/BIP.docx';
            $outputName = "BIP_$im.docx";
            break;

        case 'releve_service':
            $templateFile = APP_ROOT . '/pieces/Accessoires/releve_de_service.docx';
            $outputName = "Releve_Service_$im.docx";
            break;

        case 'attestation_non_interruption':
            $templateFile = APP_ROOT . '/pieces/Accessoires/attestation_non_interruption.docx';
            $outputName = "Attestation_Service_$im.docx";
            break;

        default:
            die("Modèle non reconnu.");
    }

    // 2. Vérifier si le fichier template existe physiquement
    if (!file_exists($templateFile)) {
        die("Erreur : Le fichier modèle $templateFile est introuvable.");
    }

    // 3. Initialiser le processeur avec le template choisi
    $template = new TemplateProcessor($templateFile);

    if ($type_doc === 'bip') {      
        $photoPath = APP_ROOT . '/images/' . $im . ".jpg";
        if (file_exists($photoPath)) {
            $template->setImageValue('photo', [
                'path' => $photoPath, 'width' => 170, 'height' => 170, 'ratio' => false
            ]);
        }
        // 1. Début Etat Civil
        $stmtEtatCivil = $pdo->prepare("SELECT * FROM personnel_etat_civil WHERE im = ?");
        $stmtEtatCivil->execute([$im]);
        $etatCivil = $stmtEtatCivil->fetch(PDO::FETCH_ASSOC);

        $sexe = $etatCivil['sexe'];
        $coche = " X "; 
        $vide  = "   "; 
        if ($sexe == "Masculin") {
            $template->setValue('m', $coche);
            $template->setValue('f', $vide);
        } else {
            $template->setValue('m', $vide);
            $template->setValue('f', $coche);
        }   
        
        $sit_fam = $etatCivil['situation_familiale']; 
        $coche = " X "; 
        $vide  = "   "; 
        if ($sit_fam == "Célibataire") {
            $template->setValue('C', $coche);
            $template->setValue('M', $vide);
            $template->setValue('D', $vide);
            $template->setValue('V', $vide);
        } else if ($sit_fam == "Marié(e)") {
            $template->setValue('C', $vide);
            $template->setValue('M', $coche);
            $template->setValue('D', $vide);
            $template->setValue('V', $vide);
        } else if ($sit_fam == "Divorcé(e)") {
            $template->setValue('C', $vide);
            $template->setValue('M', $vide);
            $template->setValue('D', $coche);
            $template->setValue('V', $vide);
        } else {
            $template->setValue('C', $vide);
            $template->setValue('M', $vide);
            $template->setValue('D', $vide);
            $template->setValue('V', $coche);
        }

        if ($etatCivil) {
            $template->setValue('cin', strtoupper($etatCivil['cin']));
            $template->setValue('date_cin', date('d/m/Y', strtotime($etatCivil['date_cin'])));
            $template->setValue('lieu_cin', strtoupper($etatCivil['lieu_cin']));
            $template->setValue('im', $im);
            $template->setValue('nom', strtoupper($etatCivil['nom']));
            $template->setValue('prenoms', strtoupper($etatCivil['prenoms']));
            $template->setValue('date_naiss', date('d/m/Y', strtotime($etatCivil['date_naiss'])));
            $template->setValue('lieu_naiss', strtoupper($etatCivil['lieu_naiss']));
            $template->setValue('adresse', strtoupper($etatCivil['adresse']));
            $template->setValue('num_tel', $etatCivil['num_tel']);
            $template->setValue('adress_mail', $etatCivil['adress_mail']);
            $template->setValue('nbr_enfant', $etatCivil['nbr_enfant']);
            $template->setValue('nom_conjoint', strtoupper($etatCivil['nom_conjoint']));
            $template->setValue('profession_conjoint', strtoupper($etatCivil['profession_conjoint']));
            $template->setValue('fonction_conjoint', strtoupper($etatCivil['fonction_conjoint']));
            $template->setValue('lieu_serv_conjoint', strtoupper($etatCivil['lieu_serv_conjoint']));
        }

        $br = '</w:t><w:br/><w:t>';
        $est_fonctionnaire = $etatCivil['est_fonctionnaire'];

        if ($est_fonctionnaire == "1") {
            $template->setValue('siconjointe', $br . 'Si conjoint(e) fonctionnaire');
            $template->setValue('matricule_conj', $br . 'Matricule : ');
            $template->setValue('im_conjoint', strtoupper($etatCivil['im_conjoint']));
            $template->setValue('corps_conj', $br . 'Corps : ');           
            $template->setValue('corps_conjoint', strtoupper($etatCivil['corps_conjoint']));
            $template->setValue('grade_conj', $br . 'Grade : ');
            $template->setValue('grade_conjoint', strtoupper($etatCivil['grade_conjoint']));
        } else {
            $template->setValue('siconjointe', '');
            $template->setValue('matricule_conj', '');
            $template->setValue('im_conjoint', '');
            $template->setValue('corps_conj', '');           
            $template->setValue('corps_conjoint', '');
            $template->setValue('grade_conj', '');
            $template->setValue('grade_conjoint', '');
        }

        // Fin Etat Civil
        // 2. Début Diplômes et compétences
        function remplirDiplomes($pdo, $im, $type, $prefix, &$template) {
            $stmt = $pdo->prepare("SELECT libelle, specialite, annee 
                                FROM personnel_diplomes 
                                WHERE im = :im AND type_diplome = :type 
                                ORDER BY annee DESC LIMIT 3");
            $stmt->execute([':im' => $im, ':type' => $type]);
            $diplomes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            for ($i = 1; $i <= 3; $i++) {
                $index = $i - 1;
                if (isset($diplomes[$index])) {
                    $template->setValue("d_{$prefix}{$i}", $diplomes[$index]['libelle']);
                    $template->setValue("s_{$prefix}{$i}", $diplomes[$index]['specialite']);
                    $template->setValue("a_{$prefix}{$i}", $diplomes[$index]['annee']);
                } else {
                    $template->setValue("d_{$prefix}{$i}", "");
                    $template->setValue("s_{$prefix}{$i}", "");
                    $template->setValue("a_{$prefix}{$i}", "");
                }
            }
        }
        remplirDiplomes($pdo, $im, 'acad', 'ac', $template);
        remplirDiplomes($pdo, $im, 'pedag', 'pe', $template);

        $stmtCompetences = $pdo->prepare("SELECT * FROM personnel_competences WHERE im = ?");
        $stmtCompetences->execute([$im]);
        $Competences = $stmtCompetences->fetch(PDO::FETCH_ASSOC);

        $info_bureautique = $Competences['info_bureautique'];
        if ($info_bureautique == 1) {
            $template->setValue('B', 'X'); 
        } else {
            $template->setValue('B', ' '); 
        }

        $info_programmation = $Competences['info_programmation'];
        if ($info_programmation == 1) {
            $template->setValue('P', 'X'); 
        } else {
            $template->setValue('P', ' '); 
        }

        $info_reseau = $Competences['info_reseau'];
        if ($info_reseau == 1) {
            $template->setValue('R', 'X'); 
        } else {
            $template->setValue('R', ' '); 
        }

        if (!empty($Competences['info_autres'])) {
            $template->setValue('A', 'X'); 
            $template->setValue('info_autres', $Competences['info_autres']); 
        } else {
            $template->setValue('A', ' ');
            $template->setValue('info_autres', ''); 
        }

        $langueFr = $Competences['langue_fr']; 
        $coche = " X "; 
        $vide  = "   "; 
        if ($langueFr == "Mauvais") {
            $template->setValue('FM', $coche);
            $template->setValue('FB', $vide);
            $template->setValue('FE', $vide);
        } else if ($langueFr == "Bon") {
            $template->setValue('FM', $vide);
            $template->setValue('FB', $coche);
            $template->setValue('FE', $vide);
        } else {
            $template->setValue('FM', $vide);
            $template->setValue('FB', $vide);
            $template->setValue('FE', $coche);
        }

        $langueAng = $Competences['langue_en']; 
        $coche = " X "; 
        $vide  = "   "; 
        if ($langueAng == "Mauvais") {
            $template->setValue('AM', $coche);
            $template->setValue('AB', $vide);
            $template->setValue('AE', $vide);
        } else if ($langueAng == "Bon") {
            $template->setValue('AM', $vide);
            $template->setValue('AB', $coche);
            $template->setValue('AE', $vide);
        } else {
            $template->setValue('AM', $vide);
            $template->setValue('AB', $vide);
            $template->setValue('AE', $coche);
        }
        $template->setValue('langue_autres', $Competences['langue_autres']);
        $stmtSituationActuelle = $pdo->prepare("SELECT * FROM personnel_situation_actuelle WHERE im = ?");
        $stmtSituationActuelle->execute([$im]);
        $SituationActuelle = $stmtSituationActuelle->fetch(PDO::FETCH_ASSOC);
        if ($SituationActuelle) {
            $template->setValue('num_acte_actuel', strtoupper($SituationActuelle['num_acte_actuel']));
            $template->setValue('date_acte_actuel', date('d/m/Y', strtotime($SituationActuelle['date_acte_actuel'])));
            $template->setValue('date_entree_admin', date('d/m/Y', strtotime($SituationActuelle['date_entree_admin'])));
            $template->setValue('statut_actuel', strtoupper($SituationActuelle['statut_actuel']));
            $template->setValue('code_cadre_actuel', strtoupper($SituationActuelle['code_corps_actuel']));
            $template->setValue('corps_actuel', strtoupper($SituationActuelle['corps_actuel']));
            $template->setValue('grade_actuel', strtoupper($SituationActuelle['grade_actuel']));
            $template->setValue('budget', strtoupper($SituationActuelle['budget']));
            $template->setValue('mode_paiement', strtoupper($SituationActuelle['mode_paiement']));
            $template->setValue('categorie_actuel', strtoupper($SituationActuelle['categorie_actuel']));
            $template->setValue('indice_actuel', strtoupper($SituationActuelle['indice_actuel']));
            $template->setValue('date_d_effet_actuel', date('d/m/Y', strtotime($SituationActuelle['date_d_effet_actuel'])));
            $template->setValue('chap_budg', strtoupper($SituationActuelle['chap_budg']));
            $template->setValue('imput_budg', strtoupper($SituationActuelle['imput_budg']));
            $template->setValue('num_fin_actuel', strtoupper($SituationActuelle['num_fin_actuel']));
            $template->setValue('date_fin_actuel', date('d/m/Y', strtotime($SituationActuelle['date_fin_actuel'])));
            $template->setValue('num_cde_actuel', strtoupper($SituationActuelle['num_cde_actuel']));            
            $template->setValue('date_cde_actuel', date('d/m/Y', strtotime($SituationActuelle['date_cde_actuel'])));
        }
        // Fin Situation actuelle
        // 4. Début Avancement successif
        $stmtAvancements = $pdo->prepare("SELECT * FROM personnel_avancements WHERE im = ? ORDER BY av_date_effet ASC");
        $stmtAvancements->execute([$im]);
        $Avancements = $stmtAvancements->fetchAll(PDO::FETCH_ASSOC);

        if (count($Avancements) > 0) {
            $template->cloneRow('ta', count($Avancements));
            foreach ($Avancements as $index => $row) {
                $i = $index + 1;

                // Fonction interne pour gérer les dates nulles proprement
                $formatDate = function($dateValue) {
                    return (!empty($dateValue) && $dateValue !== '0000-00-00') 
                        ? date('d/m/Y', strtotime($dateValue)) 
                        : ''; // Retourne vide si null ou 0000-00-00
                };

                $template->setValue('ta#' . $i, $row['av_type_avancement'] ?? '');
                $template->setValue('cp#' . $i, $row['av_corps'] ?? '');
                $template->setValue('gd#' . $i, $row['av_grade'] ?? '');
                $template->setValue('de#' . $i, $formatDate($row['av_date_effet']));
                $template->setValue('ind#' . $i, $row['av_indice'] ?? '');
                $template->setValue('nfin#' . $i, $row['av_visa_no'] ?? '');
                $template->setValue('dfin#' . $i, $formatDate($row['av_visa_date']));
                $template->setValue('ncf#' . $i, $row['av_ctrl_no'] ?? '');
                $template->setValue('dcf#' . $i, $formatDate($row['av_ctrl_date']));
                $template->setValue('nacte#' . $i, $row['av_acte_no'] ?? '');
                $template->setValue('dacte#' . $i, $formatDate($row['av_acte_date']));
                
                // Gestion des bonifications (souvent nulles)
                $template->setValue('dbonif#' . $i, $row['av_bonif_duree'] ?? '');
                $template->setValue('nbonif#' . $i, $row['av_bonif_no'] ?? '');
                $template->setValue('dtbonif#' . $i, $formatDate($row['av_bonif_date']));
                $template->setValue('tbonif#' . $i, $row['av_bonif_type'] ?? '');
                $template->setValue('ebonif#' . $i, $row['av_etat'] ?? '');
            }
        }
        // Fin Avancement successif
        // 5. Début Poste actuel
        $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
        $stmtPosteActuel->execute([$im]);
        $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

        $br = '</w:t><w:br/><w:t>';
        $type_fonction = $PosteActuel['type_fonction'];
        $type_etablissement = $PosteActuel['type_etablissement'];

        if ($type_fonction == "Personnel administratif" || ($type_fonction == "Personnel enseignant" && ($type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE"))) {
            $template->setValue('type_fonction', strtoupper($type_fonction));
            $template->setValue('nom_fonction', strtoupper($PosteActuel['nom_fonction']));
            $template->setValue('type_etablissement', strtoupper($PosteActuel['type_etablissement']));
            $template->setValue('matiere_enseignee', '');
            $template->setValue('nom_matiere', '');
        } else {
            $template->setValue('type_fonction', strtoupper($type_fonction));
            $template->setValue('nom_fonction', strtoupper($PosteActuel['nom_fonction']));
            $template->setValue('type_etablissement', strtoupper($PosteActuel['type_etablissement']));
            $template->setValue('matiere_enseignee', $br . 'Matière enseignée : ');
            $template->setValue('nom_matiere', strtoupper($PosteActuel['nom_matiere'] ?? ''));
        }        
        
        $type_direction = $PosteActuel['type_direction']; 
        $nom_service = $PosteActuel['nom_service'];
        $nom_division = $PosteActuel['nom_division'];
        $nom_region = $PosteActuel['nom_region']; 
        $nom_district = $PosteActuel['nom_district'];        

        if ($type_fonction == "Personnel administratif" && $type_etablissement == "DREN" ) {
            $template->setValue('type_direction', strtoupper($type_direction));
            $template->setValue('district', '');
            $template->setValue('nom_district', '');
            $template->setValue('type_serv_div_zap', $br . 'Service : ');
            $template->setValue('service_division', strtoupper($nom_service));
            $template->setValue('local_service', "DREN ".strtoupper($nom_region));
        } else if ($type_fonction == "Personnel administratif" && $type_etablissement == "CISCO") {
            $template->setValue('type_direction', strtoupper($type_direction));
            $template->setValue('district', '');
            $template->setValue('nom_district', '');
            $template->setValue('type_serv_div_zap', $br . 'Division : ');
            $template->setValue('service_division', strtoupper($nom_division));
            $template->setValue('local_service', "CISCO ".strtoupper($nom_district));
        } else if (($type_fonction == "Personnel administratif" || $type_fonction == "Personnel enseignant") && $type_etablissement == "CRFRP") {
            $template->setValue('type_direction', strtoupper($type_direction));
            $template->setValue('district', '');
            $template->setValue('nom_district', '');
            $template->setValue('type_serv_div_zap', '');
            $template->setValue('service_division', '');
            $template->setValue('local_service', strtoupper($PosteActuel['nom_etablissement']));
        } else if (($type_fonction == "Personnel administratif" || $type_fonction == "Personnel enseignant") && ($type_etablissement == "LYCEE" || $type_etablissement == "COLLEGE" || $type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE")) {
            $template->setValue('type_direction', strtoupper($type_direction));
            $template->setValue('district', $br . 'CISCO : ');
            $template->setValue('nom_district', strtoupper($nom_district));
            $template->setValue('type_serv_div_zap', $br . 'ZAP : ');
            $template->setValue('service_division', strtoupper($PosteActuel['nom_zap']));
            $template->setValue('local_service', strtoupper($PosteActuel['nom_etablissement']));
        }        
        
        // Fin Poste actuel
        // 6. Début distinction honorifique
        $stmtDistinction = $pdo->prepare("SELECT * FROM personnel_distinctions WHERE im = ?");
        $stmtDistinction->execute([$im]);
        $Distinction = $stmtDistinction->fetchAll(PDO::FETCH_ASSOC);
        if (count($Distinction) > 0) {
            $template->cloneRow('numdist', count($Distinction));
            foreach ($Distinction as $index => $row) {
                $i = $index + 1;
                $template->setValue('numdist#' . $i, $row['num_acte']);
                $template->setValue('ddist#' . $i, date('d/m/Y', strtotime($row['date_acte'])));
                $template->setValue('gdist#' . $i, $row['type_grade']);
                $template->setValue('ngdist#' . $i, $row['nom_grade']);
                $template->setValue('nactdist#' . $i, $row['nature_acte']);
            }
        }
        // Fin distinction honorifique
        // 7. Début affectation successive
        $stmtAffectationSuccessive = $pdo->prepare("SELECT * FROM personnel_affectations WHERE im = ?");
        $stmtAffectationSuccessive->execute([$im]);
        $AffectationSuccessive = $stmtAffectationSuccessive->fetchAll(PDO::FETCH_ASSOC);
        if (count($AffectationSuccessive) > 0) {
            $template->cloneRow('tpa', count($AffectationSuccessive));
            foreach ($AffectationSuccessive as $index => $row) {
                $i = $index + 1;
                $template->setValue('tpa#' . $i, $row['type_acte']);
                $template->setValue('numacte#' . $i, $row['num_acte']);
                $template->setValue('dateacte#' . $i, date('d/m/Y', strtotime($row['date_acte'])));
                $template->setValue('fonction#' . $i, $row['fonction']);
                $template->setValue('lieuaffec#' . $i, $row['lieu_affectation']);
                $template->setValue('loc#' . $i, $row['localite']);
            }
        }
        // Fin affectation successive
        // 8. Début congé
        $stmtConge = $pdo->prepare("SELECT * FROM personnel_conges WHERE im = ?");
        $stmtConge->execute([$im]);
        $Conge = $stmtConge->fetchAll(PDO::FETCH_ASSOC);
        if (count($Conge) > 0) {
            $template->cloneRow('aconge', count($Conge));
            foreach ($Conge as $index => $row) {
                $i = $index + 1;
                $template->setValue('aconge#' . $i, $row['annee']);
                $template->setValue('nconge#' . $i, $row['num_decision']);
                $template->setValue('dconge#' . $i, date('d/m/Y', strtotime($row['date_decision'])));
            }
        }
        // Fin congé
    } else if ($type_doc === 'certificat_administratif') {
        $stmtEtatCivil = $pdo->prepare("SELECT * FROM personnel_etat_civil WHERE im = ?");
        $stmtEtatCivil->execute([$im]);
        $etatCivil = $stmtEtatCivil->fetch(PDO::FETCH_ASSOC);

        $sexe = $etatCivil['sexe'];
        if ($sexe == "Masculin") {
            $template->setValue('nee_le', "Né le");
        } else {
            $template->setValue('nee_le', "Née le");
        }

        if ($etatCivil) {
            $template->setValue('nom', strtoupper($etatCivil['nom']));
            $template->setValue('prenoms', $etatCivil['prenoms']);
            $template->setValue('matricule', strtoupper($etatCivil['im']));
            $template->setValue('date_naiss', date('d/m/Y', strtotime($etatCivil['date_naiss'])));
            $template->setValue('lieu_naiss', strtoupper($etatCivil['lieu_naiss'] ?? ''));
            $template->setValue('cin', strtoupper($etatCivil['cin'] ?? ''));
            $template->setValue('date_cin', date('d/m/Y', strtotime($etatCivil['date_cin'])));
            $template->setValue('lieu_cin', strtoupper($etatCivil['lieu_cin'] ?? ''));
        }

        $stmtSituationActuelle = $pdo->prepare("SELECT * FROM personnel_situation_actuelle WHERE im = ?");
        $stmtSituationActuelle->execute([$im]);
        $SituationActuelle = $stmtSituationActuelle->fetch(PDO::FETCH_ASSOC);

        if ($SituationActuelle) {
            $template->setValue('indice_actuel', strtoupper($SituationActuelle['indice_actuel']));
            $template->setValue('corps_actuel', strtoupper($SituationActuelle['corps_actuel'] ?? ''));
            $template->setValue('grade_actuel', strtoupper($SituationActuelle['grade_actuel'] ?? ''));
            $template->setValue('imput_budg', strtoupper($SituationActuelle['imput_budg'] ?? ''));
            $template->setValue('date_entree_admin', date('d/m/Y', strtotime($SituationActuelle['date_entree_admin'])));
        }

        $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
        $stmtPosteActuel->execute([$im]);
        $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);        

        $br = '</w:t><w:br/><w:t>';
        $lieu_signature = ucfirst(mb_strtolower($PosteActuel['nom_district'] ?? ''));
        $type_fonction = $PosteActuel['type_fonction'];
        $type_etablissement = $PosteActuel['type_etablissement'];
        $type_direction = $PosteActuel['type_direction']; 
        $nom_service = $PosteActuel['nom_service'];
        $nom_division = $PosteActuel['nom_division'];
        $nom_region = $PosteActuel['nom_region']; 
        $nom_district = $PosteActuel['nom_district']; 
        $nom_zap = $PosteActuel['nom_zap'];
        $nom_etablissement = $PosteActuel['nom_etablissement'];

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
            $template->setValue('en_service', $nom_direction);
            $template->setValue('lieu_signature', 'Antananarivo');
        } else if ($type_etablissement =="DREN") {
            $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
            $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
            $template->setValue('chef_signataire', "Chef de Service de la Gestion des Ressources Humaines, de la Direction Régionale de l’Education Nationale de ");
            $template->setValue('nom_region', ucfirst(mb_strtolower($PosteActuel['nom_region'] ?? '')));
            $template->setValue('nom_district', '');
            $template->setValue('en_service', "DREN ".$nom_region);
            $template->setValue('lieu_signature', $chef_lieu_region); 
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

    } else if ($type_doc === 'attestation_non_interruption') {
        $stmtEtatCivil = $pdo->prepare("SELECT * FROM personnel_etat_civil WHERE im = ?");
        $stmtEtatCivil->execute([$im]);
        $etatCivil = $stmtEtatCivil->fetch(PDO::FETCH_ASSOC);

        $sexe = $etatCivil['sexe'];
        if ($sexe == "Masculin") {
            $template->setValue('nee_le', "Né le");
        } else {
            $template->setValue('nee_le', "Née le");
        }

        if ($etatCivil) {
            $template->setValue('nom', strtoupper($etatCivil['nom']));
            $template->setValue('prenoms', $etatCivil['prenoms']);
            $template->setValue('matricule', strtoupper($etatCivil['im']));
            $template->setValue('date_naiss', date('d/m/Y', strtotime($etatCivil['date_naiss'])));
            $template->setValue('lieu_naiss', strtoupper($etatCivil['lieu_naiss'] ?? ''));
            $template->setValue('cin', strtoupper($etatCivil['cin'] ?? ''));
            $template->setValue('date_cin', date('d/m/Y', strtotime($etatCivil['date_cin'])));
            $template->setValue('lieu_cin', strtoupper($etatCivil['lieu_cin'] ?? ''));
        }

        $stmtSituationActuelle = $pdo->prepare("SELECT * FROM personnel_situation_actuelle WHERE im = ?");
        $stmtSituationActuelle->execute([$im]);
        $SituationActuelle = $stmtSituationActuelle->fetch(PDO::FETCH_ASSOC);

        if ($SituationActuelle) {
            $template->setValue('indice_actuel', strtoupper($SituationActuelle['indice_actuel']));
            $template->setValue('corps_actuel', strtoupper($SituationActuelle['corps_actuel'] ?? ''));
            $template->setValue('grade_actuel', strtoupper($SituationActuelle['grade_actuel'] ?? ''));
            $template->setValue('imput_budg', strtoupper($SituationActuelle['imput_budg'] ?? ''));
            $template->setValue('date_entree_admin', date('d/m/Y', strtotime($SituationActuelle['date_entree_admin'])));
        }

        $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
        $stmtPosteActuel->execute([$im]);
        $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);        

        $br = '</w:t><w:br/><w:t>';
        $lieu_signature = ucfirst(mb_strtolower($PosteActuel['nom_district'] ?? ''));
        $type_fonction = $PosteActuel['type_fonction'];
        $type_etablissement = $PosteActuel['type_etablissement'];
        $type_direction = $PosteActuel['type_direction']; 
        $nom_service = $PosteActuel['nom_service'];
        $nom_division = $PosteActuel['nom_division'];
        $nom_region = $PosteActuel['nom_region']; 
        $nom_district = $PosteActuel['nom_district']; 
        $nom_zap = $PosteActuel['nom_zap'];
        $nom_etablissement = $PosteActuel['nom_etablissement'];

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
            $template->setValue('lieu_signature', $chef_lieu_region); 
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
    } else {

    }
    
    if (ob_get_length()) ob_clean();
    // Empeche toute sortie parasite de corrompre le fichier
    vider_tampon_sortie();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment;filename="' . $outputName . '"');
    header('Cache-Control: max-age=0');

    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur lors de la génération : " . $e->getMessage());
}
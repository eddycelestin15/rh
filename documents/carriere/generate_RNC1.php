<?php
// Tampon de sortie : evite qu'un avertissement PHP ne corrompe le .docx
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
use PhpOffice\PhpWord\TemplateProcessor; 

if (isset($_GET['im'], $_GET['corps'])) {
    $im = $_GET['im'];
    $corps = $_GET['corps'];
    $code_corps_prefix = substr($corps, 0, 1);
    $alerte_id = $_GET['alerte_id'] ?? '';

    try {
        
        $stmt = $pdo->prepare("SELECT ec.*, sa.* FROM personnel_etat_civil ec JOIN personnel_situation_actuelle sa ON ec.im = sa.im WHERE ec.im = ?");
        $stmt->execute([$im]);
        $agent = $stmt->fetch();

        if (!$agent) die("Agent introuvable.");

        $stmtDiplome = $pdo->prepare(" SELECT libelle FROM personnel_diplomes WHERE im = ? AND type_diplome = 'acad' ORDER BY annee DESC LIMIT 1");
        $stmtDiplome->execute([$im]);
        $diplome_plus_recent = $stmtDiplome->fetchColumn() ?: ''; 

        $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
        $stmtPosteActuel->execute([$im]);
        $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

        $code_corps_officiel = $agent['code_corps_actuel'] ?? '';
        $code_corps_prefix = strtoupper(substr($code_corps_officiel, 0, 1));

        // --- AJOUT : RÉCUPÉRATION DU MODE (Individuel ou Complet) ---
        $mode = $_GET['mode'] ?? 'complet';

        // --- MODIFICATION : SÉLECTION DU TEMPLATE SELON LE MODE ---
        switch ($mode) {
            case 'btn':
                $templateFile = APP_ROOT . '/pieces/rectoVerso/BIN.docx';
                $prefix_file = "BIN_";
                break;
            case 'pct':
                $templateFile = APP_ROOT . '/pieces/rectoVerso/projet_contrat_RNC1.docx';
                $prefix_file = "Projet_Contrat_RNC1_";
                break;
            case 'pavenant':
                $templateFile = APP_ROOT . '/pieces/Projet/projet_avenant_RNC1.docx';
                $prefix_file = "Projet_Avenant_RNC1_";
                break;
            case 'vmed57':
                $templateFile = APP_ROOT . '/pieces/rectoVerso/visite_medicale_57.docx';
                $prefix_file = "Visite_Medical_57_";
                break;
            case 'vmed58':
                $templateFile = APP_ROOT . '/pieces/rectoVerso/visite_medicale_58.docx';
                $prefix_file = "Visite_Medical_58_";
                break;
            default:
                $prefix_file = "Contrat_RNC1_";
                if ($code_corps_prefix === 'J') {
                    $templateFile = APP_ROOT . '/pieces/Contrat_avenant/contrat_avec_avenant_RNC1.docx';
                } else {
                    $templateFile = APP_ROOT . '/pieces/Contrat_avenant/contrat_sans_avenant_RNC1.docx';
                }
                break;
        }

        if (!file_exists($templateFile)) erreur_gabarit_absent($templateFile);
        $template = new TemplateProcessor($templateFile);

        $projet = $_SESSION['dernier_projet_calcule'][0] ?? null;
        $cat = $agent['categorie_actuel'] ?? '';

        if ($projet) {            
            // --- PREMIER CONTRAT (SITUATION ACTUELLE) ---
            $template->setValue('corps_c1', $projet['avant']['corps']);
            $template->setValue('grade_c1', $projet['avant']['grade']);
            $template->setValue('indice_c1', $projet['avant']['indice']);
            $template->setValue('date_effet_c1', date('d/m/Y', strtotime($projet['avant']['date'])));
            $template->setValue('date_fin_c1', date('d/m/Y', strtotime($projet['avant']['date_fin'])));
            $template->setValue('duree_c1', $projet['avant']['duree_label']); 

            // --- PREMIER AVENANT (DÉDUIT) ---
            $propose = $projet['apres'][0];
            if (isset($propose['date_effet_avenant'])) {
                $template->setValue('date_effet_av1', date('d/m/Y', strtotime($propose['date_effet_avenant'])));
                $template->setValue('corps_av1', $propose['corps']);
                $template->setValue('grade_av1', $propose['grade']);
                $template->setValue('indice_av1', $propose['indice']);
            }            
            // --- PROPOSITION (DEUXIÈME CONTRAT) ---
            $template->setValue('corps_c2', $propose['corps']);
            $template->setValue('grade_c2', $propose['grade']);
            $template->setValue('indice_c2', $propose['indice']);
            $template->setValue('date_effet_c2', date('d/m/Y', strtotime($propose['date'])));
            $template->setValue('duree_c2', $propose['duree']);

            $texte_assim_c3 = "";
            if (in_array($cat, ['IV', 'V', 'VI', 'VIII'])) {
                $texte_assim_c3 = "CONT. DE LA CAT. " . $cat . " " . $propose['grade'];
            } elseif (in_array($cat, ['II', 'III'])) {
                $texte_assim_c3 = "AUXILIAIRE AUX " . $propose['grade'];
            }
            $template->setValue('assimilation_c3', $texte_assim_c3); 
        }

        $template->setValue('diplome_acad', htmlspecialchars($diplome_plus_recent));

        $date_debut_stage = $agent['date_d_effet_actuel'] ?? $agent['date_entree_admin'];
        if ($date_debut_stage) {
            $timestamp_debut = strtotime($date_debut_stage);
            $annee_debut = date('Y', $timestamp_debut);
            $annee_fin = date('Y', strtotime($date_debut_stage . " + 1 year"));
            $periode_stage = $annee_debut . " - " . $annee_fin;
            $template->setValue('periode_stage', $periode_stage);
        }

        $grade_c1 = $projet['avant']['grade'] ?? $agent['grade_actuel']; 
        $texte_assim_c1 = "";
        $texte_assim_c2 = "";
        
        $groupe = "";
        if (in_array($cat, ['IV', 'V', 'VI', 'VIII'])) {
            $texte_assim_c1 = "CONT. DE LA CAT. " . $cat . ", " . $grade_c1;
            $texte_assim_c2 = "CONT. DE LA CAT. " . $cat.",";
            $groupe = "V";
        } elseif (in_array($cat, ['II', 'III'])) {
            $texte_assim_c1 = "AUXILIAIRE AUX " . $grade_c1;
            $texte_assim_c2 = "AUXILIAIRE AUX";
            $groupe = "II";
        }
        $template->setValue('assimilation_c1', $texte_assim_c1);
        $template->setValue('assimilation_c2', $texte_assim_c2); 
        $template->setValue('groupe', $groupe);              

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

        // Récupération numero_dos
        $demandes = [
            'c1' => ['alerte' => $im . '_RNC1', 'type' => 'contrat1'],
            'a1' => ['alerte' => $im . '_RNC1', 'type' => 'avenant1']
        ];
        $nums_word = [];
        foreach ($demandes as $cle_word => $critere) {
            $stmt = $pdo->prepare("
                SELECT numero_dos 
                FROM demandes_numeros_dos 
                WHERE im = ? 
                AND alerte_id = ? 
                AND type_dos = ? 
                AND statut = 'ATTRIBUE' 
                LIMIT 1
            ");
            
            $stmt->execute([$im, $critere['alerte'], $critere['type']]);
            $data = $stmt->fetch();
            $nums_word[$cle_word] = ($data && !empty($data['numero_dos'])) ? $data['numero_dos'] : "";
        }
        $template->setValue('numero_dos_ct1', $nums_word['c1']); 
        $template->setValue('numero_dos_av1', $nums_word['a1']);

        $categorie_actuel = $agent['categorie_actuel'] ?? '';
        $groupeCont = '';

        if ($categorie_actuel === 'II' || $categorie_actuel === 'III') {
            $groupeCont = 'III';
        } elseif (in_array($categorie_actuel, ['IV', 'V', 'VI', 'VIII'])) {
            $groupeCont = 'V';
        }

        $genre_input = $_GET['genre'] ?? 'Mr';
        $genre_maj = ($genre_input === 'Mme') ? 'MADAME' : 'MONSIEUR';
        $genre_min = ($genre_input === 'Mme') ? 'Madame' : 'Monsieur';
        $template->setValue('genre_min', $genre_min);
        $template->setValue('genre_maj', $genre_maj);

        $template->setValue('annee', date('Y'));
        $date_du_jour = (new DateTime())->format('d/m/Y');
        $template->setValue('date_du_jour', $date_du_jour);
        $template->setValue('nom', htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
        $template->setValue('nom_contrat', htmlspecialchars(mb_strtoupper($agent['nom'] ?? '', 'UTF-8')));
        $template->setValue('prenoms_contrat', htmlspecialchars(mb_strtoupper($agent['prenoms'] ?? '', 'UTF-8'))); 
        $template->setValue('im', $im);
        $template->setValue('date_naiss', date('d/m/Y', strtotime($agent['date_naiss'])));
        $template->setValue('lieu_naiss', $agent['lieu_naiss']);
        $template->setValue('situation_familiale', $agent['situation_familiale']);
        $template->setValue('nom_conjoint', $agent['nom_conjoint']);
        $template->setValue('nbr_enfant', $agent['nbr_enfant']);
        $template->setValue('cin', $agent['cin']);
        $template->setValue('date_cin', date('d/m/Y', strtotime($agent['date_cin'])));
        $template->setValue('lieu_cin', $agent['lieu_cin']);
        $template->setValue('adresse', $agent['adresse']);
        $template->setValue('corps_actuel', $agent['corps_actuel']);
        $template->setValue('grade_actuel', $agent['grade_actuel']);
        $template->setValue('date_d_effet_actuel', date('d/m/Y', strtotime($agent['date_d_effet_actuel'])));
        $template->setValue('num_acte_actuel', $agent['num_acte_actuel']);
        $template->setValue('date_acte_actuel', date('d/m/Y', strtotime($agent['date_acte_actuel'])));
        $template->setValue('date_entree_admin', date('d/m/Y', strtotime($agent['date_entree_admin'])));
        $template->setValue('chapitre', $agent['chap_budg'] ?? '');
        $template->setValue('groupeCont', $groupeCont);
        $template->setValue('imputation', $agent['imput_budg'] ?? '');
        $template->setValue('mode_paiement', $agent['mode_paiement'] ?? '');

        $fileName = $prefix_file . $im . ".docx";

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
}
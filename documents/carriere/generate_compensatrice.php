<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

// Fonction de conversion des nombres (avec gestion du demi-jour) en toutes lettres
function nombreEnLettresAvecDemi($nombre) {
    $f = new NumberFormatter("fr", NumberFormatter::SPELLOUT);
    
    $entier = floor($nombre);
    $decimal = $nombre - $entier;

    $texteEntier = mb_strtoupper($f->format($entier), 'UTF-8');

    // Traitement du demi-jour (0.5)
    if (abs($decimal - 0.5) < 0.01) {
        if ($entier == 0) {
            return "DEMI";
        }
        return $texteEntier . " ET DEMI";
    }

    return $texteEntier;
}

// Validation du paramètre matricule (IM)
$im = isset($_GET['im']) ? trim($_GET['im']) : '';
if (empty($im)) {
    die("Erreur : Le matricule (IM) est requis.");
}

// --- RÉCUPÉRATION DES PARAMÈTRES SUPPLÉMENTAIRES DE L'ARRÊTÉ DE RETRAITE ---
$numArreteRetraite  = isset($_GET['num_arrete_retraite']) ? trim($_GET['num_arrete_retraite']) : '';
$dateArreteRetraite = isset($_GET['date_arrete_retraite']) ? trim($_GET['date_arrete_retraite']) : '';

// Formatage de la date de l'arrêté au format JJ/MM/AAAA
$dateArreteRetraiteFr = '';
if (!empty($dateArreteRetraite)) {
    $dateObj = DateTime::createFromFormat('Y-m-d', $dateArreteRetraite);
    if ($dateObj) {
        $dateArreteRetraiteFr = $dateObj->format('d/m/Y');
    } else {
        $dateArreteRetraiteFr = $dateArreteRetraite;
    }
}

try {
    // 1. Récupération des données de l'état civil (Nom, Prénoms, Date de Naissance)
    $stmtEC = $pdo->prepare("SELECT * FROM personnel_etat_civil WHERE im = :im LIMIT 1");
    $stmtEC->execute([':im' => $im]);
    $etatCivil = $stmtEC->fetch(PDO::FETCH_ASSOC);

    if (!$etatCivil) {
        throw new Exception("Agent introuvable dans la base de données.");
    }

    $nomPrenoms = trim($etatCivil['nom'] . ' ' . $etatCivil['prenoms']);

    // CALCUL DE LA DATE DE RETRAITE (Date de naissance + 60 ans)
    $dateRetraiteFr = '';
    if (!empty($etatCivil['date_naiss'])) {
        $dtNaiss = new DateTime($etatCivil['date_naiss']);
        $dtNaiss->modify('+60 years');
        $dateRetraiteFr = $dtNaiss->format('d/m/Y');
    }

    // 2. Récupération de la situation administrative actuelle
    $stmtSit = $pdo->prepare("SELECT * FROM personnel_situation_actuelle WHERE im = :im LIMIT 1");
    $stmtSit->execute([':im' => $im]);
    $situation = $stmtSit->fetch(PDO::FETCH_ASSOC);

    $budget = $situation['budget'] ?? 'GENERAL';
    $imputation = $situation['imput_budg'] ?? '';
    
    // Assemblage Grade/Emploi
    $gradeEmploi = '';
    if (!empty($situation['corps_actuel'])) {
        $gradeEmploi .= $situation['corps_actuel'];
    }
    if (!empty($situation['grade_actuel'])) {
        $gradeEmploi .= ($gradeEmploi ? ', ' : '') . str_replace('/', ' ', $situation['grade_actuel']);
    }
    $indice = $situation['indice_actuel'] ?? ''; 

    // --- RÉCUPÉRATION ET RESTRICTION AUX 3 ANNÉES DE LA DEMANDE ---
    $anneeRetraite = (int)(new DateTime($etatCivil['date_naiss']))->modify('+60 years')->format('Y');
    $moisJourNaiss = (new DateTime($etatCivil['date_naiss']))->format('m-d');

    if ($moisJourNaiss === '01-01') {
        $anneesCibles = [$anneeRetraite - 3, $anneeRetraite - 2, $anneeRetraite - 1];
    } else {
        $anneesCibles = [$anneeRetraite - 2, $anneeRetraite - 1, $anneeRetraite];
    }

    // Requête sécurisée filtrée uniquement sur ces 3 années
    $inClause = implode(',', array_fill(0, count($anneesCibles), '?'));
    $params = array_merge([$im], $anneesCibles);

    $stmtConges = $pdo->prepare("SELECT annee, num_decision, date_decision, jours_total, nbr_jours_pris 
                                 FROM personnel_conges 
                                 WHERE im = ? AND annee IN ($inClause)
                                 ORDER BY annee ASC");
    $stmtConges->execute($params);
    $congesRows = $stmtConges->fetchAll(PDO::FETCH_ASSOC);

    $anneesArray = [];

    foreach ($congesRows as $row) {
        $anneesArray[] = $row['annee'];
    }

    $anneesTexte = implode('-', $anneesArray);

    // 5. Chargement du modèle de document Word
    $templatePath = __DIR__ . '/../../pieces/Compensatrice/demande_compensatrice.docx';
    if (!file_exists($templatePath)) {
        throw new Exception("Le fichier modèle 'decision_compensatrice.docx' est introuvable.");
    }

    $template = new TemplateProcessor($templatePath);

    // 3. Récupération du lieu / poste de service
    $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
    $stmtPosteActuel->execute([$im]);
    $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

    $br = '</w:t><w:br/><w:t>';
    $type_etablissement = $PosteActuel['type_etablissement'] ?? '';
    $type_fonction      = $PosteActuel['type_fonction'] ?? '';
    $type_direction     = $PosteActuel['type_direction'] ?? '';
    $nom_direction      = $PosteActuel['nom_direction'] ?? '';
    $nom_region         = $PosteActuel['nom_region'] ?? ''; 
    $nom_district       = $PosteActuel['nom_district'] ?? ''; 
    $nom_zap            = $PosteActuel['nom_zap'] ?? '';
    $nom_etablissement  = $PosteActuel['nom_etablissement'] ?? '';

    $lieuService = "";
    if ($type_etablissement === 'MEN CENTRAL') {
        $lieuService = !empty($nom_direction) ? $nom_direction : '-';
    } elseif ($type_etablissement === 'DREN') {
        $lieuService = "BUREAU DREN " . $nom_region;
    } elseif ($type_etablissement === 'CISCO') {
        $lieuService = "BUREAU CISCO " . $nom_district;
    } elseif ($type_etablissement === 'CRFRP') {
        $lieuService = !empty($nom_etablissement) ? $nom_etablissement : '-';
    } elseif (in_array($type_etablissement, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
        $lieuService = "DREN " . $nom_region . " / CISCO " . $nom_district . " / ZAP " . $nom_zap . " / " . $nom_etablissement;
    } else {
        $lieuService = !empty($nom_etablissement) ? $nom_etablissement : '-';
    }

    $nom_crfrp_propre = $PosteActuel['nom_etablissement'] ?? '';
    if (stripos($nom_crfrp_propre, 'CRFRP') !== false) {
        $nom_crfrp_propre = trim(preg_replace('/^CRFRP\s+/i', '', $nom_crfrp_propre));
    }
    $nom_crfrp = $nom_crfrp_propre;
    
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
        $template->setValue('lieu_signature', 'Antananarivo');
        $template->setValue('adresser_a', 'LE DIRECTEUR DES RESSOURCES HUMAINES');
        $template->setValue('chef_lieu_region', '');
        $template->setValue('titre_signataire', 'Le Directeur');
    } else if ($type_etablissement =="DREN") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
        $template->setValue('chef_signataire', "Chef de Service de la Gestion des Ressources Humaines, de la Direction Régionale de l’Education Nationale de ");
        $template->setValue('nom_region', ucfirst(mb_strtolower($PosteActuel['nom_region'] ?? '')));
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au");
        $template->setValue('lieu_signature', $chef_lieu_region); 
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('titre_signataire', 'Le Préfet');
    } else if ($type_etablissement =="CISCO") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $chef_lieu_district);
        $template->setValue('au_a_la', "au ");
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('titre_signataire', 'Le Préfet');
    } else if ($type_etablissement =="CRFRP") {
        $template->setValue('type_direction', "DIRECTION GENERALE DE L’INSTITUT NATIONAL" .$br. "DE FORMATION PEDAGOGIQUE");
        $template->setValue('sgrh_cisco_crfrp', "CENTRE REGIONAL DE FORMATION" .$br. "ET DE RECHERCHE PEDAGOGIQUE ".$nom_crfrp);
        $template->setValue('chef_signataire', "Chef de Centre  Régional de Formation et de Recherche Pédagogique de ".ucfirst(mb_strtolower($nom_crfrp, 'UTF-8')));
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au ");
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('titre_signataire', 'Le Préfet');
    } else if ($type_etablissement == "LYCEE" || $type_etablissement == "COLLEGE") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $chef_lieu_district);
        $template->setValue('au_a_la', "au ");
        $template->setValue('lieu_signature', $chef_lieu_district);
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('titre_signataire', 'Le Préfet');
    } else if ($type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $chef_lieu_district);
        $template->setValue('au_a_la', "à l'");
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('titre_signataire', 'Le Préfet');
    }

    // Remplacement des variables de l'état civil & poste
    $genre_input = $_GET['genre'] ?? 'Mr';
    $genre_maj = ($genre_input === 'Mme') ? 'MADAME' : 'MONSIEUR';
    $genre_min = ($genre_input === 'Mme') ? 'Madame' : 'Monsieur';
    $template->setValue('genre_min', $genre_min);
    $template->setValue('genre_maj', $genre_maj);

    $sexe = $etatCivil['sexe'];
    $situation_familiale = $etatCivil['situation_familiale'];
        
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
    $template->setValue('nom_complet', htmlspecialchars($nomPrenoms, ENT_QUOTES, 'UTF-8'));
    $template->setValue('im', htmlspecialchars($im, ENT_QUOTES, 'UTF-8'));
    $template->setValue('budget', htmlspecialchars($budget, ENT_QUOTES, 'UTF-8'));
    $template->setValue('imputation', htmlspecialchars($imputation, ENT_QUOTES, 'UTF-8'));
    $template->setValue('corps_grade', htmlspecialchars($gradeEmploi, ENT_QUOTES, 'UTF-8'));
    $template->setValue('indice', htmlspecialchars($indice, ENT_QUOTES, 'UTF-8'));
    $template->setValue('lieu_service', htmlspecialchars($lieuService, ENT_QUOTES, 'UTF-8'));
    $template->setValue('date_retraite', htmlspecialchars($dateRetraiteFr, ENT_QUOTES, 'UTF-8'));
    $template->setValue('annee_conge', htmlspecialchars($anneesTexte, ENT_QUOTES, 'UTF-8'));
    $template->setValue('date_naiss', date('d/m/Y', strtotime($etatCivil['date_naiss'])));
    $template->setValue('lieu_naiss', $etatCivil['lieu_naiss']);
    $template->setValue('situation_familiale', $etatCivil['situation_familiale']);
    $nbrEnfantVal = (empty($etatCivil['nbr_enfant']) || (int)$etatCivil['nbr_enfant'] === 0) 
        ? '00' 
        : sprintf('%02d', (int)$etatCivil['nbr_enfant']);
    $template->setValue('nbr_enfant', $nbrEnfantVal);
    $template->setValue('cin', $etatCivil['cin']);
    $template->setValue('date_cin', date('d/m/Y', strtotime($etatCivil['date_cin'])));
    $template->setValue('lieu_cin', $etatCivil['lieu_cin']);
    $template->setValue('date_entree_admin', date('d/m/Y', strtotime($situation['date_entree_admin'])));
    $template->setValue('mode_paiement', $situation['mode_paiement'] ?? '');

    // Remplacement des variables de l'arrêté d'admission à la retraite
    $template->setValue('num_arrete_admission', htmlspecialchars($numArreteRetraite, ENT_QUOTES, 'UTF-8'));
    $template->setValue('date_arrete_admission', htmlspecialchars($dateArreteRetraiteFr, ENT_QUOTES, 'UTF-8'));

    // --- REMPLISSAGE DYNAMIQUE DU TABLEAU WORD ---
    $countRows = count($congesRows);
    if ($countRows > 0) {
        // Clonage de la ligne du tableau dans le document Word autant de fois qu'il y a d'années enregistrées
        $template->cloneRow('annee', $countRows);

        foreach ($congesRows as $index => $row) {
            $i = $index + 1; 

            // Formatage de la date de décision (JJ/MM/AAAA)
            $dateDecisFr = !empty($row['date_decision']) ? (new DateTime($row['date_decision']))->format('d/m/Y') : '';
            
            // Formatage du texte N° et Date de Décision
            $decisionInfo = $row['num_decision'] . ' du ' . $dateDecisFr;

            // Calcul des jours restants non jouis et conversion en lettres
            $joursRestants = (float)$row['jours_total'] - (float)$row['nbr_jours_pris'];
            $joursFormate = str_replace('.', ',', (string)$joursRestants);
            $joursLettres = nombreEnLettresAvecDemi($joursRestants); // Utilise la fonction déclarée en haut
            
            // Format : "Trente (30) jours" ou "Sept et demi (7,5) jours"
            $nbJoursTexte = ucfirst(mb_strtolower($joursLettres, 'UTF-8')) . ' (' . $joursFormate . ') jours';

            // Injection des valeurs dans le tableau Word
            $template->setValue("annee#{$i}", $row['annee']);
            $template->setValue("decision_info#{$i}", $decisionInfo);
            $template->setValue("nb_jours_lettres#{$i}", $nbJoursTexte);
            $template->setValue("observation#{$i}", 'NON JOUIS');
        }
    } else {
        // S'il n'y a pas d'enregistrements, effacer les balises de la ligne
        $template->cloneRow('annee', 1);
        $template->setValue('annee#1', '-');
        $template->setValue('decision_info#1', '-');
        $template->setValue('nb_jours_lettres#1', '-');
        $template->setValue('observation#1', '-');
    }
    // 6. Génération et téléchargement direct du fichier
    $fileName = 'Demande_Compensatrice_' . $im . '.docx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur lors de la génération du document : " . $e->getMessage());
}
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
    $stmtEC = $pdo->prepare("SELECT nom, prenoms, date_naiss FROM personnel_etat_civil WHERE im = :im LIMIT 1");
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
    $stmtSit = $pdo->prepare("SELECT budget, imput_budg, corps_actuel, grade_actuel, indice_actuel 
                              FROM personnel_situation_actuelle WHERE im = :im LIMIT 1");
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

    // --- 4. RÉCUPÉRATION ET RESTRICTION AUX 3 ANNÉES DE LA DEMANDE ---
    
    // Calcul des 3 années théoriques selon la date de naissance (Si né le 01/01 -> -1, -2, -3 sinon -> 0, -1, -2)
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

    $stmtConges = $pdo->prepare("SELECT annee, jours_total 
                                 FROM personnel_conges 
                                 WHERE im = ? AND annee IN ($inClause)
                                 ORDER BY annee ASC");
    $stmtConges->execute($params);
    $congesRows = $stmtConges->fetchAll(PDO::FETCH_ASSOC);

    $totalJours = 0.0;
    $anneesArray = [];

    foreach ($congesRows as $row) {
        $totalJours += (float)$row['jours_total'];
        $anneesArray[] = $row['annee'];
    }

    // Formatage sans arrondi (ex: 60,5 ou 60)
    $totalJoursFormate = str_replace('.', ',', (string)$totalJours);
    $joursLettres = nombreEnLettresAvecDemi($totalJours);
    
    // Texte complet : "SOIXANTE ET DEMI (60,5) JOURS"
    $chaineCongesComplete = $joursLettres . ' (' . $totalJoursFormate . ') JOURS';
    $anneesTexte = implode(', ', $anneesArray);

    // 5. Chargement du modèle de document Word
    $templatePath = __DIR__ . '/../../pieces/Compensatrice/decision_compensatrice.docx';
    if (!file_exists($templatePath)) {
        throw new Exception("Le fichier modèle 'decision_compensatrice.docx' est introuvable.");
    }

    $template = new TemplateProcessor($templatePath);

    $type_dos = 'Compensatrice'; 
    $stmt = $pdo->prepare("SELECT numero_dos FROM demandes_numeros_dos WHERE im = ? AND type_dos = ? AND statut = 'ATTRIBUE' LIMIT 1");
    $stmt->execute([$im, $type_dos]);
    $result = $stmt->fetch();
    $numero_dos = $result ? $result['numero_dos'] : '';
    $template->setValue('numero_dos', $numero_dos);

    // 3. Récupération du lieu / poste de service
    $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
    $stmtPosteActuel->execute([$im]);
    $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

    $type_etablissement = $PosteActuel['type_etablissement'] ?? '';
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
    $chef_lieu_region_val = mb_convert_case($chef_lieu_region, MB_CASE_TITLE, "UTF-8");

    $br = "\n";
    if ($type_etablissement == "MEN CENTRAL") {
        $template->setValue('prefecture', "PREFECTURE D'ANTANANARIVO");
        $template->setValue('chef_lieu_region', 'Antananarivo');
    } else {
        $template->setValue('prefecture', $prefecture_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val); 
    }

    // Remplacement des variables de l'état civil & poste
    $template->setValue('nom_complet', htmlspecialchars($nomPrenoms, ENT_QUOTES, 'UTF-8'));
    $template->setValue('im', htmlspecialchars($im, ENT_QUOTES, 'UTF-8'));
    $template->setValue('budget', htmlspecialchars($budget, ENT_QUOTES, 'UTF-8'));
    $template->setValue('imputation', htmlspecialchars($imputation, ENT_QUOTES, 'UTF-8'));
    $template->setValue('corps_grade', htmlspecialchars($gradeEmploi, ENT_QUOTES, 'UTF-8'));
    $template->setValue('indice', htmlspecialchars($indice, ENT_QUOTES, 'UTF-8'));
    $template->setValue('lieu_service', htmlspecialchars($lieuService, ENT_QUOTES, 'UTF-8'));
    
    // Date de retraite (60 ans après date_naiss)
    $template->setValue('date_retraite', htmlspecialchars($dateRetraiteFr, ENT_QUOTES, 'UTF-8'));

    // Remplacement des valeurs calculées de congé
    $template->setValue('total_conge', htmlspecialchars($chaineCongesComplete, ENT_QUOTES, 'UTF-8'));
    $template->setValue('annee_conge', htmlspecialchars($anneesTexte, ENT_QUOTES, 'UTF-8'));

    // Remplacement des variables de l'arrêté d'admission à la retraite
    $template->setValue('num_arrete_admission', htmlspecialchars($numArreteRetraite, ENT_QUOTES, 'UTF-8'));
    $template->setValue('date_arrete_admission', htmlspecialchars($dateArreteRetraiteFr, ENT_QUOTES, 'UTF-8'));

    // 6. Génération et téléchargement direct du fichier
    $fileName = 'Decision_Compensatrice_' . $im . '.docx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur lors de la génération du document : " . $e->getMessage());
}
<?php
// Tampon de sortie : evite qu'un avertissement PHP ne corrompe le .docx
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
use PhpOffice\PhpWord\TemplateProcessor;

$im = $_GET['im'] ?? '';
$type_demande = $_GET['type'] ?? '';

if (!$im) die("IM manquant.");

try {
    $stmt = $pdo->prepare("SELECT ec.*, sa.* FROM personnel_etat_civil ec 
                           JOIN personnel_situation_actuelle sa ON ec.im = sa.im 
                           WHERE ec.im = ?");
    $stmt->execute([$im]);
    $agent = $stmt->fetch();

    $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
    $stmtPosteActuel->execute([$im]);
    $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

    if (!$agent) die("Agent introuvable.");
    $templatePath = APP_ROOT . '/pieces/Mandatement/Renouvellement_avec_rappel_solde.docx';
    if (!file_exists($templatePath)) {
        erreur_gabarit_absent($templatePath);
    }

    $template = new TemplateProcessor($templatePath);

    $br = '</w:t><w:br/><w:t>';
    $logoPath = APP_ROOT . '/Logo/Embleme.png';
    $sexe = $agent['sexe'];
    $lieu_signature = ucfirst(mb_strtolower($PosteActuel['nom_district'] ?? ''));
    $type_direction = $PosteActuel['type_direction'] ?? '';
    $nom_direction = $PosteActuel['nom_direction'] ?? '';
    $type_fonction = $PosteActuel['type_fonction'] ?? '';
    $type_etablissement = $PosteActuel['type_etablissement'] ?? '';
    $nom_region = $PosteActuel['nom_region'] ?? '';
    $nom_district = $PosteActuel['nom_district'] ?? '';
    $nom_etablissement = $PosteActuel['nom_etablissement'] ?? '';
    $nom_zap = $PosteActuel['nom_zap'] ?? '';

    // Localite de service attendue par le gabarit : elle n'etait definie
    // nulle part, le document sortait donc avec un champ vide.
    $localite_service = $PosteActuel['nom_etablissement'] ?? '';

    if ($sexe == "Masculin"){
        $template->setValue('nee_le', "Né le");
        $template->setValue('interessee', "L'intéressé");
    } else if ($sexe == "Féminin"){
        $template->setValue('nee_le', "Née le");
        $template->setValue('interessee', "L'intéressée");
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
    $nom_region_formate = mb_convert_case($nom_region, MB_CASE_TITLE, "UTF-8");

    if ($type_fonction =="Personnel administratif" && $type_etablissement =="DREN") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
        $template->setValue('chef_signataire', "Chef de Service de la Gestion des Ressources Humaines, de la Direction Régionale de l’Education Nationale de ");
        $template->setValue('nom_region', ucfirst(mb_strtolower($PosteActuel['nom_region'] ?? '')));
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', "DREN ".$nom_region);
        $template->setValue('lieu_signature', ucfirst(mb_strtolower($ville, 'UTF-8')));
        $template->setValue('region', $nom_region_formate);
    } else if ($type_fonction =="Personnel administratif" && $type_etablissement =="CISCO") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $lieu_signature);
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', "CISCO ".$nom_district);
        $template->setValue('lieu_signature', $lieu_signature);
        $template->setValue('region', $nom_region_formate);
    } else if (($type_fonction =="Personnel administratif" || $type_fonction =="Personnel enseignant") && $type_etablissement =="CRFRP") {
        $template->setValue('type_direction', "DIRECTION GENERALE DE L’INSTITUT NATIONAL" .$br. "DE FORMATION PEDAGOGIQUE");
        $template->setValue('sgrh_cisco_crfrp', "CENTRE REGIONAL DE FORMATION" .$br. "ET DE RECHERCHE PEDAGOGIQUE ".$nom_crfrp);
        $template->setValue('chef_signataire', "Chef de Centre  Régional de Formation et de Recherche Pédagogique de ".ucfirst(mb_strtolower($nom_crfrp, 'UTF-8')));
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', $nom_etablissement);
        $template->setValue('lieu_signature', $lieu_signature);
        $template->setValue('region', $nom_region_formate);
    } else if (($type_fonction == "Personnel administratif" || $type_fonction == "Personnel enseignant") && ($type_etablissement == "LYCEE" || $type_etablissement == "COLLEGE")) {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $lieu_signature);
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
        $template->setValue('lieu_signature', $lieu_signature);
        $template->setValue('region', $nom_region_formate);
    } else if (($type_fonction == "Personnel administratif" || $type_fonction == "Personnel enseignant") && ($type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE")) {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $lieu_signature);
        $template->setValue('au_a_la', "à l'");
        $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
        $template->setValue('lieu_signature', $lieu_signature);
        $template->setValue('region', $nom_region_formate);
    }

    // 6. Remplissage des données administratives fixes
    $genre_input = $_GET['genre'] ?? 'Mr';
    $genre_min = ($genre_input === 'Mme') ? 'Madame' : 'Monsieur';
    $template->setValue('genre', $genre_min);
    $template->setValue('nom', htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
    $template->setValue('im', $agent['im']);
    $template->setValue('corps', $agent['corps_actuel']);
    $template->setValue('grade', $agent['grade_actuel']);
    $template->setValue('indice', $agent['indice_actuel']);
    $template->setValue('imputation', $agent['imput_budg']);
    $template->setValue('localite_service', $localite_service);
    $template->setValue('date_naiss', date('d/m/Y', strtotime($agent['date_naiss'])));
    $template->setValue('lieu_naiss', $agent['lieu_naiss']);
    $template->setValue('cin', $agent['cin']);
    $template->setValue('date_cin', date('d/m/Y', strtotime($agent['date_cin'])));
    $template->setValue('date_entree_admin', date('d/m/Y', strtotime($agent['date_entree_admin'])));
    $template->setValue('lieu_cin', $agent['lieu_cin']);

    // Promotion des valeurs « new_* » vers les colonnes « *_actuel ».
    //
    // Ces colonnes appartiennent à acte_formate elle-même : on les copie donc
    // directement en SQL. La version précédente les lisait dans $agent, qui ne
    // les contient pas (la requête du haut joint personnel_situation_actuelle,
    // pas acte_formate) : chaque génération écrasait ces colonnes avec NULL.
    //
    // La mise à jour est faite AVANT l'envoi du fichier : tout avertissement
    // PHP émis après le début du téléchargement se retrouverait collé à la fin
    // du .docx et le corromprait.
    $stmtUpdateAgent = $pdo->prepare(
        "UPDATE acte_formate
            SET corps_actuel        = new_corps,
                grade_actuel        = new_grade,
                code_corps_actuel   = new_code_corps,
                code_grade_actuel   = new_code_grade,
                indice_actuel       = new_indice,
                date_d_effet_actuel = new_date_d_effet,
                statut              = 'termine'
          WHERE im = :im AND (statut = 'en_attente' OR statut = 'termine')"
    );
    $stmtUpdateAgent->execute([':im' => $im]);

    $filename = "Piece_Mandatement_" . $im . ".docx";
    // Empêche toute sortie parasite de corrompre le fichier
    vider_tampon_sortie();
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
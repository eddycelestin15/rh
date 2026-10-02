<?php
// Tampon de sortie : evite qu'un avertissement PHP ne corrompe le .docx
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
use PhpOffice\PhpWord\TemplateProcessor;

function nombreEnLettres($nombre) {
    $dict = [
        0 => 'zéro', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 
        5 => 'cinq', 6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf', 10 => 'dix'
    ];
    return isset($dict[$nombre]) ? $dict[$nombre] : (string)$nombre;
}

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

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM personnel_enfants WHERE im_parent = ? AND statut_bc = 0");
        $stmtCount->execute([$im]);
        $nbr_enfants_non_payes = $stmtCount->fetchColumn();

        $stmtCountEnf = $pdo->prepare("SELECT COUNT(*) FROM personnel_enfants WHERE im_parent = ?");
        $stmtCountEnf->execute([$im]);
        $nbr_enfants = $stmtCountEnf->fetchColumn();

        if (!$agent) {
            die("Agent introuvable ou situation non renseignée.");
        }

        $templateFile = APP_ROOT . '/pieces/Allocation_familiale/Allocation_familiale.docx';

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
        $nbr_enfants_lettres = nombreEnLettres($nbr_enfants_non_payes);

        // --- INJECTION DANS LE WORD ---
        $template->setValue('nbr_ltr', ucfirst($nbr_enfants_lettres)); 
        $template->setValue('nbr_chiff', $nbr_enfants_non_payes);
            
        if ($sexe=="Masculin"){
            $template->setValue('nee_le', "Né le");
            $template->setValue('interessee', "L'intéressé");
        } else if ($sexe=="Féminin"){
            $template->setValue('nee_le', "Née le");
            $template->setValue('interessee', "L'intéressée");
        }
        
        if ($nbr_enfants <= 4) {
            $nbr_acte = "02";
        } elseif ($nbr_enfants >= 5 && $nbr_enfants <= 8) {
            $nbr_acte = "04";
        } elseif ($nbr_enfants >= 9 && $nbr_enfants <= 12) {
            $nbr_acte = "06";
        } elseif ($nbr_enfants >= 13 && $nbr_enfants <= 16) {
            $nbr_acte = "08"; 
        } elseif ($nbr_enfants >= 17 && $nbr_enfants <= 20) {
            $nbr_acte = "10"; 
        }

        // 3. Remplissage des informations d'État Civil
        $template->setValue('nom', htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
        $template->setValue('im', $agent['im']);
        $template->setValue('corps', $agent['corps_actuel']);
        $template->setValue('grade', $agent['grade_actuel']);
        $template->setValue('indice', $agent['indice_actuel']);
        $template->setValue('imputation', $agent['imput_budg']);
        $template->setValue('code_corps', $agent['code_corps_actuel']);
        $template->setValue('date_naiss', date('d/m/Y', strtotime($agent['date_naiss'])));
        $template->setValue('lieu_naiss', $agent['lieu_naiss']);
        $template->setValue('cin', $agent['cin']);
        $template->setValue('date_cin', date('d/m/Y', strtotime($agent['date_cin'])));
        $template->setValue('lieu_cin', $agent['lieu_cin']);
        $template->setValue('situation_familiale', $situation_familiale);  
        $template->setValue('nbr_acte', $nbr_acte);      

        // 6. Génération et téléchargement
        $fileName = "Demande_allocation_familiale" . $im . ".docx";
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
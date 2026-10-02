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

        $sqlParent = "SELECT * FROM personnel_certificat_cnaps WHERE im_agent = ?";
        $stmtParent = $pdo->prepare($sqlParent);
        $stmtParent->execute([$im]);
        $agent = $stmtParent->fetch();

        $sqlEnfant = "SELECT nom_enfant, prenoms_enfant, date_naiss_enfant, lieu_naiss_enfant, sexe
              FROM personnel_enfants 
              WHERE im_parent = ? AND statut_bc = 0 
              ORDER BY date_naiss_enfant ASC";
        $stmtEnfant = $pdo->prepare($sqlEnfant);
        $stmtEnfant->execute([$im]);
        $enfants_liste = $stmtEnfant->fetchAll();

        if (!$agent) {
            die("Agent introuvable ou situation non renseignée.");
        }

        $templateFile = APP_ROOT . '/pieces/Allocation_familiale/certificat_de_non_paiement.docx';

        if (!file_exists($templateFile)) erreur_gabarit_absent($templateFile);
        $template = new TemplateProcessor($templateFile);

        $template->setValue('nom', htmlspecialchars($agent['agent_nom'] ?? ''));
        $template->setValue('prenoms', htmlspecialchars($agent['agent_prenoms'] ?? ''));
        $template->setValue('date_naiss', (!empty($agent['agent_date_naiss'])) ? date('d/m/Y', strtotime($agent['agent_date_naiss'])) : '');
        $template->setValue('lieu_naiss', $agent['agent_lieu_naiss'] ?? '');
        $template->setValue('prefecture', $agent['agent_sous_pref'] ?? '');
        $template->setValue('cin', $agent['agent_cin'] ?? '');
        $template->setValue('date_cin', (!empty($agent['agent_date_cin'])) ? date('d/m/Y', strtotime($agent['agent_date_cin'])) : '');
        $template->setValue('lieu_cin', $agent['agent_lieu_cin'] ?? '');
        $template->setValue('pere', $agent['agent_pere'] ?? '');
        $template->setValue('mere', $agent['agent_mere'] ?? '');
        $template->setValue('adresse', $agent['agent_adresse'] ?? '');
        $template->setValue('matricule_cnaps', $agent['agent_mat_cnaps'] ?? '');
        $template->setValue('service_employeur', $agent['agent_service'] ?? ''); 
        
        $template->setValue('nom_conj', htmlspecialchars($agent['conjoint_nom'] ?? ''));
        $template->setValue('prenoms_conj', htmlspecialchars($agent['conjoint_prenoms'] ?? ''));
        $template->setValue('date_naiss_conj', (!empty($agent['conjoint_date_naiss'])) ? date('d/m/Y', strtotime($agent['conjoint_date_naiss'])) : '');
        $template->setValue('lieu_naiss_conj', $agent['conjoint_lieu_naiss'] ?? '');
        $template->setValue('prefecture_conj', $agent['conjoint_sous_pref'] ?? '');
        $template->setValue('cin_conj', $agent['conjoint_cin'] ?? '');
        $template->setValue('date_cin_conj', (!empty($agent['conjoint_date_cin'])) ? date('d/m/Y', strtotime($agent['conjoint_date_cin'])) : '');
        $template->setValue('lieu_cin_conj', $agent['conjoint_lieu_cin'] ?? '');
        $template->setValue('pere_conj', $agent['conjoint_pere'] ?? '');
        $template->setValue('mere_conj', $agent['conjoint_mere'] ?? '');
        $template->setValue('adresse_conj', $agent['conjoint_adresse'] ?? '');
        $template->setValue('matricule_cnaps_conj', $agent['conjoint_mat_cnaps'] ?? '');
        $template->setValue('service_employeur_conj', $agent['conjoint_service'] ?? '');

        $liste_complete = "";
        if (!empty($enfants_liste)) {
            foreach ($enfants_liste as $index => $enf) {
                $i = $index + 1;
                $accord = ($enf['sexe'] === 'Féminin') ? 'née' : 'né';                
                $date_n = (!empty($enf['date_naiss_enfant'])) ? date('d/m/Y', strtotime($enf['date_naiss_enfant'])) : '';
                $ligne = $i . ". " . $enf['nom_enfant'] . " " . $enf['prenoms_enfant'] . ", " . $accord . " le " . $date_n . " à " . $enf['lieu_naiss_enfant'];
                
                $liste_complete .= $ligne . "</w:t><w:br/><w:t>";
            }
        } else {
            $liste_complete = "- Aucun enfant enregistré";
        }
        $template->setValue('liste_enfants', $liste_complete);

        $fileName = "Demande_certificat_non_paiement_" . $im . ".docx";

        if (ob_get_length()) {
            ob_end_clean(); 
        }

        // Empeche toute sortie parasite de corrompre le fichier
        vider_tampon_sortie();
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        $template->saveAs('php://output');
        exit;

    } catch (Exception $e) {
        die("Erreur lors de la génération : " . $e->getMessage());
    }
} else {
    die("Numéro matricule (IM) manquant.");
}
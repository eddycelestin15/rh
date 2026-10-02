<?php
// Tampon de sortie : evite qu'un avertissement PHP ne corrompe le .docx
ob_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/check_session.php';

use PhpOffice\PhpWord\TemplateProcessor;

$im = $_SESSION['user_im']; // Récupération du matricule de la session

try {
    // 1. Charger le template
    // Tous les gabarits .docx du projet vivent dans pieces/ : on y range
    // aussi celui-ci. Le fichier est absent du depot -> message explicite
    // plutot qu'un telechargement corrompu.
    $templatePath = APP_ROOT . '/pieces/template_fiche.docx';
    if (!file_exists($templatePath)) {
        erreur_gabarit_absent($templatePath);
    }

    $templateProcessor = new TemplateProcessor($templatePath);

    // 2. CONFIGURATION DE LA PHOTO
    // Chemin vers votre dossier d'images (adapté à votre structure)
    $photoPath = APP_ROOT . '/images/' . $im . ".jpg"; 

    if (file_exists($photoPath)) {
        // On remplace le tag ${photo} par l'image réelle
        $templateProcessor->setImageValue('photo', [
            'path'   => $photoPath,
            'width'  => 170, // Correspond à ~3.5cm
            'height' => 170, // Correspond à ~4.5cm
            'ratio'  => false  // On force ces dimensions pour garder l'alignement du tableau
        ]);
    } else {
        // Si aucune photo n'est trouvée, on peut mettre une image par défaut ou un texte
        $templateProcessor->setValue('photo', 'PHOTO NON DISPONIBLE');
    }

    // 3. REMPLISSAGE DES AUTRES DONNÉES (Exemple pour les affectations)
    $stmt = $pdo->prepare("SELECT * FROM personnel_affectations WHERE im = ? ORDER BY date_acte ASC");
    $stmt->execute([$im]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($data) > 0) {
        $templateProcessor->cloneRow('ta', count($data));
        foreach ($data as $index => $row) {
            $i = $index + 1;
            $templateProcessor->setValue('ta#' . $i, $row['type_acte']);
            $templateProcessor->setValue('na#' . $i, $row['num_acte']);
            $templateProcessor->setValue('da#' . $i, date('d/m/Y', strtotime($row['date_acte'])));
            $templateProcessor->setValue('fo#' . $i, $row['fonction']);
            $templateProcessor->setValue('la#' . $i, $row['lieu_affectation']);
        }
    }

    // 4. SORTIE DU FICHIER
    $fileName = "Fiche_Personnel_" . $im . ".docx";
    // Empeche toute sortie parasite de corrompre le fichier
    vider_tampon_sortie();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    $templateProcessor->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
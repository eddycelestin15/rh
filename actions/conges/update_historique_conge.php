<?php
// Désactiver toute sortie HTML indésirable
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

// Nettoyer d'éventuels echos venant des fichiers inclus
ob_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée.']);
    exit;
}

// Extraction des données POST
$im = trim($_POST['im'] ?? '');
$annee = isset($_POST['annee']) ? (int)$_POST['annee'] : 0;
$num_decision = trim($_POST['num_decision'] ?? '');
$date_decision = trim($_POST['date_decision'] ?? '');
$joursRaw = str_replace(',', '.', $_POST['jours_total'] ?? '30');
$jours_total = (float)$joursRaw;

// Contrôle des clés composites obligatoires
if (empty($im) || $annee <= 0) {
    echo json_encode([
        'status'  => 'error', 
        'message' => 'Matricule (IM) ou année invalide.'
    ]);
    exit;
}

if (empty($num_decision) || empty($date_decision) || $jours_total <= 0) {
    echo json_encode([
        'status'  => 'error', 
        'message' => 'Veuillez remplir le numéro, la date de décision et le total de jours.'
    ]);
    exit;
}

try {
    // UPDATE ciblé sur l'agent (im) et l'année (annee)
    $sql = "UPDATE personnel_conges 
            SET num_decision = :num_decision, 
                date_decision = :date_decision, 
                jours_total = :jours_total 
            WHERE im = :im AND annee = :annee";

    $stmt = $pdo->prepare($sql);
    $executed = $stmt->execute([
        ':num_decision' => $num_decision,
        ':date_decision' => $date_decision,
        ':jours_total'   => $jours_total,
        ':im'            => $im,
        ':annee'         => $annee
    ]);

    if ($executed) {
        echo json_encode([
            'status'  => 'success',
            'message' => 'Décision mise à jour avec succès.',
            'im'      => $im,
            'annee'   => $annee
        ]);
    } else {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Échec de l\'exécution de la mise à jour.'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error', 
        'message' => 'Erreur SQL lors de l\'UPDATE : ' . $e->getMessage()
    ]);
}
exit;
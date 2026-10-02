<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

// Vérification de la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée.']);
    exit;
}

// Récupération des données du formulaire
$im = trim($_POST['im'] ?? '');
$annee = filter_var($_POST['annee'] ?? null, FILTER_VALIDATE_INT);
$num_decision = trim($_POST['num_decision'] ?? '');
$date_decision = trim($_POST['date_decision'] ?? '');
$joursRaw = str_replace(',', '.', $_POST['jours_total'] ?? '30');
$jours_total = filter_var($joursRaw, FILTER_VALIDATE_FLOAT);

// Validation
if (empty($im) || !$annee || empty($num_decision) || empty($date_decision) || $jours_total === false || $jours_total <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires correctement.']);
    exit;
}

try {
    // Vérification si une décision existe déjà pour cette année et cet IM
    $checkStmt = $pdo->prepare("SELECT id FROM personnel_conges WHERE im = ? AND annee = ?");
    $checkStmt->execute([$im, $annee]);
    if ($checkStmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Une décision existe déjà pour cette année.']);
        exit;
    }

    // Insertion du nouvel historique de congé
    $sql = "INSERT INTO personnel_conges (im, annee, num_decision, date_decision, jours_total, nbr_jours_pris) 
            VALUES (?, ?, ?, ?, ?, 0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $im,
        $annee,
        $num_decision,
        $date_decision,
        $jours_total
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Décision ajoutée avec succès.']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
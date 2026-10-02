<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$im = $_POST['im'] ?? null;
$annee = isset($_POST['annee']) ? (int)$_POST['annee'] : null;
$joursTotal = isset($_POST['jours_total']) ? (float)$_POST['jours_total'] : 30.0;

if (!$im || !$annee) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

try {
    // Vérifier si la demande existe déjà pour cette année
    $stmtCheck = $pdo->prepare("SELECT id FROM personnel_conges WHERE im = ? AND annee = ?");
    $stmtCheck->execute([$im, $annee]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Demande déjà enregistrée']);
        exit;
    }

    // Enregistrement de la demande sans numéro ni date de décision
    $stmt = $pdo->prepare("
        INSERT INTO personnel_conges (im, annee, jours_total, nbr_jours_pris) 
        VALUES (?, ?, ?, 0.0)
    ");
    $stmt->execute([$im, $annee, $joursTotal]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
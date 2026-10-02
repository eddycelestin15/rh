<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée.']);
    exit;
}

$im          = trim($_POST['im'] ?? '');
$type_grade  = trim($_POST['type_grade'] ?? '');
$nature_acte = trim($_POST['nature_acte'] ?? '');
$nom_grade   = trim($_POST['nom_grade'] ?? '');
$num_acte    = trim($_POST['num_acte'] ?? '');
$date_acte   = trim($_POST['date_acte'] ?? '');

if (empty($im) || empty($type_grade) || empty($nature_acte) || empty($nom_grade) || empty($num_acte) || empty($date_acte)) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit;
}

try {
    $sql = "INSERT INTO personnel_distinctions (im, type_grade, nature_acte, nom_grade, num_acte, date_acte) 
            VALUES (:im, :type_grade, :nature_acte, :nom_grade, :num_acte, :date_acte)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':im'          => $im,
        ':type_grade'  => $type_grade,
        ':nature_acte' => $nature_acte,
        ':nom_grade'   => $nom_grade,
        ':num_acte'    => $num_acte,
        ':date_acte'   => $date_acte
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Distinction enregistrée avec succès.']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
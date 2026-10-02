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
$nom_grade   = trim($_POST['nom_grade'] ?? '');
$nature_acte = trim($_POST['nature_acte'] ?? '');
$num_acte    = trim($_POST['num_acte'] ?? '');
$date_acte   = trim($_POST['date_acte'] ?? '');

if (empty($im) || empty($type_grade) || empty($nom_grade) || empty($nature_acte) || empty($num_acte) || empty($date_acte)) {
    echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit;
}

try {
    // Vérification de l'existence de l'enregistrement pour cet IM, Type de Grade et Nom de Grade
    $checkStmt = $pdo->prepare("SELECT id FROM personnel_distinctions WHERE im = :im AND type_grade = :type_grade AND nom_grade = :nom_grade");
    $checkStmt->execute([
        ':im'         => $im,
        ':type_grade' => $type_grade,
        ':nom_grade'  => $nom_grade
    ]);

    if (!$checkStmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Aucune distinction trouvée pour cet IM, ce type et ce nom de grade.']);
        exit;
    }

    // Mise à jour de la nature, du numéro et de la date d'acte
    $sql = "UPDATE personnel_distinctions 
            SET nature_acte = :nature_acte, 
                num_acte    = :num_acte, 
                date_acte   = :date_acte 
            WHERE im = :im 
              AND type_grade = :type_grade 
              AND nom_grade = :nom_grade";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nature_acte' => $nature_acte,
        ':num_acte'    => $num_acte,
        ':date_acte'   => $date_acte,
        ':im'          => $im,
        ':type_grade'  => $type_grade,
        ':nom_grade'   => $nom_grade
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Distinction mise à jour avec succès.']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
<?php
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

// Vérification de la session
if (empty($_SESSION['user_im']) && empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$im =$input['im'] ?? '';
$alerte_id =$input['alerte_id'] ?? '';
$num_arrete = trim($input['num_arrete_retraite'] ?? '');
$date_arrete = trim($input['date_arrete_retraite'] ?? '');

if (empty($im) || empty($num_arrete) || empty($date_arrete)) {
    echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
    exit;
}

try {
    // Vérification si un arrêté existe déjà pour cet agent / cette alerte
    $stmtCheck =$pdo->prepare("SELECT id FROM arrete_admission_retraite WHERE im = ? AND alerte_id = ?");
    $stmtCheck->execute([$im,$alerte_id]);
    $existant =$stmtCheck->fetchColumn();

    if ($existant) {
        // Mise à jour si existe
        $stmt =$pdo->prepare("
            UPDATE arrete_admission_retraite 
            SET num_arrete_retraite = ?, date_arrete_retraite = ? 
            WHERE id = ?
        ");
        $stmt->execute([$num_arrete, $date_arrete,$existant]);
    } else {
        // Insertion sinon
        $stmt =$pdo->prepare("
            INSERT INTO arrete_admission_retraite (im, alerte_id, num_arrete_retraite, date_arrete_retraite) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$im,$alerte_id, $num_arrete,$date_arrete]);
    }

    echo json_encode(['success' => true, 'message' => 'Arrêté d\'admission à la retraite enregistré avec succès']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur BDD : ' . $e->getMessage()]);
}
<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit;
}

// === DEBUG : Afficher ce qui est reçu ===
error_log("=== DEBUG save_affectation ===\n" . print_r($_POST, true));

try {
    $id               = isset($_POST['id']) && !empty($_POST['id']) ? intval($_POST['id']) : null;
    $im               = trim($_POST['im'] ?? '');
    $type_acte        = trim($_POST['type_acte'] ?? '');
    $num_acte         = trim($_POST['num_acte'] ?? '');
    $date_acte        = trim($_POST['date_acte'] ?? '');
    $fonction         = trim($_POST['fonction'] ?? '');
    $lieu_affectation = trim($_POST['lieu_affectation'] ?? '');
    $localite         = trim($_POST['localite'] ?? '');

    // Validation
    if (empty($im) || empty($type_acte) || empty($num_acte) || empty($date_acte) || empty($fonction)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Champs manquants. Reçu : ' . json_encode($_POST)
        ]);
        exit;
    }

    $allowed_types = ['Décision', 'Arrêté', 'Note de service', 'Contrat'];
    if (!in_array($type_acte, $allowed_types)) {
        echo json_encode(['status' => 'error', 'message' => 'Type d\'acte invalide : ' . $type_acte]);
        exit;
    }

    if ($id) {
        $sql = "UPDATE personnel_affectations SET im=:im, type_acte=:type_acte, num_acte=:num_acte, 
                date_acte=:date_acte, fonction=:fonction, lieu_affectation=:lieu, localite=:localite 
                WHERE id=:id";
        $params = [':id'=>$id, ':im'=>$im, ':type_acte'=>$type_acte, ':num_acte'=>$num_acte,
                   ':date_acte'=>$date_acte, ':fonction'=>$fonction, ':lieu'=>$lieu_affectation, ':localite'=>$localite];
    } else {
        $sql = "INSERT INTO personnel_affectations (im, type_acte, num_acte, date_acte, fonction, lieu_affectation, localite) 
                VALUES (:im, :type_acte, :num_acte, :date_acte, :fonction, :lieu, :localite)";
        $params = [':im'=>$im, ':type_acte'=>$type_acte, ':num_acte'=>$num_acte, ':date_acte'=>$date_acte,
                   ':fonction'=>$fonction, ':lieu'=>$lieu_affectation, ':localite'=>$localite];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'status' => 'success',
        'message' => $id ? 'Modifié avec succès' : 'Enregistré avec succès'
    ]);

} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur BDD : ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
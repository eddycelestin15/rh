<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    // S'assurer que la session est démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once __DIR__ . '/../../includes/check_session.php';
    require_once __DIR__ . '/../../includes/config.php';

    $data = json_decode(file_get_contents('php://input'), true);

    $im = trim($data['im'] ?? '');
    $numero = trim($data['numero'] ?? '');
    $sigle = trim($data['sigle'] ?? '');
    $annee = (int)date('Y');

    if (empty($im) || empty($numero) || empty($sigle)) {
        throw new Exception("Tous les champs (matricule, numéro, sigle) sont requis.");
    }

    // Récupération du lieu d'affectation de l'utilisateur connecté
    $lieu = '';
    if (!empty($_SESSION['user_im'])) {
        $stmtLieu = $pdo->prepare("SELECT code_lieu_affectation FROM utilisateurs WHERE im = ?");
        $stmtLieu->execute([$_SESSION['user_im']]);
        $lieu = $stmtLieu->fetchColumn() ?: '';
    }

    // Insertion sécurisée dans la table releve_service
    $stmt = $pdo->prepare("
        INSERT INTO releve_service (annee, numero, sigle, lieu_direction_service, im) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $annee,
        $numero,
        $sigle,
        $lieu,
        $im
    ]);

    ob_end_clean();

    echo json_encode([
        'success' => true,
        'releve_id' => $pdo->lastInsertId()
    ]);
    exit;

} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "Erreur Base de Données: " . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
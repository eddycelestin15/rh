<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
// Champs obligatoires : sans ce controle, les champs absents partaient en base
// sous forme de valeurs nulles (et PHP 8 emettait un avertissement par champ).
require_once __DIR__ . '/../../includes/helpers.php';
exiger_champs($_POST, ['type_acte', 'num_acte', 'date_acte', 'fonction', 'lieu_affectation', 'localite']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID de ligne manquant']);
            exit;
        }

        $sql = "UPDATE personnel_affectations SET 
                    type_acte = :ta, 
                    num_acte = :na, 
                    date_acte = :da, 
                    fonction = :fo, 
                    lieu_affectation = :la, 
                    localite = :loc 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ta'  => $_POST['type_acte'],
            ':na'  => $_POST['num_acte'],
            ':da'  => $_POST['date_acte'],
            ':fo'  => $_POST['fonction'],
            ':la'  => strtoupper($_POST['lieu_affectation']),
            ':loc' => strtoupper($_POST['localite']),
            ':id'  => $id
        ]);

        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
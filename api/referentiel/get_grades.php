<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
// get_grades.php

$corps_id = $_GET['corps_id'] ?? null;

if ($corps_id) {
    // On récupère le modele_id lié au corps pour avoir les bons grades
    $stmt = $pdo->prepare("SELECT modele_id FROM ref_corps WHERE id = ?");
    $stmt->execute([$corps_id]);
    $modele_id = $stmt->fetchColumn();

    if ($modele_id) {
        $stmtGrades = $pdo->prepare("SELECT id, libelle_grade FROM ref_grades_types WHERE modele_id = ? ORDER BY id ASC");
        $stmtGrades->execute([$modele_id]);
        $grades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($grades);
        exit;
    }
}

echo json_encode([]); // Renvoie une liste vide si rien n'est trouvé
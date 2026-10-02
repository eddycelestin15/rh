<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';

if (isset($_GET['corps_id'])) {
    $corps_id = intval($_GET['corps_id']);
    
    // On cherche d'abord l'ID du grade type correspondant au libellé exact
    // Dans votre table ref_grades_types, on cherche '2°CLASSE/1°ECHELON'
    $stmt = $pdo->prepare("
        SELECT g.id as grade_id, i.indice 
        FROM ref_grades_types g
        JOIN ref_grille_indiciaire i ON g.id = i.grade_type_id
        WHERE i.corps_id = ? 
        AND g.libelle_grade = '2°CLASSE/1°ECHELON'
        LIMIT 1
    ");
    $stmt->execute([$corps_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    if ($result) {
        echo json_encode([
            'success' => true, 
            'grade_id' => $result['grade_id'], 
            'indice' => $result['indice']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Grade non trouvé pour ce corps']);
    }
}
?>
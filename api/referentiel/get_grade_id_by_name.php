<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

$name = $_GET['name'] ?? '';

if (empty($name)) {
    echo json_encode(['id' => null, 'error' => 'Nom de grade manquant']);
    exit;
}

try {
    // On nettoie la base et la recherche : pas d'espaces, tout en majuscules
    $sql = "SELECT id FROM ref_grades_types 
            WHERE UPPER(REPLACE(libelle_grade, ' ', '')) = UPPER(REPLACE(?, ' ', '')) 
            LIMIT 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['id' => $result ? $result['id'] : null]);

} catch (PDOException $e) {
    echo json_encode(['id' => null, 'error' => $e->getMessage()]);
}
?>
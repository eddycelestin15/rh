<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

$corps_id = isset($_GET['corps_id']) ? intval($_GET['corps_id']) : 0;
// Note : vérifiez si votre JS envoie 'grade_id' ou 'grade_type_id'
$grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (isset($_GET['grade_type_id']) ? intval($_GET['grade_type_id']) : 0);

if ($corps_id > 0 && $grade_id > 0) {
    $stmt = $pdo->prepare("SELECT indice FROM ref_grille_indiciaire WHERE corps_id = ? AND grade_type_id = ?");
    $stmt->execute([$corps_id, $grade_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // On renvoie toujours un objet avec une clé indice, même vide
    echo json_encode(['indice' => $result ? $result['indice'] : ""]);
} else {
    echo json_encode(['indice' => ""]);
}
exit;
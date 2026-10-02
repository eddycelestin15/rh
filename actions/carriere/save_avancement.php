<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$im       = $data['im'] ?? '';
$statut   = $data['statut'] ?? '';
$corps    = $data['corps'] ?? '';
$grade    = $data['grade'] ?? '';
$typeacte = $data['type_acte'] ?? '';
$numero   = $data['numero_acte'] ?? '';
$dateacte = $data['date_acte'] ?? '';

if (empty($im) || empty($corps) || empty($grade) || empty($typeacte) || empty($numero) || empty($dateacte)) {
    echo json_encode(['success' => false, 'message' => 'Données incomplètes']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO bin_agent 
    (im_bin, corps_bin, grade_bin, type_acte_bin, numero_acte_bin, date_acte_bin, statut) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$success = $stmt->execute([$im, $corps, $grade, $typeacte, $numero, $dateacte, $statut]);

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Avancement enregistré avec succès' : 'Erreur lors de l\'enregistrement'
]);
?>
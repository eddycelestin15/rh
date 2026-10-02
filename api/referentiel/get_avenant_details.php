<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
$im = $_GET['im'] ?? '';
$grade = $_GET['grade'] ?? '';
$mode = $_GET['mode'] ?? ''; // 'avenant' ou 'contrat'

$res = null;

if ($mode === 'contrat') {
    // Recherche du 3ème contrat (le plus récent de type 'Contrat')
    // On utilise les alias (AS) pour que le JS reçoive toujours les mêmes noms de clés
    $sql = "SELECT 
                av_acte_no AS num_avenant, 
                av_acte_date AS date_avenant, 
                av_date_effet AS date_effet_avenant, 
                av_indice AS indice, 
                av_visa_no AS num_fin_avenant, 
                av_visa_date AS date_fin_avenant, 
                av_ctrl_no AS num_cde_avenant, 
                av_ctrl_date AS date_cde_avenant 
            FROM personnel_avancements 
            WHERE im = ? AND av_type_acte = 'Contrat' 
            ORDER BY av_date_effet DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$im]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Recherche de l'avenant spécifique dans la table personnel_avenant
    // (Assurez-vous que les noms de colonnes ici correspondent à votre table personnel_avenant)
    $sql = "SELECT * FROM personnel_avenant WHERE im = ? AND grade = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$im, $grade]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
}

echo json_encode($res ?: []);
<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
// Champs obligatoires : sans ce controle, les champs absents partaient en base
// sous forme de valeurs nulles (et PHP 8 emettait un avertissement par champ).
require_once __DIR__ . '/../../includes/helpers.php';
exiger_champs($_GET, ['im']);
$im = $_GET['im'];

// Trouve le numéro DCF lié à la région de l'agent
$sql = "SELECT r.num_dcf 
        FROM numero_declaration_regional r
        JOIN personnel_poste_actuel p ON r.nom_region = p.nom_region
        WHERE p.im = ? LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$im]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['num_dcf' => $res ? $res['num_dcf'] : '']);
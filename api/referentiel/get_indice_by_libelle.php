<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
// Champs obligatoires : sans ce controle, les champs absents partaient en base
// sous forme de valeurs nulles (et PHP 8 emettait un avertissement par champ).
require_once __DIR__ . '/../../includes/helpers.php';
exiger_champs($_GET, ['corps_id', 'libelle_grade']);
$corps_id = $_GET['corps_id'];
$libelle = $_GET['libelle_grade'];

$sql = "SELECT g.id as grade_type_id, i.indice 
        FROM ref_grades_types g
        JOIN ref_grille_indiciaire i ON g.id = i.grade_type_id
        WHERE i.corps_id = ? AND g.libelle_grade = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$corps_id, $libelle]);
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: ['indice' => null]);
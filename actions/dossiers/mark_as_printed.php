<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/destinations.php';

header('Content-Type: application/json');

$dest = $_POST['dest'] ?? '';

// Les noms de colonnes viennent d'une correspondance interne : aucune valeur
// transmise par le client n'est injectée telle quelle dans la requête.
$colonnes = destination_colonnes($dest);

if ($colonnes === null) {
    echo json_encode(['success' => false, 'error' => 'Destination inconnue']);
    exit;
}

$colSelection = $colonnes['selection'];
$colImprime   = $colonnes['imprime'];

// Marque comme imprimés les dossiers retenus pour cette destination et pas
// encore imprimés, puis les retire de la sélection.
//
// L'ancienne version utilisait la destination logique (« dren ») comme nom de
// colonne alors que la colonne réelle est « augure_dren » : la requête échouait
// systématiquement avec « Unknown column 'dren' ».
$sql = "UPDATE demandes_numeros_dos
           SET $colImprime = 1,
               $colSelection = 0
         WHERE $colSelection = 1
           AND $colImprime = 0";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    echo json_encode([
        'success'          => true,
        'message'          => 'Bordereau validé',
        'agents_confirmes' => $stmt->rowCount(),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

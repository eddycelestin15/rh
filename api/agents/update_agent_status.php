<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/destinations.php';

header('Content-Type: application/json');

$ids    = $_POST['id']   ?? '';
$action = $_POST['action'] ?? '';
$dest   = $_POST['dest'] ?? '';

if ($ids === '' || $dest === '') {
    echo json_encode(['status' => 'error', 'message' => 'Paramètres manquants']);
    exit;
}

// Le nom de colonne provient d'une correspondance interne, jamais du client.
//
// L'ancienne liste blanche contenait des libellés qui ne sont pas des colonnes
// de demandes_numeros_dos (« dren », « fonction_publique »,
// « solde_et_pensions »…) : la requête échouait avec « Unknown column ».
$colonnes = destination_colonnes($dest);

if ($colonnes === null) {
    echo json_encode(['status' => 'error', 'message' => 'Destination de sélection invalide']);
    exit;
}

$colSelection = $colonnes['selection'];

// Identifiants : uniquement des entiers, dédoublonnés.
$ids_clean = array_values(array_unique(array_filter(
    array_map('intval', explode(',', $ids)),
    static fn ($n) => $n > 0
)));

if (!$ids_clean) {
    echo json_encode(['status' => 'error', 'message' => 'Aucun identifiant valide']);
    exit;
}

// 1 pour ajouter au bordereau, 0 pour retirer.
$valeur = ($action === 'add') ? 1 : 0;

$placeholders = implode(',', array_fill(0, count($ids_clean), '?'));

try {
    $stmt = $pdo->prepare(
        "UPDATE demandes_numeros_dos SET $colSelection = ? WHERE id IN ($placeholders)"
    );
    $stmt->execute(array_merge([$valeur], $ids_clean));

    echo json_encode(['status' => 'success', 'new_status' => $valeur]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

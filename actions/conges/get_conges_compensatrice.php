<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json; charset=utf-8');

// Sécurité basique : uniquement les requêtes GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$im     = trim($_GET['im'] ?? '');
$annees = trim($_GET['annees'] ?? '');

if (empty($im) || empty($annees)) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants (im et annees sont obligatoires)'
    ]);
    exit;
}

// Nettoyage et validation des années
$listeAnnees = array_filter(
    array_map('intval', explode(',', $annees)),
    fn($a) => $a >= 2000 && $a <= 2100
);

if (empty($listeAnnees)) {
    echo json_encode([
        'success' => false,
        'message' => 'Aucune année valide fournie'
    ]);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($listeAnnees), '?'));

    $sql = "
        SELECT 
            annee,
            num_decision,
            date_decision,
            jours_total
        FROM personnel_conges
        WHERE im = ?
          AND annee IN ($placeholders)
        ORDER BY annee ASC
    ";

    $params = array_merge([$im], $listeAnnees);
    $stmt   = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indexation par année pour un accès simple côté JS
    $data = [];
    foreach ($rows as $row) {
        $data[(int)$row['annee']] = [
            'num_decision'  => $row['num_decision'],
            'date_decision' => $row['date_decision'],   
            'jours_total'   => (float)$row['jours_total']
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données : ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}
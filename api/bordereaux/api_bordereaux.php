<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
/**
 * API bordereaux : liste des bordereaux archivés et détail des agents d'un bordereau.
 *
 * Le bloc « details » reprend ce que faisait api_details.php (supprimé, doublon).
 */
require_once __DIR__ . '/../../includes/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? null;

// --- LISTE DES BORDEREAUX ---
if (!$action) {
    header('Content-Type: application/json');

    $type = $_POST['type'] ?? $_GET['type'] ?? 'tous';

    // La colonne de statut de archives_bordereaux_dren est `statut_bordereau`
    // (`statut_dren` appartient à suivi_agents_bordereau).
    $sql = "SELECT id, type_bordereau, numero_complet, date_envoi_bordereau,
                   destination, statut_bordereau AS statut
            FROM archives_bordereaux_dren
            WHERE 1=1";

    $params = [];
    if ($type !== 'tous') {
        $sql .= " AND type_bordereau = ?";
        $params[] = $type;
    }
    $sql .= " ORDER BY date_envoi_bordereau DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode($stmt->fetchAll());
    exit;
}

// --- DÉTAIL DES AGENTS D'UN BORDEREAU ---
if ($action === 'details') {
    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

    // suivi_agents_bordereau ne stocke que l'IM : le nom vient de l'état civil.
    $stmt = $pdo->prepare(
        "SELECT s.im_agent, s.statut_dren, e.nom, e.prenoms
           FROM suivi_agents_bordereau s
           LEFT JOIN personnel_etat_civil e ON e.im = s.im_agent
          WHERE s.id_bordereau = ?
          ORDER BY e.nom, e.prenoms"
    );
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll();

    // Format JSON demandé explicitement, sinon rendu HTML (comportement historique).
    if (($_POST['format'] ?? $_GET['format'] ?? 'html') === 'json') {
        header('Content-Type: application/json');
        echo json_encode($rows);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    $html = "<table border='1' width='100%'>"
          . "<tr><th>IM</th><th>Nom</th><th>Statut</th></tr>";

    foreach ($rows as $r) {
        $nomComplet = trim(($r['nom'] ?? '') . ' ' . ($r['prenoms'] ?? ''));
        $html .= '<tr>'
               . '<td>' . htmlspecialchars($r['im_agent'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
               . '<td>' . htmlspecialchars($nomComplet, ENT_QUOTES, 'UTF-8') . '</td>'
               . '<td>' . htmlspecialchars($r['statut_dren'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
               . '</tr>';
    }

    echo $html . '</table>';
    exit;
}

header('Content-Type: application/json');
http_response_code(400);
echo json_encode(['error' => 'Action inconnue']);

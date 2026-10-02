<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// 1. Récupérer les grades associés à un Corps (via le modele_id)
if ($action === 'get_grades_by_corps') {
    $corps_id = intval($_GET['corps_id'] ?? 0);
    if (!$corps_id) {
        echo json_encode([]);
        exit;
    }

    try {
        // Trouver d'abord le modele_id du corps
        $stmtModel = $pdo->prepare("SELECT modele_id FROM ref_corps WHERE id = ?");
        $stmtModel->execute([$corps_id]);
        $corps = $stmtModel->fetch(PDO::FETCH_ASSOC);
        $modele_id = $corps['modele_id'] ?? null;

        if (!$modele_id) {
            echo json_encode([]);
            exit;
        }

        // Récupérer les grades liés à ce modèle
        $stmtGrades = $pdo->prepare("SELECT code_grade, libelle_grade, id FROM ref_grades_types WHERE modele_id = ? ORDER BY id ASC");
        $stmtGrades->execute([$modele_id]);
        echo json_encode($stmtGrades->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 2. Récupérer l'indice exact correspondant au couple Corps + Grade
if ($action === 'get_indice') {
    $corps_id = intval($_GET['corps_id'] ?? 0);
    $grade_type_id = intval($_GET['grade_type_id'] ?? 0);

    if (!$corps_id || !$grade_type_id) {
        echo json_encode(['indice' => '']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT indice FROM ref_grille_indiciaire WHERE corps_id = ? AND grade_type_id = ? LIMIT 1");
        $stmt->execute([$corps_id, $grade_type_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['indice' => $res['indice'] ?? '']);
    } catch (Exception $e) {
        echo json_encode(['indice' => '']);
    }
    exit;
}
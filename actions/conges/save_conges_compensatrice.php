<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php'; 

$response = [
    'success' => false,
    'message' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Méthode de requête non autorisée.");
    }

    $jsonContent = file_get_contents('php://input');
    $data = json_decode($jsonContent, true);

    if (!$data || empty($data['im']) || empty($data['records']) || !is_array($data['records'])) {
        throw new Exception("Données reçues incomplètes ou invalides.");
    }

    $im = trim($data['im']);
    $records = $data['records'];

    if (count($records) === 0) {
        throw new Exception("Aucune donnée de congé transmise.");
    }

    $pdo->beginTransaction();

    $sqlCheck = "SELECT id FROM personnel_conges WHERE im = :im AND annee = :annee LIMIT 1";
    $stmtCheck = $pdo->prepare($sqlCheck);

    // jours_total reçoit la valeur saisie, nbr_jours_pris est initialisé à 0
    $sqlInsert = "INSERT INTO personnel_conges (im, annee, num_decision, date_decision, jours_total, nbr_jours_pris) 
                  VALUES (:im, :annee, :num_decision, :date_decision, :jours_total, 0.0)";
    $stmtInsert = $pdo->prepare($sqlInsert);

    // Mise à jour de jours_total et réinitialisation à 0 de nbr_jours_pris
    $sqlUpdate = "UPDATE personnel_conges 
                  SET num_decision = :num_decision, 
                      date_decision = :date_decision, 
                      jours_total = :jours_total,
                      nbr_jours_pris = 0.0 
                  WHERE id = :id";
    $stmtUpdate = $pdo->prepare($sqlUpdate);

    foreach ($records as $index => $record) {
        if (empty($record['annee']) || empty($record['numero_decision']) || empty($record['date_decision']) || !isset($record['jours_obtenus'])) {
            throw new Exception("Informations manquantes à la ligne " . ($index + 1) . ".");
        }

        $annee = (int)$record['annee'];
        $numDecision = trim($record['numero_decision']);
        $dateDecision = $record['date_decision'];
        $joursTotal = (float)$record['jours_obtenus'];

        $stmtCheck->execute([':im' => $im, ':annee' => $annee]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmtUpdate->execute([
                ':num_decision'  => $numDecision,
                ':date_decision' => $dateDecision,
                ':jours_total'   => $joursTotal,
                ':id'            => $existing['id']
            ]);
        } else {
            $stmtInsert->execute([
                ':im'            => $im,
                ':annee'         => $annee,
                ':num_decision'  => $numDecision,
                ':date_decision' => $dateDecision,
                ':jours_total'   => $joursTotal
            ]);
        }
    }

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = "Les congés ont été enregistrés avec succès dans personnel_conges.";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = "Erreur BDD : " . $e->getMessage();
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
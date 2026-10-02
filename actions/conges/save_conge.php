<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Fonction de réponse d'erreur unifiée (gère AJAX et formulaires standard)
function repondreErreur($message, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    } else {
        die("Erreur : " . htmlspecialchars($message));
    }
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

exiger_champs($_POST, ['decision_id', 'jours_demande']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['decision_id']);
    $jours = floatval($_POST['jours_demande']);
    $im = $_SESSION['user_im'];

    try {
        $pdo->beginTransaction();

        // 1. Récupération et vérification du solde + présence du numéro de décision
        $stmt = $pdo->prepare("
            SELECT nbr_jours_pris, jours_total, num_decision 
            FROM personnel_conges 
            WHERE id = ? AND im = ?
        ");
        $stmt->execute([$id, $im]);
        $data = $stmt->fetch();

        if (!$data) {
            throw new Exception("Enregistrement introuvable.");
        }

        // 2. VERIFICATION CLÉ : La décision possède-t-elle un numéro attribué ?
        if (is_null($data['num_decision']) || trim($data['num_decision']) === '') {
            throw new Exception("Cette demande est en attente de numéro de décision. Vous ne pouvez pas encore déduire de jours dessus.");
        }

        // 3. Contrôles sur les jours demandés
        $reliquat = floatval($data['jours_total']) - floatval($data['nbr_jours_pris']);

        if ($jours <= 0) {
            throw new Exception("Le nombre de jours doit être supérieur à 0.");
        }
        if ($jours > 15) {
            throw new Exception("La limite autorisée est de 15 jours maximum par prise.");
        }
        if ($jours > $reliquat) {
            throw new Exception("Solde insuffisant (Reste disponible : {$reliquat} j).");
        }

        // 4. Mise à jour des jours pris
        $nouveau_pris = floatval($data['nbr_jours_pris']) + $jours;
        $upd = $pdo->prepare("UPDATE personnel_conges SET nbr_jours_pris = ? WHERE id = ?");
        $upd->execute([$nouveau_pris, $id]);

        $pdo->commit();

        // 5. Réponse
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'success',
                'message' => 'Déduction effectuée avec succès.',
                'jours' => $jours
            ]);
            exit;
        } else {
            rediriger("pages/conges/prendre_conge.php?success=1&jours=$jours");
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        repondreErreur($e->getMessage(), $isAjax);
    }
}
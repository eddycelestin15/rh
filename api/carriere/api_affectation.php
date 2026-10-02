<?php
// api_affectation.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/config.php'; // Fournit la variable $pdo

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ENREGISTREMENT ---
    if ($action === 'ajouter') {
        if (empty($_POST['im']) || empty($_POST['type_acte']) || empty($_POST['num_acte']) || empty($_POST['date_acte']) || empty($_POST['fonction'])) {
            echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
            exit;
        }

        if ($_POST['im'] !== $_SESSION['user_im']) {
            echo json_encode(['status' => 'error', 'message' => 'Action non autorisée.']);
            exit;
        }

        try {
            $sql = "INSERT INTO personnel_affectations (im, type_acte, num_acte, date_acte, fonction, lieu_affectation, localite) 
                    VALUES (:im, :type_acte, :num_acte, :date_acte, :fonction, :lieu_affectation, :localite)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':im'               => $_POST['im'],
                ':type_acte'        => $_POST['type_acte'],
                ':num_acte'         => $_POST['num_acte'],
                ':date_acte'        => $_POST['date_acte'],
                ':fonction'         => $_POST['fonction'],
                ':lieu_affectation' => !empty($_POST['lieu_affectation']) ? $_POST['lieu_affectation'] : null,
                ':localite'         => !empty($_POST['localite']) ? $_POST['localite'] : null
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Affectation enregistrée avec succès !']);
            exit;
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(['status' => 'error', 'message' => 'Cet acte d\'affectation existe déjà pour ce numéro matricule.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Erreur : ' . $e->getMessage()]);
            }
            exit;
        }
    }

    // --- MODIFICATION ---
    if ($action === 'modifier') {
        if (empty($_POST['id']) || empty($_POST['type_acte']) || empty($_POST['num_acte']) || empty($_POST['date_acte']) || empty($_POST['fonction'])) {
            echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
            exit;
        }

        try {
            $sql = "UPDATE personnel_affectations 
                    SET type_acte = :type_acte, num_acte = :num_acte, date_acte = :date_acte, fonction = :fonction, lieu_affectation = :lieu_affectation, localite = :localite 
                    WHERE id = :id AND im = :im";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id'               => $_POST['id'],
                ':im'               => $_SESSION['user_im'],
                ':type_acte'        => $_POST['type_acte'],
                ':num_acte'         => $_POST['num_acte'],
                ':date_acte'        => $_POST['date_acte'],
                ':fonction'         => $_POST['fonction'],
                ':lieu_affectation' => !empty($_POST['lieu_affectation']) ? $_POST['lieu_affectation'] : null,
                ':localite'         => !empty($_POST['localite']) ? $_POST['localite'] : null
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Affectation mise à jour avec succès !']);
            exit;
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Erreur de modification : ' . $e->getMessage()]);
            exit;
        }
    }

    // --- SUPPRESSION ---
    if ($action === 'supprimer') {
        if (empty($_POST['id'])) {
            echo json_encode(['status' => 'error', 'message' => 'ID manquant.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM personnel_affectations WHERE id = ? AND im = ?");
            $stmt->execute([$_POST['id'], $_SESSION['user_im']]);
            echo json_encode(['status' => 'success', 'message' => 'Affectation supprimée avec succès !']);
            exit;
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Erreur de suppression : ' . $e->getMessage()]);
            exit;
        }
    }
}

echo json_encode(['status' => 'error', 'message' => 'Requête invalide.']);
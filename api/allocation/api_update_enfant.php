<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Récupération des données
        $id_enfant = $_POST['id_enfant'] ?? null;
        
        if (!$id_enfant) {
            echo json_encode(['success' => false, 'message' => "ID enfant manquant."]);
            exit;
        }

        $nom = strtoupper($_POST['nom']);
        $prenoms = ucwords(strtolower($_POST['prenoms']));
        $date_naiss = $_POST['date_naiss'];
        $lieu_naiss = $_POST['lieu_naiss'];
        $sexe = $_POST['sexe'];
        $num_copie = $_POST['num_copie'];
        $type_filiation = $_POST['type_filiation'];

        // 2. Vérification de l'âge (doit rester éligible < 20 ans)
        $dateN = new DateTime($date_naiss);
        $aujourdhui = new DateTime();
        $age = $aujourdhui->diff($dateN)->y;

        if ($age >= 19) {
            echo json_encode(['success' => false, 'message' => "L'enfant a $age ans. La date de naissance saisie le rend inéligible."]);
            exit;
        }

        // 3. Requête de mise à jour
        $sql = "UPDATE personnel_enfants SET 
                nom_enfant = ?, 
                prenoms_enfant = ?, 
                date_naiss_enfant = ?, 
                lieu_naiss_enfant = ?, 
                sexe = ?, 
                num_copie_acte = ?, 
                type_filiation = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $nom, 
            $prenoms, 
            $date_naiss, 
            $lieu_naiss, 
            $sexe, 
            $num_copie, 
            $type_filiation, 
            $id_enfant
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => "Informations mises à jour avec succès."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Aucune modification effectuée ou erreur SQL."]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => "Erreur système : " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Méthode non autorisée."]);
}
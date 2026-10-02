<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
// Champs obligatoires : sans ce controle, les champs absents partaient en base
// sous forme de valeurs nulles (et PHP 8 emettait un avertissement par champ).
require_once __DIR__ . '/../../includes/helpers.php';
exiger_champs($_POST, ['im_parent', 'nom', 'prenoms', 'date_naiss', 'lieu_naiss', 'sexe', 'num_copie', 'type_filiation', 'inscrit_sur_bc']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $im_parent = $_POST['im_parent'];
        $nom = strtoupper($_POST['nom']);
        $prenoms = ucwords(strtolower($_POST['prenoms']));
        $date_naiss = $_POST['date_naiss'];
        $lieu_naiss = $_POST['lieu_naiss'];
        $sexe = $_POST['sexe']; // Nouveau champ
        $num_copie = $_POST['num_copie'];
        $type_filiation = $_POST['type_filiation'];
        
        // Logique : OUI = 1, NON = 0
        $inscrit_sur_bc = $_POST['inscrit_sur_bc'];
        $statut_bc = ($inscrit_sur_bc === 'oui') ? 1 : 0;

        // Vérification de l'âge (doit être < 21 ans)
        $dateN = new DateTime($date_naiss);
        $aujourdhui = new DateTime();
        $age = $aujourdhui->diff($dateN)->y;

        if ($age >= 19) {
            echo json_encode(['success' => false, 'message' => "L'enfant a $age ans. Il doit avoir moins de 19 ans pour l'allocation."]);
            exit;
        }

        $sqlUpdateAnciens = "UPDATE personnel_enfants 
                             SET situation = 'non_utilise' 
                             WHERE im_parent = ? AND situation = 'utilise'";
        $stmtUpdate = $pdo->prepare($sqlUpdateAnciens);
        $stmtUpdate->execute([$im_parent]);

        // Requête mise à jour avec sexe et statut_bc
        $sql = "INSERT INTO personnel_enfants (im_parent, nom_enfant, prenoms_enfant, date_naiss_enfant, lieu_naiss_enfant, sexe, num_copie_acte, type_filiation, statut_bc) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $im_parent, 
            $nom, 
            $prenoms, 
            $date_naiss, 
            $lieu_naiss, 
            $sexe, 
            $num_copie, 
            $type_filiation, 
            $statut_bc
        ]);

        if ($result) {
            // MODIFICATION ICI : On récupère le dernier ID inséré pour le renvoyer au JavaScript
            $id_enfant = $pdo->lastInsertId();
            echo json_encode([
                'success' => true, 
                'message' => "Enfant enregistré avec succès.",
                'enfant_id' => $id_enfant
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => "Erreur lors de l'enregistrement."]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => "Erreur système : " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Requête non autorisée."]);
}
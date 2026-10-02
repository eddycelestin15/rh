<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
// Champs obligatoires : sans ce controle les valeurs absentes partaient
// en base sous forme de NULL (et PHP 8 signalait chaque champ manquant).
require_once __DIR__ . '/../../includes/helpers.php';
exiger_champs($_POST, ['agent_date_naiss', 'agent_date_cin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Requête avec ON DUPLICATE KEY UPDATE pour écraser les anciennes données de l'agent
        $sql = "INSERT INTO personnel_certificat_cnaps (
                    im_agent, 
                    agent_nom, agent_prenoms, agent_date_naiss, agent_lieu_naiss, 
                    agent_sous_pref, agent_cin, agent_date_cin, agent_lieu_cin, 
                    agent_pere, agent_mere, agent_adresse, agent_mat_cnaps, agent_service,
                    conjoint_nom, conjoint_prenoms, conjoint_date_naiss, conjoint_lieu_naiss,
                    conjoint_sous_pref, conjoint_cin, conjoint_date_cin, conjoint_lieu_cin,
                    conjoint_pere, conjoint_mere, conjoint_adresse, conjoint_mat_cnaps, conjoint_service
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
                ON DUPLICATE KEY UPDATE 
                    agent_nom = VALUES(agent_nom),
                    agent_prenoms = VALUES(agent_prenoms),
                    agent_date_naiss = VALUES(agent_date_naiss),
                    agent_lieu_naiss = VALUES(agent_lieu_naiss),
                    agent_sous_pref = VALUES(agent_sous_pref),
                    agent_cin = VALUES(agent_cin),
                    agent_date_cin = VALUES(agent_date_cin),
                    agent_lieu_cin = VALUES(agent_lieu_cin),
                    agent_pere = VALUES(agent_pere),
                    agent_mere = VALUES(agent_mere),
                    agent_adresse = VALUES(agent_adresse),
                    agent_mat_cnaps = VALUES(agent_mat_cnaps),
                    agent_service = VALUES(agent_service),
                    conjoint_nom = VALUES(conjoint_nom),
                    conjoint_prenoms = VALUES(conjoint_prenoms),
                    conjoint_date_naiss = VALUES(conjoint_date_naiss),
                    conjoint_lieu_naiss = VALUES(conjoint_lieu_naiss),
                    conjoint_sous_pref = VALUES(conjoint_sous_pref),
                    conjoint_cin = VALUES(conjoint_cin),
                    conjoint_date_cin = VALUES(conjoint_date_cin),
                    conjoint_lieu_cin = VALUES(conjoint_lieu_cin),
                    conjoint_pere = VALUES(conjoint_pere),
                    conjoint_mere = VALUES(conjoint_mere),
                    conjoint_adresse = VALUES(conjoint_adresse),
                    conjoint_mat_cnaps = VALUES(conjoint_mat_cnaps),
                    conjoint_service = VALUES(conjoint_service),
                    date_demande = CURRENT_TIMESTAMP";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            $_SESSION['user_im'],
            // Données Agent
            $_POST['agent_nom'] ?? null,
            $_POST['agent_prenoms'] ?? null,
            ($_POST['agent_date_naiss'] != '') ? $_POST['agent_date_naiss'] : null,
            $_POST['agent_lieu_naiss'] ?? null,
            $_POST['agent_sous_pref'] ?? null,
            $_POST['agent_cin'] ?? null,
            ($_POST['agent_date_cin'] != '') ? $_POST['agent_date_cin'] : null,
            $_POST['agent_lieu_cin'] ?? null,
            $_POST['agent_pere'] ?? null,
            $_POST['agent_mere'] ?? null,
            $_POST['agent_adresse'] ?? null,
            $_POST['agent_mat_cnaps'] ?? null,
            $_POST['agent_service'] ?? null,
            // Données Conjoint
            $_POST['conjoint_nom'] ?? null,
            $_POST['conjoint_prenoms'] ?? null,
            ($_POST['conjoint_date_naiss'] != '') ? $_POST['conjoint_date_naiss'] : null,
            $_POST['conjoint_lieu_naiss'] ?? null,
            $_POST['conjoint_sous_pref'] ?? null,
            $_POST['conjoint_cin'] ?? null,
            ($_POST['conjoint_date_cin'] != '') ? $_POST['conjoint_date_cin'] : null,
            $_POST['conjoint_lieu_cin'] ?? null,
            $_POST['conjoint_pere'] ?? null,
            $_POST['conjoint_mere'] ?? null,
            $_POST['conjoint_adresse'] ?? null,
            $_POST['conjoint_mat_cnaps'] ?? null,
            $_POST['conjoint_service'] ?? null
        ]);

        echo json_encode(['success' => true, 'message' => 'Données mises à jour avec succès.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
    }
}
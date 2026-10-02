<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenoms = trim($_POST['prenoms']);
    $im = trim($_POST['im']);
    $tel = trim($_POST['telephone']);
    $email = trim($_POST['email']);
    $mdp = $_POST['mdp'];
    $mdp_confirm = $_POST['mdp_confirm'];

    // 1. Vérification des mots de passe
    if ($mdp !== $mdp_confirm) {
        die("Erreur : Les mots de passe ne correspondent pas.");
    }

    try {
        // 2. Vérifier si l'IM existe déjà
        $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE im = ?");
        $check->execute([$im]);
        if ($check->rowCount() > 0) {
            die("Erreur : Cet IM est déjà enregistré.");
        }

        // 3. Cryptage et Insertion
        $hash = password_hash($mdp, PASSWORD_DEFAULT);
        
        // Pour un agent, niveau et code_lieu sont souvent remplis plus tard par le RH 
        // ou via sa fiche de poste, donc on met des valeurs par défaut
        $sql = "INSERT INTO utilisateurs (im, nom, prenoms, telephone, email, mot_de_passe, type_compte, role_specifique) 
                VALUES (?, ?, ?, ?, ?, ?, 'agent', 'agent')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$im, $nom, $prenoms, $tel, $email, $hash]);

        // Redirection vers le login avec succès
        rediriger("agent?signup=success");

    } catch (PDOException $e) {
        die("Erreur lors de l'inscription : " . $e->getMessage());
    }
}
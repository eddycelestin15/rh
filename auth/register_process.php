<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/alertes_notifications.php'; // Pour les fonctions d'envoi Email/WhatsApp

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $im = strtoupper(trim($_POST['im'] ?? ''));
    $nom = strtoupper(trim($_POST['nom'] ?? ''));
    $prenoms = trim($_POST['prenoms'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mdp = $_POST['mdp'] ?? ''; 
    $mdp_confirm = $_POST['mdp_confirm'] ?? '';

    // Traitement du téléphone avec +261
    $tel_saisi = trim($_POST['telephone'] ?? '');
    $telephone_final = "+261" . $tel_saisi;

    // --- VALIDATIONS ---    
    if ($mdp !== $mdp_confirm) {
        rediriger("auth/register_agent.php?error=password_mismatch");
    }

    if (strlen($tel_saisi) !== 9) {
        rediriger("auth/register_agent.php?error=invalid_phone");
    }

    // Hachage du mot de passe
    $hash = password_hash($mdp, PASSWORD_DEFAULT);

    // Génération du code de vérification à 6 chiffres
    $code_verification = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    try {
        $pdo->beginTransaction();

        // 1. Vérifier si l'IM existe déjà
        $check = $pdo->prepare("SELECT im FROM utilisateurs WHERE im = ?");
        $check->execute([$im]);
        if ($check->fetch()) {
            $pdo->rollBack();
            rediriger("auth/register_agent.php?error=im_exists");
        }

        // 2. Insertion de l'utilisateur (Compte inactif avec code à 6 chiffres)[cite: 6]
        $sql = "INSERT INTO utilisateurs (
            im, nom, prenoms, telephone, email, 
            mot_de_passe, type_compte, role_specifique,
            code_verification, est_actif
        ) VALUES (?, ?, ?, ?, ?, ?, 'agent', 'agent', ?, 0)";

        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $im, $nom, $prenoms, $telephone_final, $email, $hash, $code_verification
        ]);

        if ($success) {
            $pdo->commit();

            // Envoi du code par Email et WhatsApp[cite: 6]
            sendVerificationCode($email, $telephone_final, $prenoms ?: $nom, $code_verification);

            // Redirection vers la page de validation du code avec un statut pour afficher la modale
            rediriger("auth/verify_code.php?im=" . urlencode($im) . "&status=code_sent");
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Erreur critique : " . $e->getMessage());
    }
} else {
    rediriger("auth/register_agent.php");
}
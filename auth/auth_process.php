<?php
require_once __DIR__ . '/../includes/bootstrap.php';
session_start();
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $im = strtoupper(trim($_POST['im'] ?? ''));
    $mdp = $_POST['mdp'] ?? '';
    $type_form = $_POST['type_connexion'] ?? 'agent';

    if (empty($im) || empty($mdp)) {
        rediriger($type_form . "?error=empty");
    }

    try {
        // Le SELECT * récupère bien la colonne 'photo'[cite: 15, 17]
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE UPPER(im) = ?");
        $stmt->execute([$im]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($mdp, $user['mot_de_passe'])) {
                
                // Vérification de sécurité du rôle[cite: 17]
                if ($type_form === 'admin' && $user['type_compte'] !== 'admin') {
                    rediriger("administrateur?error=access_denied");
                }

                if ($type_form === 'responsable' && !in_array($user['type_compte'], ['responsable', 'admin'])) {
                    rediriger("responsable?error=access_denied");
                }

                // Initialisation session[cite: 17]
                $_SESSION['user_im'] = $user['im'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_prenoms'] = $user['prenoms'];
                $_SESSION['user_type'] = $user['type_compte'];
                $_SESSION['user_role'] = $user['role_specifique'];
                $_SESSION['user_niveau'] = $user['niveau'];
                $_SESSION['user_code_lieu'] = $user['code_lieu_affectation'];
                $_SESSION['user_photo'] = $user['photo']; 

                rediriger("index.php");

            } else {
                $page = ($type_form === 'admin') ? 'administrateur' : (($type_form === 'responsable') ? 'responsable' : 'agent');
                rediriger("$page?error=password");
            }
        } else {
            $page = ($type_form === 'admin') ? 'administrateur' : (($type_form === 'responsable') ? 'responsable' : 'agent');
            rediriger("$page?error=not_found");
        }

    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
}
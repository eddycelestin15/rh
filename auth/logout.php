<?php
require_once __DIR__ . '/../includes/bootstrap.php';
session_start();

// 1. On identifie le type d'utilisateur pour rediriger vers la bonne page de login[cite: 20]
$type = $_SESSION['user_type'] ?? 'agent'; 

// 2. On vide toutes les variables de session (IM, Nom, Photo, etc.)[cite: 20]
$_SESSION = array();

// 3. On détruit physiquement le fichier de session sur le serveur
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 4. Redirection vers vos pages d'accueil (URLs réécrites)[cite: 14, 20]
switch($type) {
    case 'admin':
        rediriger("administrateur");
        break;
    case 'responsable':
        rediriger("responsable");
        break;
    default:
        rediriger("agent");
        break;
}
exit;
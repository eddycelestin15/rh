<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $im = $_SESSION['user_im'];
    // Champs facultatifs du formulaire de profil.
    $tel   = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Gestion de l'upload d'image
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $upload_dir = APP_ROOT . '/uploads/profils/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $file_name = $im . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;
        // En base on conserve un chemin RELATIF : il sert directement d'URL
        // dans les <img src>. Y mettre le chemin disque casserait l'affichage.
        $chemin_relatif = 'uploads/profils/' . $file_name;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET photo = ? WHERE im = ?");
            $stmt->execute([$chemin_relatif, $im]);
        }
    }

    // Mise à jour des autres infos
    $stmt = $pdo->prepare("UPDATE utilisateurs SET telephone = ?, email = ? WHERE im = ?");
    $stmt->execute([$tel, $email, $im]);

    // Retourne à l'index avec le paramètre de page pour rester dans le layout
    rediriger("index.php?page=profil");
}
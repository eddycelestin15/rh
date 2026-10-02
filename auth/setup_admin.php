<?php
require_once __DIR__ . '/../includes/config.php';

// Configuration de l'admin
$im_admin = "ADMIN";
$password_clair = "admin123"; // C'est ce que vous taperez pour vous connecter
$password_hash = password_hash($password_clair, PASSWORD_DEFAULT);

try {
    // 1. On nettoie si un ancien admin existe déjà pour éviter l'erreur "Duplicate entry"
    $pdo->prepare("DELETE FROM utilisateurs WHERE im = ?")->execute([$im_admin]);

    // 2. On insère le nouvel admin
    $sql = "INSERT INTO utilisateurs (
                im, nom, prenoms, telephone, email, 
                mot_de_passe, type_compte, role_specifique, 
                niveau, code_lieu_affectation
            ) VALUES (
                ?, 'ADMINISTRATEUR', 'Principal', '0000000000', 'admin@domaine.mg', 
                ?, 'admin', 'admin', 
                'national', 'CENTRAL'
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$im_admin, $password_hash]);

    echo "<div style='font-family:sans-serif; padding:20px; border:2px solid green; border-radius:10px; max-width:500px; margin:50px auto; text-align:center;'>";
    echo "<h2 style='color:green;'>✅ Admin créé avec succès !</h2>";
    echo "<p>Identifiant : <b>$im_admin</b></p>";
    echo "<p>Mot de passe : <b>$password_clair</b></p>";
    echo "<hr>";
    echo "<p style='color:red;'><b>⚠️ ATTENTION :</b> Supprimez le fichier <code>setup_admin.php</code> de votre dossier maintenant pour des raisons de sécurité.</p>";
    echo "<a href='administrateur' style='display:inline-block; padding:10px 20px; background:#000; color:#fff; text-decoration:none; border-radius:5px;'>Aller à la page de connexion</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json');

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accès restreint']);
    exit;
}

exiger_champs($_POST, ['im', 'nom', 'niveau', 'role_specifique']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $im = strtoupper(trim($_POST['im']));
    $nom = strtoupper(trim($_POST['nom']));
    $prenoms = trim($_POST['prenoms'] ?? '');
    $niveau = $_POST['niveau']; 
    $role = $_POST['role_specifique'];
    
    $nom_lieu = '';

    try {
        if ($niveau === 'central') {
            $nom_lieu = 'ANTANANARIVO';
        } elseif ($niveau === 'regional' && !empty($_POST['region_id'])) {
            $stmt = $pdo->prepare("SELECT nom_region FROM ref_regions WHERE id = ?");
            $stmt->execute([$_POST['region_id']]);
            $res = $stmt->fetch();
            $nom_lieu = $res ? $res['nom_region'] : '';
            
        } elseif ($niveau === 'district' && !empty($_POST['district_id'])) {
            $stmt = $pdo->prepare("SELECT nom_district FROM ref_districts WHERE id = ?");
            $stmt->execute([$_POST['district_id']]);
            $res = $stmt->fetch();
            $nom_lieu = $res ? $res['nom_district'] : '';
            
        } elseif ($niveau === 'crfrp' && !empty($_POST['crfrp_id'])) {
            $stmt = $pdo->prepare("SELECT nom_crfrp FROM ref_crfrp WHERE id = ?");
            $stmt->execute([$_POST['crfrp_id']]);
            $res = $stmt->fetch();
            $nom_lieu = $res ? $res['nom_crfrp'] : '';
        }

        if (empty($nom_lieu)) {
            throw new Exception("Lieu d'affectation invalide ou non sélectionné.");
        }

        // 1. Vérification si le matricule existe déjà dans la base
        $check = $pdo->prepare("SELECT id, type_compte FROM utilisateurs WHERE UPPER(im) = ?");
        $check->execute([$im]);
        $user_existant = $check->fetch(PDO::FETCH_ASSOC);

        if ($user_existant) {
            // L'agent existe déjà : On met à jour son type de compte, son rôle et son affectation
            $sql_update = "UPDATE utilisateurs 
                           SET type_compte = 'responsable', 
                               role_specifique = ?, 
                               niveau = ?, 
                               code_lieu_affectation = ? 
                           WHERE id = ?";
            
            $stmt_update = $pdo->prepare($sql_update);
            $result = $stmt_update->execute([$role, $niveau, $nom_lieu, $user_existant['id']]);

            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'L\'agent existant a été promu Responsable avec succès.'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Échec lors de la mise à jour des droits de l\'agent.']);
            }

        } else {
            // L'utilisateur n'existe pas : Création d'un nouveau compte
            $password_default = password_hash('123456', PASSWORD_DEFAULT);
            
            $sql_insert = "INSERT INTO utilisateurs 
                           (im, nom, prenoms, mot_de_passe, type_compte, role_specifique, niveau, code_lieu_affectation, telephone, est_actif) 
                           VALUES (?, ?, ?, ?, 'responsable', ?, ?, ?, '', 1)";
            
            $stmt_insert = $pdo->prepare($sql_insert);
            $result = $stmt_insert->execute([
                $im, 
                $nom, 
                $prenoms, 
                $password_default, 
                $role, 
                $niveau, 
                $nom_lieu 
            ]);

            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Nouveau responsable enregistré avec succès.'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement en base.']);
            }
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur système : ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
}
?>
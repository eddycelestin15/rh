<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Exiger 'message' et 'destinataire', 'objet' et 'suggestion' deviennent optionnels selon le cas
exiger_champs($_POST, ['destinataire', 'message']);

$dest = $_POST['destinataire']; 
$exp = $_SESSION['user_im'];
$objet = $_POST['objet'] ?? '';
$contenu = $_POST['message'];
$suggestion = $_POST['suggestion'] ?? null; // Récupère la suggestion si présente

try {
    if (strpos($dest, 'tous_') === 0) {
        // Envoi groupé
        $roleCible = str_replace('tous_', '', $dest);
        
        if ($roleCible === 'responsables_region') {
            $sqlF = "SELECT im FROM utilisateurs WHERE type_compte = 'responsable' AND niveau = 'regional'";
        } else {
            $sqlF = "SELECT im FROM utilisateurs WHERE role_specifique = :r";
        }
        
        $stmtF = $pdo->prepare($sqlF);
        if ($roleCible !== 'responsables_region') { $stmtF->bindValue(':r', $roleCible); }
        $stmtF->execute();
        $destinataires = $stmtF->fetchAll(PDO::FETCH_COLUMN);

        // Insertion multiple avec le champ suggestion
        $stmtInsert = $pdo->prepare("INSERT INTO messages (expediteur_im, destinataire_im_ou_role, objet, contenu, suggestion) VALUES (?, ?, ?, ?, ?)");
        foreach ($destinataires as $imDest) {
            $stmtInsert->execute([$exp, $imDest, $objet, $contenu, $suggestion]);
        }
    } else {
        // Envoi simple à un seul IM
        $stmt = $pdo->prepare("INSERT INTO messages (expediteur_im, destinataire_im_ou_role, objet, contenu, suggestion) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$exp, $dest, $objet, $contenu, $suggestion]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
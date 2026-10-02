<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$user_im = $_SESSION['im'] ?? $_SESSION['user_im'] ?? null;
$user_role = $_SESSION['role_specifique'] ?? $_SESSION['type_compte'] ?? 'agent';

if (!$user_im) { die("Erreur de session."); }

// --- 1. ACTION : MARQUAGE COMME LU ---
// On transforme le 0 en 1 pour cet utilisateur. 
// Pour éviter l'erreur de duplicata (1062), on supprime d'abord les anciennes archives (statut 1)
if ($user_role === 'agent') {
    $pdo->prepare("DELETE FROM notifications WHERE im = ? AND est_lu = 1")->execute([$user_im]);
    $pdo->prepare("UPDATE notifications SET est_lu = 1 WHERE im = ? AND est_lu = 0")->execute([$user_im]);
}

// --- 2. LOGIQUE DE DÉTECTION ---
$sqlDetections = "SELECT ps.im, ps.code_corps_actuel, ps.date_entree_admin, u.nom, u.prenoms 
                  FROM personnel_situation_actuelle ps 
                  JOIN utilisateurs u ON ps.im = u.im
                  WHERE 
                  (ps.statut_actuel = 'Contractuel EFA' AND (ps.code_corps_actuel LIKE 'J%' OR ps.code_corps_actuel LIKE 'K%' OR ps.code_corps_actuel LIKE 'L%'))
                  OR (DATEDIFF(CURDATE(), ps.date_entree_admin) >= 365)
                  OR (ps.code_corps_actuel LIKE 'U%')";

$stmt = $pdo->query($sqlDetections);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($agents as $agent) {
    $date_debut = $agent['date_entree_admin'];
    $diff = date_diff(date_create($date_debut), date_create('now'));
    $jours_ecoules = (int)$diff->format('%a');

    $msg = "";
    $type_alerte = "";
    $cle_contrat = ""; // Clé pour identifier si c'est le 1er ou 2eme contrat

    // Calcul selon vos nouvelles conditions
    if ($jours_ecoules >= 540 && $jours_ecoules < 1000) {
        $date_fin = date('d/m/Y', strtotime($date_debut . " + 2 years"));
        $msg = "M/Mme " . htmlspecialchars($agent['nom']) . ", votre premier contrat expirera le " . $date_fin . ". Veuillez passer dès maintenant auprès du responsable du personnel non encadré pour procéder à son renouvellement.";
        $type_alerte = 'contrat';
        $cle_contrat = "premier contrat";
    } 
    elseif ($jours_ecoules >= 1270 && $jours_ecoules < 1800) {
        $date_fin = date('d/m/Y', strtotime($date_debut . " + 4 years"));
        $msg = "M/Mme " . htmlspecialchars($agent['nom']) . ", votre deuxième contrat expirera le " . $date_fin . ". Veuillez passer dès maintenant auprès du responsable du personnel non encadré pour procéder à son renouvellement.";
        $type_alerte = 'contrat';
        $cle_contrat = "deuxième contrat";
    }
    elseif (strpos($agent['code_corps_actuel'], 'U') === 0) {
        $msg = "M/Mme " . htmlspecialchars($agent['nom']) . ", vous devez passer à l'intégration";
        $type_alerte = 'integration';
        $cle_contrat = "intégration";
    }

    // --- INSERTION SANS CRÉER DE NOUVELLE LIGNE INUTILE ---
    if ($msg !== "") {
        // IMPORTANT : On vérifie si une notification existe déjà pour cet IM et ce TYPE de contrat
        // (on cherche dans le message si "premier" ou "deuxième" existe déjà pour cet agent)
        $check = $pdo->prepare("SELECT id FROM notifications WHERE im = ? AND message LIKE ?");
        $check->execute([$agent['im'], "%$cle_contrat%"]);
        
        if ($check->rowCount() == 0) {
            // Si aucune ligne (qu'elle soit lue ou non) n'existe, ALORS on insère
            try {
                $pdo->prepare("INSERT INTO notifications (im, type_alerte, message, est_lu) VALUES (?, ?, ?, 0)")
                    ->execute([$agent['im'], $type_alerte, $msg]);
            } catch (PDOException $e) { /* Doublon ignoré */ }
        }
    }
}

// --- 3. AFFICHAGE (Reste identique) ---
$isResponsable = in_array($user_role, ['chef_service', 'resp_encadre', 'resp_non_encadre']);
if ($isResponsable) {
    $stmtDisplay = $pdo->query("SELECT n.*, u.nom, u.prenoms FROM notifications n JOIN utilisateurs u ON n.im = u.im ORDER BY n.created_at DESC");
} else {
    $stmtDisplay = $pdo->prepare("SELECT n.*, u.nom, u.prenoms FROM notifications n JOIN utilisateurs u ON n.im = u.im WHERE n.im = ? ORDER BY n.created_at DESC");
    $stmtDisplay->execute([$user_im]);
}
$notifications = $stmtDisplay->fetchAll();
?>

<div class="p-8 max-w-4xl mx-auto">
    <h2 class="text-2xl font-black text-slate-800 mb-6 uppercase italic tracking-tighter text-center">Notifications</h2>
    <div class="space-y-4">
        <?php foreach ($notifications as $n): ?>
            <div class="p-5 bg-white rounded-2xl border border-slate-100 flex gap-4 <?php echo $n['est_lu'] ? 'opacity-60' : 'border-l-4 border-l-amber-500'; ?>">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-700 font-semibold"><?php echo htmlspecialchars($n['message']); ?></p>
                    <span class="text-[9px] text-slate-400 font-bold"><?php echo date('d/m/Y', strtotime($n['created_at'])); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
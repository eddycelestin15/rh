<?php
    require_once __DIR__ . '/../../includes/config.php'; 
    require_once __DIR__ . '/../../includes/check_session.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $im = $_GET['im'] ?? $_SESSION['user_im'] ?? '';

    // Récupération facultative des informations de l'agent si la table personnel existe
    $agent = null;
    if ($im) {
        try {
            $stmt = $pdo->prepare("SELECT nom, prenoms FROM personnel_etat_civil WHERE im = ?");
            $stmt->execute([$im]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
    }
?>

<link rel="stylesheet" href="assets/css/historique_conge.css">

<div class="conge-container" style="max-width: 900px; margin: 0 auto; padding: 32px 24px;">
    <!-- Carte d'information de l'agent -->
    <?php if ($im): ?>
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 42px; height: 42px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                <i class="fas fa-user-id-badge"></i>
            </div>
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">
                    <?php if ($agent): ?>
                        <span style="font-weight: 500; color: #475569;"><?= htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']) ?></span>
                    <?php endif; ?>
                </span>
                <div style="font-size: 1.05rem; font-weight: 700; color: #0f172a;">
                    IM : <?= htmlspecialchars($im) ?>                    
                </div>
            </div>
        </div>
        <span style="background: #fef3c7; color: #d97706; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
            <i class="fas fa-clock"></i> En cours de développement
        </span>
    </div>
    <?php endif; ?>

    <!-- Zone du message d'attente / Placeholder -->
    <div style="text-align: center; padding: 48px 24px; background: #ffffff; border: 2px dashed #cbd5e1; border-radius: 16px; margin-top: 10px;">
        <div style="width: 72px; height: 72px; background: #f0f9ff; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);">
            <i class="fas fa-award"></i>
        </div>

        <h4 style="margin: 0 0 10px 0; font-size: 1.35rem; color: #1e293b; font-weight: 700;">
            Module de Demande de Distinction
        </h4>
        
        <p style="color: #64748b; max-width: 520px; margin: 0 auto 24px; font-size: 0.95rem; line-height: 1.6;">
            La demande de distinction honorifique n'est pas encore disponible pour le moment.
        </p>
    </div>
</div>
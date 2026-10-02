<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$im = $_SESSION['user_im'];

try {
    // MODIFICATION ICI : On exige que num_decision ne soit ni NULL ni vide
    $stmt = $pdo->prepare("
        SELECT id, annee, num_decision, jours_total, nbr_jours_pris, 
               (jours_total - nbr_jours_pris) as reliquat_jours 
        FROM personnel_conges 
        WHERE im = ? 
          AND num_decision IS NOT NULL 
          AND TRIM(num_decision) != ''
          AND (jours_total - nbr_jours_pris) > 0
        ORDER BY annee ASC
    ");
    $stmt->execute([$im]);
    $decisions = $stmt->fetchAll();

    $stmtAgent = $pdo->prepare("SELECT nom, prenoms FROM personnel_etat_civil WHERE im = ?");
    $stmtAgent->execute([$im]);
    $agent = $stmtAgent->fetch() ?: ['nom' => '', 'prenoms' => ''];
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="assets/css/prendre_conge.css">
</head>
<body>

<div class="container">
    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>Demande enregistrée : <b><?= htmlspecialchars($_GET['jours']) ?> jours</b> déduits avec succès.</span>
        </div>
    <?php endif; ?>

    <div class="header-white">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="header-icon">
                <i class="fas fa-umbrella-beach"></i>
            </div>
            <h2 style="margin:0; color:var(--primary)">Prendre un congé</h2>
        </div>
        <div class="agent-badge">
            <i class="fas fa-user-circle"></i>
            <span>Agent: <b><?= htmlspecialchars(trim(($agent['nom'] ?? '') . ' ' . ($agent['prenoms'] ?? ''))) ?></b> (IM: <?= htmlspecialchars($im) ?>)</span>
        </div>
    </div>

    <p class="section-title">1. Choisissez la décision à utiliser :</p>
    
    <?php if (empty($decisions)): ?>
        <div class="empty-state" style="padding: 30px; text-align: center; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1; margin-top: 15px;">
            <i class="fas fa-clock" style="font-size: 2.5rem; color: #f59e0b; margin-bottom: 10px;"></i>
            <h3 style="color: #0f172a; margin: 0 0 6px 0;">Aucune décision valide disponible</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0;">
                Vous n'avez aucune décision de congé numérotée avec du solde disponible.<br>
                Si vous avez une demande en cours, veuillez attendre l'attribution de son numéro de décision dans l'historique.
            </p>
        </div>
    <?php else: ?>
        <div class="decision-grid">
            <?php foreach ($decisions as $d): ?>
            <div class="card" onclick="window.selectDecision(this, <?= $d['id'] ?>, <?= $d['reliquat_jours'] ?>, '<?= htmlspecialchars($d['annee']) ?>')">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <p class="card-num">Décision N° <?= htmlspecialchars($d['num_decision']) ?></p>
                    <i class="fas fa-calendar-alt" style="color: var(--slate-light); font-size: 0.9rem;"></i>
                </div>
                <h3 class="year-title"><?= htmlspecialchars($d['annee']) ?></h3>
                <div class="reliquat"><i class="fas fa-bed" style="margin-right: 4px;"></i> Reste : <?= str_replace('.', ',', $d['reliquat_jours']) ?> j</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="form-box" id="formBox">
            <form id="congeForm" action="actions/conges/save_conge.php" method="POST">
                <input type="hidden" name="decision_id" id="decision_id">
                
                <div class="form-group">
                    <label for="jours_input">Nombre de jours à prendre pour l'année <span id="lblYear" style="color:var(--primary)"></span> :</label>
                    <input type="number" name="jours_demande" id="jours_input" step="0.5" min="0.5" placeholder="Ex: 5" required>
                </div>
                
                <p id="limitMsg" class="limit-info"></p>
                <button type="submit" class="btn-go">
                    <i class="fas fa-paper-plane" style="margin-right: 8px;"></i> VALIDER LA DÉDUCTION
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- MODALE DE CONFIRMATION DE DÉDUCTION -->
<div class="modal-bg" id="confirmModal">
    <div class="modal-content">
        <div class="modal-icon question">
            <i class="fas fa-umbrella-beach"></i>
        </div>
        <h3>Confirmation</h3>
        <p style="color: var(--slate); font-size: 0.95rem; margin: 12px 0 24px 0; line-height: 1.5;">
            Voulez-vous vraiment prendre <b id="confirmJoursText" style="color: var(--primary);"></b> jour(s) de congé ?
        </p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button type="button" onclick="window.closeModal('confirmModal')" class="btn-cancel">Annuler</button>
            <button type="button" onclick="window.confirmAndSubmit()" class="btn-confirm">Oui, valider</button>
        </div>
    </div>
</div>

<!-- MODALE D'ERREUR -->
<div class="modal-bg" id="errorModal">
    <div class="modal-content">
        <div class="modal-icon error">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 id="errTitle">Erreur de saisie</h3>
        <p id="errDesc" style="color: var(--slate); font-size: 0.95rem; margin: 12px 0 24px 0; line-height: 1.5;"></p>
        <button type="button" onclick="window.closeModal('errorModal')" class="btn-cancel" style="width: 100%;">Fermer</button>
    </div>
</div>

<script src="assets/js/prendre_conge.js"></script>
</body>
</html>
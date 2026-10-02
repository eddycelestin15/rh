<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$im = $_SESSION['user_im'];

// 1. Récupération de la situation actuelle (Dernier avancement validé)
$querySit = "SELECT a.*, g.id as grade_id, g.modele_id, g.duree_annees 
             FROM personnel_avancements a
             JOIN ref_grades_types g ON a.av_grade = g.libelle_grade
             WHERE a.im = ? 
             ORDER BY a.av_date_effet DESC LIMIT 1";
$stmt = $pdo->prepare($querySit);
$stmt->execute([$im]);
$sit = $stmt->fetch(PDO::FETCH_ASSOC);

$futur_grade = null;
$date_effet_prevue = "";
$futur_indice = "";

if ($sit) {
    // Calcul de la date d'effet prévue
    $date_actuelle = new DateTime($sit['av_date_effet']);
    $duree = !empty($sit['duree_annees']) ? $sit['duree_annees'] : 2; 
    $date_actuelle->modify("+" . $duree . " years");
    $date_effet_prevue = $date_actuelle->format('Y-m-d');

    // 2. Recherche du grade suivant
    $queryNext = "SELECT * FROM ref_grades_types 
                  WHERE modele_id = ? AND id > ? 
                  ORDER BY id ASC LIMIT 1";
    $stmtNext = $pdo->prepare($queryNext);
    $stmtNext->execute([$sit['modele_id'], $sit['grade_id']]);
    $futur_grade = $stmtNext->fetch(PDO::FETCH_ASSOC);
    
    // 3. Récupération de l'indice
    if ($futur_grade) {
        $stmtC = $pdo->prepare("SELECT id FROM ref_corps WHERE libelle_corps = ?");
        $stmtC->execute([$sit['av_corps']]);
        $corps_id = $stmtC->fetchColumn();

        if ($corps_id) {
            $stmtIndice = $pdo->prepare("SELECT indice FROM ref_grille_indiciaire WHERE corps_id = ? AND grade_type_id = ?");
            $stmtIndice->execute([$corps_id, $futur_grade['id']]);
            $futur_indice = $stmtIndice->fetchColumn();
        }
    }
}

function formatDate($date) {
    return ($date && $date !== '0000-00-00') ? date('d/m/Y', strtotime($date)) : '---';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <title>Demande d'Avenant - Consultation</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="p-6 bg-slate-100 font-sans">

    <div class="max-w-4xl mx-auto space-y-6">
        
        <div class="bg-white border-l-4 border-slate-400 rounded-xl shadow-sm overflow-hidden">
            <div class="bg-slate-500 px-4 py-1.5 w-fit">
                <h2 class="text-white text-[10px] font-bold uppercase tracking-widest">Votre Situation Actuelle</h2>
            </div>
            
            <div class="p-5 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Corps</label>
                    <p class="text-sm font-bold text-slate-700"><?= htmlspecialchars($sit['av_corps'] ?? '---') ?></p>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Grade</label>
                    <p class="text-sm font-medium text-slate-600"><?= htmlspecialchars($sit['av_grade'] ?? '---') ?></p>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Indice</label>
                    <p class="text-sm font-bold text-indigo-600"><?= htmlspecialchars($sit['av_indice'] ?? '---') ?></p>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Depuis le</label>
                    <p class="text-sm font-medium text-slate-600"><?= formatDate($sit['av_date_effet'] ?? null) ?></p>
                </div>
            </div>
        </div>

        <form action="traitement_avenant.php" method="POST" class="bg-white border-t-4 border-indigo-600 rounded-xl shadow-lg overflow-hidden">
            <div class="p-8 space-y-8">
                
                <div class="flex items-center justify-between border-b pb-4">
                    <div>
                        <h1 class="text-xl font-bold text-slate-800">Proposition d'Avancement</h1>
                        <p class="text-xs text-slate-500 mt-1">Ces informations sont calculées automatiquement par le système et ne peuvent être modifiées.</p>
                    </div>
                    <i class="fas fa-calculator text-3xl text-slate-200"></i>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase">
                            <i class="fas fa-calendar-check text-indigo-500"></i> Date d'effet prévue
                        </label>
                        <input type="date" name="date_effet_demande" value="<?= $date_effet_prevue ?>" 
                               readonly class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-bold text-slate-700 cursor-not-allowed">
                    </div>

                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase">
                            <i class="fas fa-layer-group text-indigo-500"></i> Type d'acte
                        </label>
                        <input type="text" name="type_avancement" value="AVANCEMENT D'ECHELON" 
                               readonly class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-medium text-slate-700 cursor-not-allowed">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2 space-y-2">
                        <label class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase">
                            <i class="fas fa-id-badge text-indigo-500"></i> Nouveau Grade calculé
                        </label>
                        <input type="text" name="nouveau_grade" value="<?= htmlspecialchars($futur_grade['libelle_grade'] ?? 'ÉCHELON MAXIMUM ATTEINT') ?>" 
                               readonly class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-bold text-slate-800 cursor-not-allowed">
                    </div>
                    
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase">
                            <i class="fas fa-chart-line text-indigo-500"></i> Nouvel Indice
                        </label>
                        <input type="text" name="nouvel_indice" value="<?= $futur_indice ?>" 
                               readonly class="w-full bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-3 text-sm font-black text-indigo-700 cursor-not-allowed text-center">
                    </div>
                </div>

                <?php if ($futur_grade): ?>
                <div class="pt-6 flex flex-col items-center">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white w-full md:w-auto px-10 py-3.5 rounded-full font-bold text-sm uppercase tracking-wider shadow-xl transition-all flex items-center justify-center gap-3">
                        <i class="fas fa-paper-plane"></i> Soumettre cette proposition
                    </button>
                    <p class="text-[10px] text-slate-400 mt-4 italic text-center">En cliquant sur soumettre, vous validez l'exactitude de ces informations calculées.</p>
                </div>
                <?php else: ?>
                <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg text-orange-700 text-center text-sm font-medium">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Vous avez atteint le grade le plus élevé de votre modèle de progression.
                </div>
                <?php endif; ?>
            </div>
        </form>

    </div>
</body>
</html>
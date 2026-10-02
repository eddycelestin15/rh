<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
    $userIm = $_SESSION['user_im'];

    try {
        // On ajoute : AND v.alerte_id NOT LIKE 'HIDDEN_%'
        $sql = "SELECT v.alerte_id, v.titre, v.message, an.date_lecture 
                FROM alertes_notifications an
                JOIN v_moteur_alertes v ON an.alerte_id = v.alerte_id
                WHERE an.im_user = :im
                AND v.alerte_id NOT LIKE 'HIDDEN_%' 
                ORDER BY an.date_lecture DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['im' => $userIm]);
        $archives = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $archives = [];
    }
?>

<div class="max-w-4xl mx-auto animate-fadeIn">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Archives des Notifications</h2>
            <p class="text-slate-500 text-sm">Historique complet de vos alertes consultées.</p>
        </div>
        <div class="bg-sky-100 text-sky-700 px-4 py-2 rounded-lg font-bold">
            Total : <?= count($archives) ?>
        </div>
    </div>

    <?php if (empty($archives)): ?>
        <div class="text-center py-16 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
            <i class="fas fa-folder-open text-slate-300 text-4xl mb-3"></i>
            <p class="text-slate-500 font-medium">Aucune notification archivée pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($archives as $notif): ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-all flex flex-col md:flex-row md:items-center justify-between gap-4 group">
                    <div class="space-y-1.5 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md uppercase">
                                LU LE <?= date('d/m/Y à H:i', strtotime($notif['date_lecture'])) ?>
                            </span>
                        </div>
                        <h3 class="font-extrabold text-slate-800 group-hover:text-sky-600 transition-colors">
                            <?php echo htmlspecialchars($notif['titre'] ?? ''); ?>
                        </h3>
                        <p class="text-slate-600 text-sm leading-relaxed line-clamp-2">
                            <?php echo nl2br(htmlspecialchars($notif['message'] ?? '')); ?>
                        </p>
                    </div>

                    <div class="w-full md:w-auto">
                        <button onclick="loadPage('pages/notifications/detail_notification.php?alerte_id=<?php echo urlencode($notif['alerte_id'] ?? ''); ?>', 'Régularisation')" 
                                class="w-full md:w-auto whitespace-nowrap px-4 py-2 bg-slate-100 hover:bg-sky-600 text-slate-600 hover:text-white rounded-xl text-xs font-black transition-all flex items-center justify-center gap-2">
                            VOIR LE DÉTAILS <i class=\"fas fa-chevron-right\"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }\r\n    to { opacity: 1; transform: translateY(0); }\r\n}
.animate-fadeIn { animation: fadeIn 0.4s ease-out forwards; }
</style>
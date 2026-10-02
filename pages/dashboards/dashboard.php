<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
// 1. Initialisation de la session pour AJAX
if (session_status() === PHP_SESSION_NONE) { 
}

// 2. Connexion à la base de données

// 3. Récupération sécurisée du matricule
$im_user = $_SESSION['user_im'] ?? ''; 

if (empty($im_user)) {
    die("<div class='p-8 text-center'><div class='bg-red-50 text-red-500 p-6 rounded-[2rem] inline-block font-bold italic'>
        <i class='fas fa-exclamation-circle mr-2'></i>Session expirée.</div></div>");
}

/**
 * FONCTION DE NORMALISATION
 * Permet de comparer "2ème Échelon" et "2eme echelon" sans erreur
 */
function normalizeGradeName($name) {
    $name = mb_strtoupper($name, 'UTF-8');
    $name = str_replace(['È', 'É', 'Ê', 'Ë'], 'E', $name);
    // Supprime tout ce qui n'est pas lettre ou chiffre
    return preg_replace('/[^A-Z0-9]/', '', $name);
}

// --- LOGIQUE DE DÉTECTION DU CORPS ---
$col_corps = "";
$check_cols = $conn->query("SHOW COLUMNS FROM utilisateurs");
while($col = $check_cols->fetch_assoc()){
    if(in_array($col['Field'], ['corps_id', 'id_corps', 'corps'])) { 
        $col_corps = $col['Field']; 
        break; 
    }
}

$modele_id = null;
$corps_id = null;
if (!empty($col_corps)) {
    $sqlUser = "SELECT u.*, c.modele_id, c.id as ref_corps_id 
                FROM utilisateurs u 
                LEFT JOIN ref_corps c ON u.$col_corps = c.id 
                WHERE u.im = '$im_user' LIMIT 1";
    $resUser = $conn->query($sqlUser);
    if($userInfos = $resUser->fetch_assoc()){
        $modele_id = $userInfos['modele_id'];
        $corps_id = $userInfos['ref_corps_id'];
    }
}

// 4. Calcul du solde de congés
$resConges = $conn->query("SELECT SUM(jours_total) as total, SUM(nbr_jours_pris) as pris FROM personnel_conges WHERE im = '$im_user'");
$dataConges = $resConges->fetch_assoc();
$total_droits = $dataConges['total'] ?? 0;
$total_pris = $dataConges['pris'] ?? 0;
$solde_actuel = $total_droits - $total_pris;
$pourcentage_restant = ($total_droits > 0) ? ($solde_actuel / $total_droits) * 100 : 0;

// 5. RÉCUPÉRATION DE L'HISTORIQUE RÉEL
$reels = [];
$resAv = $conn->query("SELECT * FROM personnel_avancements WHERE im = '$im_user' ORDER BY av_date_effet ASC");
$premier_acte_reel = null;
$dernier_acte_reel = null;

while($row = $resAv->fetch_assoc()){
    $normKey = normalizeGradeName($row['av_type_acte']);
    $reels[$normKey] = $row;
    if (!$premier_acte_reel) $premier_acte_reel = $row;
    $dernier_acte_reel = $row;
}

// 6. CONSTRUCTION DES DONNÉES (LOGIQUE DES BARRES JAUNES)
$dates = [];
$indices = [];
$colors = [];

if ($premier_acte_reel && $modele_id && $corps_id) {
    // Récupération de la grille de progression légale
    $sqlGrille = "SELECT gt.libelle_grade, gt.duree_annees, gi.indice
                  FROM ref_grades_types gt
                  JOIN ref_grille_indiciaire gi ON gt.id = gi.grade_type_id
                  WHERE gt.modele_id = $modele_id AND gi.corps_id = $corps_id
                  ORDER BY gt.id ASC";
    $resGrille = $conn->query($sqlGrille);

    $current_date = strtotime($premier_acte_reel['av_date_effet']);
    $found_start = false;

    while($grade = $resGrille->fetch_assoc()) {
        $lib_theo_norm = normalizeGradeName($grade['libelle_grade']);
        $lib_start_norm = normalizeGradeName($premier_acte_reel['av_type_acte']);

        // On commence le graphique seulement au grade d'entrée de l'agent
        if (!$found_start && $lib_theo_norm !== $lib_start_norm) continue;
        $found_start = true;

        if (isset($reels[$lib_theo_norm])) {
            // BLEU : L'acte existe dans la base
            $dates[] = date('d/m/Y', strtotime($reels[$lib_theo_norm]['av_date_effet']));
            $current_date = strtotime($reels[$lib_theo_norm]['av_date_effet']); // Recalage sur le réel
            $colors[] = 'rgba(14, 165, 233, 0.7)'; 
        } else {
            // JAUNE : Acte manquant ou futur (théorique)
            $dates[] = date('d/m/Y', $current_date);
            $colors[] = 'rgba(251, 191, 36, 0.8)'; 
        }

        $indices[] = (int)$grade['indice'];
        $duree = (int)($grade['duree_annees'] ?? 2);
        $current_date = strtotime("+$duree years", $current_date);
        
        // On s'arrête si on dépasse 3 ans dans le futur pour ne pas surcharger
        if ($current_date > strtotime("+3 years") && !isset($reels[$lib_theo_norm])) break;
    }
}

// Fallback si la logique complexe échoue
if(empty($dates)) { 
    $resBackup = $conn->query("SELECT av_date_effet, av_indice FROM personnel_avancements WHERE im = '$im_user' ORDER BY av_date_effet ASC");
    while($r = $resBackup->fetch_assoc()){
        $dates[] = date('d/m/Y', strtotime($r['av_date_effet']));
        $indices[] = (int)$r['av_indice'];
        $colors[] = 'rgba(14, 165, 233, 0.7)';
    }
}
if(empty($dates)) { $dates = ['Aucun']; $indices = [0]; $colors = ['#cbd5e1']; }
?>

<div class="p-6 space-y-6 animate-pop-in">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-[2rem] p-6 shadow-sm border border-slate-100 flex items-center gap-6">
            <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-file-signature"></i></div>
            <div class="text-left">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Dernier Avancement</p>
                <h3 class="text-lg font-black text-slate-800 truncate w-48"><?php echo ($dernier_acte_reel) ? $dernier_acte_reel['av_type_acte'] : 'Aucun'; ?></h3>
                <p class="text-[9px] text-slate-400 italic"><?php echo ($dernier_acte_reel) ? "Indice : " . $dernier_acte_reel['av_indice'] : 'Non renseigné'; ?></p>
            </div>
        </div>
        <div class="bg-white rounded-[2rem] p-6 shadow-sm border border-slate-100 flex items-center gap-6">
            <div class="w-16 h-16 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center text-2xl"><i class="fas fa-calendar-check"></i></div>
            <div class="flex-1 text-left">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Solde Congés</p>
                <h3 class="text-3xl font-black text-slate-800"><?php echo $solde_actuel; ?> <span class="text-sm font-bold text-slate-400">Jours</span></h3>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-2 overflow-hidden">
                    <div class="bg-emerald-500 h-full transition-all duration-500" style="width: <?php echo $pourcentage_restant; ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[2.5rem] p-8 shadow-sm border border-slate-100 text-left">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-lg font-black text-slate-800 uppercase tracking-tighter">Histogramme de Carrière</h3>
                <p class="text-xs text-slate-400 font-bold">
                    <span class="text-sky-500">■</span> Enregistré 
                    <span class="text-amber-400 ml-3">■</span> Retard / Prévision (théorique)
                </p>
            </div>
            <i class="fas fa-chart-bar text-sky-500 text-2xl"></i>
        </div>
        <div style="position: relative; height:350px; width:100%;">
            <canvas id="avancementChart"></canvas>
        </div>
    </div>
</div>

<script>
(function() {
    // On attend que Chart.js soit prêt (chargé dans index.php)
    let checkInterval = setInterval(() => {
        const canvas = document.getElementById('avancementChart');
        if (canvas && typeof Chart !== 'undefined') {
            clearInterval(checkInterval);
            renderMyChart(canvas);
        }
    }, 100);

    function renderMyChart(canvas) {
        const ctx = canvas.getContext('2d');
        if (window.myAvancementChart instanceof Chart) { window.myAvancementChart.destroy(); }

        window.myAvancementChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Indice',
                    data: <?php echo json_encode($indices); ?>,
                    backgroundColor: <?php echo json_encode($colors); ?>,
                    borderRadius: 8,
                    barPercentage: 0.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: false, title: { display: true, text: 'Indice' } },
                    x: { title: { display: true, text: 'Date d\'effet' } }
                },
                animation: {
                    onComplete: function() {
                        const chartInstance = this;
                        const ctx = chartInstance.ctx;
                        ctx.font = "bold 12px sans-serif";
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        this.data.datasets.forEach(function(dataset, i) {
                            const meta = chartInstance.getDatasetMeta(i);
                            meta.data.forEach(function(bar, index) {
                                ctx.fillStyle = dataset.backgroundColor[index];
                                ctx.fillText(dataset.data[index], bar.x, bar.y - 5);
                            });
                        });
                    }
                }
            }
        });
    }
})();
</script>
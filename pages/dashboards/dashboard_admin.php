<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

// --- SÉCURITÉ ---
// On vérifie le type_compte selon votre table utilisateurs
if ($_SESSION['user_type'] !== 'admin') {
    echo "<div class='flex items-center p-8 bg-rose-50 text-rose-700 rounded-3xl border border-rose-100'>
            <i class='fas fa-exclamation-triangle mr-3'></i>
            <span class='font-bold uppercase text-xs tracking-widest'>Accès refusé : Espace réservé à l'administrateur.</span>
          </div>";
    exit;
}

// --- RÉCUPÉRATION DES STATS ---
try {
    // Total dans la table personnel_fpe
    $totalFpe = $pdo->query("SELECT COUNT(*) FROM personnel_fpe")->fetchColumn();
    // Nombre d'utilisateurs inscrits dans le système
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    // Nombre de CISCO différentes importées
    $nbCisco = $pdo->query("SELECT COUNT(DISTINCT nom_cisco) FROM personnel_fpe")->fetchColumn();
} catch (Exception $e) {
    $totalFpe = $totalUsers = $nbCisco = 0;
}
?>

<div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight uppercase">Administration Système</h2>
            <p class="text-slate-500 text-sm font-medium italic">Configuration globale et maintenance des données</p>
        </div>

        <div class="flex items-center p-2 bg-white rounded-[2rem] shadow-sm border border-slate-100">
            <input type="file" id="excelFileInput" accept=".xlsx, .xls" class="hidden" onchange="handleImport(this)">
            <button onclick="document.getElementById('excelFileInput').click()" id="btnImport" 
                    class="flex items-center gap-3 bg-slate-900 hover:bg-rose-600 text-white px-6 py-3 rounded-[1.5rem] font-bold text-[10px] tracking-widest transition-all shadow-xl shadow-slate-200 group">
                <i class="fas fa-file-excel text-rose-400 group-hover:text-white transition-colors"></i>
                IMPORTER PERSONNEL_FPE (.XLSX)
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-50 relative overflow-hidden group">
            <div class="relative z-10">
                <div class="w-12 h-12 bg-sky-50 text-sky-500 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-database text-xl"></i>
                </div>
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Lignes Personnel FPE</p>
                <h3 class="text-4xl font-black text-slate-800 mt-1"><?php echo number_format($totalFpe, 0, '.', ' '); ?></h3>
            </div>
            <i class="fas fa-file-csv absolute -bottom-4 -right-4 text-7xl text-slate-50 opacity-50"></i>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-50 relative overflow-hidden group">
            <div class="relative z-10">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-users-cog text-xl"></i>
                </div>
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Comptes Utilisateurs</p>
                <h3 class="text-4xl font-black text-slate-800 mt-1"><?php echo $totalUsers; ?></h3>
            </div>
            <i class="fas fa-users absolute -bottom-4 -right-4 text-7xl text-slate-50 opacity-50"></i>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-50 relative overflow-hidden group">
            <div class="relative z-10">
                <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <i class="fas fa-map-marked-alt text-xl"></i>
                </div>
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Circonscriptions (CISCO)</p>
                <h3 class="text-4xl font-black text-slate-800 mt-1"><?php echo $nbCisco; ?></h3>
            </div>
            <i class="fas fa-map absolute -bottom-4 -right-4 text-7xl text-slate-50 opacity-50"></i>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-[#0f172a] p-8 rounded-[3rem] text-white shadow-2xl">
            <h4 class="text-sm font-black uppercase tracking-widest mb-6 text-sky-400">Journal du Système</h4>
            <div class="space-y-4">
                <div class="flex items-center gap-4 p-4 bg-white/5 rounded-2xl border border-white/10">
                    <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                    <div class="text-xs">
                        <p class="font-bold">Base de données opérationnelle</p>
                        <p class="text-slate-400 text-[10px] mt-1">Dernière vérification : <?php echo date('H:i'); ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-4 bg-white/5 rounded-2xl border border-white/10 opacity-60">
                    <div class="w-2 h-2 bg-slate-500 rounded-full"></div>
                    <div class="text-xs">
                        <p class="font-bold">Sauvegarde automatique prévue</p>
                        <p class="text-slate-400 text-[10px] mt-1">À 00h00 chaque jour</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[3rem] border border-slate-100 flex flex-col justify-center items-center text-center">
            <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-trash-alt text-2xl"></i>
            </div>
            <h4 class="font-bold text-slate-800">Zone de Danger</h4>
            <p class="text-slate-400 text-xs mt-2 mb-6">Action irréversible sur les données importées</p>
            <button onclick="if(confirm('Voulez-vous vraiment vider la table personnel_fpe ?')) { /* Action ici */ }" 
                    class="text-[10px] font-black text-rose-500 border-2 border-rose-100 px-6 py-2 rounded-xl hover:bg-rose-500 hover:text-white transition-all">
                RÉINITIALISER LA TABLE FPE
            </button>
        </div>
    </div>
</div>
<div id="modalStats" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden animate-in zoom-in duration-300">
        <div class="p-8 bg-gradient-to-r from-emerald-500 to-teal-600 text-white flex justify-between items-center">
            <div>
                <h3 class="text-xl font-black uppercase tracking-tight">Importation Terminée</h3>
                <p class="text-emerald-100 text-xs font-medium">Résumé des données injectées</p>
            </div>
            <div class="bg-white/20 p-3 rounded-2xl">
                <i class="fas fa-check-double text-2xl"></i>
            </div>
        </div>

        <div class="p-8 max-h-[60vh] overflow-y-auto space-y-6">
            <div class="text-center bg-slate-50 p-6 rounded-3xl border border-slate-100">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Total Importé</p>
                <h4 id="resTotal" class="text-5xl font-black text-slate-800">0</h4>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h5 class="text-[10px] font-black text-slate-400 uppercase mb-3 flex items-center gap-2">
                        <i class="fas fa-user-tag text-emerald-500"></i> Par Statut
                    </h5>
                    <div id="resStatuts" class="space-y-2 text-xs font-bold text-slate-600"></div>
                </div>
                <div>
                    <h5 class="text-[10px] font-black text-slate-400 uppercase mb-3 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-sky-500"></i> Par Cisco
                    </h5>
                    <div id="resCiscos" class="space-y-2 text-xs font-bold text-slate-600"></div>
                </div>
            </div>
        </div>

        <div class="p-6 bg-slate-50 border-t border-slate-100 text-right">
            <button onclick="closeModal()" class="bg-slate-900 text-white px-8 py-3 rounded-2xl font-bold text-xs hover:bg-slate-800 transition-all uppercase tracking-widest">
                Terminer
            </button>
        </div>
    </div>
</div>

<script>
function handleImport(input) {
    if (!input.files || input.files.length === 0) return;
    
    const btn = document.getElementById('btnImport');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner animate-spin"></i> TRAITEMENT...';

    const formData = new FormData();
    formData.append('file_excel', input.files[0]);

    fetch('actions/personnel/import_fpe.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            showStatsModal(data.stats);
        } else {
            alert("Erreur: " + data.message);
        }
    })
    .catch(err => alert("Erreur réseau (vérifiez la taille du fichier ou le timeout PHP)"))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}

function showStatsModal(stats) {
    document.getElementById('resTotal').innerText = stats.total;
    
    // Remplissage Statuts
    const containerStatuts = document.getElementById('resStatuts');
    containerStatuts.innerHTML = Object.entries(stats.statuts)
        .map(([name, count]) => `<div class="flex justify-between p-3 bg-white border border-slate-100 rounded-xl"><span>${name}</span><span class="text-emerald-500">${count}</span></div>`)
        .join('');

    // Remplissage Ciscos
    const containerCiscos = document.getElementById('resCiscos');
    containerCiscos.innerHTML = Object.entries(stats.ciscos)
        .map(([name, count]) => `<div class="flex justify-between p-3 bg-white border border-slate-100 rounded-xl"><span>${name}</span><span class="text-sky-500">${count}</span></div>`)
        .join('');

    document.getElementById('modalStats').classList.remove('hidden');
    document.getElementById('modalStats').classList.add('flex');
}

function closeModal() {
    document.getElementById('modalStats').classList.add('hidden');
    loadPage('pages/dashboards/dashboard_admin.php', 'Administration'); // Rafraîchir les stats du dashboard
}
</script>
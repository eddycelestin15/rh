<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
$userRole = $_SESSION['user_role'] ?? '';
$isAdmin = ($userRole === 'admin');
$anneeCourante = date('Y');
$anneeProchaine = $anneeCourante + 1;

try {
    // STATS GLOBALES
    $total = $pdo->query("SELECT COUNT(*) FROM personnel_fpe")->fetchColumn() ?: 0;
    
    // COMPTAGE DES AGENTS À RÉGULARISER (IM UNIQUES de v_moteur_alertes)
    $sqlAlertes = "SELECT COUNT(DISTINCT im) FROM v_moteur_alertes WHERE date_reception_technique <= CURRENT_DATE";
    $nbAgentsARegulariser = $pdo->query($sqlAlertes)->fetchColumn() ?: 0;

    // RETRAITES
    $retraiteCetteAnnee = $pdo->query("SELECT COUNT(*) FROM personnel_fpe WHERE date_naissance IS NOT NULL AND YEAR(date_naissance) = ($anneeCourante - 60)")->fetchColumn() ?: 0;
    $retraiteAnneeProch = $pdo->query("SELECT COUNT(*) FROM personnel_fpe WHERE date_naissance IS NOT NULL AND YEAR(date_naissance) = ($anneeCourante - 59)")->fetchColumn() ?: 0;

    // ADMINS PAR STRUCTURE
    $adminDren = $pdo->query("SELECT COUNT(*) FROM personnel_fpe WHERE bureau_d = 1")->fetchColumn() ?: 0;
    $adminZap  = $pdo->query("SELECT COUNT(*) FROM personnel_fpe WHERE bureau_z = 1")->fetchColumn() ?: 0;
    $crfrp     = $pdo->query("SELECT COUNT(*) FROM personnel_fpe WHERE crfrp = 1")->fetchColumn() ?: 0;
    
    // DÉTAIL CISCO
    $ciscoStats = $pdo->query("SELECT nom_cisco, COUNT(*) as nb_admin FROM personnel_fpe WHERE bureau_c = 1 GROUP BY nom_cisco")->fetchAll();

} catch (Exception $e) { die("Erreur SQL : " . $e->getMessage()); }
?>

<div class="h-full flex flex-col gap-4 p-4 animate-in fade-in duration-500" id="dashboard-view">
    
    <div class="flex justify-end items-center px-2">
        <?php if($isAdmin): ?>
        <button onclick="document.getElementById('excelFileInput').click()" class="bg-slate-900 text-white px-5 py-2 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-sky-600 transition-all flex items-center gap-2 shadow-sm">
            <i class="fas fa-file-import text-sky-400"></i> Importer Données
        </button>
        <input type="file" id="excelFileInput" class="hidden" accept=".csv" onchange="handleImport(this)">
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div onclick="showList('all', 'Effectif Global')" class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm cursor-pointer hover:border-slate-400 transition-all group">
            <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Effectif Global</p>
            <div class="flex justify-between items-end">
                <i class="fas fa-users text-2xl text-slate-200 group-hover:text-slate-500"></i>
                <h3 class="text-3xl font-black text-slate-800"><?php echo $total; ?></h3>
            </div>
        </div>

        <div onclick="showList('alertes_all', 'Agents à régulariser (Retards)')" class="bg-rose-50 p-5 rounded-xl border-2 border-rose-100 shadow-sm cursor-pointer hover:border-rose-500 transition-all group animate-pulse hover:animate-none">
            <p class="text-[10px] font-bold text-rose-600 uppercase mb-1">Besoin de Régularisation</p>
            <div class="flex justify-between items-end">
                <i class="fas fa-exclamation-circle text-2xl text-rose-200 group-hover:text-rose-500"></i>
                <h3 class="text-3xl font-black text-rose-700"><?php echo $nbAgentsARegulariser; ?></h3>
            </div>
        </div>

        <div onclick="showList('retraite_now', 'Départs Retraite <?php echo $anneeCourante; ?>')" class="bg-amber-50 p-5 rounded-xl border border-amber-100 shadow-sm cursor-pointer hover:border-amber-500 transition-all group">
            <p class="text-[10px] font-bold text-amber-600 uppercase mb-1">Retraite <?php echo $anneeCourante; ?></p>
            <div class="flex justify-between items-end">
                <i class="fas fa-user-clock text-2xl text-amber-200 group-hover:text-amber-500"></i>
                <h3 class="text-3xl font-black text-amber-700"><?php echo $retraiteCetteAnnee; ?></h3>
            </div>
        </div>

        <div onclick="showList('retraite_next', 'Anticipation Retraite <?php echo $anneeProchaine; ?>')" class="bg-sky-600 p-5 rounded-xl shadow-lg cursor-pointer hover:bg-sky-700 transition-all group">
            <p class="text-[10px] font-bold text-sky-100 uppercase mb-1">Anticipation <?php echo $anneeProchaine; ?></p>
            <div class="flex justify-between items-end">
                <i class="fas fa-hourglass-half text-2xl text-sky-400 opacity-50"></i>
                <h3 class="text-3xl font-black text-white"><?php echo $retraiteAnneeProch; ?></h3>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div onclick="showList('bureau_d', 'Direction (DREN)')" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm cursor-pointer hover:border-sky-500 transition-all flex items-center justify-between group">
            <div class="w-12 h-12 bg-sky-50 rounded-xl flex items-center justify-center text-sky-500 group-hover:bg-sky-500 group-hover:text-white transition-all">
                <i class="fas fa-building text-xl"></i>
            </div>
            <div class="text-right">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Bureau DREN</p>
                <h4 class="text-2xl font-black text-slate-800"><?php echo $adminDren; ?></h4>
            </div>
        </div>

        <div onclick="showList('bureau_z', 'Bureaux ZAP')" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm cursor-pointer hover:border-emerald-500 transition-all flex items-center justify-between group">
            <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-all">
                <i class="fas fa-map-marked-alt text-xl"></i>
            </div>
            <div class="text-right">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Bureaux ZAP</p>
                <h4 class="text-2xl font-black text-slate-800"><?php echo $adminZap; ?></h4>
            </div>
        </div>

        <div onclick="showList('crfrp', 'Centre Régional (CRFRP)')" class="bg-indigo-600 p-6 rounded-2xl text-white shadow-lg cursor-pointer hover:bg-indigo-700 transition-all flex items-center justify-between group">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-white">
                <i class="fas fa-graduation-cap text-xl"></i>
            </div>
            <div class="text-right">
                <p class="text-[9px] font-black opacity-60 uppercase tracking-widest">CRFRP</p>
                <h4 class="text-2xl font-black"><?php echo $crfrp; ?></h4>
            </div>
        </div>
    </div>

    <div class="bg-white border border-slate-100 rounded-xl shadow-sm flex-1 flex flex-col min-h-0 overflow-hidden">
        <div class="p-4 border-b border-slate-50 bg-slate-50/30 flex justify-between items-center">
            <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-700">
                <i class="fas fa-sitemap text-sky-600 mr-2"></i> Administratifs par CISCO
            </h3>
        </div>
        <div class="p-4 overflow-y-auto custom-scrollbar">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <?php foreach($ciscoStats as $cs): ?>
                <div onclick="showList('cisco_<?php echo urlencode($cs['nom_cisco']); ?>', 'Admin : <?php echo $cs['nom_cisco']; ?>')" 
                     class="bg-slate-50 p-3 rounded-xl border border-transparent hover:border-sky-300 hover:bg-white transition-all cursor-pointer group flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-500 uppercase truncate"><?php echo $cs['nom_cisco']; ?></span>
                    <span class="text-lg font-black text-slate-800 group-hover:text-sky-600"><?php echo $cs['nb_admin']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div id="list-view" class="hidden h-full flex flex-col animate-in slide-in-from-right duration-500 p-4">
    <div class="bg-white rounded-xl shadow-xl border border-slate-100 flex flex-col h-full overflow-hidden">
        <div class="p-4 border-b flex items-center gap-4 bg-white shrink-0">
            <button onclick="closeList()" class="w-8 h-8 bg-slate-100 rounded-lg hover:bg-slate-900 hover:text-white transition-all">
                <i class="fas fa-arrow-left text-xs"></i>
            </button>
            <h2 id="list-title" class="text-sm font-black uppercase italic text-slate-800"></h2>
            <div class="relative flex-grow max-w-xs ml-auto">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="searchTable" onkeyup="instantSearch()" placeholder="Rechercher un agent..." class="w-full bg-slate-50 border border-slate-200 rounded-lg py-2 pl-9 pr-4 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none">
            </div>
        </div>
        <div id="list-content" class="flex-1 overflow-auto p-4 bg-slate-50/30 custom-scrollbar"></div>
        <div id="list-pagination" class="px-4 py-3 bg-white border-t flex justify-center items-center"></div>
    </div>
</div>
<div id="form-view" class="hidden animate__animated animate__fadeIn">
    <div class="flex items-center gap-4 mb-6">
        <button onclick="closeForm()" class="bg-slate-100 p-3 rounded-xl hover:bg-rose-100 text-slate-500 hover:text-rose-600 transition-all">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Dossier de régularisation</h2>
    </div>
    
    <div id="form-container" class="bg-white rounded-3xl shadow-xl p-2">
        <div class="flex justify-center p-20">
            <i class="fas fa-spinner fa-spin text-3xl text-rose-600"></i>
        </div>
    </div>
</div>

<script>
function ouvrirFormulaire(im, alerteId) {
    // Masquer le dashboard et la liste
    document.getElementById('dashboard-view').classList.add('hidden');
    document.getElementById('list-view').classList.add('hidden');
    
    // Afficher la vue formulaire
    const formView = document.getElementById('form-view');
    const container = document.getElementById('form-container');
    formView.classList.remove('hidden');
    
    // Loader
    container.innerHTML = '<div class="flex justify-center p-20"><i class="fas fa-spinner fa-spin text-3xl text-rose-600"></i></div>';

    // Appel AJAX vers votre fichier formulaire_demande.php
    fetch(`formulaire_demande.php?im=${im}&alerte_id=${alerteId}`)
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
            
            // Re-déclencher les scripts contenus dans le formulaire chargé (si nécessaire)
            const scripts = container.querySelectorAll("script");
            scripts.forEach(oldScript => {
                const newScript = document.createElement("script");
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        });
}

function closeForm() {
    document.getElementById('form-view').classList.add('hidden');
    // On retourne à la liste (list-view) au lieu du dashboard pour ne pas perdre sa recherche
    document.getElementById('list-view').classList.remove('hidden');
}
////////////////////////////////////////
function changePage(type, pageNum) {
    const content = document.getElementById('list-content');
    const pagination = document.getElementById('list-pagination');
    
    content.innerHTML = '<div class="p-10 text-center text-[10px] font-bold uppercase animate-pulse">Chargement des agents...</div>';
    
    fetch(`fetch_liste_data.php?type=${type}&page=${pageNum}`)
        .then(res => res.text())
        .then(html => {
            // On sépare le tableau de la pagination
            const parts = html.split('###PAGINATION###');
            content.innerHTML = parts[0];
            
            if (parts[1]) {
                // On rend la pagination plus compacte techniquement
                let cleanPagination = parts[1].replace(/px-6 py-2\.5/g, 'px-4 py-1.5 text-[10px]');
                cleanPagination = cleanPagination.replace(/gap-6/g, 'gap-3');
                pagination.innerHTML = cleanPagination;
            } else {
                pagination.innerHTML = '';
            }
        });
}

function showList(type, title) {
    document.getElementById('dashboard-view').classList.add('hidden');
    document.getElementById('list-view').classList.remove('hidden');
    document.getElementById('list-title').innerText = title;
    changePage(type, 1);
}

function closeList() {
    document.getElementById('dashboard-view').classList.remove('hidden');
    document.getElementById('list-view').classList.add('hidden');
}

function instantSearch() {
    let input = document.getElementById('searchTable').value.toUpperCase();
    let rows = document.querySelectorAll("#table-personnel tbody tr");
    rows.forEach(row => {
        let text = row.innerText.toUpperCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

function handleImport(input) {
    if(!input.files[0]) return;
    let formData = new FormData();
    formData.append('file_excel', input.files[0]);
    fetch('actions/personnel/import_fpe.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) { alert("Importation réussie : " + data.stats.total + " agents."); location.reload(); }
        else { alert("Erreur : " + data.message); }
    });
}
</script>
</body>
</html>
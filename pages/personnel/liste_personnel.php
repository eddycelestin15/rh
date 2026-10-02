<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

// 1. Paramètres de pagination
$limit = 13;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$start = ($page - 1) * $limit;

// 2. Récupération des filtres
$search = $_GET['search'] ?? '';
$cisco_filter = $_GET['cisco'] ?? '';
$filtre_rapide = $_GET['filtre'] ?? '';

// 3. Construction de la clause WHERE
$conditions = ["1=1"];
$params = [];

if ($search) {
    $conditions[] = "(f.im LIKE ? OR f.nom_et_prenoms LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($cisco_filter) {
    $conditions[] = "f.nom_cisco = ?";
    $params[] = $cisco_filter;
}

// Gestion des clics provenant du Dashboard
if ($filtre_rapide === 'crfrp') $conditions[] = "f.crfrp = 1";
if ($filtre_rapide === 'bureau_d') $conditions[] = "f.bureau_d = 1";
if ($filtre_rapide === 'hors_fpe') $conditions[] = "f.hors_fpe = 1";
if ($filtre_rapide === 'admin_cisco') $conditions[] = "f.bureau_c = 1";

$whereSQL = "WHERE " . implode(" AND ", $conditions);

try {
    // 4. Requête principale avec détection des alertes
    $sql = "SELECT f.*, s.statut_actuel, 
               COUNT(v.alerte_id) as nb_alertes
        FROM personnel_fpe f
        LEFT JOIN personnel_situation_actuelle s ON f.im = s.im
        LEFT JOIN v_moteur_alertes v ON f.im = v.im
        $whereSQL 
        GROUP BY f.im
        ORDER BY f.nom_et_prenoms ASC
        LIMIT $start, $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $agents = $stmt->fetchAll();

    // 5. Total pour la pagination
    $countSql = "SELECT COUNT(DISTINCT f.im) FROM personnel_fpe f 
                 LEFT JOIN personnel_situation_actuelle s ON f.im = s.im 
                 LEFT JOIN v_moteur_alertes v ON f.im = v.im 
                 $whereSQL";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalCount = $stmtCount->fetchColumn();
    $totalPages = ceil($totalCount / $limit);

    // Liste des Ciscos pour le filtre
    $allCiscos = $pdo->query("SELECT DISTINCT nom_cisco FROM personnel_fpe ORDER BY nom_cisco")->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    die("Erreur technique : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <title>Liste du Personnel - DREN</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-screen p-4 md:p-8">

    <div class="max-w-7xl mx-auto space-y-6">
        
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="pages/dashboards/dashboard.php" class="bg-white p-3 rounded-2xl shadow-sm hover:bg-slate-100 transition-colors">
                    <i class="fas fa-arrow-left text-slate-400"></i>
                </a>
                <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tighter">Répertoire <span class="text-sky-500">Personnel</span></h2>
            </div>
            <span class="bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase">Total : <?php echo $totalCount; ?> agents</span>
        </div>

        <form method="GET" class="bg-white p-4 rounded-[2rem] shadow-sm border border-slate-100 flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[250px]">
                <label class="text-[9px] font-black uppercase text-slate-400 ml-2">Rechercher un agent</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nom ou IM..." class="w-full bg-slate-50 border-none rounded-2xl py-3 pl-12 text-sm focus:ring-2 focus:ring-sky-500">
                </div>
            </div>
            
            <div class="w-64">
                <label class="text-[9px] font-black uppercase text-slate-400 ml-2">Cisco</label>
                <select name="cisco" class="w-full bg-slate-50 border-none rounded-2xl py-3 text-sm focus:ring-2 focus:ring-sky-500">
                    <option value="">Toutes les CISCO</option>
                    <?php foreach($allCiscos as $c): ?>
                        <option value="<?php echo $c; ?>" <?php echo ($cisco_filter == $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="bg-slate-900 text-white px-8 py-3 rounded-2xl font-black text-[10px] uppercase hover:bg-sky-600 transition-all shadow-lg shadow-slate-200">
                Filtrer
            </button>
            <a href="pages/personnel/liste_personnel.php" class="bg-slate-100 text-slate-500 px-6 py-3 rounded-2xl font-black text-[10px] uppercase hover:bg-slate-200 transition-all">
                Reset
            </a>
        </form>

        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-100">
                            <th class="p-5 text-[10px] font-black uppercase text-slate-400">Matricule (IM)</th>
                            <th class="p-5 text-[10px] font-black uppercase text-slate-400">Identité de l'Agent</th>
                            <th class="p-5 text-[10px] font-black uppercase text-slate-400">Affectation</th>
                            <th class="p-5 text-[10px] font-black uppercase text-slate-400">Statut</th>
                            <th class="p-5 text-[10px] font-black uppercase text-slate-400 text-center">État Situation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach($agents as $a): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-5">
                                <span class="bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg font-black text-xs"><?php echo $a['im']; ?></span>
                            </td>
                            <td class="p-5">
                                <p class="font-bold text-slate-800 text-sm uppercase leading-tight"><?php echo $a['nom_et_prenoms']; ?></p>
                                <p class="text-[9px] text-slate-400 font-bold uppercase mt-1">DREN Vatovavy</p>
                            </td>
                            <td class="p-5">
                                <p class="text-xs font-black text-slate-700 uppercase"><?php echo $a['nom_etablissement']; ?></p>
                                <p class="text-[10px] text-sky-500 font-bold uppercase tracking-tighter"><?php echo $a['nom_cisco']; ?></p>
                            </td>
                            <td class="p-5">
                                <span class="text-[10px] font-black text-slate-500 italic"><?php echo $a['statut']; ?></span>
                            </td>
                            <td class="p-5 text-center">
                                <?php if (empty($a['statut_actuel'])): ?>
                                    <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 text-slate-400 text-[9px] font-black uppercase">
                                        <i class="fas fa-question-circle"></i> En attente
                                    </span>
                                <?php elseif ($a['nb_alertes'] > 0): ?>
                                    <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-rose-50 text-rose-600 text-[9px] font-black uppercase border border-rose-100">
                                        <i class="fas fa-exclamation-triangle"></i> Pas à jour
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-50 text-emerald-600 text-[9px] font-black uppercase border border-emerald-100">
                                        <i class="fas fa-check-circle"></i> Situation à jour
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-6 bg-slate-50 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest italic">
                    Affichage page <?php echo $page; ?> / <?php echo $totalPages; ?>
                </p>
                <div class="flex gap-2">
                    <?php if($page > 1): ?>
                        <a href="?p=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&cisco=<?php echo urlencode($cisco_filter); ?>" 
                           class="bg-white border border-slate-200 px-6 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-slate-100 transition-all">Précédent</a>
                    <?php endif; ?>

                    <?php if($page < $totalPages): ?>
                        <a href="?p=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&cisco=<?php echo urlencode($cisco_filter); ?>" 
                           class="bg-slate-900 text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-sky-600 transition-all shadow-lg shadow-slate-200">Suivant</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
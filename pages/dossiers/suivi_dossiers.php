<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$user_im = $_SESSION['user_im'];
// 1. Récupération des informations de l'utilisateur
$stmt = $pdo->prepare("SELECT niveau, code_lieu_affectation, role_specifique FROM utilisateurs WHERE im = ?");
$stmt->execute([$user_im]);
$user = $stmt->fetch();

$niveau = $user['niveau'] ?? '';
$role = $user['role_specifique'] ?? '';
$lieu = $user['code_lieu_affectation'] ?? ''; // On le garde en variable mais on ne l'utilise plus dans le WHERE SQL

// 2. Initialisation des options visibles selon le rôle
$optionsVisibles = [];
if ($role === 'resp_non_encadre') {
    $optionsVisibles = ['renouvellement' => "Renouvellement de contrat", 'avenant' => "Avenant"];
} elseif ($role === 'resp_encadre') {
    $optionsVisibles = [
        'avancement_classe'  => "Avancement de classe et echelon", 
        'avancement_echelon' => "Avancement d'echelon", 
        'integration'        => "Intégration", 
        'titularisation'     => "Titularisation"
    ];
}

if (in_array($role, ['chef_service', 'chef_division', 'resp_personnel_crfrp'])) {
    $optionsVisibles['conge_annuel'] = "Congé annuel";
}

$mappingObjets = [
    'renouvellement'     => "Renouvellement de contrat",
    'avenant'            => "Avenant",
    'avancement_classe'  => "Avancement de classe",
    'avancement_echelon' => "Avancement d'échelon",
    'integration'        => "Intégration",
    'titularisation'     => "Titularisation",
    'conge_annuel'       => "Congé annuel"
];

// Détection du responsable District
$isDistrictResp = ($niveau === 'district' && ($role === 'resp_encadre' || $role === 'resp_non_encadre'));

$showTable = false;
$filter_type = $_GET['type'] ?? 'tous';
$filter_dest = $_GET['dest'] ?? 'tous';
$view = $_GET['view'] ?? null;

// --- LOGIQUE DE RÉCUPÉRATION DES DONNÉES ---
$bordereaux = [];

if ($view === 'results') {
    // 1. Détermination de la table et de la colonne statut
    if ($isDistrictResp || $filter_dest === 'dren') {
        $tableName = 'archives_bordereaux_dren';
        $colStatut = 'statut_dren';
    } else {
        $mappingTable = [
            'solde_et_pensions'  => 'archives_bordereaux_solde',
            'controle_financier' => 'archives_bordereaux_cde',
            'prefecture'         => 'archives_bordereaux_prefecture'
        ];
        $mappingStatut = [
            'solde_et_pensions'  => 'statut_solde',
            'controle_financier' => 'statut_controle_financier',
            'prefecture'         => 'statut_prefecture'
        ];
        $tableName = $mappingTable[$filter_dest] ?? 'archives_bordereaux_dren';
        $colStatut = $mappingStatut[$filter_dest] ?? 'statut_dren';
    }

    $params = [];
    $whereClauses = [];

    // Filtre par type si spécifié
    if ($filter_type !== 'tous') {
        $whereClauses[] = "b.type_bordereau = ?";
        $params[] = $filter_type;
    }

    $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

    // REQUÊTE CORRIGÉE
    $sql = "SELECT b.*, 
            COUNT(s.id) as total_agents,
            SUM(CASE WHEN s.$colStatut = 'en_attente' THEN 1 ELSE 0 END) as nb_en_attente,
            SUM(CASE WHEN s.$colStatut = 'rejete' THEN 1 ELSE 0 END) as nb_anomalies,
            SUM(CASE WHEN s.$colStatut = 'valide' THEN 1 ELSE 0 END) as nb_valides
            FROM $tableName b
            LEFT JOIN suivi_agents_bordereau s ON b.id = s.id_bordereau AND b.type_bordereau = s.type_bordereau
            $whereSql
            GROUP BY b.id
            HAVING b.statut_bordereau = 'en_attente' 
               OR nb_en_attente > 0 
               OR total_agents = 0
            ORDER BY b.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bordereaux = $stmt->fetchAll();
}

$showTable = ($view === 'results' || $isDistrictResp);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <title>Suivi des Dossiers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-slate-50 p-4">
<div id="filter_section" class="<?= $showTable ? 'hidden' : '' ?> max-w-4xl mx-auto mt-12 p-1 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-[2rem] shadow-2xl transition-all duration-500">
    <div class="bg-white rounded-[1.8rem] p-8 md:p-12 border border-white/20">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl mb-4 shadow-inner">
                <i class="fas fa-search-location text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Suivi des Bordereaux</h1>
            <p class="text-slate-500 mt-2 font-medium">Sélectionnez vos critères pour consulter les archives</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-2">
                <label class="flex items-center gap-2 text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">
                    <i class="fas fa-copy text-blue-500"></i> Type de demande
                </label>
                <select id="f_type" class="w-full bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-500 p-4 rounded-2xl text-slate-700 font-semibold transition-all appearance-none cursor-pointer shadow-sm">
                    <option value="tous" <?= $filter_type == 'tous' ? 'selected' : '' ?>>-- Choisir type demande --</option>
                    <?php foreach($optionsVisibles as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $filter_type == $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">
                    <i class="fas fa-map-marker-alt text-blue-500"></i> Destination
                </label>
                <select id="f_dest" class="w-full bg-slate-50 border-none ring-1 ring-slate-200 focus:ring-2 focus:ring-blue-500 p-4 rounded-2xl text-slate-700 font-semibold transition-all appearance-none cursor-pointer shadow-sm">
                    <option value="tous">-- Choisir destination --</option>
                    <?php if($niveau === 'regional'): ?>
                        <option value="dren" <?= ($filter_dest === 'dren') ? 'selected' : '' ?>>Circonscription Scolaire</option>
                        <option value="solde_et_pensions" <?= ($filter_dest === 'solde_et_pensions') ? 'selected' : '' ?>>Solde et Pensions</option>
                        <option value="controle_financier" <?= ($filter_dest === 'controle_financier') ? 'selected' : '' ?>>Contrôle financier</option>
                        <option value="prefecture" <?= ($filter_dest === 'prefecture') ? 'selected' : '' ?>>Préfecture</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center">
            <button onclick="afficherTableauParNavigation()" class="group relative bg-slate-900 hover:bg-blue-600 text-white px-12 py-4 rounded-2xl font-bold shadow-xl transition-all hover:-translate-y-1 active:scale-95">
                <span class="flex items-center gap-3">
                    Rechercher les bordereaux
                    <i class="fas fa-chevron-right text-xs group-hover:translate-x-1 transition-transform"></i>
                </span>
            </button>
            <div class="flex items-center gap-2 mt-6 text-slate-400">
                <span class="h-px w-8 bg-slate-200"></span>
                <span class="text-[10px] font-bold uppercase tracking-tighter">Session : <?= htmlspecialchars($niveau) ?></span>
                <span class="h-px w-8 bg-slate-200"></span>
            </div>
        </div>
    </div>
</div>

<div id="table_section" class="<?= $showTable ? '' : 'hidden'; ?> mt-10 animate-fade-in">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg border border-slate-200">
        <?php if ($isDistrictResp): ?>
        <div class="flex flex-col md:flex-row justify-between items-end mb-6 gap-4">            
            <div class="w-full md:w-64">                
                <div class="relative group">
                    <select id="quick_filter_type" onchange="filtrerTableauLocal()" 
                        class="block w-full bg-white border border-slate-200 text-slate-700 py-2.5 px-4 pr-10 rounded-xl text-sm
                            appearance-none cursor-pointer
                            transition-all duration-200
                            hover:border-blue-300 hover:shadow-sm
                            focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:outline-none shadow-sm">
                        
                        <option value="tous"> -- Tout type de demande --</option>                        
                        <?php foreach ($optionsVisibles as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= ($filter_type == $val) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lbl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 group-hover:text-blue-500 transition-colors">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>
            </div>

            <div class="w-full md:w-80">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <i class="fas fa-search text-slate-300"></i>
                    </div>
                    <input type="text" id="search_bordereau" onkeyup="filtrerTableauLocal()" 
                        class="bg-white border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 block w-full pl-10 p-2.5 shadow-sm transition-all" 
                        placeholder="Saisir un numéro bordereau ...">
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-sm bg-white">
            <table class="w-full text-left border-collapse" id="main_table_bordereaux">
                <thead>
                    <tr class="bg-blue-600 text-white" style="font-size: 14px;">
                        <th class="p-2 border border-blue-700 text-center">N°</th>
                        <th class="p-2 border border-blue-700">Objet de la demande</th>
                        <th class="p-2 border border-blue-700"><?php echo ($niveau == 'regional') ? 'Venant de' : 'Envoyé à'; ?></th>
                        <th class="p-2 border border-blue-700">Numéro bordereau</th>
                        <th class="p-2 border border-blue-700">Date d'émission</th>
                        <th class="p-2 border border-blue-700"><?php echo ($niveau == 'district') ? 'Référence DREN' : 'Réception du dossier'; ?></th>
                        <th class="p-2 border border-blue-700 text-center">Validation</th>
                    </tr>
                </thead>
                <tbody style="font-size: 13px;">
                    <?php foreach ($bordereaux as $index => $b): ?>
                        <tr data-type="<?= htmlspecialchars($b['type_bordereau']) ?>" class="hover:bg-slate-50 transition-colors">     
                            <td class="p-2 border text-center font-bold">
                                <?php echo $index + 1; ?>
                            </td>

                            <td class="p-2 border">
                                <?php echo $mappingObjets[$b['type_bordereau']] ?? $b['type_bordereau']; ?>
                            </td>

                            <td class="p-2 border font-bold text-blue-600">
                                <?php echo ($niveau == 'regional') ? "CISCO " . $b['expediteur'] : "DREN " . $lieu; ?>
                            </td>

                            <td class="p-2 border font-bold">
                                <?php echo $b['numero_complet']; ?>
                            </td>
                            
                            <td class="p-2 border">
                                <?php if($niveau == 'district' && empty($b['date_envoi_bordereau'])): ?>
                                    <div class="flex items-center gap-2">
                                        <input type="date" id="date_emiss_<?php echo $b['id']; ?>" value="<?php echo date('Y-m-d'); ?>" class="border p-1 text-[10px]">
                                        <button onclick="confirmerEnvoi(<?php echo $b['id']; ?>)" class="bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">Envoyer</button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-green-700 font-medium">
                                        <i class="fas fa-paper-plane mr-1"></i>
                                        <?php echo !empty($b['date_envoi_bordereau']) ? date('d/m/Y', strtotime($b['date_envoi_bordereau'])) : '---'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="p-2 border">
                                <?php if($niveau == 'district'): ?>
                                    <span class="font-mono text-blue-600"><?php echo $b['reference_destination'] ?? '---'; ?></span>
                                <?php else: ?>
                                    <?php if(empty($b['reference_destination'])): ?>
                                        <?php 
                                            // LOGIQUE DYNAMIQUE PAR LIGNE
                                            $currentLabel = "Donner Référence"; // Par défaut
                                            
                                            // On vérifie la destination réelle du bordereau actuel ($b)
                                            if ($b['destination'] === 'dren') {
                                                $currentLabel = "Donner Référence DREN";
                                            } elseif ($b['destination'] === 'solde_et_pensions') {
                                                $currentLabel = "Donner Référence Solde";
                                            } elseif ($b['destination'] === 'controle_financier') {
                                                $currentLabel = "Donner Référence CDE";
                                            } elseif ($b['destination'] === 'prefecture') {
                                                $currentLabel = "Donner Référence Préfecture";
                                            }
                                        ?>
                                        <button onclick="marquerRecu(<?= $b['id'] ?>)" 
                                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-all flex items-center gap-2">
                                            <i class="fas fa-check-circle"></i>
                                            <?= $currentLabel ?> </button>
                                    <?php else: ?>
                                        <span class="text-slate-600 italic"><?php echo $b['reference_destination']; ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td class="p-2 border text-center">
                                <?php if ($niveau == 'district'): ?>
                                    <?php if (!empty($b['date_envoi_bordereau']) && empty($b['reference_destination'])): ?>
                                        <span class="bg-blue-600 text-white px-3 py-1 rounded-full text-[13px] flex items-center justify-center gap-1">
                                            <i class="fas fa-circle-notch fa-spin"></i> En attente
                                        </span>
                                    <?php elseif (!empty($b['reference_destination'])): ?>
                                        <?php if ($b['nb_en_attente'] > 0): ?>
                                            <button onclick="voirDetails(<?php echo $b['id']; ?>)" class="bg-orange-500 text-white px-2 py-1 rounded text-[13px]">Traitement en cours...</button>
                                        <?php elseif ($b['nb_anomalies'] > 0): ?>
                                            <button onclick="voirDetails(<?php echo $b['id']; ?>)" class="bg-red-600 text-white px-2 py-1 rounded text-[13px] animate-pulse">Anomalie trouvée</button>
                                        <?php else: ?>
                                            <button onclick="voirDetails(<?php echo $b['id']; ?>)" class="text-green-600 font-bold"><i class="fas fa-check-double"></i> Succès</button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400">---</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button onclick="voirDetails(<?php echo $b['id']; ?>)" class="bg-blue-600 text-white px-3 py-1 rounded-lg hover:bg-blue-700">
                                        <i class="fa fa-eye mr-1"></i> Traiter Agents
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="no-results-row" class="<?= count($bordereaux) > 0 ? 'hidden' : '' ?>">
                        <td colspan="7" class="p-12 text-center">
                            <div class="flex flex-col items-center justify-center text-slate-400">
                                <i class="fas fa-folder-open text-6xl mb-4 opacity-20"></i>
                                <p class="text-lg font-medium">Aucun bordereau généré pour ce type de demande</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div id="pagination_container" class="flex flex-col md:flex-row items-center justify-between gap-4 mt-6 py-4 border-t border-slate-100 hidden">
    
                <div class="text-sm text-slate-500 font-medium">
                     Affichage de <span id="page_start" class="text-slate-900">0</span> 
                    à <span id="page_end" class="text-slate-900">0</span> 
                    sur <span id="total_records" class="text-slate-900">0</span> bordereaux
                </div>

                <div class="flex items-center gap-3">
                    <button onclick="changePage(-1)" id="btn_prev" 
                            class="flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm">
                        <i class="fas fa-chevron-left text-[10px]"></i> Précédent
                    </button>
                    
                    <div id="page_number" class="flex items-center justify-center w-9 h-9 border-2 border-blue-600 rounded-lg bg-blue-50 text-blue-700 font-bold shadow-sm">
                        1
                    </div>

                    <button onclick="changePage(1)" id="btn_next" 
                            class="flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm">
                        Suivant <i class="fas fa-chevron-right text-[10px]"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="modalDetails" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex justify-between items-center p-4 border-b bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800">Détails et Traitement des Agents</h3>
            <button onclick="document.getElementById('modalDetails').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-2xl">&times;</button>
        </div>
        <div id="detailsContent" class="overflow-y-auto p-4"></div>
    </div>
</div>

<script>
// On utilise un bloc local (IIFE) pour isoler les variables et éviter l'erreur "already declared"
(function() {
    // Variables locales à ce bloc
    let currentPage = 1;
    const recordsPerPage = 7;
    let filteredRows = [];

    // On définit les fonctions de manière à ce qu'elles soient accessibles par les attributs onclick HTML
    window.filtrerTableauLocal = function() {
        const typeSelect = document.getElementById('quick_filter_type');
        const typeValue = typeSelect ? typeSelect.value : 'tous';
        const searchValue = document.getElementById('search_bordereau')?.value.toLowerCase() || '';
        
        // Sélection de toutes les lignes sauf celle du message "aucun résultat"
        const allRows = Array.from(document.querySelectorAll('#main_table_bordereaux tbody tr:not(#no-results-row)'));
        const noResultsRow = document.getElementById('no-results-row');
        const paginationContainer = document.getElementById('pagination_container');
        
        // 1. Logique de filtrage
        filteredRows = allRows.filter(row => {
            const rowType = row.getAttribute('data-type') || '';
            const cellNumText = row.cells[3]?.textContent.trim().toLowerCase() || ''; // Colonne Numéro

            const matchType = (typeValue === 'tous' || rowType === typeValue);
            const matchSearch = cellNumText.includes(searchValue);

            return matchType && matchSearch;
        });

        // Revenir à la page 1 lors d'un changement de filtre
        currentPage = 1;
        
        // 2. Mise à jour de l'affichage
        if (filteredRows.length === 0) {
            allRows.forEach(r => r.style.display = "none");
            if (noResultsRow) noResultsRow.classList.remove('hidden');
            if (paginationContainer) paginationContainer.classList.add('hidden');
        } else {
            if (noResultsRow) noResultsRow.classList.add('hidden');
            if (paginationContainer) paginationContainer.classList.remove('hidden');
            updateTableDisplay();
        }
    };

    function updateTableDisplay() {
        const allRows = document.querySelectorAll('#main_table_bordereaux tbody tr:not(#no-results-row)');
        
        // Cacher tout
        allRows.forEach(row => row.style.display = "none");

        // Calcul des limites
        const startIndex = (currentPage - 1) * recordsPerPage;
        const endIndex = Math.min(startIndex + recordsPerPage, filteredRows.length);
        
        // Afficher uniquement les lignes de la page active
        const rowsToShow = filteredRows.slice(startIndex, endIndex);
        rowsToShow.forEach(row => row.style.display = "");

        // Mise à jour des textes d'affichage (Bas à gauche)
        document.getElementById('page_start').textContent = filteredRows.length > 0 ? startIndex + 1 : 0;
        document.getElementById('page_end').textContent = endIndex;
        document.getElementById('total_records').textContent = filteredRows.length;
        
        // Mise à jour de la pagination (Bas à droite)
        const totalPages = Math.ceil(filteredRows.length / recordsPerPage);
        document.getElementById('page_number').textContent = currentPage;
        
        // Désactiver les boutons si limites atteintes
        const btnPrev = document.getElementById('btn_prev');
        const btnNext = document.getElementById('btn_next');
        if(btnPrev) btnPrev.disabled = (currentPage === 1);
        if(btnNext) btnNext.disabled = (currentPage === totalPages || totalPages === 0);
    }

    window.changePage = function(direction) {
        const totalPages = Math.ceil(filteredRows.length / recordsPerPage);
        const newPage = currentPage + direction;

        if (newPage >= 1 && newPage <= totalPages) {
            currentPage = newPage;
            updateTableDisplay();
            // Scroll fluide vers le haut du tableau
            document.getElementById('main_table_bordereaux').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };

    // Lancer le filtre au chargement du script
    filtrerTableauLocal();

})(); 
</script>

<script>
function afficherTableauSuivi() {
    const typeDossier = document.getElementById('filter_type_dos').value;
    const destination = document.getElementById('select_service_destination')?.value || 'tous';

    // Animation de transition
    const filterSection = document.getElementById('filter_section');
    const tableSection = document.getElementById('table_section');

    filterSection.classList.add('opacity-0', 'scale-95');
    
    setTimeout(() => {
        filterSection.classList.add('hidden');
        tableSection.classList.remove('hidden');
        tableSection.classList.add('opacity-100', 'scale-100');
        
        // Optionnel : Lancer une fonction de chargement de données filtrées ici
        // loadData(typeDossier, destination); 
    }, 300);
}

function afficherTableauParNavigation() {
    const type = document.getElementById('f_type').value;
    const dest = document.getElementById('f_dest').value;

    if (!type || !dest) {
        Swal.fire('Attention', 'Veuillez choisir un type et une destination', 'warning');
        return;
    }
    const state = { view: 'results', type: type, dest: dest };
    history.pushState(state, "", ""); 
    
    document.getElementById('filter_section').classList.add('hidden');
    document.getElementById('table_section').classList.remove('hidden');
}

window.onpopstate = function(event) {
    if (!event.state || event.state.view !== 'results') {
        document.getElementById('table_section').classList.add('hidden');
        document.getElementById('filter_section').classList.remove('hidden');
    }
};

function basculerVersTableau() {
    document.getElementById('filter_section').classList.add('hidden');
    document.getElementById('table_section').classList.remove('hidden');
}

function revenirAuxFiltres() {
    document.getElementById('table_section').classList.add('hidden');
    document.getElementById('filter_section').classList.remove('hidden');
}

window.niveauUtilisateur = "<?php echo $niveau; ?>"; 
// Ou vérifiez avant de déclarer :
if (typeof niveauUtilisateur === 'undefined') {
    window.niveauUtilisateur = "<?php echo $niveau; ?>";
}
function voirDetails(id) {
    // Récupération de la destination pour le filtrage des colonnes
    const dest = '<?php echo $filter_dest; ?>' || 'dren';

    fetch(`api_suivi.php?action=get_agents_bordereau_complet&id=${id}`)
    .then(r => r.json())
    .then(data => {
        // --- SÉCURITÉ : Vérifier si data est bien un tableau ---
        if (!Array.isArray(data)) {
            console.error("Réponse invalide de l'API:", data);
            Swal.fire('Erreur', 'Les données reçues sont malformées.', 'error');
            return;
        }

        let html = `
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse bg-white">
                    <thead>
                        <tr class="bg-blue-600 text-white" style="font-size: 14px;">
                            <th class="p-3 border border-blue-700 text-center">N°</th>
                            <th class="p-3 border border-blue-700">IM</th>
                            <th class="p-3 border border-blue-700">Nom et prénoms</th>
                            <th class="p-3 border border-blue-700">Lieu de service</th>
                            <th class="p-3 border border-blue-700">Corps et grade actuel</th>
                            <th class="p-3 border border-blue-700 text-center">Statut</th>
                            <th class="p-3 border border-blue-700 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 13px;">`;

        if (data.length === 0) {
            html += `<tr><td colspan="7" class="p-4 text-center italic text-slate-500">Aucun agent trouvé pour ce bordereau.</td></tr>`;
        } else {
            data.forEach((agent, index) => {
                // Sélection dynamique du statut selon la destination
                let statut = agent.statut_dren || 'en_attente';
                let motif = agent.motif_dren || '';

                if (dest === 'solde_et_pensions') { statut = agent.statut_solde; motif = agent.motif_solde; }
                else if (dest === 'controle_financier') { statut = agent.statut_controle_financier; motif = agent.motif_cde; }
                else if (dest === 'prefecture') { statut = agent.statut_prefet; motif = agent.motif_prefet; }

                const badgeClass = {
                    'en_attente': 'bg-slate-100 text-slate-600',
                    'valide': 'bg-green-100 text-green-700 border border-green-200',
                    'rejete': 'bg-red-100 text-red-700 border border-red-200'
                }[statut] || 'bg-gray-100';
                let localite = "";
                if (!agent.nom_district && !agent.nom_zap && !agent.nom_etablissement) {
                    localite = "DREN " + (agent.nom_region || "");
                } else if (!agent.nom_zap && !agent.nom_etablissement) {
                    localite = "CISCO " + (agent.nom_district || "");
                } else if (agent.nom_etablissement && !agent.nom_zap) {
                    localite = agent.nom_etablissement;
                } else if (agent.nom_etablissement) {
                    localite = "ZAP " + agent.nom_zap + " - " + agent.nom_etablissement;
                }

                html += `
                    <tr class="hover:bg-blue-50">
                        <td class="p-2 border text-center font-bold">${index + 1}</td>
                        <td class="p-2 border font-mono text-blue-600">${agent.im_agent}</td>
                        <td class="p-2 border font-medium">${agent.nom || ''} ${agent.prenoms || ''}</td>
                        <td class="p-3 border">${localite}</td>
                        <td class="p-2 border text-[11px] italic">
                            ${agent.corps_actuel || ''} - ${agent.grade_actuel || ''}
                        </td>
                        <td class="p-2 border text-center">
                            <span class="px-2 py-1 rounded text-[10px] font-bold uppercase ${badgeClass}">
                                ${statut.replace('_', ' ')}
                            </span>
                        </td>
                        <td class="p-2 border text-center">
                            <button onclick="traiterAgent(${agent.id}, '${statut}', ${id}, '${dest}')" 
                                    class="bg-slate-800 text-white px-2 py-1 rounded text-[11px] hover:bg-blue-700">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>`;
            });
        }

        html += `</tbody></table></div>`;

        Swal.fire({
            title: '<span class="text-blue-700 font-bold italic">Détails des Agents du Bordereau</span>',
            html: html,
            width: '95%',
            showConfirmButton: false,
            showCloseButton: true
        });
    })
    .catch(err => {
        console.error("Erreur Fetch:", err);
        Swal.fire('Erreur', 'Impossible de charger les données. Vérifiez l\'API.', 'error');
    });
}

function getBadgeStatut(statut) {
    const classes = {
        'en_attente': 'bg-amber-100 text-amber-700 border-amber-200',
        'valide': 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'rejete': 'bg-red-100 text-red-700 border-red-200'
    };
    const labels = { 'en_attente': 'En attente', 'valide': 'Validé', 'rejete': 'Rejeté' };
    return `<span class="px-2 py-1 rounded-full border text-[11px] font-medium ${classes[statut] || ''}">${labels[statut] || statut}</span>`;
}

function confirmerEnvoi(id) {
    const dateSaisie = document.getElementById('date_emiss_' + id).value;
    if(!dateSaisie) return Swal.fire('Attention', 'Veuillez choisir une date', 'warning');

    Swal.fire({
        title: 'Confirmer l\'envoi ?',
        text: "Voulez-vous envoyer ce bordereau à la DREN ?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Oui, envoyer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'marquer_envoi');
            fd.append('id', id);
            fd.append('date', dateSaisie);

            fetch('api/bordereaux/api_suivi.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { 
                if(d.success) {
                    Swal.fire('Succès', 'Bordereau envoyé', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Erreur', d.message, 'error');
                }
            });
        }
    });
}

function marquerRecu(id) {
    // On récupère la destination actuelle (ex: dren, solde_et_pensions...)
    const dest = '<?= $filter_dest ?>';
    const today = new Date().toISOString().split('T')[0];
    
    // Libellé dynamique pour le placeholder
    let placeholderRef = "N° Référence (ex: 2026-DREN-001)";
    if(dest === 'solde_et_pensions') placeholderRef = "N° Référence Solde";
    if(dest === 'controle_financier') placeholderRef = "N° Référence CF";
    if(dest === 'prefecture') placeholderRef = "N° Référence Préfecture";

    Swal.fire({
        title: 'Réception Dossier',
        html: `
            <div class="text-left mb-2 text-sm text-slate-500">Date de réception :</div>
            <input type="date" id="date_recep" class="swal2-input" value="${today}">
            <div class="text-left mb-2 mt-4 text-sm text-slate-500">Référence du courrier :</div>
            <input type="text" id="ref_courrier" class="swal2-input" placeholder="${placeholderRef}">
        `,
        preConfirm: () => {
            const date = document.getElementById('date_recep').value;
            const ref = document.getElementById('ref_courrier').value;
            if(!date || !ref) return Swal.showValidationMessage('Tous les champs sont requis');
            return { date, ref };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'recevoir');
            fd.append('id', id);
            fd.append('dest', dest); 
            
            // Formatage de la référence : "N° XXX du JJ/MM/AAAA"
            const refDate = result.value.date.split('-').reverse().join('/');
            fd.append('reference_complet', result.value.ref + " du " + refDate);

            fetch('api/bordereaux/api_suivi.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { 
                if(d.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Réception enregistrée',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Erreur', d.message, 'error');
                }
            });
        }
    });
}

function traiterAgent(idSuivi, statutActuel, idBordereau, service) {
    Swal.fire({
        title: 'Vérification du dossier',
        text: "Le dossier présente-t-il une anomalie ?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Non (Valider)',
        cancelButtonText: 'Oui (Rejeter)',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#ef4444',
    }).then((result) => {
        if (result.isConfirmed) {
            // Bouton Vert : Valider
            envoyerUpdateAgent(idSuivi, 'valide', '', idBordereau, service);
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Bouton Rouge : Rejeter -> On demande le motif
            Swal.fire({
                title: 'Motif du rejet',
                input: 'textarea',
                inputPlaceholder: 'Expliquez la raison du rejet...',
                showCancelButton: true,
                confirmButtonText: 'Confirmer le Rejet',
                confirmButtonColor: '#ef4444',
                preConfirm: (value) => {
                    if (!value) return Swal.showValidationMessage('Veuillez saisir un motif');
                    return value;
                }
            }).then(res => {
                if(res.isConfirmed && res.value) {
                    envoyerUpdateAgent(idSuivi, 'rejete', res.value, idBordereau, service);
                }
            });
        }
    });
}

function envoyerUpdateAgent(id, statut, motif, idB, service) {
    const fd = new FormData();
    fd.append('action', 'update_statut_agent');
    fd.append('id', id);
    fd.append('statut', statut);
    fd.append('motif', motif);
    fd.append('service', service); // AJOUT CRUCIAL

    fetch('api/bordereaux/api_suivi.php', { method: 'POST', body: fd })
    .then(r => {
        if (!r.ok) throw new Error('Erreur Serveur');
        return r.json();
    })
    .then(d => {
        if(d.success) {
            voirDetails(idB); // Rafraîchit le modal
        } else {
            Swal.fire('Erreur', d.message || 'Erreur lors de la mise à jour', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Erreur Critique', 'L\'API a retourné une erreur 500. Vérifiez les colonnes SQL.', 'error');
    });
}

/**
 * Affiche le motif du rejet dans une modale SweetAlert2
 * @param {string} motifBase64 - Le motif encodé en base64
 */
function afficherMotifRejet(motifBase64) {
    try {
        const motif = decodeURIComponent(escape(atob(motifBase64)));
        Swal.fire({
            title: 'Motif du Rejet',
            text: motif,
            icon: 'info',
            confirmButtonText: 'Fermer',
            confirmButtonColor: '#3b82f6',
            customClass: {
                title: 'text-lg font-bold text-slate-800',
                htmlContainer: 'text-left text-slate-600 bg-slate-50 p-4 rounded-lg border border-slate-200'
            }
        });
    } catch (e) {
        console.error("Erreur de décodage du motif", e);
        Swal.fire('Erreur', 'Impossible d\'afficher le motif.', 'error');
    }
}
</script>
</body>
</html>
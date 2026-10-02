<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$userIm = $_SESSION['user_im'];
$userRole = trim($_SESSION['user_role'] ?? '');
$userNiveau = strtolower(trim($_SESSION['user_niveau'] ?? ''));

$roleSpecifique = '';
try {
    $stmtRole = $pdo->prepare("SELECT role_specifique, niveau FROM utilisateurs WHERE im = ?");
    $stmtRole->execute([$userIm]);
    $userU = $stmtRole->fetch();
    $roleSpecifique = $userU['role_specifique'] ?? '';
    $userNiveau = strtolower(trim($userU['niveau'] ?? $userNiveau));
} catch (Exception $e) {
    $roleSpecifique = '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <title>Traitement des Dossiers</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf-8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Lien vers la feuille de style externalisée -->
    <link rel="stylesheet" href="assets/css/traitement.css">
</head>
<body class="bg-slate-50 px-1 py-6 sm:px-4">

<div class="w-full mx-auto bg-white rounded-2xl shadow-xl p-4 sm:p-6 border border-slate-100">
    <h1 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-3 px-1">
        <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
            <i class="fas fa-tasks"></i>
        </div>
        Traitement et validation des dossiers agents
    </h1>

    <div class="flex flex-col md:flex-row md:items-end gap-4 mb-6 bg-slate-50 p-4 rounded-xl border border-slate-200/60">
        <div class="flex flex-col flex-1">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Recherche rapide :</label>
            <div id="search_left_placeholder" class="w-full relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                    <i class="fas fa-search"></i>
                </div>
                <input type="text" id="custom_search_mock" disabled placeholder="Sélectionnez un type d'abord..." class="w-full border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-sm bg-slate-100 font-medium text-slate-400 shadow-sm cursor-not-allowed">
            </div>
        </div>

        <div class="flex flex-col">
            <label for="filter_type_bordereau" class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type de demande :</label>
            <select id="filter_type_bordereau" class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white min-w-[280px] shadow-sm font-medium text-slate-700">
                <option value="">-- Choisir un type de demande --</option>
                <?php if ($roleSpecifique === 'resp_non_encadre' && ($userNiveau === 'central' || $userNiveau === 'regional' || $userNiveau === 'district')) : ?>
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="integration">Intégration</option>
                <?php elseif ($roleSpecifique === 'resp_encadre' && ($userNiveau === 'central' || $userNiveau === 'regional' || $userNiveau === 'district')) : ?>
                    <option value="titularisation">Titularisation</option>
                    <option value="avancement_classe">Avancement de classe</option>
                    <option value="avancement_echelon">Avancement d'échelon</option> 
                <?php elseif ($roleSpecifique === 'resp_retraite') : ?>
                    <option value="admission_retraite">Admission à la retraite</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                <?php elseif ($roleSpecifique === 'resp_personnel_crfrp' && $userNiveau === 'crfrp') : ?>
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="integration">Intégration</option>
                    <option value="titularisation">Titularisation</option>
                    <option value="avancement_classe">Avancement de classe</option>
                    <option value="avancement_echelon">Avancement d'échelon</option>
                    <option value="admission_retraite">Admission à la retraite</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                <?php else : ?>
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="integration">Intégration</option>
                    <option value="titularisation">Titularisation</option>
                    <option value="avancement_classe">Avancement de classe</option>
                    <option value="avancement_echelon">Avancement d'échelon</option>
                    <option value="admission_retraite">Admission à la retraite</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                <?php endif; ?>
            </select>
        </div>

        <div>
            <button id="btn_charger_bordereau" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm px-5 py-2.5 rounded-xl transition-all shadow-md shadow-blue-500/10 active:scale-95 flex items-center gap-2 h-[42px] w-full md:w-auto justify-center">
                <i class="fas fa-sync-alt text-xs"></i> Afficher la liste
            </button>
        </div>
    </div>

    <div class="overflow-x-auto bg-white">
        <table id="tableReferences" class="w-full">
            <thead id="dynamic_thead">
                <tr>
                    <td colspan="11" class="py-12 text-center border-none bg-white">
                        <div class="flex flex-col items-center justify-center space-y-4 animate-in fade-in zoom-in duration-300">
                            <i class="fas fa-mouse-pointer text-6xl text-slate-200"></i>
                            <div class="flex flex-col items-center">
                                <span class="text-xl font-black text-slate-400 uppercase tracking-tighter">Selectionner un type de demande</span>
                                <span class="text-[12px] font-bold text-slate-400 uppercase tracking-[0.3em] mt-1 text-center">Choisir un type pour afficher la liste des agents en attente de traitement</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white"></tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    let dataTableInstance = null;
    const roleSpecifique = <?php echo json_encode($roleSpecifique); ?>;
    const userNiveau = <?php echo json_encode($userNiveau); ?>;

    const structuresThead = {
        // --- NIVEAU CENTRAL ---
        'central_encadre': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="4" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">FOP</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MEN</th>
            </tr>`,
        'central_non_encadre': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="3" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MEN</th>
            </tr>`,
        'central_admission_retraite': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="5" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DRH</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MTEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">PRIMATURE</th>
            </tr>`,
        'central_compensatrice': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="3" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MEN</th>
            </tr>`,
        'central_installation': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="4" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DRH</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MEN</th>
            </tr>`,
        'central_integration': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="6" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DRH</th>
                <th class="p-3 text-xs font-semibold uppercase">FOP</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MTEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">PRIMATURE</th>
            </tr>`,
        'central_titularisation': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="6" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DRH</th>
                <th class="p-3 text-xs font-semibold uppercase">FOP</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MTEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">PRIMATURE</th>
            </tr>`,

        // --- NIVEAU REGIONAL / DISTRICT / CRFRP ---
        'regional_integration': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="7" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DREN</th>
                <th class="p-3 text-xs font-semibold uppercase">DRHEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">DRH</th>
                <th class="p-3 text-xs font-semibold uppercase">MTEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">PRIMATURE</th>
            </tr>`,
        'regional_titularisation': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="7" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DREN</th>
                <th class="p-3 text-xs font-semibold uppercase">DRHEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">DRH</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MTEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">PRIMATURE</th>
            </tr>`,
        'regional_non_encadre': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="4" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DREN</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">Préfecture</th>
            </tr>`,
        'regional_encadre': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="5" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DREN</th>
                <th class="p-3 text-xs font-semibold uppercase">DRHEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">Préfecture</th>
            </tr>`,
        'regional_admission_retraite': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="6" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DRHEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">DRH</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MTEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">PRIMATURE</th>
            </tr>`,
        'regional_compensatrice': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="4" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DREN</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">Préfecture</th>
            </tr>`,
        'regional_installation': `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">N°</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Nom & Prénoms</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">IM</th>
                <th rowspan="2" class="p-4 border-b border-blue-800 text-xs uppercase font-semibold">Corps / Grade</th>
                <th colspan="5" class="p-2.5 bg-blue-900/60 font-bold text-xs uppercase border-b border-blue-800">Traitements de Dossiers</th>
            </tr>
            <tr class="text-white divide-x divide-blue-600/20">
                <th class="p-3 text-xs font-semibold uppercase">DRHEFOP</th>
                <th class="p-3 text-xs font-semibold uppercase">SOLDE</th>
                <th class="p-3 text-xs font-semibold uppercase">DRH</th>                
                <th class="p-3 text-xs font-semibold uppercase">CDE</th>
                <th class="p-3 text-xs font-semibold uppercase">MEN</th>
            </tr>`
    };

    function getStructureKey(type, typeEtablissement) {
        if (!type) return null;

        if (userNiveau === 'central') {
            if (type === 'renouvellement' || type === 'avenant') return 'central_non_encadre';
            if (type === 'integration') return 'central_integration';
            if (type === 'titularisation') return 'central_titularisation';
            if (type === 'admission_retraite') return 'central_admission_retraite';
            if (type === 'compensatrice') return 'central_compensatrice';
            if (type === 'installation') return 'central_installation';
            return 'central_encadre';
        } else {
            if (type === 'renouvellement' || type === 'avenant') return 'regional_non_encadre';
            if (type === 'integration') return 'regional_integration';
            if (type === 'titularisation') return 'regional_titularisation';
            if (type === 'admission_retraite') return 'regional_admission_retraite';
            if (type === 'compensatrice') return 'regional_compensatrice';
            if (type === 'installation') return 'regional_installation';            
            return 'regional_encadre';
        }
    }

    function getColumnsConfig(key) {
        let cols = [
            { data: null, render: (d, t, r, meta) => meta.row + 1, className: 'font-bold p-4 text-slate-700' },
            { data: 'nom_complet', className: 'font-medium p-4 text-left' },
            { data: 'im_agent', className: 'font-mono text-blue-600 font-bold p-4 text-center' },
            { data: 'corps_grade', className: 'p-4 text-slate-500 text-left font-medium', defaultContent: '---' }
        ];

        switch (key) {
            // --- NIVEAU CENTRAL ---
            case 'central_admission_retraite':
                cols.push(
                    { data: 'drh', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'mtefop', className: 'text-center' },
                    { data: 'primature', className: 'text-center' }
                );
                break;
                
            case 'central_compensatrice':
                cols.push(
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'men', className: 'text-center' }
                );
                break;
            case 'central_installation':
                cols.push(
                    { data: 'drh', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'men', className: 'text-center' }
                );
                break;

            case 'central_encadre':
                cols.push(
                    { data: 'fop', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'men', className: 'text-center' }
                );
                break;

            case 'central_non_encadre':
                cols.push(
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'men', className: 'text-center' }
                );
                break;
            case 'central_integration':
                cols.push(
                    { data: 'drh', className: 'text-center' },
                    { data: 'fop', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'mtefop', className: 'text-center' },
                    { data: 'primature', className: 'text-center' }
                );
                break;
            case 'central_titularisation':
                cols.push(
                    { data: 'drh', className: 'text-center' },
                    { data: 'fop', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'mtefop', className: 'text-center' },
                    { data: 'primature', className: 'text-center' }
                );
                break;

            // --- NIVEAU REGIONAL / DISTRICT / CRFRP ---
            case 'regional_admission_retraite':
                cols.push(
                    { data: 'fop', className: 'text-center' },
                    { data: 'drh', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'mtefop', className: 'text-center' },
                    { data: 'primature', className: 'text-center' }
                );
                break;

            case 'regional_compensatrice':
                cols.push(
                    { data: 'dren', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'prefet', className: 'text-center' }
                );
                break;

            case 'regional_installation':
                cols.push(
                    { data: 'fop', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },                    
                    { data: 'drh', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'men', className: 'text-center' }
                );
                break;

            case 'regional_integration':
                cols.push(
                    { data: 'dren', className: 'text-center' },
                    { data: 'fop', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'drh', className: 'text-center' },
                    { data: 'mtefop', className: 'text-center' },
                    { data: 'primature', className: 'text-center' }
                );
                break;
            case 'regional_titularisation':
                cols.push(
                    { data: 'dren', className: 'text-center' },
                    { data: 'fop', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'drh', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'mtefop', className: 'text-center' },
                    { data: 'primature', className: 'text-center' }
                );
                break;

            case 'regional_non_encadre':
                cols.push(
                    { data: 'dren', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'prefet', className: 'text-center' }
                );
                break;

            case 'regional_encadre':
                cols.push(
                    { data: 'dren', className: 'text-center' },
                    { data: 'fop', className: 'text-center' },
                    { data: 'solde', className: 'text-center' },
                    { data: 'cde', className: 'text-center' },
                    { data: 'prefet', className: 'text-center' }
                );
                break;
        }

        return cols;
    }

    $('#btn_charger_bordereau').on('click', function() {
        const typeDemande = $('#filter_type_bordereau').val();
        
        if (!typeDemande) {
            Swal.fire('Sélection requise', "Veuillez choisir un type de demande.", 'info');
            return;
        }

        // Bouton en mode chargement
        const $btn =$(this);
        const originalBtnText = $btn.html();$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin text-xs"></i> Chargement...');

        $.ajax({
            url: 'api/dossiers/api_traitement.php',
            type: 'POST',
            data: {
                action: 'list_traitement',
                type: typeDemande
            },
            dataType: 'json',
            success: function(json) {
                const listData = json.data || [];
                
                // Récupération de type_etablissement sur le premier agent pour ajuster les colonnes au niveau central
                const firstEtab = listData.length > 0 ? (listData[0].type_etablissement || '') : '';
                const structKey = getStructureKey(typeDemande, firstEtab);

                if (!structKey) {
                    $btn.prop('disabled', false).html(originalBtnText);
                    return;
                }

                // Réinitialisation propre de DataTables et du DOM du tableau
                if (dataTableInstance) {
                    dataTableInstance.destroy();
                    $('#tableReferences').empty();
                }

                // Injection dynamique de l'en-tête (thead) correspondant à la structure retenue
                $('#tableReferences').html('<thead id="dynamic_thead"></thead><tbody class="divide-y divide-slate-100 bg-white"></tbody>');
                $('#dynamic_thead').html(structuresThead[structKey]);

                // Initialisation de DataTables avec les données directement chargées
                dataTableInstance = $('#tableReferences').DataTable({
                    data: listData,
                    columns: getColumnsConfig(structKey),
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json",
                        emptyTable: "Aucune donnée disponible",
                        zeroRecords: "Aucune donnée disponible"
                    },
                    dom: 't<"bottom"ip>',
                    initComplete: function() {
                        let searchContainer = `
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                                    <i class="fas fa-search"></i>
                                </div>
                                <input type="text" id="real_dt_search" placeholder="Rechercher par nom, IM, corps..." class="w-full border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all bg-white font-medium text-slate-700 shadow-sm">
                            </div>
                        `;
                        
                        $('#search_left_placeholder').html(searchContainer);
                        
                        $('#real_dt_search').on('keyup change clear', function() {
                            if (dataTableInstance) {
                                dataTableInstance.search($(this).val()).draw();
                            }
                        });
                    },
                    fnDrawCallback: function() {
                        if (this.fnGetData().length === 0) {
                            $('.dataTables_empty').html(`
                                <div class="flex flex-col items-center justify-center py-16 px-4">
                                    <div class="w-20 h-20 bg-blue-50 rounded-2xl flex items-center justify-center mb-6">
                                        <i class="fas fa-inbox text-5xl text-blue-300"></i>
                                    </div>
                                    <h3 class="text-xl font-semibold text-slate-700 mb-2">Aucun dossier en attente de traitement pour ce type de demande pour l'instant</h3>
                                </div>
                            `);
                        }
                    }
                });
            },
            error: function() {
                Swal.fire('Erreur', 'Impossible de récupérer la liste des dossiers.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalBtnText);
            }
        });
    });
});

window.ouvrirModalTraitement = function(idSuivi, dest, im = '') {
    Swal.fire({
        title: "Traitement du dossier",
        text: "Le dossier de l'agent est-il complet et sans anomalie ?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#ef4444',
        confirmButtonText: '<i class="fas fa-check"></i> Oui (Valider)',
        cancelButtonText: '<i class="fas fa-times"></i> Non (Rejeté)',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            updateStatutAgent(idSuivi, dest, 'valide', '', im);
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire({
                title: 'Motif du rejet',
                input: 'textarea',
                inputPlaceholder: 'Entrez le motif du rejet...',
                showCancelButton: true,
                confirmButtonText: 'Confirmer',
                cancelButtonText: 'Annuler'
            }).then((res) => {
                if (res.isConfirmed && res.value) {
                    updateStatutAgent(idSuivi, dest, 'rejete', res.value, im);
                }
            });
        }
    });
};

window.updateStatutAgent = function(idSuivi, dest, statut, motif = '', im = '') {
    let titreLoader = "Traitement en cours...";
    let texteLoader = "Veuillez patienter pendant l'enregistrement...";

    if (statut === 'valide' && (dest === 'prefet' || dest === 'primature')) {
        texteLoader = "Validation en cours...";
    }

    Swal.fire({
        title: titreLoader,
        text: texteLoader,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading(); 
        }
    });

    $.ajax({
        url: 'api/dossiers/api_traitement.php',
        method: 'POST',
        data: {
            action: 'update_statut_agent',
            id: idSuivi,
            destination: dest,
            statut: statut,
            motif: motif,
            im: im
        },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                Swal.fire({ 
                    icon: 'success', 
                    title: 'Traitement de dossier termminé avec succes !', 
                    timer: 1500, 
                    showConfirmButton: false 
                });

                $('#btn_charger_bordereau').click();
                setTimeout(() => {
                    $('#btn_charger_bordereau').click();
                }, 300);
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue.', 'error');
            }
        },
        error: function(xhr, status, error) {
            Swal.fire('Erreur serveur', 'Impossible d\'effectuer le traitement. Réessayez.', 'error');
        }
    });
};

window.regulariserAgent = function(idSuivi, dest) {
    $.ajax({
        url: 'api/dossiers/api_traitement.php',
        method: 'POST',
        data: {
            action: 'regulariser_agent',
            id: idSuivi,
            destination: dest
        },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Dossier prêt pour traitement',
                    text: 'Le bouton "Traiter" est à nouveau disponible.',
                    timer: 2000,
                    showConfirmButton: false
                });
                $('#btn_charger_bordereau').click(); 
            } else {
                Swal.fire('Erreur', res.message, 'error');
            }
        }
    });
};

const userNiveau = <?php echo json_encode(strtolower(trim($userNiveau))); ?>;
window.voirMotifRejet = function(element, idSuivi, dest) {
    const motif = $(element).data('motif'); 
    if (userNiveau === 'district') {
        Swal.fire({
            title: 'Motif du rejet',
            html: `<div class="text-left p-3 bg-red-50 text-red-700 rounded-xl border border-red-100 text-sm font-medium mb-4">${motif}</div>`,
            icon: 'info',
            showConfirmButton: false, 
            showCancelButton: true,
            cancelButtonText: 'Fermer',
            cancelButtonColor: '#64748b'
        });
    } else {
        Swal.fire({
            title: 'Motif du rejet',
            html: `<div class="text-left p-3 bg-red-50 text-red-700 rounded-xl border border-red-100 text-sm font-medium mb-4">${motif}</div>`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-tools"></i> Rectification / Reprendre',
            cancelButtonText: 'Fermer',
            confirmButtonColor: '#3b82f6'
        }).then((result) => {
            if (result.isConfirmed) {
                window.regulariserAgent(idSuivi, dest);
            }
        });
    }
};
</script>
</body>
</html>
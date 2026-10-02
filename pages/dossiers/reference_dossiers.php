<?php
    require_once __DIR__ . '/../../includes/bootstrap.php';
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/check_session.php';

    $userIm = $_SESSION['user_im'];
    $userRole = trim($_SESSION['user_role'] ?? '');
    $userNiveau = trim($_SESSION['user_niveau'] ?? '');

    $roleSpecifique = '';
    try {
        $stmtRole = $pdo->prepare("SELECT role_specifique, niveau FROM utilisateurs WHERE im = ?");
        $stmtRole->execute([$userIm]);
        $userU = $stmtRole->fetch();
        $roleSpecifique = $userU['role_specifique'] ?? '';
        $userNiveau = $userU['niveau'] ?? $userNiveau;
    } catch (Exception $e) {
        $roleSpecifique = '';
    }

    // --- DÉFINITION DES RÔLES ET DROITS ---
    $isNonEncadre = ($roleSpecifique === 'resp_non_encadre');
    $isEncadre   = ($roleSpecifique === 'resp_encadre');
    $isSolde     = ($roleSpecifique === 'resp_solde');
    $isCRFRP     = ($roleSpecifique === 'resp_personnel_crfrp');
    $isRetraite  = ($roleSpecifique === 'resp_retraite');
    $isAdmin     = in_array($roleSpecifique, ['admin', 'chef_service', 'chef_division']) || in_array($userRole, ['admin', 'chef_service', 'chef_division']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <title>Suivi des Références Bordereaux</title>
    <style>
        #tableReferences {
            border-collapse: separate !important;
            border-spacing: 0 !important;
            width: 100% !important;
            margin-top: 10px !important;
            border: 1px solid rgba(2, 132, 199, 0.2) !important;
            border-radius: 12px !important;
            overflow: hidden !important;
            font-family: ui-sans-serif, system-ui, sans-serif !important;
        }

        #tableReferences thead th {
            background-color: #0284c7 !important;
            color: white !important;
            font-weight: 700 !important;
            padding: 12px 14px !important;
            font-size: 13px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            text-align: center !important;
            vertical-align: middle !important;
        }

        #tableReferences tbody td {
            padding: 10px 10px !important;
            border: 1px solid rgba(2, 132, 199, 0.1) !important;
            color: #334155 !important;
            vertical-align: middle !important;
            font-size: 14px !important;
            text-align: center !important;
        }

        /* Alignement à gauche uniquement pour la colonne Liste des agents */
        #tableReferences tbody td.text-left,
        #tableReferences tbody td.dt-left {
            text-align: left !important;
        }

        #tableReferences tbody tr:hover td {
            background-color: rgba(240, 249, 255, 0.7) !important;
            border-color: rgba(2, 132, 199, 0.3) !important;
            transition: all 0.2s ease;
        }

        .dataTables_wrapper .bottom {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-top: 20px !important;
            padding-top: 15px !important;
            border-top: 1px solid rgba(2, 132, 199, 0.1) !important;
        }

        .dataTables_info {
            font-size: 14px !important;
            color: #64748b !important;
        }

        .dataTables_paginate .paginate_button {
            padding: 0.4rem 0.8rem !important;
            border-radius: 0.5rem !important;
            border: 1px solid rgba(2, 132, 199, 0.2) !important;
            cursor: pointer !important;
            background: white !important;
            color: #0284c7 !important;
            font-size: 13px !important;
            font-weight: 600 !important;
        }

        .dataTables_paginate .paginate_button.current {
            background-color: #0284c7 !important;
            color: white !important;
            border-color: #0284c7 !important;
        }

        .dataTables_empty {
            padding: 50px !important;
            background-color: #f8fafc !important;
            color: #64748b !important;
            font-style: italic !important;
            font-size: 14px !important;
            text-align: center !important;
        }
    </style>
</head>
<body class="bg-slate-50 px-1 py-6 sm:px-4">

<div class="w-full mx-auto bg-white rounded-2xl shadow-xl p-4 sm:p-6 border border-slate-100">
    <h1 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-3 px-1">
        <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
            <i class="fas fa-paste"></i>
        </div>
        Bordereaux en attente de référence du destinataire
    </h1>

    <div class="flex flex-col lg:flex-row lg:items-end gap-3 mb-6 bg-slate-50 p-4 rounded-xl border border-slate-200/60">
        <div class="flex flex-col flex-1">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Recherche rapide :</label>
            <div id="search_left_placeholder" class="w-full relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                    <i class="fas fa-search"></i>
                </div>
                <input type="text" id="custom_search_mock" disabled placeholder="Faites vos sélections..." class="w-full border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-sm bg-slate-100 font-medium text-slate-400 shadow-sm cursor-not-allowed">
            </div>
        </div>

        <!-- 1. Type Bordereau -->
        <div class="flex flex-col">
            <label for="filter_type_bordereau_cat" class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type Bordereau :</label>
            <select id="filter_type_bordereau_cat" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white min-w-[170px] shadow-sm font-medium text-slate-700">
                <?php if ($isSolde): ?>
                    <option value="mandatement" selected>Mandatement</option>
                <?php elseif ($isNonEncadre || $isEncadre || $isRetraite): ?>
                    <option value="creation_projet" selected>Création de Projet</option>
                <?php else: ?>
                    <option value="" disabled selected>-- Choisir type --</option>
                    <option value="creation_projet">Création de Projet</option>
                    <option value="mandatement">Mandatement</option>
                    <?php if ($isCRFRP || $isAdmin): ?>
                        <option value="conge">Décision de congé</option>
                    <?php endif; ?>
                <?php endif; ?>
            </select>
        </div>

        <!-- 2. Type de demande -->
        <div class="flex flex-col">
            <label for="filter_type_bordereau" class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type de demande :</label>
            <select id="filter_type_bordereau" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white min-w-[210px] shadow-sm font-medium text-slate-700">
                <option value="">-- Choisir une demande --</option>
                <?php if ($roleSpecifique === 'resp_solde') : ?>
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="avenant_avec_contrat">Avenant (Rappel différentiel moins perçu)</option>
                    <option value="avancement_classe">Avancement de classe</option>
                    <option value="avancement_echelon">Avancement d'échelon</option>
                    <option value="titularisation">Titularisation</option>
                    <option value="integration">Intégration</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                <?php elseif ($roleSpecifique === 'resp_non_encadre') : ?>
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="integration">Intégration</option>
                <?php elseif ($roleSpecifique === 'resp_encadre') : ?>
                    <option value="titularisation">Titularisation</option>
                    <option value="avancement_classe">Avancement de classe</option>
                    <option value="avancement_echelon">Avancement d'échelon</option>
                <?php elseif (($roleSpecifique === 'resp_personnel_crfrp' && $userNiveau === 'crfrp') || $isAdmin) : ?>
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="avenant_avec_contrat">Avenant (Rappel différentiel moins perçu)</option>
                    <option value="integration">Intégration</option>
                    <option value="titularisation">Titularisation</option>
                    <option value="avancement_classe">Avancement de classe</option>
                    <option value="avancement_echelon">Avancement d'échelon</option>
                    <option value="admission_retraite">Admission à la retraite</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                <?php elseif ($roleSpecifique === 'resp_retraite'): ?>
                    <option value="admission_retraite">Admission à la retraite</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                <?php endif; ?>
            </select>
        </div>

        <!-- 3. Destination -->
        <div class="flex flex-col">
            <label for="filter_destination" class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Destination :</label>
            <select id="filter_destination" class="border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white min-w-[180px] shadow-sm font-medium text-slate-700">
                <?php if ($roleSpecifique === 'resp_solde') : ?>
                    <option value="augure_dsp" selected>Solde et Pensions (DSP)</option>
                <?php elseif (strtolower($userNiveau) === 'district' && $roleSpecifique === 'resp_retraite') : ?>
                    <option value="augure_dren" selected>DREN</option>
                <?php else : ?>
                    <option value="">-- Toutes les destinations --</option>                    
                    <?php if (strtolower($userNiveau) === 'central') : ?>
                        <option value="drh">Direction des Ressources Humaines (DRH)</option>
                        <option value="mtefop">Fonction publique (MTeFOP)</option>
                        <option value="augure_dsp">Solde et Pensions (MEF)</option>
                        <option value="dgcf">Contrôle Financier (DGCF)</option>
                        <option value="primature">Primature</option>
                    <?php else : ?>
                        <option value="augure_dren">DREN</option>
                        <option value="augure_fop">Fonction Publique (DRHEFOP)</option>
                        <option value="augure_dsp">Solde et Pensions (DSP)</option>
                        <option value="augure_cf">Contrôle Financier (DGCF)</option>
                        <option value="prefecture">Préfecture</option>
                        <option value="drh">Direction des Ressources Humaines (DRH)</option>
                        <option value="mtefop">Fonction publique (MTeFOP)</option>
                        <option value="primature">Primature</option>
                    <?php endif; ?>
                <?php endif; ?>
            </select>
        </div>

        <div>
            <button id="btn_charger_bordereau" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm px-4 py-2.5 rounded-xl transition-all shadow-md shadow-blue-500/10 active:scale-95 flex items-center gap-2 h-[42px] justify-center">
                <i class="fas fa-sync-alt text-xs"></i> Afficher la liste
            </button>
        </div>
    </div>

    <div class="overflow-x-auto bg-white">
        <table id="tableReferences" class="w-full">
            <thead id="dynamic_thead">
                <tr>
                    <td colspan="5" class="py-12 text-center border-none bg-white">
                        <div class="flex flex-col items-center justify-center space-y-4">
                            <i class="fas fa-mouse-pointer text-6xl text-slate-200"></i>
                            <div class="flex flex-col items-center">
                                <span class="text-xl font-black text-slate-400 uppercase tracking-tighter">Sélectionner les filtres</span>
                                <span class="text-[12px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-1 text-center">Choisissez le type de demande et la destination pour afficher le tableau</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white"></tbody>
        </table>
    </div>
</div>

<!-- Modale de Saisie de Référence Embellie -->
<div id="modalRef" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 transition-all duration-300 opacity-0">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden transform transition-all scale-95 duration-300 border border-slate-100">
        
        <!-- En-tête colorée avec dégradé -->
        <div class="bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-700 px-6 py-5 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-white border border-white/20 shadow-inner">
                    <i class="fas fa-file-signature text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold tracking-wide">Attribution de Référence</h3>
                    <p id="modalTypeDemande" class="text-xs font-medium text-blue-100/90 mt-0.5"></p>
                </div>
            </div>
            <button type="button" onclick="closeModalRef()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white/80 hover:text-white transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        
        <!-- Corps de la modale -->
        <form id="form_add_ref" class="p-6">
            <input type="hidden" id="ref_id" name="id">
            <input type="hidden" id="ref_dest" name="dest_key">
            <input type="hidden" id="ref_type_demande" name="type_demande">
            
            <!-- Badge Destination -->
            <div class="mb-5 flex items-center justify-between bg-slate-50 p-3 rounded-2xl border border-slate-100">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Destination</span>
                <span id="modalDestBadge" class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-100/80 text-blue-800 font-bold text-xs rounded-xl shadow-sm border border-blue-200/60"></span>
            </div>

            <!-- Message d'information dynamique pour la DREN (Nouvelle année) -->
            <div id="drenRefHint" class="hidden mb-5 p-3.5 bg-amber-50/80 border border-amber-200/80 rounded-2xl text-xs text-amber-900 shadow-sm flex items-start gap-3">
                <div class="w-7 h-7 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fas fa-calendar-check text-sm"></i>
                </div>
                <div id="drenRefHintText" class="leading-relaxed"></div>
            </div>

            <div class="space-y-4">
                <!-- Date de Référence -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Date de Référence</label>
                    <div class="relative">
                        <input type="date" id="date_ref_input" name="date_ref" class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 focus:outline-none transition-all bg-slate-50/50" required>
                    </div>
                </div>

                <!-- Numéro de Référence -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Numéro de Référence</label>
                    <div class="relative">
                        <input type="text" id="reference_input" name="reference" placeholder="Ex: 001/2026" class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-bold text-blue-700 placeholder-slate-300 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 focus:outline-none transition-all bg-slate-50/50" required>
                    </div>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModalRef()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-xs rounded-xl transition-all active:scale-95">
                    Annuler
                </button>
                <button type="submit" id="btnSubmitRef" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl shadow-lg shadow-blue-500/20 transition-all active:scale-95">
                    <i class="fas fa-check-circle"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
<script>
// Variable globale obligatoire
let dataTableInstance = null;

$(document).ready(function() {
    const userNiveau = "<?= strtolower($userNiveau) ?>";
    const roleSpecifique = "<?= $roleSpecifique ?>";

    // Libellés simplifiés selon vos instructions
    const destLabels = {
        'augure_dren': 'DREN',
        'augure_fop': 'Fonction Publique (DRHEFOP)',
        'augure_dsp': 'Solde et Pensions (DSP)',
        'augure_cf': 'Contrôle Financier (DGCF)',
        'prefecture': 'Préfecture',
        'drh': 'Direction des Ressources Humaines (DRH)',
        'mtefop': 'Fonction publique (MTeFOP)',
        'primature': 'Primature'
    };

    function updateDestinationsOptions() {
        if (roleSpecifique === 'resp_solde') {
            const $destSelect =$('#filter_destination');
            $destSelect.empty().append('<option value="augure_dsp" selected>Solde et Pensions (DSP)</option>');
            return;
        }

        const cat = $('#filter_type_bordereau_cat').val();
        const demande = $('#filter_type_bordereau').val();
        const $destSelect =$('#filter_destination');
        $destSelect.empty();$destSelect.append('<option value="">-- Toutes les destinations --</option>');

        let allowedKeys = [];

        if (cat === 'mandatement') {
            allowedKeys = ['augure_dsp'];
        } else if (cat === 'conge' || demande === 'conge_annuel') {
            allowedKeys = ['augure_fop', 'prefecture'];
        } else {
            // --- NIVEAU DISTRICT ---
            if (userNiveau === 'district') {
                if (demande === 'admission_retraite') {
                    allowedKeys = ['augure_fop', 'drh'];
                } else if (demande === 'compensatrice') {
                    allowedKeys = ['augure_dren'];
                } else if (demande === 'installation') {
                    allowedKeys = ['augure_fop', 'augure_dsp', 'drh'];
                } else if (['renouvellement', 'avenant'].includes(demande)) {
                    allowedKeys = ['augure_dren', 'augure_dsp', 'augure_cf', 'prefecture'];
                } else if (['integration'].includes(demande)) {
                    allowedKeys = ['augure_dren', 'augure_fop', 'augure_dsp', 'augure_cf', 'drh'];
                } else if (demande === 'titularisation') {
                    allowedKeys = ['augure_dren', 'augure_fop', 'augure_dsp', 'drh'];
                } else if (['avancement_classe', 'avancement_echelon'].includes(demande)) {
                    allowedKeys = ['augure_dren', 'augure_fop', 'augure_dsp', 'augure_cf', 'prefecture'];
                } else {
                    allowedKeys = ['augure_dren'];
                }
            }
            // --- NIVEAU CENTRAL ---
            else if (userNiveau === 'central') {
                if (demande === 'admission_retraite') {
                    allowedKeys = ['drh', 'augure_dsp', 'augure_cf', 'mtefop', 'primature'];
                } else if (demande === 'compensatrice') {
                    allowedKeys = ['drh', 'augure_dsp', 'augure_cf', 'men'];
                } else if (demande === 'installation') {
                    allowedKeys = ['drh', 'augure_dsp', 'augure_cf', 'men'];
                } else if (['integration', 'titularisation'].includes(demande)) {
                    allowedKeys = ['drh', 'augure_fop', 'augure_dsp', 'augure_cf', 'mtefop', 'primature'];
                } else if (['renouvellement', 'avenant', 'avancement_classe', 'avancement_echelon'].includes(demande)) {
                    allowedKeys = ['augure_dsp', 'augure_cf', 'men'];
                } else {
                    allowedKeys = ['drh', 'mtefop', 'augure_dsp', 'primature'];
                }
            } 
            // --- NIVEAU RÉGIONAL ---
            else if (userNiveau === 'regional') {
                if (demande === 'admission_retraite') {
                    allowedKeys = ['augure_fop', 'drh'];
                } else if (demande === 'compensatrice') {
                    allowedKeys = ['augure_dren', 'augure_dsp', 'augure_cf', 'prefecture'];
                } else if (demande === 'installation') {
                    allowedKeys = ['augure_fop', 'augure_dsp', 'drh'];
                } else if (['renouvellement', 'avenant'].includes(demande)) {
                    allowedKeys = ['augure_dren', 'augure_dsp', 'augure_cf', 'prefecture'];
                } else if (['avancement_classe', 'avancement_echelon'].includes(demande)) {
                    allowedKeys = ['augure_dren', 'augure_fop', 'augure_dsp', 'augure_cf', 'prefecture'];
                } else if (demande === 'integration') {
                    allowedKeys = ['augure_dren', 'augure_fop', 'augure_dsp', 'augure_cf', 'drh'];
                } else if (demande === 'titularisation') {
                    allowedKeys = ['augure_dren', 'augure_fop', 'augure_dsp', 'drh'];
                } else {
                    allowedKeys = ['augure_dren', ...Object.keys(destLabels).filter(k => k !== 'augure_dren')];
                }
            }
            // --- NIVEAU CRFRP ---
            else if (userNiveau === 'crfrp') {
                if (demande === 'admission_retraite') {
                    allowedKeys = ['augure_fop', 'drh'];
                } else if (demande === 'compensatrice') {
                    allowedKeys = ['augure_dren', 'augure_dsp', 'augure_cf', 'prefecture'];
                } else if (demande === 'installation') {
                    allowedKeys = ['augure_fop', 'augure_dsp', 'drh'];
                } else if (demande === 'avenant_avec_contrat') {
                    allowedKeys = ['augure_dsp'];
                } else if (['renouvellement', 'avenant'].includes(demande)) {
                    allowedKeys = ['augure_dren', 'augure_dsp', 'augure_cf', 'prefecture'];
                } else if (['integration'].includes(demande)) {
                    allowedKeys = ['augure_dren', 'augure_fop', 'augure_dsp', 'augure_cf', 'drh'];
                } else if (demande === 'titularisation') {
                    allowedKeys = ['augure_dren', 'augure_fop', 'augure_dsp', 'drh'];
                } else if (['avancement_classe', 'avancement_echelon'].includes(demande)) {
                    allowedKeys = ['augure_dren', 'augure_fop', 'augure_dsp', 'augure_cf', 'prefecture'];
                } else {
                    allowedKeys = ['augure_dren'];
                }
            }
        }

        allowedKeys.forEach(function(key) {
            if (destLabels[key]) {
                $destSelect.append(`<option value="${key}">${destLabels[key]}</option>`);
            }
        });
    }

    $('#filter_type_bordereau, #filter_type_bordereau_cat').on('change', function() {
        updateDestinationsOptions();
    });

    function renderCelluleDest(data, cellKey, row) {
        if (!data) {
            return `<span class="text-slate-300 font-medium text-xs">---</span>`;
        }

        // Récupération de la clé du type de demande
        const typeDemandeKey = row.type_demande || row.type_bordereau || $('#filter_type_bordereau').val();
        
        // Vérification : si c'est district mais pas solde, certains types de demandes autorisent quand même la saisie de référence
        const estDistrictSaisieAutorisee = (userNiveau === 'district' && roleSpecifique !== 'resp_solde') && 
            ['admission_retraite', 'installation'].includes(typeDemandeKey);

        if (data.reference && data.reference.trim() !== '') {
            let dateFormated = data.date_ref ? data.date_ref.split('-').reverse().join('/') : '';
            
            // Seuls les dossiers en attente non gérés par le district restent en lecture seule sans clic
            if (userNiveau === 'district' && roleSpecifique !== 'resp_solde' && !estDistrictSaisieAutorisee) {
                return `
                    <div class="flex flex-col items-center justify-center p-1.5 bg-blue-50 rounded-xl border border-blue-100 shadow-sm mx-auto max-w-[180px]">
                        <span class="text-blue-700 font-bold text-[12px] tracking-wide">N° ${data.reference} du ${dateFormated}</span>
                    </div>
                `;
            }

            return `
                <div onclick="openModalRef(${row.id}, '${cellKey}')" class="flex flex-col items-center justify-center p-1.5 bg-blue-50 rounded-xl border border-blue-100 shadow-sm mx-auto max-w-[180px] cursor-pointer hover:bg-blue-100/70 transition-all" title="Cliquez pour modifier la référence">
                    <span class="text-blue-700 font-bold text-[12px] tracking-wide">N° ${data.reference} du ${dateFormated}</span>
                </div>
            `;
        } else {
            // Si c'est un district mais que la demande est admission_retraite ou installation, on affiche le bouton "Donner Référence"
            if (userNiveau === 'district' && roleSpecifique !== 'resp_solde' && !estDistrictSaisieAutorisee) {
                return `
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-50 text-amber-600 border border-amber-200/60 shadow-sm mx-auto cursor-default">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500 border border-amber-600 border-dashed animate-spin"></span>
                        </span>
                        En attente
                    </span>
                `;
            }

            return `
                <button type="button" onclick="openModalRef(${row.id}, '${cellKey}')" 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl transition-all shadow-sm active:scale-95 whitespace-nowrap mx-auto">
                    <i class="fas fa-plus-circle text-[12px]"></i> Donner Référence
                </button>
            `;
        }
    }

    function buildTableHeader(selectedDest) {
        if (userNiveau === 'district' && roleSpecifique !== 'resp_solde') {
            return `
                <tr class="divide-x divide-blue-600/30 text-white">
                    <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">N°</th>
                    <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Type de demande</th>
                    <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Numéro Bordereau</th>
                    <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Nombre d'agents</th>
                    <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Liste des agents</th>
                    <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Expéditeur</th>
                    <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Destinataire</th>
                    <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Référence du dossier</th>
                </tr>`;
        }

        let colsHtml = '';
        if (selectedDest && destLabels[selectedDest]) {
            colsHtml = `<th class="p-3 text-xs font-semibold uppercase">${destLabels[selectedDest]}</th>`;
        } else {
            $('#filter_destination option').each(function() {
                let val = $(this).val();
                if (val && destLabels[val]) {
                    colsHtml += `<th class="p-3 text-xs font-semibold uppercase">${destLabels[val]}</th>`;
                }
            });
        }

        return `
            <tr class="divide-x divide-blue-600/30 text-white">
                <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">N°</th>
                <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Numéro Bordereau</th>
                <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Nombre d'agents</th>
                <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Liste des agents</th>
                <th class="p-4 border-b border-blue-800 text-xs uppercase tracking-wider font-semibold">Expéditeur</th>
                ${colsHtml}
            </tr>`;
    }

    function getColumnsConfig(selectedDest) {
        if (userNiveau === 'district' && roleSpecifique !== 'resp_solde') {
            return [
                { data: null, render: (d, t, r, meta) => meta.row + 1, className: 'font-bold p-4 text-slate-700' },
                { data: 'type_demande_libelle', className: 'p-4 text-slate-700 font-semibold text-left' },
                { data: 'numero_complet', className: 'font-mono text-blue-600 font-bold p-4 text-left' },
                { 
                    data: 'nombre_agents', 
                    className: 'p-4 font-bold text-slate-700',
                    render: function(data) {
                        const nb = parseInt(data) || 0;
                        return `<span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded-lg bg-blue-50 text-blue-700 font-bold border border-blue-100">${String(nb).padStart(2, '0')}</span>`;
                    }
                },
                { data: 'formatted_agents_list', className: 'p-4 text-slate-700 font-medium text-left leading-relaxed' },
                { data: 'expediteur', className: 'p-4 text-slate-500 font-medium text-left' },
                { data: 'destinataire', className: 'p-4 text-slate-700 font-semibold text-left' },
                { 
                    data: null, 
                    render: function(d, t, r) {
                        return renderCelluleDest(r[r.dest_key], r.dest_key, r);
                    } 
                }
            ];
        }

        let cols = [
            { data: null, render: (d, t, r, meta) => meta.row + 1, className: 'font-bold p-4 text-slate-700' },
            { data: 'numero_complet', className: 'font-mono text-blue-600 font-bold p-4 text-left' },
            { 
                data: 'nombre_agents', 
                className: 'p-4 font-bold text-slate-700',
                render: function(data) {
                    const nb = parseInt(data) || 0;
                    return `<span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded-lg bg-blue-50 text-blue-700 font-bold border border-blue-100">${String(nb).padStart(2, '0')}</span>`;
                }
            },
            { data: 'formatted_agents_list', className: 'p-4 text-slate-700 font-medium text-left leading-relaxed' },
            { data: 'expediteur', className: 'p-4 text-slate-500 font-medium text-left' }
        ];

        if (selectedDest && destLabels[selectedDest]) {
            cols.push({
                data: selectedDest,
                render: (d, t, r) => renderCelluleDest(d, selectedDest, r)
            });
        } else {
            $('#filter_destination option').each(function() {
                let val = $(this).val();
                if (val && destLabels[val]) {
                    cols.push({
                        data: val,
                        render: (d, t, r) => renderCelluleDest(d, val, r)
                    });
                }
            });
        }

        return cols;
    }

    $('#filter_type_bordereau_cat').on('change', function() {
        if (roleSpecifique === 'resp_solde') return;

        const tbCat = $(this).val();
        let demandOpts = '<option value="">-- Choisir une demande --</option>';
        
        if (tbCat === 'conge') {
            demandOpts = '<option value="conge_annuel" selected>Congé annuel</option>';
        } else if (tbCat === 'mandatement') {
            if (roleSpecifique === 'resp_non_encadre') {
                demandOpts += `
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="integration">Intégration</option>
                `;
            } else if (roleSpecifique === 'resp_retraite') {
                demandOpts += `
                    <option value="admission_retraite">Admission à la retraite</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                `;
            } else {
                demandOpts += `
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="avenant_avec_contrat">Avenant (Rappel différentiel moins perçu)</option>
                    <option value="integration">Intégration</option>
                    <option value="titularisation">Titularisation</option>
                    <option value="avancement_classe">Avancement de classe</option>
                    <option value="avancement_echelon">Avancement d'échelon</option>
                    <option value="admission_retraite">Admission à la retraite</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                `;
            }
        } else {
            // Cas Creation de projet / Autres
            if (roleSpecifique === 'resp_non_encadre') {
                demandOpts += `
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="integration">Intégration</option>
                `;
            } else if (roleSpecifique === 'resp_encadre') {
                demandOpts += `
                    <option value="titularisation">Titularisation</option>
                    <option value="avancement_classe">Avancement de classe</option>
                    <option value="avancement_echelon">Avancement d'échelon</option>
                `;
            } else if (roleSpecifique === 'resp_retraite') {
                demandOpts += `
                    <option value="admission_retraite">Admission à la retraite</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                `;
            } else if (['resp_personnel_crfrp', 'admin', 'chef_service', 'chef_division'].includes(roleSpecifique) || userNiveau === 'district' || userNiveau === 'central') {
                demandOpts += `
                    <option value="renouvellement">Renouvellement de contrat</option>
                    <option value="avenant">Avenant</option>
                    <option value="avenant_avec_contrat">Avenant (Rappel différentiel moins perçu)</option>
                    <option value="integration">Intégration</option>
                    <option value="titularisation">Titularisation</option>
                    <option value="avancement_classe">Avancement de classe</option>
                    <option value="avancement_echelon">Avancement d'échelon</option>
                    <option value="admission_retraite">Admission à la retraite</option>
                    <option value="compensatrice">Compensatrice</option>
                    <option value="installation">Installation</option>
                `;
            }
        }

        $('#filter_type_bordereau').html(demandOpts);
        updateDestinationsOptions();
    });

    function loadTableData() {
        const typeDemande = $('#filter_type_bordereau').val();
        const typeBordereauCat = $('#filter_type_bordereau_cat').val();
        const selectedDest = $('#filter_destination').val();
        
        // --- CONTROLE STRICT : Vérifier la valeur exacte du Type de demande et Destination ---
        // Remplacez 'tous' par la valeur par défaut / générique de votre <select> si elle existe
        const isTypeDemandeValide = typeDemande && typeDemande !== '' && typeDemande !== 'tous';
        const isDestinationValide = selectedDest && selectedDest !== '' && selectedDest !== 'tous';

        if (!isTypeDemandeValide || !isDestinationValide) {
            Swal.fire({
                icon: 'info',
                title: 'Sélection requise',
                text: "Veuillez sélectionner un type de demande valide et une destination avant d'afficher la liste.",
                confirmButtonColor: '#0284c7'
            });
            return;
        }

        if (dataTableInstance) {
            dataTableInstance.destroy();
            $('#tableReferences').empty();
        }

        $('#tableReferences').html('<thead id="dynamic_thead"></thead><tbody class="divide-y divide-slate-100 bg-white"></tbody>');
        $('#dynamic_thead').html(buildTableHeader(selectedDest));

        dataTableInstance = $('#tableReferences').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'api/dossiers/api_reference.php',
                type: 'POST',
                data: {
                    action: 'list_bordereau',
                    type: typeDemande,
                    type_bordereau_cat: typeBordereauCat,
                    destination: selectedDest
                },
                dataSrc: function(json) {
                    return json.data || [];
                }
            },
            columns: getColumnsConfig(selectedDest),
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
                        <input type="text" id="real_dt_search" placeholder="Rechercher par n° bordereau, nom, IM, expéditeur..." class="w-full border border-slate-200 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all bg-white font-medium text-slate-700 shadow-sm">
                    </div>
                `;
                $('#search_left_placeholder').html(searchContainer);
                
                $('#real_dt_search').off('keyup change clear').on('keyup change clear', function() {
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
                            <h3 class="text-xl font-semibold text-slate-700 mb-2">Aucun bordereau en attente de référence</h3>
                        </div>
                    `);
                }
            }
        });
    }

    $('#btn_charger_bordereau').on('click', loadTableData);

    $('#form_add_ref').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnSubmitRef');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Traitement...');
        
        $.ajax({
            url: 'api/dossiers/api_reference.php',
            type: 'POST',
            data: $(this).serialize() + '&action=add_reference',
            dataType: 'json',
            success: function(res) {
                if (res && res.success === true) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Référence enregistrée',
                        text: 'Le suivi a été mis à jour avec succès.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    closeModalRef();
                    if (dataTableInstance) dataTableInstance.ajax.reload(null, false);
                } else {
                    Swal.fire('Erreur', (res && res.message) ? res.message : 'Une erreur est survenue lors de l\'enregistrement', 'error');
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Référence enregistrée',
                    text: 'Le suivi a été mis à jour.',
                    timer: 1500,
                    showConfirmButton: false
                });
                closeModalRef();
                if (dataTableInstance) dataTableInstance.ajax.reload(null, false);
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    if ($('#filter_type_bordereau_cat').val()) {
        $('#filter_type_bordereau_cat').trigger('change');
    }
});

window.openModalRef = function(id, dest) {
    const destLabels = {
        'augure_dren': 'DREN',
        'augure_fop': 'Fonction Publique (DRHEFOP)',
        'augure_dsp': 'Solde et Pensions (DSP)',
        'augure_cf': 'Contrôle Financier (DGCF)',
        'prefecture': 'Préfecture',
        'drh': 'Direction des Ressources Humaines (DRH)',
        'mtefop': 'Fonction publique (MTeFOP)',
        'primature': 'Primature'
    };

    $('#ref_id').val(id);
    $('#ref_dest').val(dest);

    // Récupération des informations depuis la ligne DataTables
    let rowData = null;
    if (dataTableInstance) {
        dataTableInstance.rows().every(function() {
            let d = this.data();
            if (d.id == id) {
                rowData = d;
            }
        });
    }

    // Récupération de la clé du type de demande (ex: 'renouvellement')
    let typeDemandeKey = $('#filter_type_bordereau').val();
    if (rowData && rowData.type_bordereau) {
        typeDemandeKey = rowData.type_bordereau;
    } else if (rowData && rowData.type_demande) {
        typeDemandeKey = rowData.type_demande;
    }

    // REMPLISSAGE DU CHAMP CACHÉ POUR PHP
    $('#ref_type_demande').val(typeDemandeKey);

    // Affichage du libellé personnalisé
    const labelAffichage = destLabels[dest] || dest;
    $('#modalDestBadge').html(`<i class="fas fa-building text-[10px]"></i> ${labelAffichage}`);
    
    $('#date_ref_input').val(new Date().toISOString().split('T')[0]);
    $('#reference_input').val('');
    $('#drenRefHint').addClass('hidden');

    // Affichage du nom du TYPE DE DEMANDE dans l'en-tête de la modale
    let typeDemandeLibelle = '';
    if (rowData && rowData.type_demande_libelle) {
        typeDemandeLibelle = rowData.type_demande_libelle;
    } else {
        typeDemandeLibelle = $('#filter_type_bordereau option:selected').text();
    }

    if (typeDemandeLibelle && !typeDemandeLibelle.includes('--')) {
        $('#modalTypeDemande').text(`Type : ${typeDemandeLibelle}`);
    } else {
        $('#modalTypeDemande').text('');
    }

    // Pré-remplir la référence si elle existe déjà dans la cellule
    if (rowData && rowData[dest] && rowData[dest].reference) {
        $('#reference_input').val(rowData[dest].reference);
    }

    // TRAITEMENT SPÉCIFIQUE DESTINATION DREN (Calcul numéro de référence)
    if (dest === 'augure_dren' && typeDemandeKey) {
        $.ajax({
            url: 'api/dossiers/api_reference.php',
            type: 'POST',
            data: {
                action: 'get_next_dren_ref',
                type_demande: typeDemandeKey
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    const currentYear = res.year || new Date().getFullYear();
                    if (res.last_ref && !res.last_ref.includes('Aucune')) {
                        $('#drenRefHintText').html(`<span class="text-[14px]">Dernière référence pour ce type de demande : <strong class="text-blue-700 font-bold">${res.last_ref}</strong><br>Numéro suggéré : <strong class="text-blue-700 font-bold">${res.next_ref}</strong></span>`);
                    } else {
                        $('#drenRefHintText').html(`<span class="text-[14px]">Premier référence pour ce type de demande<br>La numérotation démarre automatiquement à <strong class="text-blue-700 font-bold">${res.next_ref}</strong></span>`);
                    }
                    
                    $('#drenRefHint').removeClass('hidden');
                    
                    if (!$('#reference_input').val()) {
                        $('#reference_input').val(res.next_ref);
                    }
                }
            }
        });
    }

    // Ouverture de la modale
    const modal = $('#modalRef');
    modal.removeClass('hidden').addClass('flex');
    setTimeout(() => {
        modal.removeClass('opacity-0').addClass('opacity-100');
        modal.find('.transform').removeClass('scale-95').addClass('scale-100');
    }, 10);
};

window.closeModalRef = function() {
    const modal = $('#modalRef');
    modal.removeClass('opacity-100').addClass('opacity-0');
    modal.find('.transform').removeClass('scale-100').addClass('scale-95');
    setTimeout(() => { 
        modal.addClass('hidden').removeClass('flex'); 
        $('#drenRefHint').addClass('hidden');
    }, 300);
};
</script>
</body>
</html>
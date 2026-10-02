<?php
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/check_session.php';
    $user_im = $_SESSION['user_im'];

    // Récupération des informations de l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT niveau, code_lieu_affectation, role_specifique FROM utilisateurs WHERE im = ?");
    $stmt->execute([$user_im]);
    $user = $stmt->fetch();

    $niveau              = $user['niveau']; 
    $code_lieu_affectation = $user['code_lieu_affectation']; 
    $role_specifique     = $user['role_specifique'];

    // Initialisation des variables par défaut
    $nom_region_fixe = '';
    $id_region_fixe  = '';
    $nom_district_fixe = '';
    $id_district_fixe = '';
    $nom_crfrp_fixe = '';
    $id_crfrp_fixe = '';

    // ====================== CAS CRFRP ======================
    if ($role_specifique === 'resp_personnel_crfrp' && $niveau === 'crfrp') {
        $stmtCrfrp = $pdo->prepare("
            SELECT c.id as crfrp_id, c.nom_crfrp, c.lieu_crfrp, 
                   d.id as dist_id, d.nom_district, 
                   r.id as reg_id, r.nom_region 
            FROM ref_crfrp c
            JOIN ref_districts d ON c.district_id = d.id
            JOIN ref_regions r ON d.region_id = r.id
            WHERE c.nom_crfrp = ?
        ");
        $stmtCrfrp->execute([$code_lieu_affectation]);
        $crfrpData = $stmtCrfrp->fetch();

        if ($crfrpData) {
            $id_crfrp_fixe     = $crfrpData['crfrp_id'];
            $nom_crfrp_fixe    = $crfrpData['nom_crfrp'];
            $id_district_fixe  = $crfrpData['dist_id'];
            $nom_district_fixe = $crfrpData['nom_district'];
            $id_region_fixe    = $crfrpData['reg_id'];
            $nom_region_fixe   = $crfrpData['nom_region'];
        }
    }
    // ====================== CAS DISTRICT ======================
    elseif ($role_specifique === 'resp_solde' && $niveau === 'district') {
        $stmtDist = $pdo->prepare("
            SELECT d.id as dist_id, d.nom_district, r.id as reg_id, r.nom_region 
            FROM ref_districts d 
            JOIN ref_regions r ON d.region_id = r.id 
            WHERE d.nom_district = ?
        ");
        $stmtDist->execute([$code_lieu_affectation]);
        $distData = $stmtDist->fetch();
        
        if ($distData) {
            $id_district_fixe  = $distData['dist_id'];
            $nom_district_fixe = $distData['nom_district'];
            $id_region_fixe    = $distData['reg_id'];
            $nom_region_fixe   = $distData['nom_region'];
        }
    }
    // ====================== CAS RÉGIONAL ======================
    elseif ($role_specifique === 'resp_solde' && $niveau === 'regional') {
        $stmtReg = $pdo->prepare("SELECT id, nom_region FROM ref_regions WHERE nom_region = ?");
        $stmtReg->execute([$code_lieu_affectation]);
        $regData = $stmtReg->fetch();
        
        if ($regData) {
            $id_region_fixe  = $regData['id'];
            $nom_region_fixe = $regData['nom_region'];
        }
    }
?>

<div id="dashboard-view" class="p-3 space-y-4 animate-in fade-in duration-500 h-full overflow-hidden flex flex-col">
    <div class="bg-white rounded-[1.5rem] shadow-xl border border-slate-50 flex-grow flex flex-col overflow-hidden">
        <div class="flex items-center justify-between border-b pb-4 px-6 pt-5">
            <div class="flex items-center gap-3">
                <div class="w-1.5 h-5 bg-sky-600 rounded-full"></div>
                <h2 class="text-lg font-black text-slate-800 uppercase tracking-wider">Mandatement des Dossiers</h2>
            </div>
            <button onclick="ouvrirFormulaireAgent()" class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-sky-100 transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> nouveau mandatement
            </button>
        </div>
        <div class="px-6 pt-4 pb-2">
            <div class="relative max-w-md">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                    <i class="fas fa-search"></i>
                </div>
                <input type="text" id="searchMandat" 
                    class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 text-sm placeholder-slate-400 transition-all"
                    placeholder="Rechercher par IM ou Nom...">
            </div>
        </div>

        <div class="flex-1 p-6 overflow-auto">
            <table id="tableMandat" class="w-full text-sm">
                <thead>
                    <tr class="bg-[#0369a1] text-white">
                        <th class="p-4 text-center">N°</th>
                        <th class="p-4 text-left">Type de demande</th>
                        <th class="p-4 text-left">Nom et Prénoms</th>
                        <th class="p-4 text-center">IM</th>
                        <th class="p-4 text-left">Corps</th>
                        <th class="p-4 text-left">Dernière situation</th>
                        <th class="p-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="sub-page-view" class="hidden p-[15px] min-h-screen bg-slate-50/50">
    <div id="sub-page-content" class="bg-white rounded-[2rem] shadow-2xl overflow-hidden min-h-[calc(100vh-30px)] flex flex-col"></div>
</div>

<template id="template-formulaire-agent">
    <div class="w-full flex-grow flex flex-col justify-between">
        <div class="bg-slate-50 border-b border-slate-100 px-12 py-8">
            <div class="relative max-w-4xl mx-auto px-4">
                <div class="absolute left-0 top-5 w-full h-0.5 bg-slate-200/80 rounded-full z-0"></div>
                <div id="progress-bar" class="absolute left-0 top-5 w-0 h-0.5 bg-sky-600 rounded-full z-0 transition-all duration-500 shadow-sm"></div>

                <div class="relative flex justify-between z-10">
                    <button type="button" onclick="window.switchTab(1)" class="group flex flex-col items-center gap-2.5 focus:outline-none" id="step-1">
                        <div class="w-10 h-10 rounded-xl bg-sky-600 text-white font-bold flex items-center justify-center shadow-lg shadow-sky-100 transition-all duration-300 border-2 border-white ring-4 ring-sky-50" id="step-icon-1">1</div>
                        <span class="text-xs font-black text-sky-600 tracking-wide uppercase" id="step-label-1">Renseignements</span>
                    </button>

                    <button type="button" onclick="window.switchTab(2)" class="group flex flex-col items-center gap-2.5 focus:outline-none" id="step-2">
                        <div class="w-10 h-10 rounded-xl bg-white text-slate-400 font-bold flex items-center justify-center transition-all duration-300 border-2 border-slate-200 group-hover:border-slate-300" id="step-icon-2">2</div>
                        <span class="text-xs font-bold text-slate-400 tracking-wide uppercase" id="step-label-2">Ancienne Position</span>
                    </button>

                    <button type="button" onclick="window.switchTab(3)" class="group flex flex-col items-center gap-2.5 focus:outline-none" id="step-3">
                        <div class="w-10 h-10 rounded-xl bg-white text-slate-400 font-bold flex items-center justify-center transition-all duration-300 border-2 border-slate-200 group-hover:border-slate-300" id="step-icon-3">3</div>
                        <span class="text-xs font-bold text-slate-400 tracking-wide uppercase" id="step-label-3">Nouvelle position</span>
                    </button>
                </div>
            </div>
        </div>

        <form id="agentForm" onsubmit="window.submitFormAgent(event)" class="p-8 w-full flex-grow flex flex-col justify-between">
            <input type="hidden" name="type_demande" id="form-type-demande">
            <input type="hidden" id="localite_service" name="localite_service" value="">
            
            <div id="tab-content-1" class="tab-content space-y-6">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="w-1 h-4 bg-sky-600 rounded-full"></div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">État Civil (<span id="txt-type-demande" class="text-sky-600"></span>)</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5"> 
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Nom</label>
                        <input type="text" id="nom" name="nom" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Prénoms</label>
                        <input type="text" id="prenoms" name="prenoms" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Date de Naissance</label>
                        <input type="date" id="date_naiss" name="date_naissance" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Lieu de Naissance</label>
                        <input type="text" id="lieu_naiss" name="lieu_naissance" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Numéro CIN</label>
                        <input type="text" id="cin" name="cin" required maxlength="12" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Date de délivrance</label>
                        <input type="date" id="date_cin" name="date_cin" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Lieu de délivrance</label>
                        <input type="text" id="lieu_cin" name="lieu_cin" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Sexe</label>
                        <select id="sexe" name="sexe" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner...</option>
                            <option value="Masculin">Masculin</option>
                            <option value="Féminin">Féminin</option>
                        </select>
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Situation Matrimoniale</label>
                        <select id="situation_matrimoniale" name="situation_matrimoniale" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner...</option>
                            <option value="Célibataire">Célibataire</option>
                            <option value="Marié(e)">Marié(e)</option>
                            <option value="Divorcé(e)">Divorcé(e)</option>
                            <option value="Veuf(ve)">Veuf(ve)</option>
                        </select>
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Nombre enfants (Inscrit dans BC)</label>
                        <input type="number" id="nombre_enfant" name="nombre_enfant" min="0" value="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                    </div>
                </div>
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3 mt-8">
                    <div class="w-1 h-4 bg-emerald-600 rounded-full"></div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Localité de Service</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Région</label>
                        <input type="text" id="localite_region" readonly 
                            class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-700 font-medium">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">District</label>
                        <div id="district_container">
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">ZAP</label>
                        <input type="text" id="localite_zap" readonly 
                            class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-700 font-medium">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Établissement</label>
                        <input type="text" id="localite_etablissement" readonly 
                            class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-700 font-medium">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Localité de service</label>
                        <input type="text" id="localite_service_display" name ="localite_service_display" readonly 
                            class="w-full px-4 py-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-semibold">
                        <input type="hidden" id="localite_service" name="localite_service" value="">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Zone</label>
                        <input type="text" id="zone" name="zone" 
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700"
                            placeholder="Ex: Centre ville">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Commune</label>
                        <select id="localite_commune" name="commune_id" 
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner la commune...</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="tab-content-2" class="tab-content space-y-6 hidden">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="w-1 h-4 bg-amber-500 rounded-full"></div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Situation administrative</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5"> 
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Matricule (IM)</label>
                        <input type="text" id="im" name="im" readonly required class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl focus:outline-none text-sm text-slate-500 font-semibold cursor-not-allowed">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Statut</label>
                        <select id="statut_agent" name="statut" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner...</option>
                            <option value="Fonctionnaire">Fonctionnaire</option>
                            <option value="Contractuel EFA">Contractuel EFA</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Date d'entrée à l'administration</label>
                        <input type="date" id="date_entree_admin" name="date_entree_admin" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 text-sm transition-all text-slate-700">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Imputation Budgétaire</label>
                        <select id="imput_budg" name="imputation_budgetaire" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner...</option>
                            <option value="00810110">00810110</option>
                            <option value="00811130">00811130</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Mode de Paiement</label>
                        <select id="mode_paiement" name="mode_paiement" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner...</option>
                            <option value="Virement">Virement</option>
                            <option value="Bon de caisse">Bon de caisse</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="w-1 h-4 bg-amber-500 rounded-full"></div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Ancienne Position</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Corps</label>
                        <select name="ancien_corps_id" id="ancien_corps" required 
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner le corps...</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Grade</label>
                        <select name="ancien_grade_id" id="ancien_grade" required 
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner le grade...</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Indice</label>
                        <input type="text" id="ancien_indice_display" readonly 
                            class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-600 font-bold outline-none">
                        <input type="hidden" name="ancien_indice" id="ancien_indice_hidden">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Date d'effet</label>
                        <input type="date" id="ancien_date_effet" name="ancien_date_effet" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 text-sm transition-all text-slate-700">
                    </div>
                </div>
            </div>

            <div id="tab-content-3" class="tab-content space-y-6 hidden">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="w-1 h-4 bg-emerald-500 rounded-full"></div>
                    <h3 id="titre-1-position" class="text-sm font-black text-slate-800 uppercase tracking-wider">Nouvelle position</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Corps</label>
                        <select name="nouveau_corps_id" id="nouveau_corps" required 
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner le corps...</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Grade</label>
                        <select name="nouveau_grade_id" id="nouveau_grade" required 
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                            <option value="">Sélectionner le grade...</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Indice</label>
                        <input type="text" id="nouveau_indice_display" readonly 
                            class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-600 font-bold outline-none">
                        <input type="hidden" name="nouveau_indice" id="nouveau_indice_hidden">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase ml-1">Date d'effet</label>
                        <input type="date" name="nouveau_date_effet" 
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-3 mb-4">
                        <div class="w-1 h-4 bg-sky-600 rounded-full"></div>
                        <h3 id="titre-2-decision" class="text-sm font-black text-slate-800 uppercase tracking-wider">Information sur la nouvelle acte</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase ml-1">Numéro Acte</label>
                            <input type="text" name="numero_acte" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase ml-1">Date Acte</label>
                            <input type="date" name="date_acte" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase ml-1">N° Visa Finance</label>
                            <input type="text" name="numero_visa_finance" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase ml-1">Date Visa Finance</label>
                            <input type="date" name="date_visa_finance" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                        </div>
                        
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase ml-1">N° Visa CDE</label>
                            <input type="text" name="numero_visa_cde" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase ml-1">Date Visa CDE</label>
                            <input type="date" name="date_visa_cde" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase ml-1">Nom Signataire</label>
                            <input type="text" name="nom_signataire" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase ml-1">Corps Signataire</label>
                            <input type="text" name="corps_signataire" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button type="button" id="btn-prev" onclick="window.prevTab()" class="px-5 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition-all flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Précédent
                    </button>
                    <button type="button" id="btn-next" onclick="window.nextTab()" class="px-6 py-2.5 bg-sky-600 text-white rounded-xl text-xs font-bold hover:bg-sky-700 shadow-lg shadow-sky-100 transition-all flex items-center gap-2">
                        Suivant <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" id="btn-save" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all hidden flex items-center gap-2">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</template> 

<div id="modalAdresseSolde" class="fixed inset-0 hidden items-center justify-center p-4" style="z-index: 200000 !important;">
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-md"></div>
    <div class="relative bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md p-8">
        <h3 class="text-xl font-black text-slate-900 mb-6 text-center">Adressé à</h3>
        
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-bold text-slate-600 mb-2">Civilité</label>
                <select id="selectGenre" class="w-full border border-slate-300 rounded-2xl px-4 py-3 focus:outline-none focus:border-emerald-500 text-slate-700 font-medium">
                    <option value="Mr">Monsieur</option>
                    <option value="Mme">Madame</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-slate-600 mb-2">Destinataire</label>
                <input id="inputDestinataire" type="text" readonly 
                       value="LE CHEF DE SERVICE DE SOLDE ET PENSIONS"
                       class="w-full border border-slate-200 bg-slate-50 rounded-2xl px-4 py-3 text-slate-700 font-semibold cursor-not-allowed">
            </div>
        </div>

        <div class="flex gap-3 mt-8">
            <button onclick="fermerModalAdresse()" type="button" 
                class="flex-1 py-4 rounded-2xl border border-slate-300 font-bold text-slate-700 hover:bg-slate-50 transition-all">
                Annuler
            </button>
            <button onclick="validerAdresseEtImprimer()" type="button" 
                class="flex-1 py-4 rounded-2xl bg-emerald-600 text-white font-black hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all">
                Valider et Imprimer
            </button>
        </div>
    </div>
</div>    

<script>
//script 1
function formaterDateFR(dateString) {
    if (!dateString) return '---';
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return dateString;
    return d.getDate().toString().padStart(2, '0') + '/' + 
        (d.getMonth() + 1).toString().padStart(2, '0') + '/' + 
        d.getFullYear();
}

window.impressionParams = { im: '', type: '' };
window.ouvrirModalAdresse = function(im, type) {
    window.impressionParams.im = im;
    window.impressionParams.type = decodeURIComponent(type || '');

    const inputDest = document.getElementById('inputDestinataire');
    if (inputDest) {
        inputDest.value = 'LE CHEF DE SERVICE DE SOLDE ET PENSIONS';
    }

    const modal = document.getElementById('modalAdresseSolde');
    if (modal) {
        document.body.appendChild(modal);
        modal.style.zIndex = '200000';
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
};

window.fermerModalAdresse = function() {
    const modal = document.getElementById('modalAdresseSolde');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.style.display = 'none';
    }
};

window.validerAdresseEtImprimer = function() {
    const genre = document.getElementById('selectGenre').value;
    const destinataire = document.getElementById('inputDestinataire').value;
    
    const im = window.impressionParams.im;
    const type = window.impressionParams.type;

    if (!im) {
        Swal.fire('Erreur', 'Informations sur l\'agent introuvables.', 'error');
        return;
    }

    // 1. Fermer la modale d'adresse
    window.fermerModalAdresse();

    // 2. Générer et ouvrir le document PDF/Mandatement
    const url = `documents/mandatement/generate_reste_mandatement.php?im=${im}&type=${type}&genre=${encodeURIComponent(genre)}&destinataire=${encodeURIComponent(destinataire)}`;
    window.open(url, '_blank');

    // 3. Recharger automatiquement la liste des mandatements
    if (typeof window.loadMandat === 'function') {
        window.loadMandat();
    }
};

window.acteRenouvellementImprime = false;
window.acteRappelImprime = false;
window.acteFormateImprime = false;

window.verifierActivationBouton = function() {
    const btn = document.getElementById('btnImprimerPieces');
    if (!btn) return;

    if (window.acteFormateImprime) {
        btn.disabled = false;
        btn.classList.remove('bg-slate-300', 'text-slate-500', 'cursor-not-allowed');
        btn.classList.add('bg-sky-600', 'text-white', 'hover:bg-sky-700');
    } else {
        btn.disabled = true;
        btn.classList.remove('bg-sky-600', 'text-white', 'hover:bg-sky-700');
        btn.classList.add('bg-slate-300', 'text-slate-500', 'cursor-not-allowed');
    }
};

window.imprimerActeFormate = function(url) {
    window.open(url, '_blank');
    window.acteFormateImprime = true;
    window.verifierActivationBouton();
};

window.imprimerActeRenouvellement = function(url) {
    window.open(url, '_blank');
    window.acteRenouvellementImprime = true;
    if (window.acteRenouvellementImprime && window.acteRappelImprime) {
        window.acteFormateImprime = true;
    }
    window.verifierActivationBouton();
};

window.imprimerActeRappel = function(url) {
    window.open(url, '_blank');
    window.acteRappelImprime = true;
    if (window.acteRenouvellementImprime && window.acteRappelImprime) {
        window.acteFormateImprime = true;
    }
    window.verifierActivationBouton();
};

window.ouvrirModaleMandatement = function(agent) {
    const AncienDateEffet = formaterDateFR(agent.date_d_effet_actuel);
    const NewDateEffet    = formaterDateFR(agent.new_date_d_effet);        
    const AncienCorps     = `${(agent.corps_actuel || '---').replace(/"/g, '&quot;')}`;
    const AncienGrade     = `${(agent.grade_actuel || '---').replace(/"/g, '&quot;')}`;    
    const NewCorps        = `${(agent.new_corps    || '---').replace(/"/g, '&quot;')}`;
    const NewGrade        = `${(agent.new_grade    || '---').replace(/"/g, '&quot;')}`;

    let titreSection = "Nouvelle position";
    if (agent.type_demande === 'compensatrice' || agent.type_demande === 'installation') {
        titreSection = "Situation de l’admission à la retraite";
    }

    // MODALE 1 : Comparaison Ancienne / Nouvelle position
    Swal.fire({
        width: '850px',
        html: `
            <div class="text-left border-b border-slate-100 pb-4 mb-4">
                <h3 class="text-lg font-bold text-slate-800 mb-3">
                    <i class="fas fa-file-invoice-dollar mr-1"></i> Mandatement de : <span class="text-sky-600">${agent.libelle_type || '---'}</span>
                </h3>
                <div class="flex flex-col gap-1">
                    <div class="text-sm text-slate-600">
                        <span class="font-semibold text-slate-400 uppercase text-[14px] tracking-wider">Nom et prénoms :</span>
                        <span class="ml-1 font-bold text-slate-700">${agent.nom || ''} ${agent.prenoms || ''}, IM : ${agent.im || '---'}</span>
                    </div>
                </div>
            </div>
            <div class="p-2 space-y-5 text-left">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h4 class="text-[14px] font-bold text-gray-500 uppercase tracking-wider mb-3">Ancienne Position</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col">
                            <label class="text-[13px] font-semibold text-gray-400 ml-1">Corps</label>
                            <input type="text" value="${AncienCorps}" class="swal2-input !m-0 !w-full !text-sm bg-gray-100" readonly>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[13px] font-semibold text-gray-400 ml-1">Grade</label>
                            <input type="text" value="${AncienGrade}" class="swal2-input !m-0 !w-full !text-sm bg-gray-100" readonly>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[13px] font-semibold text-gray-400 ml-1">Indice</label>
                            <input type="text" value="${agent.indice_actuel || '---'}" class="swal2-input !m-0 !w-full !text-sm bg-gray-100" readonly>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[13px] font-semibold text-gray-400 ml-1">Date d'effet</label>
                            <input type="text" value="${AncienDateEffet}" class="swal2-input !m-0 !w-full !text-sm bg-gray-100" readonly>
                        </div>
                    </div>
                </div>

                <div class="bg-sky-50 p-4 rounded-xl border border-sky-100">
                    <h4 class="text-[14px] font-bold text-sky-600 uppercase tracking-wider mb-3">${titreSection}</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col">
                            <label class="text-[13px] font-semibold text-sky-500 ml-1">Corps</label>
                            <input type="text" value="${NewCorps}" class="swal2-input !m-0 !w-full !text-sm border-sky-200" readonly>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[13px] font-semibold text-sky-500 ml-1">Grade</label>
                            <input type="text" value="${NewGrade}" class="swal2-input !m-0 !w-full !text-sm border-sky-200" readonly>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[13px] font-semibold text-sky-500 ml-1">Indice</label>
                            <input type="text" value="${agent.new_indice || '---'}" class="swal2-input !m-0 !w-full !text-sm border-sky-200" readonly>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[13px] font-semibold text-sky-500 ml-1">Date d'effet</label>
                            <input type="text" value="${NewDateEffet}" class="swal2-input !m-0 !w-full !text-sm border-sky-200" readonly>
                        </div>
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check mr-2"></i> Valider',
        cancelButtonText: '<i class="fas fa-times mr-2"></i> Annuler', 
        confirmButtonColor: '#0284c7', 
        cancelButtonColor: '#ef4444',  
        preConfirm: () => { return agent; }
    }).then((result) => {
        if (result.isConfirmed) {
            // SI C'EST UN EVENEMENT DE TYPE avenant_avec_contrat -> OUVRE LA MODALE 2 DE SAISIE DE L'ACTE
            if (agent.type_demande === 'avenant_avec_contrat') {
                ouvrirModaleSaisieAvenantContrat(agent);
            } else {
                // POUR LES AUTRES TYPES, AFFICHER DIRECTEMENT LA MODALE DES PIECES
                afficherModaleListePieces(agent);
            }
        }
    });
};

function ouvrirModaleSaisieAvenantContrat(agent) {
    const gradeIntitule = agent.new_grade || 'NON PRÉCISÉ';

    // 1. Récupération des données existantes
    fetch(`api/carriere/api_mandatement.php?action=get_acte_formate_av_cont&im=${agent.im}`)
        .then(res => res.json())
        .then(response => {
            const dataExistante = response.data || {};

            // 2. Affichage de la modale pré-remplie
            Swal.fire({
                width: '680px',
                padding: '1.25rem',
                background: '#ffffff',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check-circle mr-1.5"></i> Enregistrer & Continuer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#94a3b8',
                customClass: {
                    popup: 'rounded-2xl shadow-2xl border border-slate-100',
                    confirmButton: 'px-5 py-2.5 rounded-xl font-bold text-sm shadow-sm hover:shadow transition-all',
                    cancelButton: 'px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 hover:bg-slate-100 transition-all'
                },
                html: `
                    <div class="text-left text-sm">
                        <form id="formAvenantContrat" class="space-y-4">
                            
                            <!-- Section 1 : Avenant + Grade -->
                            <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-200/60">
                                <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-200/50">
                                    <span class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-hashtag text-amber-500"></i> Référence de l'Avenant
                                    </span>
                                    <span class="px-3 py-1 bg-amber-50 border border-amber-200 text-amber-800 rounded-md text-xs font-extrabold uppercase">
                                        AVENANT DU GRADE : ${gradeIntitule}
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-700">N° Avenant</label>
                                        <input type="text" id="num_avenant" 
                                               value="${dataExistante.num_acte || ''}"
                                               class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-100 text-slate-800 text-sm font-medium transition-all outline-none" 
                                               placeholder="Ex: 123/PREF/MNJ">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-700">Date Avenant</label>
                                        <input type="date" id="date_avenant" 
                                               value="${dataExistante.date_acte || ''}"
                                               class="w-full max-w-[170px] px-3 py-2 rounded-lg bg-white border border-slate-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-100 text-slate-800 text-sm font-medium transition-all outline-none">
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2 : Visas (Finance & CDE) -->
                            <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-200/60 space-y-3">
                                <span class="text-xs font-bold text-slate-600 uppercase tracking-wider block">
                                    <i class="fas fa-stamp text-sky-500 mr-1"></i> Visas d'approbation
                                </span>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-700">Visa Finance</label>
                                        <input type="text" id="visa_finance_avenant" 
                                               value="${dataExistante.num_visa_finance || ''}"
                                               class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-slate-800 text-sm font-medium transition-all outline-none" 
                                               placeholder="N° Visa Finance">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-700">Date Visa Finance</label>
                                        <input type="date" id="date_finance_avenant" 
                                               value="${dataExistante.date_visa_finance || ''}"
                                               class="w-full max-w-[170px] px-3 py-2 rounded-lg bg-white border border-slate-200 focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-slate-800 text-sm font-medium transition-all outline-none">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4 pt-1">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-700">Visa CDE</label>
                                        <input type="text" id="visa_cde_avenant" 
                                               value="${dataExistante.num_visa_cde || ''}"
                                               class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 text-slate-800 text-sm font-medium transition-all outline-none" 
                                               placeholder="N° Visa CDE">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-700">Date Visa CDE</label>
                                        <input type="date" id="date_cde_avenant" 
                                               value="${dataExistante.date_visa_cde || ''}"
                                               class="w-full max-w-[170px] px-3 py-2 rounded-lg bg-white border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 text-slate-800 text-sm font-medium transition-all outline-none">
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3 : Signataire -->
                            <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-200/60">
                                <span class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-2.5">
                                    <i class="fas fa-user-pen text-emerald-500 mr-1"></i> Signataire de l'acte
                                </span>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-700">Nom du Signataire</label>
                                        <input type="text" id="nom_signataire_avenant" 
                                               value="${dataExistante.nom_signataire || ''}"
                                               class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-slate-800 text-sm font-medium transition-all outline-none" 
                                               placeholder="Nom complet">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-bold text-slate-700">Corps du Signataire</label>
                                        <input type="text" id="corps_signataire_avenant" 
                                               value="${dataExistante.corps_signataire || ''}"
                                               class="w-full px-3 py-2 rounded-lg bg-white border border-slate-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-slate-800 text-sm font-medium transition-all outline-none" 
                                               placeholder="Ex: Administrateur Civil">
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>
                `,
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const formData = new FormData();
                    formData.append('action', 'valider_avenant_avec_contrat');
                    formData.append('im', agent.im);
                    formData.append('num_avenant', document.getElementById('num_avenant').value);
                    formData.append('date_avenant', document.getElementById('date_avenant').value);
                    formData.append('visa_finance_avenant', document.getElementById('visa_finance_avenant').value);
                    formData.append('date_finance_avenant', document.getElementById('date_finance_avenant').value);
                    formData.append('visa_cde_avenant', document.getElementById('visa_cde_avenant').value);
                    formData.append('date_cde_avenant', document.getElementById('date_cde_avenant').value);
                    formData.append('nom_signataire_avenant', document.getElementById('nom_signataire_avenant').value);
                    formData.append('corps_signataire_avenant', document.getElementById('corps_signataire_avenant').value);

                    return fetch('api/carriere/api_mandatement.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message);
                        }
                        return data;
                    })
                    .catch(err => {
                        Swal.showValidationMessage(`Erreur : ${err.message}`);
                    });
                }
            }).then((res) => {
                if (res.isConfirmed) {
                    afficherModaleListePieces(agent);
                }
            });
        })
        .catch(err => {
            console.error('Erreur lors de la récupération des données:', err);
            Swal.fire('Erreur', 'Impossible de charger les données existantes de l\'acte', 'error');
        });
}

// MODALE 3 : Affichage de la liste des pièces
function afficherModaleListePieces(agent) {
    window.acteFormateImprime = false;
    window.acteRenouvellementImprime = false;
    window.acteRappelImprime = false;

    const piecesConfig = {
        'systeme': [
            { icone: 'fa-check-circle', nom: 'Demande (03)', action: false },
            { 
                icone: 'fa-download', 
                nom: 'Acte formaté (02)', 
                action: true, 
                url: `documents/mandatement/generate_acte_formate.php?im=${encodeURIComponent(agent.im)}&type=${encodeURIComponent(agent.type_demande)}`
            },
            { icone: 'fa-check-circle', nom: 'Attestation de non interruption (03)', action: false },
            { icone: 'fa-check-circle', nom: 'Certificat administratif (03)', action: false }
        ],
        'agent': [
            { icone: 'fa-check-circle', nom: "Décision d'indemnité compensatrice (03)", action: false },
            { icone: 'fa-check-circle', nom: "Décision d'installation (03)", action: false },
            { icone: 'fa-check-circle', nom: "Arrêté d'admission à la retraite (03)", action: false },
            { icone: 'fa-check-circle', nom: 'Photocopie certifié avenant (03)', action: false },
            { icone: 'fa-check-circle', nom: 'Photocopie certifié contrat (03)', action: false },
            { icone: 'fa-check-circle', nom: 'Photocopie certifié dernier arrêté (03)', action: false },
            { icone: 'fa-check-circle', nom: "Photocopie arrêté d'intégration (03)", action: false },
            { icone: 'fa-check-circle', nom: "Relevé de service (03)", action: false },
            { icone: 'fa-check-circle', nom: "Pièce de validation (03)", action: false },
            { icone: 'fa-check-circle', nom: "Declaration de recettes (03)", action: false },
            { icone: 'fa-check-circle', nom: "Photocopie arrêté de titularisation (03)", action: false },
            { icone: 'fa-check-circle', nom: 'Avis de crédit / Souche (02)', action: false },
            { icone: 'fa-check-circle', nom: 'Photocopie CIN (01)', action: false }
        ]
    };

    let sysPieces = [...piecesConfig.systeme];
    let agtPieces = [...piecesConfig.agent];

    if (agent.type_demande === 'renouvellement' && agent.jour_effet <= 10) {
        sysPieces.splice(1, 1, 
            { 
                icone: 'fa-download', 
                nom: 'Acte formaté renouvellement (02)', 
                action: true, 
                typeAction: 'renouvellement',
                url: `documents/mandatement/generate_acte_formate.php?im=${encodeURIComponent(agent.im)}&type=${encodeURIComponent(agent.type_demande)}`
            },
            { 
                icone: 'fa-download', 
                nom: 'Acte formaté rappel de solde (02)', 
                action: true, 
                typeAction: 'rappel',
                url: `documents/mandatement/generate_acte_rappel_solde.php?im=${encodeURIComponent(agent.im)}&type=${encodeURIComponent(agent.type_demande)}`
            }
        );
    } else if (agent.type_demande === 'integration' && agent.indice_actuel !== agent.new_indice) {
        sysPieces.splice(1, 1, 
            { 
                icone: 'fa-download', 
                nom: 'Acte formaté intégration (02)', 
                action: true, 
                typeAction: 'integration',
                url: `documents/mandatement/generate_acte_formate.php?im=${encodeURIComponent(agent.im)}&type=${encodeURIComponent(agent.type_demande)}`
            },
            { 
                icone: 'fa-download', 
                nom: 'Acte formaté rappel différentiel moins perçu (02)', 
                action: true, 
                typeAction: 'rappel_differentiel',
                url: `documents/mandatement/generate_acte_rappel_differentiel_moins_perçu.php?im=${encodeURIComponent(agent.im)}&type=${encodeURIComponent(agent.type_demande)}`
            }
        );
    }

    if (agent.type_demande === "renouvellement") {
        agtPieces = agtPieces.filter(p => 
            !p.nom.includes('avenant') && 
            !p.nom.includes('arrêté') &&
            !p.nom.includes('integration') &&
            !p.nom.includes('titularisation') &&
            !p.nom.includes('admission') && 
            !p.nom.includes('compensatrice') &&
            !p.nom.includes('installation') &&
            !p.nom.includes('Relevé') && 
            !p.nom.includes('validation') &&
            !p.nom.includes('recette')
        );
    } else if (agent.type_demande === "avenant" || agent.type_demande === "avenant_avec_contrat") {
        agtPieces = agtPieces.filter(p => 
            !p.nom.includes('arrêté') &&
            !p.nom.includes('integration') &&
            !p.nom.includes('titularisation') &&
            !p.nom.includes('admission') && 
            !p.nom.includes('compensatrice') &&
            !p.nom.includes('installation') &&
            !p.nom.includes('Relevé') && 
            !p.nom.includes('validation') &&
            !p.nom.includes('recette')
        );
    } else if (agent.type_demande === "integration") {
        agtPieces = agtPieces.filter(p => 
            !p.nom.includes('avenant') && 
            !p.nom.includes('contrat') &&
            !p.nom.includes('dernier arrêté') &&
            !p.nom.includes('admission') && 
            !p.nom.includes('compensatrice') &&
            !p.nom.includes('installation') &&
            !p.nom.includes('Relevé') && 
            !p.nom.includes('validation') &&
            !p.nom.includes('recette')
        );
    } else if (agent.type_demande === "titularisation") {
        agtPieces = agtPieces.filter(p => 
            !p.nom.includes('avenant') && 
            !p.nom.includes('contrat') &&
            !p.nom.includes('dernier arrêté') &&
            !p.nom.includes('intégration') &&
            !p.nom.includes('admission') && 
            !p.nom.includes('compensatrice') &&
            !p.nom.includes('installation') &&
            !p.nom.includes('Relevé') && 
            !p.nom.includes('validation') &&
            !p.nom.includes('recette')
        );
    } else if (agent.type_demande === "avancement_classe" || agent.type_demande === "avancement_echelon") {
        agtPieces = agtPieces.filter(p => 
            !p.nom.includes('avenant') && 
            !p.nom.includes('contrat') &&
            !p.nom.includes('titularisation') &&
            !p.nom.includes('intégration') &&
            !p.nom.includes('admission') && 
            !p.nom.includes('compensatrice') &&
            !p.nom.includes('installation') &&
            !p.nom.includes('Relevé') && 
            !p.nom.includes('validation') &&
            !p.nom.includes('recette')
        );
    } else if (agent.type_demande === "compensatrice") {
        agtPieces = agtPieces.filter(p => 
            !p.nom.includes('avenant') && 
            !p.nom.includes('contrat') &&
            !p.nom.includes('titularisation') &&
            !p.nom.includes('intégration') &&
            !p.nom.includes('installation') &&
            !p.nom.includes('Relevé') && 
            !p.nom.includes('validation') &&
            !p.nom.includes('recette')            
        );
        sysPieces = sysPieces.filter(p =>
            !p.nom.includes('interruption') &&
            !p.nom.includes('administratif') 
        );
    } else if (agent.type_demande === "installation") {
        agtPieces = agtPieces.filter(p => 
            !p.nom.includes('avenant') && 
            !p.nom.includes('contrat') &&
            !p.nom.includes('titularisation') &&
            !p.nom.includes('intégration') &&
            !p.nom.includes('compensatrice')
        );
        sysPieces = sysPieces.filter(p =>
            !p.nom.includes('administratif')
        );
    }

    const allPieces = [
        ...sysPieces.map(p => ({ ...p, origine: 'sys' })),
        ...agtPieces.map(p => ({ ...p, origine: 'agt' }))
    ];

    const total = allPieces.length;
    const nbGauche = Math.ceil(total / 2);
    const colGauche = allPieces.slice(0, nbGauche);
    const colDroite = allPieces.slice(nbGauche);

    function renderPiece(piece, numero) {
        const match = (piece.nom || '').match(/^(.*?)(\s*\(\d+\))\s*$/);
        const nom   = match ? match[1].trim() : (piece.nom || '');
        const count = match ? match[2].trim() : '';

        let bg, border, text, numBg, countCl, iconCl;
        if (piece.action) {
            bg = 'bg-sky-50'; border = 'border-sky-200'; text = 'text-sky-700';
            numBg = 'bg-sky-600'; countCl = 'text-sky-600'; iconCl = 'text-sky-600';
        } else if (piece.origine === 'sys') {
            bg = 'bg-emerald-50'; border = 'border-emerald-200'; text = 'text-emerald-800';
            numBg = 'bg-emerald-600'; countCl = 'text-emerald-600'; iconCl = 'text-emerald-600';
        } else {
            bg = 'bg-rose-50'; border = 'border-rose-100'; text = 'text-rose-700';
            numBg = 'bg-rose-500'; countCl = 'text-rose-500'; iconCl = 'text-rose-500';
        }

        const inner = `
            <span class="flex-shrink-0 w-6 h-6 rounded-full ${numBg} text-white text-[11px] font-black flex items-center justify-center shadow-sm">${numero}</span>
            <span class="truncate flex-1 min-w-0 text-left font-bold">${nom}</span>
            <span class="flex-shrink-0 font-bold ${countCl}">${count}</span>
            <i class="fas ${piece.icone || 'fa-file'} ${iconCl} flex-shrink-0"></i>
        `;

        if (piece.action) {
            let onclick = `imprimerActeFormate('${piece.url}')`;
            if (piece.typeAction === 'renouvellement' || piece.typeAction === 'integration') {
                onclick = `imprimerActeRenouvellement('${piece.url}')`;
            } else if (piece.typeAction === 'rappel' || piece.typeAction === 'rappel_differentiel') {
                onclick = `imprimerActeRappel('${piece.url}')`;
            }

            return `<button type="button" onclick="${onclick}"
                        class="w-full ${bg} ${border} border ${text} text-xs px-3 py-3 rounded-xl flex items-center gap-2 hover:opacity-90 transition-all text-left">
                        ${inner}
                    </button>`;
        }

        return `<div class="w-full ${bg} ${border} border ${text} text-xs px-3 py-3 rounded-xl flex items-center gap-2">
                    ${inner}
                </div>`;
    }

    let itemsHtml = '<div class="grid grid-cols-2 gap-4 items-start">';
    itemsHtml += '<div class="flex flex-col gap-3">';
    colGauche.forEach((p, i) => { itemsHtml += renderPiece(p, i + 1); });
    itemsHtml += '</div><div class="flex flex-col gap-3">';
    colDroite.forEach((p, i) => { itemsHtml += renderPiece(p, nbGauche + i + 1); });
    itemsHtml += '</div></div>';

    Swal.fire({
        width: '850px',
        background: 'transparent',
        showConfirmButton: false,
        allowOutsideClick: false,
        html: `
            <div class="relative bg-white rounded-2xl p-6 text-left">
                <button onclick="Swal.close(); loadMandat();" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition-all shadow-sm">
                    <i class="fas fa-times text-xs"></i>
                </button>

                <div class="flex items-center gap-3 mb-6 p-4 bg-sky-600 rounded-xl text-white shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-base text-amber-400 shadow-sm">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black leading-tight tracking-wider">LISTE DES PIÈCES À FOURNIR</h3>
                    </div>
                </div>

                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100/70 flex flex-col gap-4">
                    ${itemsHtml}
                </div>

                <div class="mt-5 p-4 bg-rose-50 border border-rose-100/50 rounded-xl text-slate-800 leading-relaxed" style="font-size: 13px;">
                    <strong>N.B :</strong> Les pièces en <span class="text-red-600 font-bold">rouge</span> sont des pièces provenant de votre part, à joindre au dossier.
                    <br><span class="text-amber-600 font-semibold">Vous devez d'abord imprimer l'(les) acte(s) formaté(s) avant de pouvoir imprimer les pièces du dossier.</span>
                </div>

                <div class="mt-8 flex flex-col gap-2">
                    <button id="btnImprimerPieces" 
                            onclick="if(!window.acteFormateImprime){ Swal.fire('Attention','Veuillez d\\'abord imprimer le(s) acte(s) formaté(s).','warning'); return; } Swal.close(); ouvrirModalAdresse('${agent.im}', '${encodeURIComponent(agent.type_demande)}')" 
                            class="w-full bg-slate-300 text-slate-500 font-bold py-4 rounded-xl flex items-center justify-center gap-3 shadow-lg cursor-not-allowed transition-all"
                            disabled>
                        <i class="fas fa-print"></i> IMPRIMER LES PIÈCES DU DOSSIER
                    </button>
                </div>
            </div>
        `
    });
}

//script 2
window.currentTab = 1;
window.totalTabs = 3;

window.showSubPage = function(url) {
    const dashView = document.getElementById('dashboard-view');
    const subView = document.getElementById('sub-page-view');
    const subContent = document.getElementById('sub-page-content');

    if(dashView) dashView.classList.add('hidden');
    if(subView) subView.classList.remove('hidden');

    fetch(url)
        .then(res => res.text())
        .then(html => { 
            subContent.innerHTML = html; 
            window.scrollTo(0, 0); 
        })
        .catch(err => console.error("Erreur de chargement sub-page:", err));
};

window.hideSubPage = function() {
    const dashView = document.getElementById('dashboard-view');
    const subView = document.getElementById('sub-page-view');
    if(subView) subView.classList.add('hidden');
    if(dashView) dashView.classList.remove('hidden');
};

window.tableMandat = null;
window.loadMandat = function() {
    if ($.fn.DataTable.isDataTable('#tableMandat')) { 
        $('#tableMandat').DataTable().destroy(); 
        $('#tableMandat tbody').empty();
    }

    $.post('api/carriere/api_mandatement.php', { action: 'list_mandatement' }, function(html) {
        $('#tableMandat tbody').html(html);
        
        window.tableMandat = $('#tableMandat').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.24/i18n/French.json",
                "emptyTable": `
                    <div class="flex flex-col items-center justify-center py-10 text-slate-400">
                        <i class="fas fa-folder-open text-5xl mb-4 opacity-20"></i>
                        <span class="text-lg font-medium">Aucun(e) acte à mandater pour l'instant</span>
                    </div>
                `
            },
            "pageLength": 7,
            "dom": '<"top">rt<"bottom"ip><"clear">', 
            "columnDefs": [
                { "targets": [0, 3, 6], "className": "text-center" }, 
                { "targets": [1, 2, 4, 5], "className": "text-left" },
                { "orderable": false, "targets": [6] }
            ]
        });
    });
};

$(document).ready(function() {
    window.loadMandat();
    
    $('#searchMandat').on('keyup', function() { 
        if (window.tableMandat) {
            window.tableMandat.search(this.value).draw(); 
        }
    });
});

window.ouvrirFormulaireAgent = function() {
    Swal.fire({
        title: '<span class="text-slate-800 text-base font-black tracking-wider uppercase flex items-center gap-2 justify-center"><i class="fas fa-list-ul text-sky-600"></i> Nouveau Mandatement</span>',
        html: `
            <div class="p-2 text-left flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Matricule (IM) de l'agent :</label>
                    <input type="text" id="swal-matricule-im" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 text-sm transition-all text-slate-700 font-medium" placeholder="Ex: 365899">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-slate-500 uppercase ml-1">Sélectionner la nature de l'acte :</label>
                    <select id="swal-type-demande" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 text-sm transition-all text-slate-700 font-medium">
                        <option value="renouvellement">Renouvellement</option>
                        <option value="avenant">Avenant</option>
                        <option value="avancement_classe">Avancement de classe</option>
                        <option value="avancement_echelon">Avancement d'échelon</option>
                        <option value="titularisation">Titularisation</option>
                        <option value="integration">Intégration</option>
                        <option value="code_rubrique">Code rubrique</option>
                        <option value="compensatrice">Compensatrice</option>
                        <option value="installation">Installation</option>
                        <option value="pension">Pension</option>
                    </select>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Valider <i class="fas fa-arrow-right text-xs ml-1"></i>',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#0284c7',
        cancelButtonColor: '#94a3b8',
        customClass: {
            popup: 'rounded-[1.5rem]',
            confirmButton: 'rounded-xl px-5 py-2.5 text-xs font-bold shadow-lg shadow-sky-100 flex items-center gap-2',
            cancelButton: 'rounded-xl px-5 py-2.5 text-xs font-bold'
        },
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const imValue = document.getElementById('swal-matricule-im').value.trim();
            const typeDemandeValue = document.getElementById('swal-type-demande').value;

            if (!imValue) {
                Swal.showValidationMessage("Veuillez saisir le numéro de matricule.");
                return false;
            }

            const formData = new FormData();
            formData.append('action', 'verifier_im');
            formData.append('im', imValue);

            return fetch('api/carriere/api_mandatement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error(response.statusText);
                return response.json();
            })
            .then(result => {
                if (!result.success) {
                    if (result.message === 'not_found') {
                        Swal.showValidationMessage("Vous devez créer un compte pour cet agent.");
                    } else {
                        Swal.showValidationMessage(result.message);
                    }
                    return false;
                }
                return { agent: result.data, typeDemande: typeDemandeValue };
            })
            .catch(error => {
                Swal.showValidationMessage(`Erreur technique : ${error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const agentData = result.value.agent;
            const typeDemandeSelectionne = result.value.typeDemande;

            const dashView = document.getElementById('dashboard-view');
            const subView = document.getElementById('sub-page-view');
            const subContent = document.getElementById('sub-page-content');
            const template = document.getElementById('template-formulaire-agent');

            subContent.innerHTML = template.innerHTML;

            if(dashView) dashView.classList.add('hidden');
            if(subView) subView.classList.remove('hidden');

            setTimeout(() => {
                const formTypeInput = document.getElementById('form-type-demande');
                const txtTypeSpan = document.getElementById('txt-type-demande');
                
                if(formTypeInput) formTypeInput.value = typeDemandeSelectionne;
                if(txtTypeSpan) txtTypeSpan.innerText = typeDemandeSelectionne.replace(/_/g, ' ');

                // Mise à jour dynamique des libellés de l'onglet 3 et des sous-titres
                const stepLabel3 = document.getElementById('step-label-3');
                const titre1Position = document.getElementById('titre-1-position');
                const titre2Decision = document.getElementById('titre-2-decision');

                if (typeDemandeSelectionne === 'compensatrice') {
                    if (stepLabel3) stepLabel3.innerText = "Situation de l’admission à la retraite";
                    if (titre1Position) titre1Position.innerText = "Situation de l’admission à la retraite";
                    if (titre2Decision) titre2Decision.innerText = "Information sur la décision de compensatrice";
                } else if (typeDemandeSelectionne === 'installation') {
                    if (stepLabel3) stepLabel3.innerText = "Situation de l’admission à la retraite";
                    if (titre1Position) titre1Position.innerText = "Situation de l’admission à la retraite";
                    if (titre2Decision) titre2Decision.innerText = "Information sur la décision d'installation";
                } else {
                    if (stepLabel3) stepLabel3.innerText = "Nouvelle position";
                    if (titre1Position) titre1Position.innerText = "Nouvelle position";
                    if (titre2Decision) titre2Decision.innerText = "Information sur la nouvelle acte";
                }

                window.totalTabs = 3;

                // Remplissage des champs de renseignements
                if(document.getElementById('im')) document.getElementById('im').value = agentData.im;
                if(document.getElementById('nom')) document.getElementById('nom').value = agentData.nom;
                if(document.getElementById('prenoms')) document.getElementById('prenoms').value = agentData.prenoms;
                if(document.getElementById('cin')) document.getElementById('cin').value = agentData.cin;
                if(document.getElementById('date_cin')) document.getElementById('date_cin').value = agentData.date_cin;
                if(document.getElementById('lieu_cin')) document.getElementById('lieu_cin').value = agentData.lieu_cin;
                if(document.getElementById('date_naiss')) document.getElementById('date_naiss').value = agentData.date_naiss;
                if(document.getElementById('lieu_naiss')) document.getElementById('lieu_naiss').value = agentData.lieu_naiss;
                if(document.getElementById('situation_matrimoniale')) document.getElementById('situation_matrimoniale').value = agentData.situation_matrimoniale;
                if(document.getElementById('nombre_enfant')) document.getElementById('nombre_enfant').value = agentData.nombre_enfant;
                if(document.getElementById('sexe')) document.getElementById('sexe').value = agentData.sexe;
                if(document.getElementById('statut_agent')) document.getElementById('statut_agent').value = agentData.statut_agent;
                if(document.getElementById('date_entree_admin')) document.getElementById('date_entree_admin').value = agentData.date_entree_admin;
                if(document.getElementById('imput_budg')) document.getElementById('imput_budg').value = agentData.imput_budg;
                if(document.getElementById('mode_paiement')) document.getElementById('mode_paiement').value = agentData.mode_paiement;
                if(document.getElementById('ancien_date_effet')) document.getElementById('ancien_date_effet').value = agentData.ancien_date_effet;
                if(document.getElementById('ancien_indice_display')) document.getElementById('ancien_indice_display').value = agentData.ancien_indice;
                if(document.getElementById('ancien_indice_hidden')) document.getElementById('ancien_indice_hidden').value = agentData.ancien_indice;

                if (agentData.ancien_corps) {
                    setTimeout(() => {
                        const selectCorps = document.getElementById('ancien_corps');
                        if (selectCorps) {
                            for (let i = 0; i < selectCorps.options.length; i++) {
                                if (selectCorps.options[i].text.trim().toLowerCase() === agentData.ancien_corps.trim().toLowerCase()) {
                                    selectCorps.selectedIndex = i;
                                    break;
                                }
                            }                                
                            $('#ancien_corps').trigger('change');                  
                            if (agentData.ancien_grade) {
                                let tentatives = 0;
                                const maxTentatives = 40;
                                
                                const verifGradeInterval = setInterval(() => {
                                    const selectGrade = document.getElementById('ancien_grade');
                                    tentatives++;
                                    
                                    if (selectGrade && selectGrade.options.length > 1) {
                                        clearInterval(verifGradeInterval); 
                                        for (let j = 0; j < selectGrade.options.length; j++) {
                                            if (selectGrade.options[j].text.trim().toLowerCase() === agentData.ancien_grade.trim().toLowerCase()) {
                                                selectGrade.selectedIndex = j;
                                                break;
                                            }
                                        }
                                        $('#ancien_grade').trigger('change');
                                    }
                                    
                                    if (tentatives >= maxTentatives) {
                                        clearInterval(verifGradeInterval);
                                    }
                                }, 50);
                            }
                        }
                    }, 200);
                }                   
                if (agentData.type_etablissement) {
                    setTimeout(() => {
                        initLocaliteService(agentData);
                    }, 100);
                }

                initPositionEvents();
                chargerCorps('ancien_corps');
                chargerCorps('nouveau_corps');

                window.currentTab = 1;
                const btnNext = document.getElementById('btn-next');
                if (btnNext) {
                    btnNext.onclick = window.nextTab;
                }
                window.updateTabUI();
            }, 200);
        }
    });
};

function chargerCorps(selectId) {
    $.post('api/referentiel/api_localite_service.php', { action: 'get_corps' }, function(data) {
        $('#' + selectId).html(data);
    });
}

function chargerGrades(selectGradeId, corpsSelectId, typePosition) {
    const statut = $('select[name="statut"]').val();
    const corpsId = $('#' + corpsSelectId).val();
    const typeDemande = document.getElementById('form-type-demande') ? document.getElementById('form-type-demande').value : '';
    
    if (!corpsId) return;

    $.post('api/referentiel/api_localite_service.php', {
        action: 'get_grades',
        statut: statut,
        corps_id: corpsId,
        type_demande: typeDemande,
        type_position: typePosition  
    }, function(data) {
        $('#' + selectGradeId).html(data);
        const prefix = typePosition === 'ancien' ? 'ancien' : 'nouveau';
        $('#' + prefix + '_indice_display').val('');
        $('#' + prefix + '_indice_hidden').val('');
    }).fail(() => {
        $('#' + selectGradeId).html('<option value="">Erreur de chargement</option>');
    });
}

function chargerIndice(corpsId, gradeId, displayId, hiddenId) {
    if (!corpsId || !gradeId) return;
    
    $.post('api/referentiel/api_localite_service.php', {
        action: 'get_indice',
        corps_id: corpsId,
        grade_type_id: gradeId
    }, function(indice) {
        $('#' + displayId).val(indice);
        $('#' + hiddenId).val(indice);
    }).fail(() => console.error("Erreur chargement indice"));
}

function initPositionEvents() {
    $(document).off('change', '#ancien_corps').on('change', '#ancien_corps', function() {
        chargerGrades('ancien_grade', 'ancien_corps', 'ancien');
    });
    
    $(document).off('change', '#ancien_grade').on('change', '#ancien_grade', function() {
        const corpsId = $('#ancien_corps').val();
        const gradeId = $(this).val();
        chargerIndice(corpsId, gradeId, 'ancien_indice_display', 'ancien_indice_hidden');
    });

    $(document).off('change', '#nouveau_corps').on('change', '#nouveau_corps', function() {
        chargerGrades('nouveau_grade', 'nouveau_corps', 'nouveau');
    });
    
    $(document).off('change', '#nouveau_grade').on('change', '#nouveau_grade', function() {
        const corpsId = $('#nouveau_corps').val();
        const gradeId = $(this).val();
        chargerIndice(corpsId, gradeId, 'nouveau_indice_display', 'nouveau_indice_hidden');
    });

    $(document).off('change', 'select[name="statut"]').on('change', 'select[name="statut"]', function() {
        if (window.currentTab >= 2) {
            const ancienCorps = $('#ancien_corps').val();
            const nouveauCorps = $('#nouveau_corps').val();
            if (ancienCorps) chargerGrades('ancien_grade', 'ancien_corps', 'ancien');
            if (nouveauCorps) chargerGrades('nouveau_grade', 'nouveau_corps', 'nouveau');
        }
    });
}

function initLocaliteService(agentData) {
    const typeEtab = (agentData.type_etablissement || '').toUpperCase().trim();
    const nomRegion = agentData.nom_region || '';
    const nomDistrict = agentData.nom_district || '';
    const nomZap = agentData.nom_zap || '';
    const nomEtab = agentData.nom_etablissement || '';
    const lieuDeService = agentData.lieu_de_service || '';
    const nomDirection = agentData.nom_direction || '';

    $('#localite_region').val(nomRegion);
    $('#localite_zap').val(nomZap);
    $('#localite_etablissement').val(nomEtab);

    const isDren = typeEtab === 'DREN';
    let districtHtml = '';

    if (isDren) {
        districtHtml = `<select id="localite_district" name="district_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 text-sm transition-all text-slate-700">
                        <option value="">Sélectionner le district...</option>
                        </select>`;
    } else {
        districtHtml = `<input type="text" id="localite_district" readonly value="${nomDistrict}" 
                        class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-700 font-medium">`;
    }

    $('#district_container').html(districtHtml);

    let localiteCalc = '';
    if (typeEtab === 'DREN') {
        localiteCalc = `BUREAU DREN ${nomRegion}`;
    } else if (typeEtab === 'CISCO') {
        localiteCalc = `BUREAU CISCO ${nomDistrict}`;
    } else if (typeEtab === 'CRFRP') {
        localiteCalc = nomEtab;
    } else if (typeEtab === 'MEN CENTRAL' || lieuDeService === 'ANTANANARIVO') {
        localiteCalc = `MEN - ${nomDirection}`;
    } else {
        localiteCalc = `${nomEtab} / ZAP ${nomZap} - CISCO ${nomDistrict}`;
    }

    $('#localite_service_display').val(localiteCalc);
    $('#localite_service').val(localiteCalc);

    if (isDren) {
        chargerDistrictsPourLocalite(nomRegion, nomDistrict);
    }

    if (nomDistrict) {
        chargerCommunesPourDistrictParNom(nomDistrict);
    }

    $('#localite_district').on('change', function() {
        const districtId = $(this).val();
        const districtNom = $(this).find('option:selected').text();
        if (districtId) {
            chargerCommunesPourDistrict(districtId);
        } else if (districtNom) {
            chargerCommunesPourDistrictParNom(districtNom);
        }
        updateLocaliteService();
    });

    $('#localite_commune, #zone').on('change keyup', updateLocaliteService);
}

function chargerDistrictsPourLocalite(nomRegion, nomDistrictSelectionne) {
    $.post('api/referentiel/api_localite_service.php', {
        action: 'get_districts_by_region_nom',
        region_nom: nomRegion
    }, function(data) {
        $('#localite_district').html(data);
        if (nomDistrictSelectionne) {
            $('#localite_district option').each(function() {
                if ($(this).text().trim() === nomDistrictSelectionne.trim()) {$(this).prop('selected', true);
                }
            });
        }
    });
}

function chargerCommunesPourDistrict(districtId) {
    if (!districtId) return;
    
    $.post('api/referentiel/api_localite_service.php', {
        action: 'get_communes_by_district_id',
        district_id: districtId
    }, function(html) {
        $('#localite_commune').html(html);
    });
}

function chargerCommunesPourDistrictParNom(nomDistrict) {
    if (!nomDistrict) return;
    
    $.post('api/referentiel/api_localite_service.php', {
        action: 'get_communes_by_district',
        district_nom: nomDistrict
    }, function(html) {
        $('#localite_commune').html(html);
    });
}

function updateLocaliteService() {
    const base = document.getElementById('localite_service_display').value.split(' - ')[0] || '';
    const zone = document.getElementById('zone').value.trim();
    const communeSelect = document.getElementById('localite_commune');
    const communeText = communeSelect.options[communeSelect.selectedIndex] ? communeSelect.options[communeSelect.selectedIndex].text : '';
    
    let final = base;
    if (zone) final += ` / ${zone}`;
    if (communeText) final += `, ${communeText}`;
    
    document.getElementById('localite_service').value = final;
}

window.submitFormAgent = function(event) {
    event.preventDefault();
    
    const formData = new FormData(document.getElementById('agentForm'));
    formData.append('action', 'sauvegarder_nouvel_agent');
    
    $.ajax({
        url: 'api/carriere/api_mandatement.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({ 
                    title: 'Enregistré !', 
                    text: response.message || 'Le dossier a été sauvegardé avec succès.', 
                    icon: 'success', 
                    confirmButtonColor: '#059669' 
                }).then(() => { 
                    window.hideSubPage(); 
                    if (typeof loadMandat === 'function') {
                        loadMandat(); 
                    } 
                });
            } else {
                Swal.fire('Erreur', response.message, 'error');
            }
        },
        error: function() { 
            Swal.fire('Erreur', 'Impossible d\'enregistrer le dossier.', 'error'); 
        }
    });
};

window.updateTabUI = function() {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));

    const activeTabContent = document.getElementById(`tab-content-${window.currentTab}`);
    if (activeTabContent) activeTabContent.classList.remove('hidden');

    const progressBar = document.getElementById('progress-bar');
    if (progressBar) {
        const progressWidth = window.totalTabs > 1
            ? ((window.currentTab - 1) / (window.totalTabs - 1)) * 100
            : 0;
        progressBar.style.width = `${progressWidth}%`;
    }

    for (let i = 1; i <= 3; i++) {
        const icon = document.getElementById(`step-icon-${i}`);
        const label = document.getElementById(`step-label-${i}`);

        if (icon && label) {
            if (i < window.currentTab) {
                icon.className = "w-10 h-10 rounded-xl bg-emerald-500 text-white font-bold flex items-center justify-center shadow-lg shadow-emerald-100 transition-all duration-300 border-2 border-white ring-4 ring-emerald-50";
                icon.innerHTML = '<i class="fas fa-check text-xs"></i>';
                label.className = "text-xs font-black text-emerald-600 tracking-wide uppercase";
            } else if (i === window.currentTab) {
                const isLast = window.currentTab === window.totalTabs;
                const activeColor = isLast ? 'bg-emerald-500 ring-emerald-50 shadow-emerald-100' : 'bg-sky-600 ring-sky-50 shadow-sky-100';
                const activeText = isLast ? 'text-emerald-600' : 'text-sky-600';
                icon.className = `w-10 h-10 rounded-xl ${activeColor} text-white font-bold flex items-center justify-center shadow-lg transition-all duration-300 border-2 border-white ring-4`;
                icon.textContent = i.toString();
                label.className = `text-xs font-black ${activeText} tracking-wide uppercase`;
            } else {
                icon.className = "w-10 h-10 rounded-xl bg-white text-slate-400 font-bold flex items-center justify-center transition-all duration-300 border-2 border-slate-200";
                icon.textContent = i.toString();
                label.className = "text-xs font-bold text-slate-400 tracking-wide uppercase";
            }
        }
    }

    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnSave = document.getElementById('btn-save');

    if (btnPrev && btnNext && btnSave) {
        if (window.currentTab === 1) {
            btnPrev.innerHTML = '<i class="fas fa-times"></i> Annuler';
            btnPrev.setAttribute('onclick', 'window.hideSubPage()');
            btnPrev.className = "px-5 py-2.5 bg-rose-50 text-rose-600 rounded-xl text-xs font-bold hover:bg-rose-100 transition-all flex items-center gap-2";
        } else {
            btnPrev.innerHTML = '<i class="fas fa-arrow-left"></i> Précédent';
            btnPrev.setAttribute('onclick', 'window.prevTab()');
            btnPrev.className = "px-5 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition-all flex items-center gap-2";
        }

        if (window.currentTab === window.totalTabs) {
            btnNext.classList.add('hidden');
            btnSave.classList.remove('hidden');
        } else {
            btnNext.classList.remove('hidden');
            btnSave.classList.add('hidden');
        }
    }
};

window.nextTab = function() {
    const currentContainer = document.getElementById(`tab-content-${window.currentTab}`);
    if (!currentContainer) return;

    const inputs = currentContainer.querySelectorAll('[required]');
    let valid = true;
    let firstInvalid = null;

    inputs.forEach(input => {
        const wrapper = input.closest('div.flex.flex-col.gap-1\\.5') || 
                        input.closest('.flex-col') || 
                        input.parentElement;

        if (wrapper && wrapper.classList.contains('hidden')) {
            return; 
        }

        if (!input.checkValidity()) {
            if (!firstInvalid) firstInvalid = input;
            valid = false;
        }
    });

    if (!valid) {
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
            firstInvalid.reportValidity();
        }
        Swal.fire({
            title: 'Champs obligatoires',
            text: 'Veuillez remplir tous les champs obligatoires visibles de cet onglet.',
            icon: 'warning',
            confirmButtonColor: '#f59e0b'
        });
        return;
    }

    if (window.currentTab < window.totalTabs) { 
        window.currentTab++; 
        window.updateTabUI(); 
    }
};

window.prevTab = function() {
    if (window.currentTab > 1) { 
        window.currentTab--; 
        window.updateTabUI(); 
    }
};

window.switchTab = function(tabIndex) {
    if (tabIndex >= 1 && tabIndex <= window.totalTabs) { 
        window.currentTab = tabIndex; 
        window.updateTabUI(); 
    }
};
</script>
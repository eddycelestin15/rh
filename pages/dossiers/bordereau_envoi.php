<?php
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/check_session.php';

    $user_im = $_SESSION['user_im'];
    $stmt = $pdo->prepare("SELECT niveau, code_lieu_affectation, role_specifique FROM utilisateurs WHERE im = ?");
    $stmt->execute([$user_im]);
    $user = $stmt->fetch();

    $niv = $user['niveau'] ?? 'central'; 
    $role = strtolower(trim($user['role_specifique'] ?? ''));
    $lieu = $user['code_lieu_affectation'] ?? '';
?>
<link rel="stylesheet" href="assets/css/bordereau.css">
<div class="p-6">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Générer un bordereau administratif</h1>
            <p class="text-slate-500 mt-1">Sélectionnez les critères pour filtrer la liste des agents.</p>
        </div>
        <button id="btnGenererBordereau" onclick="genererBordereauFinal()" class="btn-action btn-generate">
            <i class="fas fa-file-signature"></i> Générer Bordereau
        </button>
    </div>
    <div class="premium-card">
        <div class="flex flex-wrap items-end gap-4">            
            <div class="flex-none">
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Type Bordereau</label>
                <select id="select_type_bordereau" name="type_bordereau" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 outline-none focus:border-blue-500 transition-all">
                    <option value="" disabled selected>-- Choisir type bordereau --</option>
                    <?php 
                        $isNonEncadre = ($role === 'resp_non_encadre');
                        $isEncadre   = ($role === 'resp_encadre');
                        $isSolde     = ($role === 'resp_solde');
                        $isCRFRP     = ($role === 'resp_personnel_crfrp');
                        $isRetraite  = ($role === 'resp_retraite');
                        $isAdmin     = in_array($role, ['admin', 'chef_service', 'chef_division']);
                    if ($isSolde): 
                    ?>
                        <option value="mandatement" selected>Mandatement</option>
                    <?php elseif ($isNonEncadre || $isEncadre || $isRetraite): ?>
                        <option value="creation_projet" selected>Création de Projet</option>
                    <?php else: ?>
                        <option value="creation_projet">Création de Projet</option>
                        <option value="mandatement">Mandatement</option>
                        <?php if ($isCRFRP || $isAdmin): ?>
                            <option value="conge">Décision de congé</option>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="flex-none">
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Type Demande</label>
                <select id="filter_type_dos" name="filter_type_dos" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 outline-none focus:border-blue-500 transition-all">
                    <option value=""> -- Choisir type demande --</option>
                    <?php 
                    if ($isSolde || $isCRFRP || $isAdmin): ?>
                        <option value="renouvellement">Renouvellement</option>
                        <option value="avenant">Avenant</option>
                        <option value="avenant_avec_contrat">Avenant (Rappel différentiel moins perçu)</option>
                        <option value="integration">Intégration</option>
                        <option value="titularisation">Titularisation</option>
                        <option value="avancement_classe">Avancement de classe</option>
                        <option value="avancement_echelon">Avancement d'échelon</option>
                        <option value="admission_retraite">Admission à la retraite</option>
                        <option value="compensatrice">Compensatrice</option>
                        <option value="installation">Installation</option>
                    <?php else: ?>
                        <?php if ($isNonEncadre): ?>
                            <option value="renouvellement">Renouvellement</option>
                            <option value="avenant">Avenant</option>
                            <option value="integration">Intégration</option>
                        <?php endif; ?>
                        <?php if ($isEncadre): ?>
                            <option value="titularisation">Titularisation</option>
                            <option value="avancement_classe">Avancement de classe</option>
                            <option value="avancement_echelon">Avancement d'échelon</option> 
                        <?php endif; ?>
                        <?php if ($isRetraite): ?>
                            <option value="admission_retraite">Admission à la retraite</option>
                            <option value="compensatrice">Compensatrice</option>
                            <option value="installation">Installation</option>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="flex-none">
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Destination</label>
                <select id="service_destination" name="service_destination" class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 outline-none focus:border-blue-500 transition-all">
                    <?php if ($isSolde): ?>
                        <option value="augure_dsp" selected>Solde et Pensions</option>
                    <?php else: ?>
                        <option value="">-- Choisir destination --</option>
                    <?php endif; ?>
                </select>
            </div>

            <button onclick="loadAgents()" class="btn-action btn-generate py-4 px-8">
                <i class="fas fa-list"></i> Afficher
            </button>

            <div class="search-input-container">
                <div class="relative">
                    <i class="fas fa-search"></i>
                    <input type="text" id="customSearch" placeholder="Recherche par IM, Nom..." class="focus:border-blue-500">
                </div>
            </div>
        </div><br>
        <table id="tableAgents" class="w-full text-left">
            <thead>
                <tr>
                    <?php if($niv === 'central'): ?>
                        <th>N°</th><th>Lieu de Service</th><th>Nom et Prénoms</th><th>IM</th><th>Corps et Grade</th><th class="text-center">Action</th>
                    <?php elseif($niv === 'regional'): ?>
                        <th>N°</th><th>CISCO</th><th>ZAP</th><th>Lieu de Service</th><th>Nom et Prénoms</th><th>IM</th><th>Corps et Grade</th><th class="text-center">Action</th>
                    <?php elseif($niv === 'district'): ?>
                        <th>N°</th><th>ZAP</th><th>Lieu de Service</th><th>Nom et Prénoms</th><th>IM</th><th>Corps et Grade</th><th class="text-center">Action</th>
                    <?php else: ?>
                        <th>N°</th><th>Nom et Prénoms</th><th>IM</th><th>Corps et Grade</th><th class="text-center">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php $colCount = ($niv === 'central') ? 6 : (($niv === 'regional') ? 8 : ($niv === 'district' ? 7 : 5)); ?>
                    <td colspan="<?= $colCount ?>" class="text-center py-20">
                        <div class="flex flex-col items-center">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-filter text-slate-300 text-2xl"></i>
                            </div>
                            <p class="text-slate-400 font-medium">Veuillez d'abord sélectionner le type de bordereau, le type de demande et la destination pour afficher la liste des agents.</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<div id="modalBordereau" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white text-center">
            <h3 class="text-xl font-bold" id="mod_titre_dossier">Détails du Bordereau</h3>
            <p class="text-blue-100 text-sm mt-1">Vérification de la numérotation avant impression</p>
        </div>
        
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Civilité & Destination</label>
                <div class="grid grid-cols-4 gap-2">
                    <div class="col-span-1">
                        <select id="mod_civilite" class="w-full h-[46px] border border-gray-300 rounded-lg px-2 text-gray-700 font-bold focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option value="MR">Mr</option>
                            <option value="MME">Mme</option>
                        </select>
                    </div>
                    <div class="col-span-3">
                        <input type="text" id="mod_destination_label" readonly class="w-full h-[46px] bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-gray-700 font-medium focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-4">
                <div class="col-span-3">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Dernier N° Enregistré</label>
                    <div id="mod_dernier_num" class="w-full h-[46px] flex items-center px-4 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-sm font-bold truncate">
                        -
                    </div>
                </div>
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">N° à utiliser</label>
                    <input type="number" id="mod_prochain_num" class="w-full h-[46px] border border-blue-300 rounded-lg px-4 py-2.5 text-blue-700 font-bold focus:ring-2 focus:ring-blue-500 outline-none text-center">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Sigle du Bordereau</label>
                <input type="text" id="mod_sigle" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 font-mono text-sm focus:ring-2 focus:ring-blue-500 outline-none uppercase" placeholder="Ex: 26-MEN/DREN-VTV/SGRH">
            </div>
        </div>

        <div class="bg-gray-50 p-4 flex justify-between gap-3">
            <button onclick="closeModalBordereau()" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-600 rounded-xl hover:bg-gray-100 font-semibold transition-all">
                Annuler
            </button>
            <button onclick="lancerImpressionBordereau()" class="flex-1 px-4 py-2.5 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 font-semibold shadow-lg shadow-emerald-200 transition-all">
                <i class="fas fa-print mr-2"></i> Imprimer
            </button>
        </div>
    </div>
</div>
<script>
    window.DB_NIVEAU = "<?= strtolower(trim($niv ?? '')) ?>";
    window.DB_ROLE   = "<?= $role ?? '' ?>";
</script>
<script>
    $(document).ready(function() {
    const DB_NIVEAU = window.DB_NIVEAU || ''; 
    const DB_ROLE = window.DB_ROLE || '';
    
    $('#filter_type_dos, #select_type_bordereau').on('change', function(e) {
        const tb = $('#select_type_bordereau').val();         
        const td = $('#filter_type_dos').val(); 
        const isSolde = DB_ROLE === 'resp_solde';    

        if ($(e.target).attr('id') === 'select_type_bordereau') {
            if (tb === 'conge') {
                $('#filter_type_dos').html('<option value="conge_annuel" selected>Congé annuel</option>');
            } else {
                let demandOpts = '<option value=""> -- Choisir type demande --</option>';
                
                if (['admin', 'chef_service', 'chef_division', 'resp_personnel_crfrp'].includes(DB_ROLE)) {
                    demandOpts += '<option value="renouvellement">Renouvellement</option>' +
                                '<option value="avenant">Avenant</option>' +
                                '<option value="avenant_avec_contrat">Avenant (Rappel différentiel moins perçu)</option>' +
                                '<option value="integration">Intégration</option>' +
                                '<option value="titularisation">Titularisation</option>' +
                                '<option value="avancement_classe">Avancement de classe</option>' +
                                '<option value="avancement_echelon">Avancement d\'échelon</option>' +
                                '<option value="admission_retraite">Admission à la retraite</option>' +
                                '<option value="compensatrice">Compensatrice</option>' +
                                '<option value="installation">Installation</option>';
                } else if (DB_ROLE === 'resp_non_encadre') {
                    demandOpts += '<option value="renouvellement">Renouvellement</option>' +
                                '<option value="avenant">Avenant</option>' +
                                '<option value="integration">Intégration</option>';
                } else if (DB_ROLE === 'resp_encadre') {
                    demandOpts += '<option value="titularisation">Titularisation</option>' +
                                '<option value="avancement_classe">Avancement de classe</option>' +
                                '<option value="avancement_echelon">Avancement d\'échelon</option>';
                } else if (DB_ROLE === 'resp_retraite') {
                    demandOpts += '<option value="admission_retraite">Admission à la retraite</option>' +
                                '<option value="compensatrice">Compensatrice</option>' +
                                '<option value="installation">Installation</option>';
                }
                
                $('#filter_type_dos').html(demandOpts);
            }
        }

        const currentTd = $('#filter_type_dos').val(); 
        let opts = '<option value="">-- Destination --</option>';
        
        if (isSolde) {
            opts = '<option value="augure_dsp" selected>Solde et Pensions</option>';
            $('#service_destination').html(opts);
            return;
        }
        
        if (currentTd && tb) {
            if (tb === 'conge') {
                opts += '<option value="augure_fop">Fonction Publique</option>';
                opts += '<option value="prefecture">Préfecture</option>';
            }
            // CAS DES RÔLES ENCADRÉ ET NON ENCADRÉ
            else if (DB_ROLE === 'resp_non_encadre' || DB_ROLE === 'resp_encadre') {
                if (tb === 'creation_projet') {
                    if (DB_NIVEAU === 'district') {
                        if (DB_ROLE === 'resp_retraite') {
                            if (['admission_retraite'].includes(currentTd)) {
                                opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                                opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                            }
                            else if (['compensatrice'].includes(currentTd)) {
                                opts += "<option value='augure_dren'>DREN</option>";
                            }
                            else if (['installation'].includes(currentTd)) {
                                opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                            } 
                        } 
                        else {
                            opts += "<option value='augure_dren'>DREN</option>";
                        }
                    } 
                    else if (DB_NIVEAU === 'central') {
                        if (DB_ROLE === 'resp_encadre') {
                            if (['titularisation', 'avancement_classe', 'avancement_echelon'].includes(currentTd)) {
                                opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                                opts += '<option value="mtefop">Fonction Publique (MTeFOP)</option>';
                                opts += '<option value="primature">Primature</option>';
                            }
                        } 
                        else if (DB_ROLE === 'resp_non_encadre') {
                            if (['integration'].includes(currentTd)) {
                                opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                                opts += '<option value="mtefop">Fonction Publique (MTeFOP)</option>';
                                opts += '<option value="primature">Primature</option>';
                            } 
                            else if (['renouvellement', 'avenant'].includes(currentTd)) {
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            }
                        }
                        else if (DB_ROLE === 'resp_retraite') {
                            if (['admission_retraite'].includes(currentTd)) {
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                                opts += '<option value="mtefop">Fonction Publique (MTeFOP)</option>';
                                opts += '<option value="primature">Primature</option>';
                            }
                            else if (['compensatrice', 'installation'].includes(currentTd)) {
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            }
                        } 
                    }
                    else if (DB_NIVEAU === 'regional') {
                        if (DB_ROLE === 'resp_encadre') {
                            if (['avancement_classe', 'avancement_echelon'].includes(currentTd)) {
                                opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                                opts += '<option value="prefecture">Préfecture</option>';
                            } 
                            else if (['titularisation'].includes(currentTd)) {
                                opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                            }
                        } 
                        else if (DB_ROLE === 'resp_non_encadre') {
                            if (['integration'].includes(currentTd)) {
                                opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                                opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                            } 
                            else if (['renouvellement', 'avenant'].includes(currentTd)) {
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                                opts += '<option value="prefecture">Préfecture</option>';
                            }
                        } else if (DB_ROLE === 'resp_retraite') {
                            if (['admission_retraite'].includes(currentTd)) {
                                opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                                opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                            }
                            else if (['installation'].includes(currentTd)) {
                                opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                            }
                            else if (['compensatrice'].includes(currentTd)) {
                                opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                                opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                                opts += '<option value="prefecture">Préfecture</option>';
                            }
                        }
                    }
                }
            }
            // CAS DES AUTRES RÔLES (resp_personnel_crfrp, chef_service, admin...)
            else {
                if (tb === 'creation_projet') {
                    if (DB_NIVEAU === 'district') {
                        if (['admission_retraite'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        }
                        else if (['installation'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        } else {
                            opts += "<option value='augure_dren'>DREN</option>";
                        }                        
                    }
                    else if (DB_NIVEAU === 'crfrp') {
                        if (['admission_retraite'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        }
                        else if (['installation'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        } 
                        else if (['renouvellement', 'avenant', 'compensatrice'].includes(currentTd)) {
                            opts += '<option value="augure_dren">DREN</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="prefecture">Préfecture</option>';
                        }
                        else if (['avancement_classe', 'avancement_echelon'].includes(currentTd)) {
                            opts += '<option value="augure_dren">DREN</option>';
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="prefecture">Préfecture</option>';
                        }
                        else if (['titularisation'].includes(currentTd)) {
                            opts += '<option value="augure_dren">DREN</option>';
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        }
                        else if (['integration'].includes(currentTd)) {
                            opts += '<option value="augure_dren">DREN</option>';
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        }
                        else {
                            opts += "<option value='augure_dren'>DREN</option>";
                        }
                    }
                    else if (DB_NIVEAU === 'regional') {
                        if (['renouvellement', 'avenant', 'compensatrice'].includes(currentTd)) {
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="prefecture">Préfecture</option>';
                        } 
                        else if (['avancement_classe', 'avancement_echelon'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="prefecture">Préfecture</option>';
                        } 
                        else if (['titularisation'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        } 
                        else if (['integration'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        } 
                        else if (['admission_retraite'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        } 
                        else if (['installation'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        }
                    }
                    else if (DB_NIVEAU === 'central') {
                        if (['admission_retraite'].includes(currentTd)) {
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="mtefop">Fonction Publique (MTeFOP)</option>';
                            opts += '<option value="primature">Primature</option>';
                        }
                        else if (['compensatrice', 'installation'].includes(currentTd)) {
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                        }
                    }
                } 
                else if (tb === 'mandatement') {
                    opts += '<option value="augure_dsp">Solde et Pensions</option>';
                }
            }
        }
        
        $('#service_destination').html(opts);
        reactiverBoutonGenerer();
    });

    window.loadAgents = function() {
        const tb = $('#select_type_bordereau').val();
        const td = $('#filter_type_dos').val();
        const sd = $('#service_destination').val();

        if(!tb || !td || !sd) {
            Swal.fire('Champs manquants', 'Merci de remplir tous les critères de filtrage.', 'warning');
            return;
        }

        if ($.fn.DataTable.isDataTable('#tableAgents')) {
            $('#tableAgents').DataTable().destroy();
        }

        $('#tableAgents').DataTable({
            "ajax": {
                "url": "api/bordereaux/api_agents_bordereau.php",
                "type": "POST",
                "data": {
                    "action": "list_agents",
                    "type_bordereau": tb,
                    "filter_type_dos": td,
                    "service_destination": sd
                }
            },
            "columns": getColumnsByNiveau(DB_NIVEAU),
            "dom": 'rtip',
            "pageLength": 10,
            "language": {
                "sEmptyTable":     "Aucune agent en attente de bordereau pour ce type de demande",
                "sInfo":           "Affichage de l'élément _START_ à _END_ sur _TOTAL_ éléments",
                "sInfoEmpty":      "Affichage de l'élément 0 à 0 sur 0 élément",
                "sInfoFiltered":   "(filtré à partir de _MAX_ éléments au total)",
                "sInfoPostFix":    "",
                "sInfoThousands":  ",",
                "sLengthMenu":     "Afficher _MENU_ éléments",
                "sLoadingRecords": "Chargement...",
                "sProcessing":     "Traitement...",
                "sSearch":         "Rechercher :",
                "sZeroRecords":    "Aucun élément correspondant trouvé",
                "oPaginate": {
                    "sFirst":    "Premier",
                    "sLast":     "Dernier",
                    "sNext":     "Suivant",
                    "sPrevious": "Précédent"
                },
                "oAria": {
                    "sSortAscending":  ": activer pour trier la colonne par ordre croissant",
                    "sSortDescending": ": activer pour trier la colonne par ordre décroissant"
                }
            }
        });
    };

    $('#customSearch').on('keyup', function() {
        if ($.fn.DataTable.isDataTable('#tableAgents')) {
            $('#tableAgents').DataTable().search(this.value).draw();
        }
    });

    function getColumnsByNiveau(niv) {
        const niveauClean = (niv || '').toString().toLowerCase().trim();
        if (niveauClean === 'central') {
            return [
                { "data": "num" },
                { "data": "lieu_service" },
                { "data": "nom_complet" },
                { "data": "im" },
                { "data": "corps_grade" },
                { "data": "action" }
            ];
        } else if (niveauClean === 'regional') {
            return [
                { "data": "num" },
                { "data": "cisco" },
                { "data": "zap" },
                { "data": "lieu_service" },
                { "data": "nom_complet" },
                { "data": "im" },
                { "data": "corps_grade" },
                { "data": "action" }
            ];
        } else if (niveauClean === 'district') {
            return [
                { "data": "num" },
                { "data": "zap" },
                { "data": "lieu_service" },
                { "data": "nom_complet" },
                { "data": "im" },
                { "data": "corps_grade" },
                { "data": "action" }
            ];
        } else {
            return [
                { "data": "num" },
                { "data": "nom_complet" },
                { "data": "im" },
                { "data": "corps_grade" },
                { "data": "action" }
            ];
        }
    }
});

// Fonction pour récupérer le type de dossier actuellement sélectionné dans le filtre
function getSelectedTypeDos() {
    return $('select[name="filter_type_dos"]').val() || $('select[name="type_dos"]').val() || $('#filter_type_dos').val() || '';
}

window.confirmerActionAgent = function(im, destination, valeur) {
    let messageStr = valeur === 1 
        ? "Voulez-vous ajouter cet agent dans le bordereau ?" 
        : "Voulez-vous retirer cet agent du bordereau ?";
        
    let confirmBtnColor = valeur === 1 ? '#2563eb' : '#dc2626';

    Swal.fire({
        title: 'Confirmation',
        text: messageStr,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: confirmBtnColor,
        cancelButtonColor: '#4b5563',
        confirmButtonText: valeur === 1 ? 'Oui, ajouter' : 'Oui, retirer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/bordereaux/api_agents_bordereau.php',
                type: 'POST',
                data: {
                    action: 'ajout_agent',
                    im: im,
                    destination: destination,
                    valeur: valeur,
                    filter_type_dos: getSelectedTypeDos() 
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Succès !', 'Opération effectuée avec succès.', 'success');
                        $('#tableAgents').DataTable().ajax.reload(null, false);
                    } else {
                        Swal.fire('Erreur', response.message || 'Une erreur est survenue', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Erreur', 'Erreur de communication avec le serveur.', 'error');
                }
            });
        }
    });
}

function updateAgentStatus(im, destination, valeur) {
    $.ajax({
        url: 'api/bordereaux/api_agents_bordereau.php',
        type: 'POST',
        data: {
            action: 'ajout_agent',
            im: im,
            destination: destination,
            valeur: valeur,
            filter_type_dos: getSelectedTypeDos(),
            type_bordereau: $('#select_type_bordereau').val() 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#tableAgents').DataTable().ajax.reload(null, false); 
                
                const message = valeur === 1 ? 'Agent ajouté au bordereau' : 'Agent retiré du bordereau';
                const toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
                toast.fire({ icon: 'success', title: message });
            } else {
                Swal.fire('Erreur', response.message, 'error');
            }
        }
    });
}

window.genererBordereauFinal = function() {
    const tb = $('#select_type_bordereau').val();
    const td = $('#filter_type_dos').val();
    const sd = $('#service_destination').val();

    if (!tb || !td || !sd) {
        Swal.fire({
            icon: 'warning',
            title: 'Attention',
            text: 'Veuillez remplir tous les filtres avant de générer.',
            confirmButtonText: 'OK'
        });
        return;
    }

    const labelsDossiers = {
        'renouvellement': 'Renouvellement de contrat',
        'avenant': 'Avenant',
        'avenant_avec_contrat': 'Avenant (Rappel différentiel moins perçu)',
        'avancement_classe': 'Avancement de classe',
        'avancement_echelon': "Avancement d'échelon",
        'integration': 'Intégration',
        'titularisation': 'Titularisation',
        'admission_retraite': 'Admission à la retraite',
        'compensatrice': 'Compensatrice',
        'installation': 'Installation',
        'conge_annuel': 'Congé annuel'
    };

    const labelDossier = labelsDossiers[td] || 'Dossier';
    const labelDest = $('#service_destination option:selected').text();

    $.ajax({
        url: 'api/bordereaux/get_last_bordereau.php',
        method: 'GET',
        data: {
            type_dos: td,
            dest: sd,
            type_bordereau: tb
        },
        dataType: 'json',
        success: function(res) {
            if (!$.fn.DataTable.isDataTable('#tableAgents')) {
                Swal.fire('Attention', 'Veuillez d’abord afficher la liste des agents.', 'warning');
                return;
            }

            const table = $('#tableAgents').DataTable();
            let nbAgentsSelectionnes = 0;
            table.rows().every(function () {
                const rowData = this.data();
                if (rowData && rowData.action && (String(rowData.action).includes('fa-check-circle') || String(rowData.action).includes('Retirer de la sélection'))) {
                    nbAgentsSelectionnes++;
                }
            });

            if (nbAgentsSelectionnes === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Aucun agent sélectionné',
                    html: `
                        <div style="font-size:16px; line-height:1.6;">
                            Veuillez ajouter au moins un agent en cliquant sur 
                            <span style="color: #2563eb; display: inline-flex; align-items: center; justify-content: center; background-color: #dbeafe; width: 28px; height: 28px; border-radius: 50%; vertical-align: middle;">
                                <i class="fas fa-plus-circle" style="font-size: 16px;"></i>
                            </span> 
                            avant de générer un bordereau.
                        </div>
                    `,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            // Ouvre la modale pour première génération
            $('#mod_titre_dossier').text('Dossier de : ' + labelDossier);
            $('#mod_destination_label').val(labelDest);
            $('#mod_dernier_num').text(res.dernier_complet || 'Premier numéro');
            $('#mod_prochain_num').val(res.next_num);

            const sigleInput = $('#mod_sigle');
            sigleInput.val(res.sigle || '');
            if (res.sigle && res.sigle !== '') {
                sigleInput.attr('readonly', true).addClass('bg-gray-100 cursor-not-allowed');
            } else {
                sigleInput.attr('readonly', false).removeClass('bg-gray-100 cursor-not-allowed');
            }

            $('#modalBordereau').removeClass('hidden').addClass('flex');
        },
        error: function() {
            Swal.fire('Erreur', 'Impossible de vérifier l\'état du bordereau.', 'error');
        }
    });
};

window.closeModalBordereau = function() {
    $('#modalBordereau').addClass('hidden').removeClass('flex');
}

window.lancerImpressionBordereau = function() {
    const num = $('#mod_prochain_num').val();
    const sigle = $('#mod_sigle').val();
    const dest = $('#service_destination').val();
    const filter = $('#filter_type_dos').val();
    const type_b = $('#select_type_bordereau').val();
    const civilite = $('#mod_civilite').val();

    if (!num || !sigle) {
        Swal.fire('Champs requis', 'Le numéro et le sigle sont obligatoires.', 'info');
        return;
    }

    closeModalBordereau();
    
    // Désactiver le bouton immédiatement après validation
    desactiverBoutonGenerer();

    const url = `documents/bordereaux/generate_bordereau.php?num=${num}&sigle=${encodeURIComponent(sigle)}&dest=${dest}&filter=${filter}&type_bordereau=${type_b}&civilite=${civilite}`;
    window.open(url, '_blank');

    if ($.fn.DataTable.isDataTable('#tableAgents')) {
        $('#tableAgents').DataTable().ajax.reload(null, false);
    }
};

// Désactive et grise le bouton
window.desactiverBoutonGenerer = function () {
    $('#btnGenererBordereau')
        .prop('disabled', true)
        .addClass('opacity-50 cursor-not-allowed')
        .attr('title', 'Un bordereau a déjà été généré, veuillez consultez la dossier de telechargement');
};

// Réactive le bouton
window.reactiverBoutonGenerer = function () {
    $('#btnGenererBordereau')
        .prop('disabled', false)
        .removeClass('opacity-50 cursor-not-allowed')
        .removeAttr('title');
};
</script>
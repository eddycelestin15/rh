<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$user_im = $_SESSION['user_im'];

// 1. Récupération des infos de l'agent
$stmt = $pdo->prepare("SELECT * FROM personnel_etat_civil WHERE im = ?");
$stmt->execute([$user_im]);
// Compte sans fiche etat civil : tableau vide plutot que false.
$agent = $stmt->fetch() ?: [];

$total_en_charge = $agent['nbr_enfant'] ?? 0;
$total_fiche_paie = $agent['nbr_enfant_bc'] ?? 0;

// 2. Récupération des enfants
$stmtE = $pdo->prepare("SELECT * FROM personnel_enfants WHERE im_parent = ? ORDER BY date_naiss_enfant ASC");
$stmtE->execute([$user_im]);
$enfants = $stmtE->fetchAll();
$nb_enfants = count($enfants);

// 1. On cherche d'abord si l'agent a déjà rempli un certificat CNAPS
$stmtCert = $pdo->prepare("SELECT * FROM personnel_certificat_cnaps WHERE im_agent = ?");
$stmtCert->execute([$user_im]);
$existingCert = $stmtCert->fetch(PDO::FETCH_ASSOC);

// 2. On récupère les infos de base de l'état civil pour le fallback
$stmtBase = $pdo->prepare("SELECT * FROM personnel_etat_civil WHERE im = ?");
$stmtBase->execute([$user_im]);
$agentBase = $stmtBase->fetch(PDO::FETCH_ASSOC);

// Déterminer la situation familiale (priorité au certificat existant)
$situation = $existingCert ? ($agentBase['situation_familiale'] ?? 'Célibataire') : ($agentBase['situation_familiale'] ?? 'Célibataire');
$isCelibataire = ($situation == 'Célibataire');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <title>Allocation Familiale</title>
    <style>
        html, body { height: 100vh; overflow: hidden; padding: 2mm; box-sizing: border-box; background-color: #f1f5f9; }
        #pageContent { height: 100%; display: flex; flex-direction: column; gap: 10px; }

        /* Empêche le premier bloc de se réduire */
        #pageContent > div:first-child {
            flex-shrink: 0;
        }

        /* Assure que le deuxième bloc prend le reste et gère le scroll */
        #pageContent > div:last-child {
            flex-grow: 1;
            min-height: 0; /* Important pour forcer le scroll interne en flexbox */
        }
        
        #tableEnfants thead th { 
            background-color: #dbeafe !important; 
            color: #1e40af !important;           
            font-weight: 800; 
            text-transform: uppercase; 
            font-size: 13px !important;          
            text-align: center !important;       
            padding: 12px; 
            border: 1px solid #bfdbfe;           
        }
        /* Style des en-têtes DataTables */
        #tableEnfants tbody td {
            font-size: 13px !important;          
            padding: 4px 6px;
            vertical-align: middle;
        }

        /* Conteneur flex pour aligner Recherche (gauche) et Bouton (droite) */
        .table-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        /* Style spécifique pour l'icône Loupe dans la barre de recherche */
        #searchContainer {
            position: relative;
        }

        #searchContainer .dataTables_filter input {
            width: 300px !important;
            border-radius: 12px !important;
            padding-left: 38px !important; /* Espace pour l'icône */
            border: 1px solid #cbd5e1 !important;
            height: 40px;
            background-color: #f8fafc;
            outline: none !important;
        }

        #searchContainer::before {
            content: "\f002"; /* Code FontAwesome pour la loupe */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 10;
        }

        .dataTables_wrapper .dataTables_filter {
            float: none !important;
            text-align: left !important;
            margin: 0 !important;
        }
        .dataTables_filter input {
            width: 300px !important; border-radius: 10px !important;
            padding-left: 35px !important; border: 1px solid #cbd5e1 !important; height: 38px;
        }
        .dataTables_filter::before {
            content: "\f002"; font-family: "Font Awesome 6 Free"; font-weight: 900;
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;
        }

        /* Conteneur de table scrollable */
        .table-scroll { flex-grow: 1; overflow-y: auto; }

        /* --- Style de la Pagination --- */
        .dataTables_wrapper .dataTables_paginate {
            padding-top: 7px !important;
        }

        /* Boutons de numéro de page */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 4px 10px !important; /* Taille réduite */
            margin-left: 5px !important;
            border-radius: 8px !important; /* Bord arrondi */
            border: 1px solid #e2e8f0 !important;
            background: white !important;
            color: #64748b !important;
            font-size: 11px !important;
            font-weight: bold !important;
        }

        /* Bouton actif (Page actuelle) */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #2563eb !important; /* Bleu */
            color: white !important;
            border: 1px solid #2563eb !important;
            shadow: 0 4px 6px -1px rgb(37 99 235 / 0.2) !important;
        }

        /* Effet au survol (Hover) */
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #dbeafe !important; /* Bleu très clair */
            color: #1e40af !important;
            border: 1px solid #bfdbfe !important;
        }

        /* Masquer les bordures par défaut de DataTables qui créent des doublons */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            color: white !important;
        }
        .dataTables_empty {
            padding: 40px !important;
            font-weight: bold;
            color: #94a3b8; /* slate-400 */
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
        }
        #modalDCF {
            z-index: 9999 !important; /* Force l'affichage au premier plan */
        }
    </style>
</head>
<body>

<div id="pageContent">    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 relative overflow-hidden flex-shrink-0">
        <div class="absolute top-0 right-0 flex">
            <div class="bg-blue-600 text-white px-4 py-2 text-center shadow-lg">
                <p class="text-[11px] uppercase font-bold opacity-80">Enfant(s) en charge</p>
                <p class="text-lg font-black" id="badge_total_en_charge"><?= $total_en_charge ?></p>
            </div>
            <div class="bg-emerald-500 text-white px-4 py-2 text-center shadow-lg">
                <p class="text-[11px] uppercase font-bold opacity-80">Enfant(s) dans Bon de caisse</p>
                <p class="text-lg font-black" id="badge_total_bc"><?= $total_fiche_paie ?></p>
            </div>
        </div>

        <h2 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-2">
            <i class="fas fa-baby-carriage text-blue-600"></i> RENSEIGNEMENTS SUR LES ENFANTS
        </h2>

        <form id="formEnfant" class="space-y-4">
            <input type="hidden" name="im_parent" value="<?= $user_im ?>">
            
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-3">
                    <label class="block text-[13px] font-bold text-slate-400 uppercase mb-1">Nom</label>
                    <input type="text" name="nom" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold uppercase">
                </div>
                <div class="col-span-3">
                    <label class="block text-[13px] font-bold text-slate-400 uppercase mb-1">Prénoms</label>
                    <input type="text" name="prenoms" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold">
                </div>
                <div class="col-span-3">
                    <label class="block text-[13px] font-bold text-slate-400 uppercase mb-1">Date de naissance</label>
                    <input type="date" name="date_naiss" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold">
                </div>
                <div class="col-span-3">
                    <label class="block text-[13px] font-bold text-slate-400 uppercase mb-1">Lieu de naissance</label>
                    <input type="text" name="lieu_naiss" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold uppercase">
                </div>
            </div>

            <div class="grid grid-cols-12 gap-4 items-end">
                <div class="col-span-2">
                    <label class="block text-[13px] font-bold text-slate-400 uppercase mb-1">Sexe</label>
                    <select name="sexe" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="" disabled selected>-- Choisir sexe --</option>
                        <option value="Masculin">Masculin</option>
                        <option value="Féminin">Féminin</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-[13px] font-bold text-slate-400 uppercase mb-1">N° Copie Acte</label>
                    <input type="text" name="num_copie" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold">
                </div>
                <div class="col-span-3">
                    <label class="block text-[13px] font-bold text-slate-400 uppercase mb-1">Filiation</label>
                    <select name="type_filiation" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="" disabled selected>-- Choisir filiation --</option>
                        <option value="Légitime">Légitime</option>
                        <option value="Naturel">Naturel</option>
                        <option value="Adopté">Adopté</option>
                        <option value="Reconnaissance">Reconnaissance</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-[13px] font-bold text-slate-400 uppercase mb-1">BON DE CAISSE</label>
                    <select name="inscrit_sur_bc" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="" disabled selected>-- Déjà sur BC? --</option>
                        <option value="oui">OUI</option>
                        <option value="non">NON</option>
                    </select>
                </div>
                <div class="col-span-3">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-2.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-2 uppercase text-xs">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-4 pt-4 border-t border-slate-100">
                <div class="col-span-3 text-center sm:text-left">
                    <p class="text-[13px] font-black text-blue-600 uppercase">Légitime</p>
                    <p class="text-[12px] text-slate-500 leading-tight">Né de parents mariés ensemble.</p>
                </div>
                <div class="col-span-3 text-center sm:text-left">
                    <p class="text-[13px] font-black text-blue-600 uppercase">Naturel</p>
                    <p class="text-[12px] text-slate-500 leading-tight">Né hors mariage des parents.</p>
                </div>
                <div class="col-span-3 text-center sm:text-left">
                    <p class="text-[13px] font-black text-blue-600 uppercase">Adoption</p>
                    <p class="text-[12px] text-slate-500 leading-tight">Filiation par décision de justice.</p>
                </div>
                <div class="col-span-3 text-center sm:text-left">
                    <p class="text-[13px] font-black text-blue-600 uppercase">Reconnaissance</p>
                    <p class="text-[12px] text-slate-500 leading-tight">Acte volontaire d'un parent.</p>
                </div>
            </div>
        </form>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex flex-col flex-grow overflow-hidden">
        <div class="table-header-actions">
            <div id="searchContainer"></div>
            <button onclick="faireDemande()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black px-6 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2 uppercase text-xs">
                <i class="fas fa-paper-plane"></i> Faire demande
            </button>
        </div>

        <div class="table-scroll">
            <table id="tableEnfants" class="w-full">
                <thead>
                    <tr>
                        <th class="text-center">N°</th>
                        <th class="text-center">N° Copie</th>
                        <th class="text-center">Nom et Prénoms</th>
                        <th class="text-center">Date et Lieu de Naissance</th>
                        <th class="text-center">Filiation</th>
                        <th class="text-center">Bon de caisse</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="text-slate-600">
                    <?php foreach ($enfants as $idx => $e): 
                        $age = (new DateTime())->diff(new DateTime($e['date_naiss_enfant']))->y;
                        $eligible = ($age < 21);
                    ?>
                    <tr class="hover:bg-blue-50/30 transition-colors border-b border-slate-100">
                        <td class="text-center font-bold text-slate-700 uppercase"><?= sprintf("%02d", $idx + 1) ?></td>
                        <td class="font-bold text-slate-700 uppercase text-center"><?= $e['num_copie_acte'] ?></td>
                        <td class="font-bold text-slate-700 uppercase"><?= htmlspecialchars($e['nom_enfant'] . ' ' . $e['prenoms_enfant']) ?></td>
                        <td class="font-bold text-slate-700 uppercase">
                            <?= date('d/m/Y', strtotime($e['date_naiss_enfant'])) ?> 
                            <span class="font-bold text-slate-700 uppercase">à</span> <?= htmlspecialchars($e['lieu_naiss_enfant']) ?>
                        </td>
                        <td class="text-center font-bold text-slate-700 uppercase"><?= $e['type_filiation'] ?></td>
                        <td class="text-center">
                            <?php if ($e['statut_bc'] == 1): ?>
                                <span class="px-3 py-1 bg-emerald-500 text-white text-[10px] font-black rounded-full uppercase shadow-sm">Déjà inscrit</span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-rose-500 text-white text-[10px] font-black rounded-full uppercase shadow-sm">Non inscrit</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button onclick='ouvrirModaleModif(<?= json_encode($e) ?>)' class="p-2 bg-amber-100 text-amber-600 hover:bg-amber-600 hover:text-white rounded-lg transition-all shadow-sm group">
                                <i class="fas fa-edit group-hover:scale-110"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalModifEnfant" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="bg-amber-500 p-6 flex justify-between items-center text-white">
            <h3 class="font-black uppercase flex items-center gap-2">
                <i class="fas fa-user-edit"></i> Correction Fiche Enfant
            </h3>
            <button onclick="fermerModaleModif()" class="hover:rotate-90 transition-transform"><i class="fas fa-times"></i></button>
        </div>
        
        <form id="formModifEnfant" class="p-6 space-y-4">
            <input type="hidden" name="id_enfant" id="modif_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Nom</label>
                    <input type="text" name="nom" id="modif_nom" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold uppercase">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Prénoms</label>
                    <input type="text" name="prenoms" id="modif_prenoms" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Date de naissance</label>
                    <input type="date" name="date_naiss" id="modif_date" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold">
                </div>
                <div class="col-span-2">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Lieu de naissance</label>
                    <input type="text" name="lieu_naiss" id="modif_lieu" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold uppercase">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Sexe</label>
                    <select name="sexe" id="modif_sexe" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold">
                        <option value="Masculin">Masculin</option>
                        <option value="Féminin">Féminin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">N° Copie</label>
                    <input type="text" name="num_copie" id="modif_copie" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Filiation</label>
                    <select name="type_filiation" id="modif_filiation" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold">
                        <option value="Légitime">Légitime</option>
                        <option value="Naturel">Naturel</option>
                        <option value="Adopté">Adopté</option>
                        <option value="Reconnaissance">Reconnaissance</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="fermerModaleModif()" class="flex-1 bg-rose-500 hover:bg-rose-600 text-white font-black py-3 rounded-xl transition-all flex items-center justify-center gap-2 uppercase text-xs shadow-lg">
                    <i class="fas fa-times-circle"></i> Annuler
                </button>
                <button type="submit" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white font-black py-3 rounded-xl transition-all flex items-center justify-center gap-2 uppercase text-xs shadow-lg">
                    <i class="fas fa-check-circle"></i> Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modalDemandeCertificat" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-50 items-center justify-center p-4">
    <div class="bg-white w-full max-w-5xl max-h-[90vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col">
        <div class="bg-blue-600 p-6 flex justify-between items-center">
            <h2 class="text-white font-black text-lg uppercase tracking-widest">Demande de Certificat de Non Paiement</h2>
            <button onclick="$('#modalDemandeCertificat').addClass('hidden')" class="text-white/80 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>

        <div class="p-6 overflow-y-auto">
            <form id="formCertificat" method="POST" target="_blank">
                <table class="w-full border-collapse border border-slate-200">
                    <thead>
                        <tr class="bg-slate-50 uppercase text-[11px] font-black text-slate-500">
                            <th class="border border-slate-200 p-3 w-1/4 text-left"></th>
                            <th class="border border-slate-200 p-3 w-3/8 text-center text-blue-600">Demandeur (Agent)</th>
                            <th class="border border-slate-200 p-3 w-3/8 text-center text-emerald-600">Conjoint(e)</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php
                        $readonly_conjoint = $isCelibataire ? 'readonly bg-slate-100 text-slate-400' : 'bg-emerald-50/30';
                        
                        $champs = [
                            ['Nom', 'nom', 'text', 'agent_nom', 'conjoint_nom'], 
                            ['Prénoms', 'prenoms', 'text', 'agent_prenoms', 'conjoint_prenoms'],
                            ['Date de naissance', 'date_naiss', 'date', 'agent_date_naiss', 'conjoint_date_naiss'], 
                            ['Lieu de naissance', 'lieu_naiss', 'text', 'agent_lieu_naiss', 'conjoint_lieu_naiss'],
                            ['Sous préfecture de', 'sous_pref', 'text', 'agent_sous_pref', 'conjoint_sous_pref'], 
                            ['N° CIN', 'cin', 'text', 'agent_cin', 'conjoint_cin'],
                            ['Date délivrance CIN', 'date_cin', 'date', 'agent_date_cin', 'conjoint_date_cin'], 
                            ['Lieu délivrance CIN', 'lieu_cin', 'text', 'agent_lieu_cin', 'conjoint_lieu_cin'],
                            ['Fils / Fille de', 'pere', 'text', 'agent_pere', 'conjoint_pere'], 
                            ['Et de', 'mere', 'text', 'agent_mere', 'conjoint_mere'],
                            ['Adresse', 'adresse', 'text', 'agent_adresse', 'conjoint_adresse'], 
                            ['Matricule CNAPS', 'mat_cnaps', 'text', 'agent_mat_cnaps', 'conjoint_mat_cnaps'],
                            ['Service Employeur', 'service', 'text', 'agent_service', 'conjoint_service']
                        ];

                        foreach ($champs as $c): 
                            if ($existingCert) {
                                $val_agent = $existingCert[$c[3]] ?? '';
                            } else {
                                $val_agent = $agentBase[$c[1]] ?? '';
                            }

                            $val_conjoint = '';
                            if ($isCelibataire) {
                                $val_conjoint = 'N/A';
                            } elseif ($existingCert) {
                                $val_conjoint = $existingCert[$c[4]] ?? '';
                            } elseif (!$isCelibataire && $c[1] == 'nom') {
                                $val_conjoint = $agentBase['nom_conjoint'] ?? '';
                            }
                        ?>
                            <tr>
                                <td class="border border-slate-200 p-2 font-bold text-slate-500 text-[11px] uppercase bg-slate-50/50"><?= $c[0] ?></td>
                                <td class="border border-slate-200 p-1">
                                    <input type="<?= $c[2] ?>" name="agent_<?= $c[1] ?>" 
                                        class="w-full p-2 bg-blue-50/30 outline-none focus:bg-blue-100 font-semibold" 
                                        value="<?= htmlspecialchars($val_agent) ?>">
                                </td>
                                <td class="border border-slate-200 p-1">
                                    <input type="<?= $c[2] ?>" name="conjoint_<?= $c[1] ?>" 
                                        class="w-full p-2 outline-none focus:bg-emerald-100 font-semibold <?= $readonly_conjoint ?>" 
                                        value="<?= htmlspecialchars($val_conjoint) ?>" <?= $isCelibataire ? 'readonly' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="mt-6 flex justify-end gap-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-black px-8 py-3 rounded-xl shadow-lg flex items-center gap-2 uppercase text-xs transition-all">
                        <i class="fas fa-print"></i> Afficher la liste des pieces
                    </button>
                    <button type="button" onclick="$('#modalDemandeCertificat').addClass('hidden')" 
                            class="bg-rose-600 hover:bg-rose-700 text-white px-6 py-3 rounded-xl font-bold uppercase text-xs flex items-center gap-2 shadow-lg transition-all">
                        <i class="fas fa-times"></i> Annuler
                    </button> 
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modalDCF" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border border-slate-200 transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-800">Déclaration de charge de famille</h3>
            <button onclick="closeModalDCF()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Numéro DCF</label>
                <input type="text" id="input_num_dcf" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-rose-500 outline-none transition-all" placeholder="Ex: 123/REG-VATO">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Date de déclaration</label>
                <input type="date" id="input_date_dcf" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-rose-500 outline-none transition-all">
            </div>
        </div>

        <div class="flex gap-3 mt-8">
            <button onclick="closeModalDCF()" class="flex-1 px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                <i class="fas fa-times"></i> Annuler
            </button>
            <button onclick="validerEtImprimer()" class="flex-1 px-4 py-2.5 bg-rose-600 text-white rounded-xl font-bold hover:bg-rose-700 shadow-lg shadow-rose-200 transition-all flex items-center justify-center gap-2">
                <i class="fas fa-print"></i> Imprimer
            </button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialisation DataTable
    var tableEnfants = $('#tableEnfants').DataTable({
        "pageLength": 10,
        "lengthChange": false,
        "dom": 'f r t <"flex justify-between items-center mt-4" i p>',
        "language": {
            "search": "",
            "searchPlaceholder": "Rechercher un enfant...",
            "paginate": { 
                "next": '<i class="fas fa-chevron-right"></i>', 
                "previous": '<i class="fas fa-chevron-left"></i>' 
            },
            "info": "Page _PAGE_ sur _PAGES_",
            "infoEmpty": "Aucune donnée disponible",
            "emptyTable": "Il n'y a pas de liste d'enfants à afficher pour l'instant",
            "zeroRecords": "Aucun enfant ne correspond à votre recherche"
        },
        "initComplete": function() {
            $('.dataTables_filter').appendTo('#searchContainer');
        }
    });

    // Soumission du formulaire d'ajout d'enfant avec actualisation instantanée du tableau
    $('#formEnfant').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({ 
            title: 'Enregistrement...', 
            allowOutsideClick: false, 
            didOpen: () => Swal.showLoading() 
        });

        // Récupération des valeurs saisies dans les champs avant réinitialisation
        var nom = $('#formEnfant input[name="nom"]').val().toUpperCase();
        var prenoms = $('#formEnfant input[name="prenoms"]').val();
        // Capitaliser la première lettre de chaque mot pour les prénoms
        prenoms = prenoms.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
        
        var rawDate = $('#formEnfant input[name="date_naiss"]').val();
        var dateFormatee = "";
        if(rawDate) {
            var parts = rawDate.split('-');
            if(parts.length === 3) dateFormatee = parts[2] + '/' + parts[1] + '/' + parts[0];
        }
        
        var lieu = $('#formEnfant input[name="lieu_naiss"]').val().toUpperCase();
        var sexe = $('#formEnfant select[name="sexe"]').val();
        var numCopie = $('#formEnfant input[name="num_copie"]').val();
        var filiation = $('#formEnfant select[name="type_filiation"]').val();
        var inscritSurBC = $('#formEnfant select[name="inscrit_sur_bc"]').val();

        $.post('api/allocation/api_allocation.php', $(this).serialize(), function(res) {
            if(res.success) {
                Swal.fire({ 
                    icon: 'success', 
                    title: 'Enfant enregistré avec succès !', 
                    showConfirmButton: false, 
                    timer: 1400 
                }).then(() => {
                    // 1. Calcul dynamique du numéro de ligne
                    var nextIdx = tableEnfants.rows().count() + 1;
                    var formattedIdx = String(nextIdx).padStart(2, '0');

                    // 2. Préparation du badge Bon de Caisse
                    var badgeBC = "";
                    if (inscritSurBC === 'oui') {
                        badgeBC = '<span class="px-3 py-1 bg-emerald-500 text-white text-[10px] font-black rounded-full uppercase shadow-sm">Déjà inscrit</span>';
                    } else {
                        badgeBC = '<span class="px-3 py-1 bg-rose-500 text-white text-[10px] font-black rounded-full uppercase shadow-sm">Non inscrit</span>';
                    }

                    // 3. Objet Enfant nécessaire pour la modale de modification dynamique
                    var enfantObj = {
                        id: res.enfant_id, 
                        im_parent: "<?= $user_im ?>",
                        nom_enfant: nom,
                        prenoms_enfant: prenoms,
                        date_naiss_enfant: rawDate,
                        lieu_naiss_enfant: lieu,
                        sexe: sexe,
                        num_copie_acte: numCopie,
                        type_filiation: filiation,
                        statut_bc: (inscritSurBC === 'oui') ? 1 : 0
                    };

                    // Échapper les guillemets pour l'injecter proprement dans le onclick du bouton HTML
                    var enfantJson = JSON.stringify(enfantObj).replace(/'/g, "&apos;").replace(/"/g, '&quot;');

                    var boutonAction = '<button onclick="ouvrirModaleModif(' + enfantJson + ')" class="p-2 bg-amber-100 text-amber-600 hover:bg-amber-600 hover:text-white rounded-lg transition-all shadow-sm group">' +
                        '<i class="fas fa-edit group-hover:scale-110"></i>' +
                    '</button>';

                    // 4. Ajout de la nouvelle ligne dans le DataTable sans rechargement
                    tableEnfants.row.add([
                        '<div class="text-center font-bold text-slate-700 uppercase">' + formattedIdx + '</div>',
                        '<div class="font-bold text-slate-700 uppercase text-center">' + numCopie + '</div>',
                        '<div class="font-bold text-slate-700 uppercase">' + nom + ' ' + prenoms + '</div>',
                        '<div class="font-bold text-slate-700 uppercase">' + dateFormatee + ' <span class="font-bold text-slate-700 uppercase">à</span> ' + lieu + '</div>',
                        '<div class="text-center font-bold text-slate-700 uppercase">' + filiation + '</div>',
                        '<div class="text-center">' + badgeBC + '</div>',
                        '<div class="text-center">' + boutonAction + '</div>'
                    ]).draw(false);

                    // 5. Ajustement des compteurs visuels en haut à droite
                    var currentCharge = parseInt($('#badge_total_en_charge').text()) || 0;
                    $('#badge_total_en_charge').text(currentCharge + 1);
                    if(inscritSurBC === 'oui') {
                        var currentBC = parseInt($('#badge_total_bc').text()) || 0;
                        $('#badge_total_bc').text(currentBC + 1);
                    }

                    // 6. Vider le formulaire pour l'ajout suivant
                    $('#formEnfant')[0].reset();
                });
            } else {
                Swal.fire('Erreur', res.message || 'Une erreur est survenue', 'error');
            }
        }, 'json');
    });

    // Soumission du formulaire de modification
    $('#formModifEnfant').on('submit', function(e) {
        e.preventDefault();
        $.post('api/allocation/api_update_enfant.php', $(this).serialize(), function(res) {
            if(res.success) {
                Swal.fire({ icon: 'success', title: 'Mis à jour !', timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
            } else {
                Swal.fire('Erreur', res.message, 'error');
            }
        }, 'json');
    });
});

window.ouvrirModaleModif = function(enfant) {
    $('#modif_id').val(enfant.id);
    $('#modif_nom').val(enfant.nom_enfant);
    $('#modif_prenoms').val(enfant.prenoms_enfant);
    $('#modif_date').val(enfant.date_naiss_enfant);
    $('#modif_lieu').val(enfant.lieu_naiss_enfant);
    $('#modif_sexe').val(enfant.sexe);
    $('#modif_copie').val(enfant.num_copie_acte);
    $('#modif_filiation').val(enfant.type_filiation);
    
    $('#modalModifEnfant').removeClass('hidden');
};

window.fermerModaleModif = function() {
    $('#modalModifEnfant').addClass('hidden');
};

// --- MODIFICATION DE LA FONCTION EXISTANTE ---
window.faireDemande = function() {
    Swal.fire({
        title: '<span class="text-blue-700">Vérification CNAPS</span>',
        html: '<p class="text-sm text-slate-600">Avez-vous déjà le certificat de non paiement à la <strong>CNAPS</strong> ?</p>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669', 
        cancelButtonColor: '#2563eb',  
        confirmButtonText: '<i class="fas fa-check mr-2"></i> OUI, J\'EN AI DEJA',
        cancelButtonText: '<i class="fas fa-times mr-2"></i> NON, PAS ENCORE',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-3xl',
            confirmButton: 'rounded-xl font-black px-6 py-3',
            cancelButton: 'rounded-xl font-black px-6 py-3'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            afficherModalPiecesGlobal(); 
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            $('#modalDemandeCertificat').removeClass('hidden').addClass('flex');
        }
    });
};

window.afficherModalPiecesGlobal = function() {
    const isCelibataire = <?= json_encode($isCelibataire) ?>;
    const sexeAgent = <?= json_encode($agent['sexe'] ?? '') ?>;
    const nbEnfants = <?= $nb_enfants ?>;
    const isMasculin = (sexeAgent === 'Masculin');

    let urlPrincipal = `generate_acte_allocation.php?im=<?= $user_im ?>`;
    let pieceSpecifique = isCelibataire && isMasculin 
        ? `<span>Lettre de consentement</span>` 
        : `<span>Acte de mariage (si marié(e) légitime)</span>`;

    let suitesActeHtml = "";
    if (nbEnfants > 4) {
        let nbSuites = Math.ceil((nbEnfants - 4) / 4);        
        for (let i = 1; i <= nbSuites; i++) {
            let urlSuite = `generate_reste_acte_allocation.php?im=<?= $user_im ?>&page=${i}`;
            suitesActeHtml += `
                <a href="javascript:void(0)" onclick="openModalDCF('${urlSuite}')" class="flex items-center justify-between p-2 bg-blue-50 border border-blue-200 rounded-lg shadow-sm text-blue-700 font-bold hover:bg-blue-100 transition-all group block">
                    <div class="flex items-center gap-3 text-xs">
                        <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center font-black">02</div>
                        <span>Acte formaté (Suite ${i})</span>
                        <i class="fas fa-download text-[10px] opacity-50 group-hover:opacity-100"></i>
                    </div>
                    <span class="text-xs font-black">(02)</span>
                </a>
            `;
        }
    }

    Swal.fire({
        width: '850px',
        padding: '0',
        scrollbarPadding: false,
        background: '#fff',
        showConfirmButton: false,
        html: `
            <div class="bg-blue-600 p-4 text-white flex items-center justify-between shadow-md">
                <div class="flex items-center gap-3">
                    <i class="fas fa-folder-open text-2xl text-blue-200"></i>
                    <h2 class="text-lg font-black uppercase tracking-tight">
                        Pièces à fournir pour la demande allocation familiale
                    </h2>
                </div>
                <button onclick="Swal.close()" class="group flex items-center justify-center w-9 h-9 rounded-full bg-red-500 hover:bg-red-600 border-2 border-white transition-all shadow-lg">
                    <i class="fas fa-times text-white text-lg"></i>
                </button>
            </div>

            <div class="text-left p-5">
                <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                    <div class="space-y-2">
                        <a href="documents/actes/generate_allocation.php?im=<?= $user_im ?>" class="flex items-center justify-between p-2 bg-blue-50 border border-blue-200 rounded-lg shadow-sm text-blue-700 font-bold hover:bg-blue-100 transition-all group block">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center font-black">01</div>
                                <span>Demande</span>
                                <i class="fas fa-download text-[10px] opacity-50 group-hover:opacity-100"></i>
                            </div>
                            <span class="text-xs font-black">(03)</span>
                        </a>

                        <a href="javascript:void(0)" onclick="openModalDCF('${urlPrincipal}')" class="flex items-center justify-between p-2 bg-blue-50 border border-blue-200 rounded-lg shadow-sm text-blue-700 font-bold hover:bg-blue-100 transition-all group block">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center font-black">02</div>
                                <span>Acte formaté </span>
                                <i class="fas fa-download text-[10px] opacity-50 group-hover:opacity-100"></i>
                            </div>
                            <span class="text-xs font-black">(02)</span>
                        </a>

                        ${suitesActeHtml}

                        <div class="flex items-center justify-between p-2 bg-rose-50 border border-rose-100 rounded-lg shadow-sm text-rose-700 font-bold">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center font-black">03</div>
                                <span>Déclaration de charge de famille</span>
                            </div>
                            <span class="text-xs font-black">(03)</span>
                        </div>

                        <div class="flex items-center justify-between p-2 bg-emerald-50 border border-emerald-100 rounded-lg shadow-sm text-emerald-700 font-bold">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black">04</div>
                                <span>Certificat non-paiement CNAPS</span>
                            </div>
                            <span class="text-xs font-black">(03)</span>
                        </div>

                        <div class="flex items-center justify-between p-2 bg-emerald-50 border border-emerald-100 rounded-lg shadow-sm text-emerald-700 font-bold">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black">05</div>
                                <span>Acte de naissance des enfants</span>
                            </div>
                            <span class="text-xs font-black">(03/enf)</span>
                        </div>

                        <div class="flex items-center justify-between p-2 bg-rose-50 border border-rose-100 rounded-lg shadow-sm text-rose-700 font-bold">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center font-black">06</div>
                                <span>Certificat de garde et de charge</span>
                            </div>
                            <span class="text-xs font-black">(03)</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-2 bg-rose-50 border border-rose-100 rounded-lg shadow-sm text-rose-700 font-bold">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-rose-600 text-white flex items-center justify-center font-black">07</div>
                                <span>Certificat de vie collectif </span>
                            </div>
                            <span class="text-xs font-black">(03)</span>
                        </div>

                        <div class="flex items-center justify-between p-2 bg-emerald-50 border border-emerald-100 rounded-lg shadow-sm text-emerald-700 font-bold">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black">08</div>
                                <span>Photocopie certifiée situation</span>
                            </div>
                            <span class="text-xs font-black">(03)</span>
                        </div>

                        <div class="flex items-center justify-between p-2 bg-emerald-50 border border-emerald-100 rounded-lg shadow-sm text-emerald-700 font-bold">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black">09</div>
                                <span>Souche BC ou avis de crédit</span>
                            </div>
                            <span class="text-xs font-black">(02)</span>
                        </div>

                        <div class="flex items-center justify-between p-2 bg-emerald-50 border border-emerald-100 rounded-lg shadow-sm text-emerald-700 font-bold">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black">10</div>
                                <span>Photocopie CIN</span>
                            </div>
                            <span class="text-xs font-black">(01)</span>
                        </div>

                        <div class="flex items-center justify-between p-2 bg-emerald-50 border border-emerald-100 rounded-lg shadow-sm text-emerald-700 font-bold">
                            <div class="flex items-center gap-3 text-xs">
                                <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black">11</div>
                                ${pieceSpecifique}
                            </div>
                            <span class="text-xs font-black">(03)</span>
                        </div>                        
                    </div>
                </div>
                <div class="mt-5 p-4 bg-rose-50 border border-rose-100/50 rounded-xl text-slate-800 leading-relaxed" style="font-size: 13px;">
                    <strong>N.B :</strong> Les pièces en <span class="text-red-600 font-bold">rouge</span> sont des pièces à vérifier et signer par la commune.
                </div>
            </div>
        `,
        customClass: {
            popup: 'rounded-xl overflow-hidden',
            htmlContainer: 'm-0 p-0'
        }
    });
};

$('#formCertificat').on('submit', function(e) {
    e.preventDefault(); 
    const formData = $(this).serialize();

    $.post('actions/carriere/save_certificat_data.php', formData, function(res) {
        if(res.success) {
            $('#modalDemandeCertificat').addClass('hidden');
            afficherModalPiecesJointes();
        } else {
            Swal.fire('Erreur', res.message, 'error');
        }
    }, 'json');
});

window.afficherModalPiecesJointes = function() {
    const isMarié = <?= json_encode(($agent['situation_familiale'] ?? '') !== 'Célibataire') ?>;    
    Swal.fire({
        width: '600px',
        showConfirmButton: false,
        html: `
            <div class="overflow-x-hidden text-left">
                <div class="bg-blue-600 p-4 text-white flex items-center justify-between shadow-md">
                    <div class="flex items-center gap-3">
                        <img src="Logo/cnaps.jpeg" alt="CNaPS" class="h-10 w-auto bg-white p-1 rounded shadow-sm">
                        <h2 class="text-md font-black uppercase tracking-tight">
                            PIÈCES À FOURNIR (CERTIFICAT NON PAIEMENT)
                        </h2>
                    </div>
                    <button onclick="Swal.close()" class="group flex items-center justify-center w-9 h-9 rounded-full bg-red-500 hover:bg-red-600 border-2 border-white transition-all shadow-lg">
                        <i class="fas fa-times text-white text-lg"></i>
                    </button>
                </div>

                <div class="flex flex-col gap-3 p-6">
                    <a href="documents/actes/generate_certificat_non_paiement.php?im=<?= $user_im ?>" target="_blank" 
                       class="flex items-center gap-4 py-3 px-5 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-all group shadow-sm">
                        <div class="flex-none w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center font-black text-xs">1</div>
                        <div class="flex-grow">
                            <p class="text-sm font-bold text-blue-900">
                                Demande certificat de non paiement (01)
                                <i class="fas fa-download ml-2 text-blue-500 group-hover:scale-110"></i>
                            </p>
                        </div>
                    </a>

                    <div class="flex items-center gap-4 py-3 px-5 bg-rose-50 border border-rose-200 rounded-lg shadow-sm">
                        <div class="flex-none w-7 h-7 rounded-full bg-rose-600 text-white flex items-center justify-center font-black text-xs">2</div>
                        <p class="text-sm font-bold text-rose-700">Contrat / Avenant / Arrêté certifié (01)</p>
                    </div>

                    <div class="flex items-center gap-4 py-3 px-5 bg-rose-50 border border-rose-200 rounded-lg shadow-sm">
                        <div class="flex-none w-7 h-7 rounded-full bg-rose-600 text-white flex items-center justify-center font-black text-xs">3</div>
                        <p class="text-sm font-bold text-rose-700">Copie acte de naissance enfants (01 chacun)</p>
                    </div>

                    <div class="flex items-center gap-4 py-3 px-5 bg-rose-50 border border-rose-200 rounded-lg shadow-sm">
                        <div class="flex-none w-7 h-7 rounded-full bg-rose-600 text-white flex items-center justify-center font-black text-xs">4</div>
                        <p class="text-sm font-bold text-rose-700">
                            ${isMarié ? "CIN de l'intéressé et du conjoint (01 chacun)" : "CIN de l'intéressé (01)"} 
                        </p>
                    </div>

                    <div class="mt-5 p-4 bg-rose-50 border border-rose-100/50 rounded-xl text-slate-800 leading-relaxed" style="font-size: 13px;">
                        <strong>N.B :</strong> Les pièces en <span class="text-red-600 font-bold">rouge</span> sont des pièces provenant de votre part, à joindre au dossier de la demande.
                    </div>
                </div>
            </div>
        `,
        customClass: {
            popup: 'rounded-xl overflow-hidden',
            htmlContainer: 'm-0 p-0'
        }
    });
};

let currentDownloadUrl = "";
window.openModalDCF = function(targetUrl) {
    currentDownloadUrl = targetUrl;
    const im = "<?= $user_im ?>";
    
    fetch(`get_region_dcf.php?im=${im}`)
        .then(response => response.json())
        .then(data => {
            const inputNum = document.getElementById('input_num_dcf');
            if (inputNum) inputNum.value = data.num_dcf || "";
            
            const modal = document.getElementById('modalDCF');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
            
            setTimeout(() => {
                if (inputNum) inputNum.focus();
            }, 100);
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Erreur', 'Impossible de charger les informations', 'error');
        });
};

window.closeModalDCF = function() {
    const modal = document.getElementById('modalDCF');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
};

window.validerEtImprimer = function() {
    const num = document.getElementById('input_num_dcf').value.trim();
    const date = document.getElementById('input_date_dcf').value;
    const im = "<?= $user_im ?>";

    if (!num || !date) {
        Swal.fire({
            icon: 'warning',
            title: 'Champs obligatoires',
            text: 'Veuillez remplir le numéro et la date de la déclaration.',
        });
        return;
    }

    fetch('actions/personnel/update_dcf_data.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `im=${im}&num_dcf=${encodeURIComponent(num)}&date_dcf=${date}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModalDCF();
            if (currentDownloadUrl) {
                window.open(currentDownloadUrl, '_blank');
            }
        } else {
            Swal.fire('Erreur', data.error || "Erreur lors de la mise à jour", 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Erreur', 'Une erreur est survenue', 'error');
    });
};
</script>
</body>
</html>
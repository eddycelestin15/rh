<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$userIm = $_SESSION['user_im'];
$userRole = trim($_SESSION['user_role'] ?? '');

try {
    $stmtLoc = $pdo->prepare("
        SELECT d.nom_district, r.nom_region 
        FROM utilisateurs u
        /* ON COMPARE LE NOM DU DISTRICT AU LIEU D'AFFECTATION */
        JOIN ref_districts d ON u.code_lieu_affectation = d.nom_district
        JOIN ref_regions r ON d.region_id = r.id
        WHERE u.im = ?
    ");
    $stmtLoc->execute([$userIm]);
    // Aucun district correspondant : on retombe sur « Non definie ».
    $loc = $stmtLoc->fetch() ?: [];
    
    $regionDestination = !empty($loc['nom_region']) ? $loc['nom_region'] : "Non définie";
    $nomDren = "DREN " . $regionDestination;
} catch (Exception $e) {
    $nomDren = "DREN Non définie";
}

$typesAutorises = [];
if ($userRole === 'resp_non_encadre') {
    $typesAutorises = ['renouvellement', 'avenant'];
} elseif ($userRole === 'resp_encadre') {
    $typesAutorises = ['avancement_classe', 'avancement_echelon', 'integration', 'titularisation'];
} else {
    $typesAutorises = ['renouvellement', 'avenant', 'avancement_classe', 'avancement_echelon', 'integration', 'titularisation', 'conge_annuel'];
}

$libellesTypes = [
    'renouvellement' => 'Renouvellement de contrat',
    'avenant' => 'Avenant',
    'avancement_classe' => 'Avancement de classe',
    'avancement_echelon' => 'Avancement d\'échelon',
    'integration' => 'Intégration',
    'titularisation' => 'Titularisation',
    'conge_annuel' => 'Congé annuel'
];
?>

<div class="p-6 bg-white rounded-2xl shadow-sm border border-slate-200" id="table_section">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Envoi de dossiers</h2>
            <p class="text-slate-500 text-sm">Gestion des bordereaux en attente d'envoi à la DREN</p>
        </div>
        
        <div class="flex items-center gap-3 w-full md:w-auto">
            <select id="filterType" class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-sky-500 focus:border-sky-500 block p-2.5">
                <option value="">Tous les types</option>
                <?php foreach ($typesAutorises as $type): ?>
                    <option value="<?php echo htmlspecialchars($libellesTypes[$type]); ?>"><?php echo htmlspecialchars($libellesTypes[$type]); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="relative w-full md:w-64">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-search text-slate-400"></i>
                </div>
                <input type="text" id="customSearch" class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-sky-500 focus:border-sky-500 block w-full pl-10 p-2.5" placeholder="Rechercher...">
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table id="tableEnvoiDossiers" class="w-full text-sm text-left text-slate-600">
            <thead class="text-xs text-white uppercase bg-sky-600 border-b-2 border-sky-700">
                <tr>
                    <th class="px-4 py-3 border-r border-sky-500/30">N°</th>
                    <th class="px-4 py-3 border-r border-sky-500/30">Objet de la demande</th>
                    <th class="px-4 py-3 border-r border-sky-500/30">Destination</th>
                    <th class="px-4 py-3 border-r border-sky-500/30">Numéro bordereau</th>
                    <th class="px-4 py-3 border-r border-sky-500/30">Date d'émission</th>
                    <th class="px-4 py-3 border-r border-sky-500/30">Référence DREN</th>
                    <th class="px-4 py-3">Validation</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $inQuery = implode("','", $typesAutorises);
                $query = "SELECT * FROM archives_bordereaux_dren 
                          WHERE statut_bordereau = 'en_attente' 
                          AND type_bordereau IN ('$inQuery')
                          ORDER BY id DESC";
                $stmt = $pdo->query($query);
                $i = 1;
                while ($row = $stmt->fetch()):
                    $obj = $libellesTypes[$row['type_bordereau']] ?? $row['type_bordereau'];
                    $isSent = !empty($row['date_envoi_bordereau']);
                ?>
                <tr>
                    <td class="text-center font-medium"><?php echo $i++; ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1 text-slate-700 font-semibold">
                            <span><?php echo htmlspecialchars($obj); ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1 text-slate-700 font-semibold">
                            <span><?php echo htmlspecialchars($nomDren); ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1 text-slate-700 font-semibold">
                            <span><?php echo htmlspecialchars($row['numero_complet']); ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($isSent): ?>
                            <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                <div class="flex items-center gap-1 text-slate-700 font-semibold">
                                    <span><?php echo date('d/m/Y', strtotime($row['date_envoi_bordereau'])); ?></span>
                                </div>                                
                                <span class="text-slate-400 font-normal">à</span>                                
                                <div class="flex items-center gap-1 text-slate-700 font-semibold">
                                    <span><?php echo date('H:i', strtotime($row['date_envoi_bordereau'])); ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex justify-center">
                                <button onclick="confirmerEnvoiDren(<?php echo $row['id']; ?>)" 
                                        class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs transition-all shadow-sm flex items-center gap-2">
                                    <i class="fas fa-paper-plane"></i> Envoyer à la DREN
                                </button>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php 
                        if ($isSent) {
                            if (!empty($row['reference_destination'])) {
                                echo '<span class="text-slate-700 font-semibold">N° '.htmlspecialchars($row['reference_destination']).' du '.date('d/m/Y', strtotime($row['date_reference_destination'])).'</span>';
                            } else {
                                echo '<span class="text-amber-600 italic text-[13px] font-medium bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200">En attente de référence</span>';
                            }
                        } else {
                            echo '<span class="text-slate-300 font-mono">---</span>';
                        }
                        ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php 
                        if ($isSent) {
                            $stmtCheck = $pdo->prepare("
                                SELECT 
                                    SUM(CASE WHEN statut_dren = 'rejete' THEN 1 ELSE 0 END) as total_rejets,
                                    SUM(CASE WHEN statut_dren = 'en_attente' THEN 1 ELSE 0 END) as total_attente,
                                    COUNT(*) as total_agents
                                FROM suivi_agents_bordereau 
                                WHERE bordereau_cisco_dren = ?
                            ");
                            $stmtCheck->execute([$row['numero_complet']]);
                            $stats = $stmtCheck->fetch();

                            if ($stats && $stats['total_agents'] > 0) {
                                if ($stats['total_rejets'] > 0) {
                                    echo '<button onclick="voirAnomalies(' . $row['id'] . ')" class="flex items-center gap-2 bg-rose-50 text-rose-600 border border-rose-200 px-3 py-1.5 rounded-lg font-bold text-[11px] hover:bg-rose-100 transition-colors mx-auto shadow-sm">
                                            <i class="fas fa-exclamation-circle text-sm"></i>
                                            Anomalie trouvée
                                          </button>';
                                } elseif ($stats['total_attente'] == 0) {
                                    echo '<div class="flex flex-col items-center justify-center">
                                            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full font-bold text-[10px] flex items-center gap-1 uppercase tracking-wider shadow-sm">
                                                <i class="fas fa-check-double text-xs"></i> Traité avec succès
                                            </span>
                                          </div>';
                                } else {
                                    echo '<span class="text-slate-500 font-medium bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg italic text-[13px] flex items-center justify-center gap-2 max-w-max mx-auto shadow-sm">
                                            <i class="fas fa-clock fa-spin text-amber-500"></i> Traitement en cours
                                          </span>';
                                }
                            } else {
                                echo '<span class="text-slate-400 italic text-[11px]">En cours de traitement...</span>';
                            }
                        } else {
                            echo '<span class="text-slate-300 font-mono">---</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalAnomalies" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 hidden items-center justify-center p-4 transition-all duration-300">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-6xl max-h-[85vh] overflow-hidden flex flex-col transform scale-95 transition-transform duration-300">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/80 backdrop-blur-sm">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-rose-100 flex items-center justify-center text-rose-600">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <span>Suivi du traitement des agents (Bordereau)</span>
            </h3>
            <button onclick="$('#modalAnomalies').addClass('hidden').removeClass('flex')" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-700 flex items-center justify-center transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 bg-slate-50/30">
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full border-collapse text-left text-sm" id="tableModalAnomalies">
                    <thead>
                        <tr class="bg-sky-600 text-white text-[12px] uppercase tracking-wider">
                            <th class="p-3 font-semibold text-center w-12">N°</th>
                            <th class="p-3 font-semibold w-24">IM</th>
                            <th class="p-3 font-semibold">Nom & Prénoms</th>
                            <th class="p-3 font-semibold">Structure / Lieu de service</th>
                            <th class="p-3 font-semibold">Corps & Grade</th>
                            <th class="p-3 font-semibold text-center w-28">Statut DREN</th>
                            <th class="p-3 font-semibold text-center w-36">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px] text-slate-600 divide-y divide-slate-100">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modalMotif" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[60] hidden items-center justify-center p-4 transition-all duration-300">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 transform scale-95 transition-transform duration-300 border border-slate-100">
        <div class="flex items-center gap-3 mb-4 text-rose-600">
            <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center text-rose-600 text-lg">
                <i class="fas fa-info-circle"></i>
            </div>
            <h4 class="font-bold text-lg text-slate-800">Motif du rejet (DREN)</h4>
        </div>
        <div class="bg-slate-50 p-4 rounded-xl border-l-4 border-rose-500 mb-6">
            <p id="textMotif" class="text-slate-600 text-sm leading-relaxed italic whitespace-pre-wrap">
                </p>
        </div>
        <button onclick="$('#modalMotif').addClass('hidden').removeClass('flex')" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-semibold py-3 rounded-xl transition-all shadow-md hover:shadow-lg active:scale-[0.98]">
            J'ai compris
        </button>
    </div>
</div>

<script>
function initDataTable() {
    if (typeof $ === 'undefined') {
        setTimeout(initDataTable, 100);
        return;
    }

    if ($.fn.DataTable.isDataTable('#tableEnvoiDossiers')) {
        $('#tableEnvoiDossiers').DataTable().destroy();
    }

    var table = $('#tableEnvoiDossiers').DataTable({
        dom: 'rt<"flex justify-between items-center mt-4"ip>',
        pageLength: 10,
        ordering: false,
        language: {
            "sEmptyTable": `
                <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                    <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mb-4 border border-slate-100">
                        <i class="fas fa-folder-open text-3xl opacity-60"></i>
                    </div>
                    <span class="text-base font-semibold text-slate-700">Aucun bordereau en attente d'envoi</span>
                    <p class="text-xs text-slate-400 mt-1">Tous les dossiers ont été correctement transmis à la DREN.</p>
                </div>
            `,
            "sInfo": "Affichage de _START_ à _END_ sur _TOTAL_ lignes",
            "sInfoEmpty": "Affichage de 0 à 0 sur 0 ligne",
            "sInfoFiltered": "(filtré depuis _MAX_ lignes)",
            "sZeroRecords": "Aucun enregistrement correspondant trouvé",
            "oPaginate": {
                "sNext": '<i class="fas fa-chevron-right text-xs"></i>',
                "sPrevious": '<i class="fas fa-chevron-left text-xs"></i>'
            }
        }
    });

    $('#customSearch').off().on('keyup', function() {
        table.search(this.value).draw();
    });

    $('#filterType').off().on('change', function() {
        var val = $.fn.dataTable.util.escapeRegex($(this).val());
        table.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
    });
}

// Initialisation globale
$(document).ready(function() {
    initDataTable();
});

function confirmerEnvoiDren(id) {
    Swal.fire({
        title: 'Confirmer l\'envoi ?',
        text: "Le bordereau sera officiellement marqué comme transmis à la DREN.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, envoyer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#0284c7',
        cancelButtonColor: '#64748b',
        borderRadius: '1rem'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'actions/dossiers/update_envoi_dren.php',
                method: 'POST',
                data: { id: id },
                success: function(response) {
                    loadPage('pages/dossiers/envoi_dossiers.php', 'Envoi de dossiers');
                }
            });
        }
    });
}

function voirAnomalies(idBordereau) {
    $.ajax({
        url: 'api/dossiers/api_reference.php',
        method: 'POST',
        data: { 
            action: 'get_agents_bordereau', 
            id: idBordereau, 
            destination: 'dren'
        },
        dataType: 'json',
        success: function(res) {
            if(res.success) {
                let html = "";
                res.agents.forEach((agent, index) => {
                    let localite = "DREN " + (agent.nom_region || "");
                    if (agent.nom_etablissement) {
                        localite = (agent.nom_zap ? "ZAP " + agent.nom_zap + " - " : "") + agent.nom_etablissement;
                    } else if (agent.nom_district) {
                        localite = "CISCO " + agent.nom_district;
                    }
                    
                    let statutHtml = "";
                    let actionHtml = "";

                    if (agent.statut_dren === 'rejete') {
                        statutHtml = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-rose-50 text-rose-600 font-bold text-[11px] border border-rose-100"><span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>REJETÉ</span>`;
                        
                        // Sécurisation de la chaîne pour éviter de briser le onclick avec des guillemets
                        let motifNettoye = (agent.motif_dren || "Aucun motif spécifié")
                            .replace(/\\/g, '\\\\')
                            .replace(/'/g, "\\'")
                            .replace(/"/g, '&quot;');
                            
                        actionHtml = `
                            <button onclick="ouvrirMotif('${motifNettoye}')" class="bg-sky-50 hover:bg-sky-100 text-sky-700 px-3 py-1.5 rounded-lg font-bold flex items-center gap-1.5 mx-auto transition-all border border-sky-100 text-[11px]">
                                <i class="fas fa-eye text-xs"></i> Voir motif
                            </button>`;
                    } else {
                        statutHtml = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 font-bold text-[11px] border border-emerald-100"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>VALIDÉ</span>`;
                        actionHtml = `<span class="text-slate-400 font-medium italic text-[11px]">En attente de visa</span>`;
                    }

                    // Formatage du nom sans forcer la majuscule sur les prénoms
                    let agentNom = (agent.nom || "").toUpperCase();
                    let agentPrenoms = agent.prenom || "";

                    html += `
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-3 font-semibold text-slate-500 text-center">${index + 1}</td>
                        <td class="p-3 font-mono font-bold text-slate-700">${agent.im_agent}</td>
                        <td class="p-3 font-semibold text-slate-800">${agentNom} ${agentPrenoms}</td>
                        <td class="p-3 text-slate-600 font-medium">${localite}</td>
                        <td class="p-3 text-slate-600">${agent.corps_actuel} <span class="text-slate-400 font-normal">/</span> ${agent.grade_actuel}</td>
                        <td class="p-3 text-center">${statutHtml}</td>
                        <td class="p-3 text-center">${actionHtml}</td>
                    </tr>`;
                });

                $('#tableModalAnomalies tbody').html(html);
                $('#modalAnomalies').removeClass('hidden').addClass('flex');
            }
        }
    });
}

function ouvrirMotif(motif) {
    $('#textMotif').text(motif);
    $('#modalMotif').removeClass('hidden').addClass('flex');
}
</script>

<style>
/* Structure globale du tableau */
#tableEnvoiDossiers {
    border: 1px solid rgba(2, 132, 199, 0.2) !important;
    border-collapse: collapse !important; 
    width: 100%;
    border-radius: 12px;
    overflow: hidden;
    font-size: 0.85rem;
}

/* En-tête */
#tableEnvoiDossiers thead th {
    background-color: #0284c7 !important;
    color: white !important;
    font-weight: 600;
    padding: 12px 10px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

/* Corps du tableau */
#tableEnvoiDossiers tbody td {
    padding: 10px 12px;
    border: 1px solid rgba(2, 132, 199, 0.1) !important; 
    color: #334155;
    vertical-align: middle;
}

#tableEnvoiDossiers tbody tr:hover td {
    background-color: rgba(240, 249, 255, 0.5) !important;
    border-color: rgba(2, 132, 199, 0.25) !important;
    transition: all 0.2s ease;
}

/* Pagination */
.dataTables_paginate {
    margin-top: 15px;
    display: flex;
    justify-content: flex-end;
    gap: 2px;
}

.dataTables_paginate .paginate_button {
    padding: 0.35rem 0.75rem !important;
    border-radius: 0.5rem !important;
    border: 1px solid rgba(2, 132, 199, 0.15) !important;
    cursor: pointer !important;
    background: white !important;
    color: #0284c7 !important;
    font-size: 0.75rem !important;
    font-weight: 600;
}

.dataTables_paginate .paginate_button.current {
    background-color: #0284c7 !important;
    color: white !important;
    border-color: #0284c7 !important;
}

.dataTables_paginate .paginate_button:hover:not(.current) {
    background-color: #f0f9ff !important;
    color: #0369a1 !important;
}

.dataTables_empty {
    padding: 0px !important;
    background-color: #ffffff !important;
}

#table_section {
    margin: 15px !important;
    padding: 24px !important;
}

.overflow-x-auto {
    padding: 4px;
}
</style>
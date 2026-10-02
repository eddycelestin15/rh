<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// affectations.php
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/config.php'; 

$agent_im = $_SESSION['user_im'] ?? '';

try {
    $stmt = $pdo->prepare("SELECT * FROM personnel_affectations WHERE im = ? ORDER BY date_enregistrement DESC");
    $stmt->execute([$agent_im]);
    $affectations = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Affectations</title>
    <!-- Feuille de style personnalisée DataTables & Modales (Même style que avancements) -->
    <link rel="stylesheet" href="assets/css/datatables-custom.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen">

    <div class="container mx-auto px-4 py-8 max-w-7xl">
        
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
                    <i class="fa-solid fa-route text-indigo-600"></i> Mouvements & Affectations
                </h1>
                <p class="text-sm text-slate-500 mt-1">Gestion des affectations professionnelles de l'agent</p>
            </div>
            <div>
                <button onclick="toggleModal('modalHistorique')" 
                    class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium px-5 py-2.5 rounded-lg shadow-sm hover:shadow-md transition-all cursor-pointer flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left"></i> Afficher l'historique
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-6 mb-8">
            <div class="flex items-center gap-2 mb-6 border-b border-slate-100 pb-3">
                <i class="fa-solid fa-circle-plus text-lg text-indigo-600"></i>
                <h2 class="text-xl font-semibold text-slate-800">Nouvelle Affectation</h2>
            </div>
            
            <form id="formAffectation" class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <input type="hidden" name="action" value="ajouter">
                
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">N° Matricule (IM)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="text" name="im" value="<?= htmlspecialchars($agent_im) ?>" readonly 
                            class="w-full pl-10 pr-3 py-2.5 border border-slate-200 rounded-lg bg-slate-100 text-slate-500 text-sm font-medium cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Type d'acte <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-file-signature"></i>
                        </span>
                        <select name="type_acte" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm appearance-none bg-white">
                            <option value="Décision">Décision</option>
                            <option value="Arrêté">Arrêté</option>
                            <option value="Note de service">Note de service</option>
                            <option value="Contrat">Contrat</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">N° de l'acte <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-hashtag"></i>
                        </span>
                        <input type="text" name="num_acte" required placeholder="Ex: 124/DREN/CISCO" class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Date de l'acte <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-calendar-days"></i>
                        </span>
                        <input type="date" name="date_acte" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Fonction occupée <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-briefcase"></i>
                        </span>
                        <input type="text" name="fonction" required placeholder="Ex: Enseignant..." class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Lieu d'affectation</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-building-flag"></i>
                        </span>
                        <input type="text" name="lieu_affectation" placeholder="Ex: DREN Vatovavy" class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Localité / Ville</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-map-location-dot"></i>
                        </span>
                        <input type="text" name="localite" placeholder="Ex: Mananjary" class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>
                </div>

                <div class="flex items-end justify-end md:col-span-1">
                    <button type="submit" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm px-6 py-2.5 rounded-lg transition-all cursor-pointer flex items-center justify-center gap-2">
                        <i class="fa-solid fa-check"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Historique (Harmonisé avec Avancements) -->
    <div id="modalHistorique" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs items-center justify-center p-4 z-50 animate-fade-in">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-5xl max-h-[85vh] flex flex-col overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-lg text-indigo-600"></i>
                    <h3 class="text-lg font-bold text-slate-800">Historique des Affectations — IM : <?= htmlspecialchars($agent_im) ?></h3>
                </div>
                <button onclick="toggleModal('modalHistorique')" class="text-slate-400 hover:text-slate-600 cursor-pointer p-1 rounded-lg hover:bg-slate-100 transition-all">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Corps du Modal (Tableau) -->
            <div class="p-6 overflow-y-auto flex-1">
                <div class="overflow-x-auto rounded-lg">
                    <table id="tableAffectations" class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white uppercase text-[11px] font-bold tracking-wider border-b border-blue-700">
                                <th class="p-3">Type Acte</th>
                                <th class="p-3">N° Acte</th>
                                <th class="p-3">Date Acte</th>
                                <th class="p-3">Fonction</th>
                                <th class="p-3">Lieu Affectation</th>
                                <th class="p-3">Localité</th>
                                <th class="p-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($affectations as $row): ?>
                                <tr id="row_<?= $row['id'] ?>" class="hover:bg-slate-50 transition-colors">
                                    <td class="p-3"><?= htmlspecialchars($row['type_acte']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($row['num_acte']) ?></td>
                                    <td class="p-3"><?= date('d/m/Y', strtotime($row['date_acte'])) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($row['fonction']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($row['lieu_affectation'] ?? '-') ?></td>
                                    <td class="p-3"><?= htmlspecialchars($row['localite'] ?? '-') ?></td>
                                    <td class="p-3 text-center whitespace-nowrap">
                                        <button onclick="ouvrirEditeur(<?= htmlspecialchars(json_encode($row)) ?>)" class="text-amber-600 hover:text-amber-700 p-1.5 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-md transition-all cursor-pointer mr-1" title="Modifier">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button onclick="supprimerAffectation(<?= $row['id'] ?>)" class="text-red-600 hover:text-red-700 p-1.5 bg-red-50 hover:bg-red-100 border border-red-200 rounded-md transition-all cursor-pointer" title="Supprimer">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modifier -->
    <div id="modalModifier" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-lg text-amber-500"></i>
                    <h3 class="text-lg font-bold text-slate-800">Modifier l'Affectation</h3>
                </div>
                <button onclick="toggleModal('modalModifier')" class="text-slate-400 hover:text-slate-600 cursor-pointer p-1 rounded-lg hover:bg-slate-100 transition-all">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <form id="formModifierAffectation" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="id" id="edit_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Type d'acte</label>
                    <select name="type_acte" id="edit_type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500">
                        <option value="Décision">Décision</option>
                        <option value="Arrêté">Arrêté</option>
                        <option value="Note de service">Note de service</option>
                        <option value="Contrat">Contrat</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">N° de l'acte</label>
                    <input type="text" name="num_acte" id="edit_num" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Date de l'acte</label>
                    <input type="date" name="date_acte" id="edit_date" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Fonction occupée</label>
                    <input type="text" name="fonction" id="edit_fonction" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Lieu d'affectation</label>
                    <input type="text" name="lieu_affectation" id="edit_lieu" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Localité</label>
                    <input type="text" name="localite" id="edit_localite" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2 flex justify-end gap-2 border-t border-slate-100 pt-4 mt-2">
                    <button type="button" onclick="toggleModal('modalModifier')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-sm px-4 py-2 rounded-lg cursor-pointer transition-all">Annuler</button>
                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-medium text-sm px-5 py-2 rounded-lg cursor-pointer transition-all flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    window.toggleModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.toggle('hidden');
            modal.classList.toggle('modal-active');
        }
    };

    window.ouvrirEditeur = function(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_type').value = data.type_acte;
        document.getElementById('edit_num').value = data.num_acte;
        document.getElementById('edit_date').value = data.date_acte;
        document.getElementById('edit_fonction').value = data.fonction;
        document.getElementById('edit_lieu').value = data.lieu_affectation || '';
        document.getElementById('edit_localite').value = data.localite || '';
        
        window.toggleModal('modalHistorique');
        window.toggleModal('modalModifier');
    };

    window.supprimerAffectation = function(id) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette affectation sera définitivement supprimée de l'historique !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Oui, supprimer !',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'supprimer');
                formData.append('id', id);

                fetch('api/carriere/api_affectation.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Supprimé !', text: data.message })
                        .then(() => { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
                    }
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Erreur', text: 'Impossible de joindre l\'API.' }));
            }
        });
    };

    $(document).ready(function() {
        if ($('#tableAffectations').length) {
            $('#tableAffectations').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/fr-FR.json' },
                pageLength: 5,
                dom: '<"top"f>rt<"bottom"p><"clear">', 
                order: [[2, 'desc']], 
                columnDefs: [
                    { type: 'date-eu', targets: 2 } 
                ],
                initComplete: function() {
                    $('#modalHistorique .dt-search input, #modalHistorique .dataTables_filter input')
                        .attr('placeholder', 'Rechercher...');
                }
            });
        }

        const formAjout = document.getElementById('formAffectation');
        if (formAjout) {
            formAjout.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(formAjout);
                fetch('api/carriere/api_affectation.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Succès !', text: data.message })
                        .then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
                    }
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur réseau.' }));
            });
        }

        const formModif = document.getElementById('formModifierAffectation');
        if (formModif) {
            formModif.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(formModif);
                fetch('api/carriere/api_affectation.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Mis à jour !', text: data.message })
                        .then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
                    }
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Erreur', text: 'Erreur réseau.' }));
            });
        }
    });
</script>
</body>
</html>
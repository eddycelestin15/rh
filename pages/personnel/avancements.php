<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
// avancements.php
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/config.php'; 

// Récupération des paramètres de l'URL pour la liaison automatique
$url_im = $_GET['im'] ?? ($_SESSION['user_im'] ?? '');
$url_type_acte = $_GET['type_acte'] ?? '';
$url_num_acte = $_GET['num_acte'] ?? '';
$url_date_acte = $_GET['date_acte'] ?? '';

try {
    // 1. Récupération de l'historique de l'agent
    $stmt = $pdo->prepare("SELECT * FROM personnel_avancements WHERE im = ? ORDER BY av_date_effet ASC");
    $stmt->execute([$url_im]);
    $avancements = $stmt->fetchAll();

    // 2. Récupération des corps pour la liste déroulante
    $stmtCorps = $pdo->query("SELECT id, libelle_corps FROM ref_corps ORDER BY libelle_corps ASC");
    $listeCorps = $stmtCorps->fetchAll();

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
    <title>Gestion des Avancements</title>
    <!-- Feuille de style personnalisée DataTables & Modales -->
    <link rel="stylesheet" href="assets/css/datatables-custom.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen">

    <div class="container mx-auto px-4 py-8 max-w-7xl">
        
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
                    <i class="fa-solid fa-arrow-up-trending-line text-indigo-600"></i> Avancements & Grades
                </h1>
                <p class="text-sm text-slate-500 mt-1">Gestion de la progression de carrière de l'agent</p>
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
                <h2 class="text-xl font-semibold text-slate-800">Nouvel Avancement</h2>
            </div>
            
            <form id="formAvancement" class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <input type="hidden" name="action" value="ajouter">
                
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">N° Matricule (IM)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="text" name="im" value="<?= htmlspecialchars($url_im) ?>" readonly 
                            class="w-full pl-10 pr-3 py-2.5 border border-slate-200 rounded-lg bg-slate-100 text-slate-500 text-sm font-medium cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Type d'acte <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-file-signature"></i>
                        </span>
                        <select name="av_type_acte" id="av_type_acte" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm appearance-none bg-white">
                            <option value="">-- Sélectionner --</option>
                            <option value="Contrat" <?= $url_type_acte === 'Contrat' ? 'selected' : '' ?>>Contrat</option>
                            <option value="Avenant" <?= $url_type_acte === 'Avenant' ? 'selected' : '' ?>>Avenant</option>
                            <option value="Arrêté" <?= strtolower($url_type_acte) === 'arrêté' || $url_type_acte === 'Arrêté' ? 'selected' : '' ?>>Arrêté</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Type d'avancement <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </span>
                        <select name="av_type_avancement" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm appearance-none bg-white">
                            <option value="Stagiaire">Stagiaire</option>
                            <option value="Echelon" selected>Echelon</option>
                            <option value="Classe">Classe</option>
                            <option value="Intégration">Intégration</option>
                            <option value="Titularisation">Titularisation</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">N° de l'acte <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-hashtag"></i>
                        </span>
                        <input type="text" name="av_acte_no" value="<?= htmlspecialchars($url_num_acte) ?>" required placeholder="Ex: 4521/MEN/SG/DRH" class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Date de l'acte <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-calendar-day"></i>
                        </span>
                        <input type="date" name="av_acte_date" value="<?= htmlspecialchars($url_date_acte) ?>" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Date d'effet <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-calendar-check"></i>
                        </span>
                        <input type="date" name="av_date_effet" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Corps <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-users-gear"></i>
                        </span>
                        <select name="av_corps" id="av_corps" required class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm appearance-none bg-white">
                            <option value="">-- Choisir corps --</option>
                            <?php foreach ($listeCorps as $corps): ?>
                                <option value="<?= htmlspecialchars($corps['libelle_corps']) ?>" data-id="<?= $corps['id'] ?>">
                                    <?= htmlspecialchars($corps['libelle_corps']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Grade<span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-award"></i>
                        </span>
                        <select name="av_grade" id="av_grade" required disabled class="w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm appearance-none bg-white">
                            <option value="">-- Choisir grade --</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Indice</label>
                    <div class="relative">
                        <input type="text" name="av_indice" id="av_indice" readonly 
                            class="w-full pl-10 pr-3 py-2.5 border border-slate-200 rounded-lg bg-slate-100 text-slate-700 font-bold font-mono text-sm cursor-not-allowed">
                    </div>
                </div>

                <div class="flex items-end justify-end md:col-span-3">
                    <button type="submit" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm px-6 py-2.5 rounded-lg transition-all cursor-pointer flex items-center justify-center gap-2">
                        <i class="fa-solid fa-check"></i> Enregistrer l'avancement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Historique -->
    <div id="modalHistorique" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs items-center justify-center p-4 z-50 animate-fade-in">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-5xl max-h-[85vh] flex flex-col overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-lg text-indigo-600"></i>
                    <h3 class="text-lg font-bold text-slate-800">Historique des Avancements — IM : <?= htmlspecialchars($url_im) ?></h3>
                </div>
                <button onclick="toggleModal('modalHistorique')" class="text-slate-400 hover:text-slate-600 cursor-pointer p-1 rounded-lg hover:bg-slate-100 transition-all">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Corps du Modal (Tableau) -->
            <div class="p-6 overflow-y-auto flex-1">
                <div class="overflow-x-auto rounded-lg">
                    <table id="tableAvancements" class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white uppercase text-[11px] font-bold tracking-wider border-b border-blue-700">
                                <th class="p-3">Type Acte</th>
                                <th class="p-3">N° Acte</th>
                                <th class="p-3">Date Acte</th>
                                <th class="p-3">Corps</th>
                                <th class="p-3">Grade</th>
                                <th class="p-3">Indice</th>                                
                                <th class="p-3">Date Effet</th>
                                <th class="p-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(!empty($avancements)): ?>
                                <?php foreach($avancements as $av): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="p-3"><?= htmlspecialchars($av['av_type_acte']) ?></td>
                                        <td class="p-3"><?= htmlspecialchars($av['av_acte_no']) ?></td>
                                        <td class="p-3"><?= date('d/m/Y', strtotime($av['av_acte_date'])) ?></td>
                                        <td class="p-3"><?= htmlspecialchars($av['av_corps']) ?></td>
                                        <td class="p-3"><?= htmlspecialchars($av['av_grade']) ?></td>
                                        <td class="p-3"><?= htmlspecialchars($av['av_indice']) ?></td>
                                        <td class="p-3"><?= date('d/m/Y', strtotime($av['av_date_effet'])) ?></td>
                                        <td class="p-3 text-center whitespace-nowrap">
                                            <button onclick="ouvrirModifier(<?= $av['id'] ?>)" class="text-amber-600 hover:text-amber-700 p-1.5 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-md transition-all cursor-pointer mr-1" title="Modifier">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button onclick="supprimerAvancement(<?= $av['id'] ?>)" class="text-red-600 hover:text-red-700 p-1.5 bg-red-50 hover:bg-red-100 border border-red-200 rounded-md transition-all cursor-pointer" title="Supprimer">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-slate-400">Aucun historique d'avancement enregistré pour cet agent.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Modifier -->
    <div id="modalModifier" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-3xl overflow-hidden">
            
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-lg text-amber-500"></i>
                    <h3 class="text-lg font-bold text-slate-800">Modifier l'Avancement</h3>
                </div>
                <button onclick="toggleModal('modalModifier')" class="text-slate-400 hover:text-slate-600 cursor-pointer p-1 rounded-lg hover:bg-slate-100 transition-all">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Formulaire de modification -->
            <form id="formModifierAvancement" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="im" value="<?= htmlspecialchars($url_im) ?>">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Type d'acte</label>
                    <select name="av_type_acte" id="edit_type_acte" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500">
                        <option value="Contrat">Contrat</option>
                        <option value="Avenant">Avenant</option>
                        <option value="Arrêté">Arrêté</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Type d'avancement</label>
                    <select name="av_type_avancement" id="edit_type_avancement" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500">
                        <option value="Stagiaire">Stagiaire</option>
                        <option value="Echelon">Echelon</option>
                        <option value="Classe">Classe</option>
                        <option value="Intégration">Intégration</option>
                        <option value="Titularisation">Titularisation</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">N° de l'acte</label>
                    <input type="text" name="av_acte_no" id="edit_acte_no" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Date de l'acte</label>
                    <input type="date" name="av_acte_date" id="edit_acte_date" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Date d'effet</label>
                    <input type="date" name="av_date_effet" id="edit_date_effet" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Corps</label>
                    <select name="av_corps" id="edit_corps" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($listeCorps as $corps): ?>
                            <option value="<?= htmlspecialchars($corps['libelle_corps']) ?>" data-id="<?= $corps['id'] ?>">
                                <?= htmlspecialchars($corps['libelle_corps']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Grade / Classe</label>
                    <select name="av_grade" id="edit_grade" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Choisissez d'abord le corps & l'acte --</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Nouvel Indice</label>
                    <input type="text" name="av_indice" id="edit_indice" readonly class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-100 text-slate-700 font-bold font-mono text-sm cursor-not-allowed">
                </div>

                <div class="flex justify-end gap-2 md:col-span-2 border-t border-slate-100 pt-4 mt-2">
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

        // --- DYNAMISME DES GRADES ET INDICES ---
        function actualiserGrades(selectCorpsId, selectActeId, selectGradeId, callbackVal) {
            const corpsSelect = document.getElementById(selectCorpsId);
            const acteSelect = document.getElementById(selectActeId);
            const gradeSelect = document.getElementById(selectGradeId);
            
            const selectedOption = corpsSelect.options[corpsSelect.selectedIndex];
            const corpsDbId = selectedOption ? selectedOption.getAttribute('data-id') : null;
            const typeActe = acteSelect.value;

            if (!corpsDbId || !typeActe) {
                gradeSelect.innerHTML = '<option value="">-- Choisissez d\'abord le corps & l\'acte --</option>';
                gradeSelect.disabled = true;
                return;
            }

            const formData = new FormData();
            formData.append('action', 'charger_grades');
            formData.append('corps_id', corpsDbId);
            formData.append('type_acte', typeActe);

            fetch('api/carriere/api_avancement.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(grades => {
                gradeSelect.innerHTML = '<option value="">-- Sélectionner un Grade --</option>';
                if(grades && grades.length > 0) {
                    grades.forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g.libelle_grade;
                        opt.setAttribute('data-grade-id', g.id);
                        opt.textContent = g.libelle_grade;
                        gradeSelect.appendChild(opt);
                    });
                    gradeSelect.disabled = false;
                    if(callbackVal) {
                        gradeSelect.value = callbackVal;
                        gradeSelect.dispatchEvent(new Event('change'));
                    }
                } else {
                    gradeSelect.innerHTML = '<option value="">Aucun grade disponible pour ces critères</option>';
                    gradeSelect.disabled = true;
                }
            }).catch(err => console.error("Erreur au chargement des grades:", err));
        }

        function actualiserIndice(selectCorpsId, selectGradeId, inputIndiceId) {
            const corpsSelect = document.getElementById(selectCorpsId);
            const gradeSelect = document.getElementById(selectGradeId);
            const indiceInput = document.getElementById(inputIndiceId);

            const optCorps = corpsSelect.options[corpsSelect.selectedIndex];
            const optGrade = gradeSelect.options[gradeSelect.selectedIndex];

            const corpsDbId = optCorps ? optCorps.getAttribute('data-id') : null;
            const gradeDbId = optGrade ? optGrade.getAttribute('data-grade-id') : null;

            if (!corpsDbId || !gradeDbId) {
                indiceInput.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('action', 'calculer_indice');
            formData.append('corps_id', corpsDbId);
            formData.append('grade_type_id', gradeDbId);

            fetch('api/carriere/api_avancement.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                indiceInput.value = data.indice || 'Non défini';
            }).catch(err => console.error("Erreur au calcul de l'indice:", err));
        }

        // Événements
        document.getElementById('av_corps').addEventListener('change', () => actualiserGrades('av_corps', 'av_type_acte', 'av_grade'));
        document.getElementById('av_type_acte').addEventListener('change', () => actualiserGrades('av_corps', 'av_type_acte', 'av_grade'));
        document.getElementById('av_grade').addEventListener('change', () => actualiserIndice('av_corps', 'av_grade', 'av_indice'));

        $(document).ready(function() {
            // Initialisation DataTables si la table historique existe
            if ($('#tableAvancements').length) {
                $('#tableAvancements').DataTable({
                    language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/fr-FR.json' },
                    pageLength: 5,
                    dom: '<"top"f>rt<"bottom"p><"clear">', 
                    order: [[6, 'asc']], 
                    columnDefs: [
                        { type: 'date-eu', targets: 6 } 
                    ],
                    initComplete: function() {
                        $('#modalHistorique .dt-search input, #modalHistorique .dataTables_filter input')
                            .attr('placeholder', 'Rechercher...');
                    }
                });
            }

            // Gestion de la soumission AJAX du formulaire d'ajout
            $('#formAvancement').on('submit', function(e) {
                e.preventDefault();
                fetch('api/carriere/api_avancement.php', { method: 'POST', body: new FormData(this) })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Succès !', text: data.message }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
                    }
                });
            });
        });

        // Événements pour le formulaire de modification (liaison dynamique)
        document.getElementById('edit_corps').addEventListener('change', () => actualiserGrades('edit_corps', 'edit_type_acte', 'edit_grade'));
        document.getElementById('edit_type_acte').addEventListener('change', () => actualiserGrades('edit_corps', 'edit_type_acte', 'edit_grade'));
        document.getElementById('edit_grade').addEventListener('change', () => actualiserIndice('edit_corps', 'edit_grade', 'edit_indice'));

        // Fonction pour charger et ouvrir le modal de modification
        window.ouvrirModifier = function(id) {
            const formData = new FormData();
            formData.append('action', 'recuperer_un');
            formData.append('id', id);

            fetch('api/carriere/api_avancement.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data) {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_type_acte').value = data.av_type_acte;
                    document.getElementById('edit_type_avancement').value = data.av_type_avancement;
                    document.getElementById('edit_acte_no').value = data.av_acte_no;
                    document.getElementById('edit_acte_date').value = data.av_acte_date;
                    document.getElementById('edit_date_effet').value = data.av_date_effet;
                    
                    const editCorpsSelect = document.getElementById('edit_corps');
                    editCorpsSelect.value = data.av_corps;

                    toggleModal('modalHistorique');
                    toggleModal('modalModifier');

                    actualiserGrades('edit_corps', 'edit_type_acte', 'edit_grade', data.av_grade);
                } else {
                    Swal.fire({ icon: 'error', title: 'Erreur', text: 'Impossible de récupérer les données de cet avancement.' });
                }
            })
            .catch(err => {
                console.error("Erreur d'ouverture du modal:", err);
                Swal.fire({ icon: 'error', title: 'Erreur', text: 'Une erreur réseau est survenue.' });
            });
        };

        // Envoi AJAX de la modification
        $('#formModifierAvancement').on('submit', function(e) {
            e.preventDefault();
            fetch('api/carriere/api_avancement.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    toggleModal('modalModifier');
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'Modifié !', 
                        text: data.message,
                        confirmButtonColor: '#3b82f6'
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
                }
            })
            .catch(err => {
                console.error("Erreur lors de la mise à jour :", err);
                Swal.fire({ icon: 'error', title: 'Erreur', text: 'Une erreur réseau est survenue.' });
            });
        });

        // Fonction AJAX de suppression d'une ligne d'avancement
        window.supprimerAvancement = function(id) {
            Swal.fire({
                title: 'Êtes-vous sûr ?',
                text: "Cette action supprimera définitivement cet avancement !",
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

                    fetch('api/carriere/api_avancement.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 'success') {
                            Swal.fire('Supprimé !', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Erreur', data.message, 'error');
                        }
                    });
                }
            });
        };
    </script>
</body>
</html>
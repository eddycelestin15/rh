<?php
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/check_session.php';

    $user_im = $_SESSION['user_im'];

    try {
        $stmt = $pdo->prepare("SELECT niveau, code_lieu_affectation, role_specifique FROM utilisateurs WHERE im = ?");
        $stmt->execute([$user_im]);
        $user = $stmt->fetch();

        $niv  = $user['niveau']; 
        $lieu = $user['code_lieu_affectation']; 
        $role = $user['role_specifique'];

        if ($role === 'resp_encadre') {
            $statutsCibles = ['Fonctionnaire'];
            $labelTitre = "Fonctionnaires";
        } else {
            $statutsCibles = ['Contractuel EFA'];
            $labelTitre = "Contractuel EFA";
        }

        $placeholders = implode(',', array_fill(0, count($statutsCibles), '?'));
        $params = $statutsCibles; 
        
        $where = " WHERE sa.statut_actuel IN ($placeholders) "; 

        if ($role === 'resp_personnel_crfrp') {
            $where .= " AND pa.type_etablissement = 'CRFRP'";
        } else {
            if ($niv === 'regional') {
                $where .= " AND pa.nom_region = ?";
                $params[] = $lieu;
            } elseif ($niv === 'district') {
                $where .= " AND pa.nom_district = ?";
                $params[] = $lieu;
            }
        }

        $sqlLog = "SELECT 
                    d.*, 
                    ec.nom, 
                    ec.prenoms, 
                    sa.statut_actuel,
                    COALESCE(
                        (SELECT GROUP_CONCAT(v.titre SEPARATOR ', ') 
                         FROM v_moteur_alertes v 
                         WHERE v.im = d.im 
                         AND (v.alerte_id = (SELECT dd.alerte_id FROM demandes_numeros_dos dd WHERE dd.im = d.im AND dd.numero_dos = d.numero_attribue LIMIT 1)
                              OR v.alerte_id LIKE CONCAT('HIDDEN_', d.im, '_AVANCEMENT_%'))
                         AND v.type_key != 'ECHELON'
                        ), 
                        'Demande Administrative'
                    ) as objet_complet
                   FROM reponses_dos d
                   JOIN personnel_etat_civil ec ON d.im = ec.im
                   JOIN personnel_situation_actuelle sa ON d.im = sa.im
                   JOIN personnel_poste_actuel pa ON d.im = pa.im
                   $where
                   ORDER BY d.date_reponse DESC";
        
        $stmtLog = $pdo->prepare($sqlLog);
        $stmtLog->execute($params);
        $historiqueDos = $stmtLog->fetchAll();

    } catch (Exception $e) {
        die("Erreur : " . $e->getMessage());
    }
?>

<div class="p-6 animate-in fade-in duration-500">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter">Historique DOS</h2>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Consultation (<?= $labelTitre ?>)</p>
        </div>
        
        <div class="relative w-full md:w-96">
            <input type="text" id="searchInput" onkeyup="filterTable()" 
                   placeholder="Rechercher nom, IM ou N° DOS..." 
                   class="w-full pl-12 pr-4 py-3 bg-white border-2 border-slate-100 rounded-2xl text-sm font-medium focus:border-indigo-500 outline-none transition-all shadow-sm">
            <i class="fas fa-search absolute left-4 top-4 text-slate-400 text-sm"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl shadow-slate-200 border border-slate-100 overflow-hidden">
        <table class="w-full text-left border-collapse" id="tableHistorique">
            <thead>
                <tr class="bg-sky-600">
                    <th class="p-5 text-white font-black uppercase text-xs tracking-widest text-center w-16">N°</th>
                    <th class="p-5 text-white font-black uppercase text-xs tracking-widest">Date d'émission</th>
                    <th class="p-5 text-white font-black uppercase text-xs tracking-widest">Objet de la demande</th>
                    <th class="p-5 text-white font-black uppercase text-xs tracking-widest text-center">N° DOS</th>
                    <th class="p-5 text-white font-black uppercase text-xs tracking-widest">Nom et prénoms</th>
                    <th class="p-5 text-white font-black uppercase text-xs tracking-widest">Matricule</th>
                    <th class="p-5 text-white font-black uppercase text-xs tracking-widest text-center">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php $n = 1; foreach ($historiqueDos as $row): ?>
                <tr class="hover:bg-slate-50/80 transition-colors table-row-item">
                    <td class="p-5 text-center text-sm text-slate-400 font-bold italic"><?= $n++; ?></td>
                    
                    <td class="p-5 text-sm text-slate-700 font-bold uppercase">
                        <?php echo date('d/m/Y H:i', strtotime($row['date_reponse'])); ?>
                    </td>
                    
                    <td class="p-5">
                        <div class="text-sm text-slate-700 font-bold uppercase leading-snug max-w-[300px]">
                            <?= htmlspecialchars($row['objet_complet']); ?>
                        </div>
                    </td>
                    
                    <td class="p-5 text-center">
                        <span class="text-sm font-black text-sky-600 tracking-tight search-target-dos">
                            <?php echo htmlspecialchars($row['numero_attribue']); ?>
                        </span>
                    </td>
                    
                    <td class="p-5">
                        <div class="flex flex-col">
                            <span class="text-slate-700 font-bold uppercase text-sm search-target-name">
                                <?php echo htmlspecialchars($row['nom'] . ' ' . $row['prenoms']); ?>
                            </span>
                        </div>
                    </td>

                    <td class="p-5">
                        <span class="text-sm font-bold text-slate-500 search-target-im">
                            <?php echo htmlspecialchars($row['im']); ?>
                        </span>
                    </td>
                    
                    <td class="p-5 text-center">
                        <?php if ($row['lu'] == 1): ?>
                            <span class="inline-flex items-center px-4 py-1.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-black uppercase border border-emerald-100">
                                <i class="fas fa-check-circle mr-1.5"></i> Déjà utilisé
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-4 py-1.5 bg-amber-50 text-amber-600 rounded-full text-[10px] font-black uppercase border border-amber-100">
                                <i class="fas fa-clock mr-1.5"></i> En attente
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="paginationControls" class="flex justify-center items-center mt-10 gap-3"></div>
</div>

<script>
// Le JavaScript reste identique pour la gestion de la recherche et pagination
let currentPage = 1;
const rowsPerPage = 10;
let filteredRows = [];

function initTable() {
    const allRows = Array.from(document.querySelectorAll('.table-row-item'));
    filteredRows = allRows;
    updatePagination();
}

function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const allRows = Array.from(document.querySelectorAll('.table-row-item'));

    filteredRows = allRows.filter(row => {
        const name = row.querySelector('.search-target-name').innerText.toLowerCase();
        const im = row.querySelector('.search-target-im').innerText.toLowerCase();
        const dos = row.querySelector('.search-target-dos').innerText.toLowerCase();
        return name.includes(searchTerm) || im.includes(searchTerm) || dos.includes(searchTerm);
    });

    currentPage = 1;
    updatePagination();
}

function updatePagination() {
    const totalRows = filteredRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage) || 1; 
    
    document.querySelectorAll('.table-row-item').forEach(row => row.style.display = 'none');

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    
    filteredRows.slice(start, end).forEach(row => row.style.display = 'table-row');

    renderPaginationButtons(totalPages);
}

function renderPaginationButtons(totalPages) {
    const container = document.getElementById('paginationControls');
    if(!container) return;
    container.innerHTML = '';

    const prevBtn = document.createElement('button');
    prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    const isPrevDisabled = currentPage === 1;
    prevBtn.className = `w-12 h-12 rounded-2xl flex items-center justify-center transition-all ${isPrevDisabled ? 'text-slate-200 cursor-not-allowed' : 'bg-white text-slate-700 shadow-sm hover:bg-indigo-600 hover:text-white border border-slate-100'}`;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; updatePagination(); window.scrollTo(0,0); } };
    container.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        if (i === currentPage) {
            pageBtn.className = "w-12 h-12 rounded-2xl bg-indigo-600 text-white font-black text-xs shadow-xl shadow-indigo-200 scale-110";
        } else {
            pageBtn.className = "w-12 h-12 rounded-2xl bg-white text-slate-500 font-bold text-xs hover:bg-indigo-50 border border-slate-100";
        }
        pageBtn.onclick = () => { currentPage = i; updatePagination(); window.scrollTo(0,0); };
        container.appendChild(pageBtn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    const isNextDisabled = currentPage === totalPages;
    nextBtn.className = `w-12 h-12 rounded-2xl flex items-center justify-center transition-all ${isNextDisabled ? 'text-slate-200 cursor-not-allowed' : 'bg-white text-slate-700 shadow-sm hover:bg-indigo-600 hover:text-white border border-slate-100'}`;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; updatePagination(); window.scrollTo(0,0); } };
    container.appendChild(nextBtn);
}

document.addEventListener('DOMContentLoaded', initTable);
</script>
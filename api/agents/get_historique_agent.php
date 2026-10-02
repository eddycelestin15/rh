<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
$im = $_GET['im'] ?? '';
$alerte_id = $_GET['alerte_id'] ?? '';

$stmtAgent = $pdo->prepare("SELECT nom, prenoms FROM personnel_etat_civil WHERE im = ?");
$stmtAgent->execute([$im]);
// Les comptes admin/responsable n'ont pas de fiche etat civil : on evite
// d'acceder a false plus bas dans le gabarit.
$agent = $stmtAgent->fetch() ?: ['nom' => '', 'prenoms' => ''];

$stmtHist = $pdo->prepare("SELECT * FROM personnel_avancements WHERE im = ? ORDER BY av_date_effet ASC");
$stmtHist->execute([$im]);
$historique = $stmtHist->fetchAll();
?>

<div class="p-6 bg-slate-50 min-h-screen animate-in fade-in duration-500">
    <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-black text-slate-800 uppercase tracking-tighter italic leading-none">Historique des carrières</h2>
            <div class="flex items-center gap-3 mt-4">
                <span class="px-4 py-1.5 bg-indigo-600 text-white rounded-xl font-black text-xs shadow-lg shadow-indigo-100">IM <?= $im ?></span>
                <span class="text-slate-500 font-bold uppercase text-lg"><?= htmlspecialchars(trim(($agent['nom'] ?? '') . ' ' . ($agent['prenoms'] ?? ''))) ?></span>
            </div>
        </div>

        <button onclick="ouvrirSaisieNumero('<?= $im ?>', '<?= $alerte_id ?>')" 
                class="bg-emerald-500 text-white px-8 py-4 rounded-[1.5rem] font-black uppercase text-[10px] shadow-2xl shadow-emerald-100 hover:bg-emerald-600 hover:-translate-y-1 transition-all flex items-center gap-3">
            <i class="fas fa-check-circle text-base"></i> Attribuer le numéro DOS
        </button>
    </div>

    <div class="bg-white rounded-[0.5rem] shadow-2xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
        <table class="w-full text-left border-collapse" id="historyTable">
            <thead>
                <tr class="bg-indigo-600 text-white">
                    <th class="p-4 text-[10px] font-black uppercase tracking-wider border-r border-indigo-500/30">N° Acte</th>
                    <th class="p-4 text-[10px] font-black uppercase tracking-wider border-r border-indigo-500/30 text-center">Date Acte</th>
                    <th class="p-4 text-[10px] font-black uppercase tracking-wider border-r border-indigo-500/30">Corps et Grade</th>
                    <th class="p-4 text-[10px] font-black uppercase tracking-wider border-r border-indigo-500/30 text-center">Indice</th>
                    <th class="p-4 text-[10px] font-black uppercase text-right bg-indigo-700 tracking-widest">Date d'effet</th>
                </tr>
            </thead>
            <tbody id="historyTableBody">
                <?php foreach ($historique as $h): ?>
                <tr class="hover:bg-indigo-50/50 transition-all border-b border-slate-50 last:border-0 history-row-item">
                    <td class="p-3 text-sm font-bold text-slate-700 uppercase italic"><?= $h['av_acte_no'] ?: '---' ?></td>
                    <td class="p-3 text-sm font-bold text-slate-500 text-center"><?= date('d/m/Y', strtotime($h['av_acte_date'])) ?></td>
                    <td class="p-3 text-sm font-bold text-slate-800 uppercase whitespace-nowrap">
                        <?= $h['av_corps'] ?> <span class="mx-2 text-slate-300">|</span> <span class="text-indigo-500"><?= $h['av_grade'] ?></span>
                    </td>
                    <td class="p-3 text-sm font-black text-slate-800 text-center italic"><?= $h['av_indice'] ?></td>
                    <td class="p-3 text-sm font-black text-indigo-600 text-right"><?= date('d/m/Y', strtotime($h['av_date_effet'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="p-6 bg-slate-50 border-t border-slate-100 flex flex-col items-center gap-3">
            <div class="flex items-center gap-2" id="historyPaginationControls"></div>
            <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">
                Page <span id="histCurrentPage" class="text-indigo-600">1</span> / <span id="histTotalPage">1</span>
            </p>
        </div>
    </div>
</div>

<script>
// Logique de pagination spécifique à l'historique
var histCurrentPage = 1;
var histRecordsPerPage = 8; // Un peu moins pour l'historique pour garder de la visibilité
var histRows = Array.from(document.querySelectorAll('.history-row-item'));

function initHistPagination() {
    const totalPages = Math.ceil(histRows.length / histRecordsPerPage);
    const start = (histCurrentPage - 1) * histRecordsPerPage;
    const end = start + histRecordsPerPage;

    histRows.forEach((row, idx) => {
        row.style.display = (idx >= start && idx < end) ? '' : 'none';
    });

    document.getElementById('histCurrentPage').innerText = histCurrentPage;
    document.getElementById('histTotalPage').innerText = totalPages || 1;
    renderHistButtons(totalPages);
}

function renderHistButtons(totalPages) {
    const container = document.getElementById('historyPaginationControls');
    container.innerHTML = '';
    if(totalPages <= 1) return;

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.innerText = i;
        btn.className = `w-10 h-10 rounded-xl font-black text-[10px] transition-all ${i === histCurrentPage ? 'bg-indigo-600 text-white shadow-lg' : 'bg-white text-slate-500 hover:bg-indigo-50'}`;
        btn.onclick = () => { histCurrentPage = i; initHistPagination(); };
        container.appendChild(btn);
    }
}

initHistPagination();
</script>
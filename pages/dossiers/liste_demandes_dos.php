<?php
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/check_session.php';

    $user_im = $_SESSION['user_im'];

    // 1. Récupérer les infos complètes du responsable
    $stmtUser = $pdo->prepare("SELECT role_specifique, code_lieu_affectation, niveau FROM utilisateurs WHERE im = ?");
    $stmtUser->execute([$user_im]);
    $user = $stmtUser->fetch();

    $role = $user['role_specifique'];
    $lieuResponsable = $user['code_lieu_affectation'];
    $niveau = $user['niveau'];

    // Détermination des statuts cibles (votre logique existante)
    if ($role === 'resp_encadre') {
        $statutsCibles = ['Fonctionnaire'];
    } else {
        $statutsCibles = ['Contractuel EFA'];
    }

    $placeholders = implode(',', array_fill(0, count($statutsCibles), '?'));

    $colonneFiltreLieu = ($niveau === 'regional') ? 'd.dos_region' : 'd.dos_district';
    $sql = "SELECT d.im, d.type_dos, d.type_titre, 
                   GROUP_CONCAT(DISTINCT d.alerte_id) as alerte_id, 
                   MIN(d.date_demande) as date_demande, 
                   ec.nom, ec.prenoms, sa.corps_actuel, sa.grade_actuel, sa.statut_actuel, 
                   sa.code_corps_actuel, v.titre as objet_alerte
            FROM demandes_numeros_dos d
            INNER JOIN personnel_etat_civil ec ON d.im = ec.im
            INNER JOIN personnel_situation_actuelle sa ON d.im = sa.im
            LEFT JOIN v_moteur_alertes v ON d.im = v.im AND d.alerte_id = v.alerte_id
            WHERE sa.statut_actuel IN ($placeholders) 
            AND d.statut = 'EN_ATTENTE'
            AND $colonneFiltreLieu = ? 
            GROUP BY d.im
            ORDER BY date_demande ASC";

    // On ajoute le lieu du responsable aux paramètres de la requête
    $params = array_merge($statutsCibles, [$lieuResponsable]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasDemandes = !empty($demandes);
?>

<div id="listeContent" class="p-4 bg-slate-50 h-auto animate-in fade-in duration-500">
    <div class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tighter italic">Demandes de numéros DOS</h2>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Gestion des attributions en attente</p>
        </div>
        <div class="flex flex-col md:flex-row items-center gap-3">
            <div class="relative w-full md:w-96">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-indigo-500"></i>
                <input type="text" id="searchInput" placeholder="Rechercher par IM ou Nom..." class="w-full pl-12 pr-4 py-3 bg-white border-2 border-slate-100 rounded-xl shadow-sm focus:border-indigo-500 outline-none font-bold text-slate-700 text-sm transition-all">
            </div>

            <?php if ($niveau === 'district'): ?>
                <button onclick="transfererToutVersDren('<?= addslashes($lieuResponsable) ?>')" 
                        class="w-full md:w-auto px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold text-xs uppercase hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-share-square mr-2"></i> 
                    Transférer demandes à la DREN
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-[0.5rem] shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
        <table class="w-full text-left border-collapse" id="mainTable">
            <thead>
                <tr class="bg-indigo-600">
                    <th class="p-4 text-[14px] font-black uppercase text-white tracking-wider w-12 text-center">N°</th>
                    <th class="p-4 text-[14px] font-black uppercase text-white tracking-wider">Date</th>
                    <th class="p-4 text-[14px] font-black uppercase text-white tracking-wider">Objet de demande</th> 
                    <th class="p-4 text-[14px] font-black uppercase text-white tracking-wider">Nom et prénoms</th>
                    <th class="p-4 text-[14px] font-black uppercase text-white tracking-wider text-center">IM</th>
                    <th class="p-4 text-[14px] font-black uppercase text-white tracking-wider text-center">Corps & Grade</th>
                    <th class="p-4 text-[14px] font-black uppercase text-white tracking-wider text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="agentsTableBody" class="divide-y divide-slate-50">
                <?php if ($hasDemandes): ?>
                    <?php $i = 1; foreach($demandes as $d): 
                        // --- PRÉPARATION DES DONNÉES (Sécurité PHP 8) ---
                        $current_alerte = $d['alerte_id'] ?? '';
                        $current_im = $d['im'] ?? '';
                        $current_statut = $d['statut_actuel'] ?? '';
                        $current_code = substr($d['code_corps_actuel'] ?? '', 0, 1);
                        $libelleAffichage = $d['objet_alerte'];
                        $type_dos = $d['type_dos']; 
                        $type_titre = $d['type_titre'] ?? '';

                        if ($current_statut === 'Contractuel EFA') {
                            if ($type_dos === 'Intégration') {
                                $libelleAffichage = "Demande d'intégration";
                            }
                            elseif (strpos($current_alerte, 'RNC1') !== false || strpos($current_alerte, 'RNC2') !== false) {
                                $libelleAffichage = "Demande de renouvellement de contrat";
                            }
                            elseif (preg_match('/Avenant([3-9]|[1-9][0-9]+)/', $type_dos)) {
                                $libelleAffichage = "Demande d'avenant";
                            }
                        } 
                        
                        elseif ($current_statut === 'Fonctionnaire') {
                            if ($type_dos === 'Titularisation') {
                                $libelleAffichage = "Demande de titularisation";
                            }
                            elseif ($type_dos === 'Avancement') {
                                $checkClasse = ['1°CLASSE/1°ECHELON', 'PRINCIPAL/1°ECHELON', 'CLASSE EXCEPTIONNELLE/1°ECHELON'];
                                $estAvancementClasse = false;
                                foreach ($checkClasse as $classe) {
                                    if (strpos($type_titre, $classe) !== false) {
                                        $estAvancementClasse = true;
                                        break;
                                    }
                                }
                                $libelleAffichage = $estAvancementClasse ? "Demande d'avancement de classe" : "Demande d'avancement d'échelon";
                            }
                        }
                    ?>
                    <tr class="hover:bg-indigo-50/50 transition-all border-b border-slate-50 last:border-0 agent-row">
                        <td class="p-3 text-center text-[13px] font-bold text-slate-700 uppercase"><?= $i++; ?></td>
                        <td class="p-3 text-[13px] font-bold text-slate-700 uppercase"><?= date('d/m/Y H:i', strtotime($d['date_demande'])); ?></td>
                        <td class="p-3">
                            <div class="text-[13px] font-black text-indigo-600 uppercase leading-tight max-w-[200px]">
                                <?= htmlspecialchars($libelleAffichage); ?>
                            </div>
                        </td>
                        <td class="p-3 text-[13px] font-black text-slate-800 uppercase"><?= htmlspecialchars(($d['nom'] ?? '') . ' ' . ($d['prenoms'] ?? '')); ?></td>
                        <td class="p-3 text-center text-[13px] font-black text-slate-700"><?= htmlspecialchars($current_im); ?></td>
                        <td class="p-3 text-center">
                            <div class="text-[13px] font-black text-slate-600 uppercase leading-tight"><?= htmlspecialchars($d['corps_actuel'] ?? ''); ?></div>
                            <div class="text-[13px] font-bold text-slate-400 uppercase italic"><?= htmlspecialchars($d['grade_actuel'] ?? ''); ?></div>
                        </td>
                        <td class="p-3 text-center">
                            <?php if ($niveau === 'regional'): ?>
                                <button onclick="voirHistoriqueAgent('<?= addslashes($current_im) ?>', '<?= addslashes($current_alerte) ?>')" 
                                        class="px-4 py-2 bg-emerald-500 text-white rounded-lg font-bold text-xs uppercase hover:bg-emerald-600 transition-all shadow-lg shadow-emerald-100">
                                    <i class="fas fa-plus-circle mr-2"></i> Donner Numéro dos
                                </button>
                            <?php else: ?>
                                <button onclick="envoyerVersDrenSeul('<?= addslashes($current_im) ?>', '<?= addslashes($current_alerte) ?>')" 
                                        class="px-4 py-2 bg-amber-500 text-white rounded-lg font-bold text-xs uppercase hover:bg-amber-600 transition-all shadow-lg shadow-amber-100">
                                    <i class="fas fa-paper-plane mr-2"></i> Envoyer demande dos
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($hasDemandes): ?>
        <div class="p-6 bg-slate-50 border-t border-slate-100 flex flex-col items-center gap-3">
            <div class="flex items-center gap-2" id="paginationControls"></div>
            <p class="text-[12px] font-black text-slate-400 uppercase tracking-widest">
                Page <span id="currentPageNum" class="text-indigo-600">1</span> / <span id="totalPageNum">1</span>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="historiqueContainer" class="hidden"></div>
<script>
// Variables globales pour la pagination
let currentPage = 1;
const rowsPerPage = 10;

/**
 * FONCTION DE FILTRAGE (RECHERCHE)
 */
window.filterTable = function() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase().trim();
    const tableBody = document.getElementById('agentsTableBody');
    const rows = tableBody.querySelectorAll('.agent-row');
    const paginationContainer = document.getElementById('paginationContainer');
    
    let visibleCount = 0;

    rows.forEach(row => {
        // On cherche dans TOUT le texte de la ligne (Nom, IM, Grade...)
        const text = row.innerText.toLowerCase();
        
        if (filter === "") {
            // Si recherche vide, on laisse la pagination gérer l'affichage
            return; 
        }

        if (text.includes(filter)) {
            row.style.display = "";
            visibleCount++;
        } else {
            row.style.display = "none";
        }
    });

    if (filter === "") {
        paginationContainer.style.display = "flex";
        initPagination(); // Relance la pagination normale
    } else {
        paginationContainer.style.display = "none";
        // Gérer le message "Aucun résultat"
        toggleNoResult(visibleCount === 0, filter);
    }
};

/**
 * GESTION DU MESSAGE "AUCUN RÉSULTAT"
 */
function toggleNoResult(show, query) {
    const tableBody = document.getElementById('agentsTableBody');
    let noMsg = document.getElementById('noResultMsg');
    if (show) {
        if (!noMsg) {
            noMsg = document.createElement('tr');
            noMsg.id = 'noResultMsg';
            noMsg.innerHTML = `<td colspan="10" class="p-10 text-center text-slate-400 font-bold italic">Aucun résultat pour "${query}"</td>`;
            tableBody.appendChild(noMsg);
        }
    } else if (noMsg) {
        noMsg.remove();
    }
}

/**
 * SYSTÈME DE PAGINATION (CORRIGÉ)
 */
window.initPagination = function() {
    const tableBody = document.getElementById('agentsTableBody');
    const rows = Array.from(tableBody.querySelectorAll('.agent-row'));
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    const container = document.getElementById('paginationContainer');
    
    container.innerHTML = '';
    if (totalPages <= 1) {
        rows.forEach(r => r.style.display = "");
        return;
    }

    // Affichage des lignes pour la page active
    rows.forEach((row, index) => {
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        row.style.display = (index >= start && index < end) ? "" : "none";
    });

    // Création des boutons (Précédent)
    const prev = document.createElement('button');
    prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prev.className = `w-12 h-12 rounded-2xl flex items-center justify-center transition-all ${currentPage === 1 ? 'text-slate-200' : 'bg-white text-indigo-600 shadow-sm'}`;
    prev.onclick = () => { if(currentPage > 1) { currentPage--; initPagination(); } };
    container.appendChild(prev);

    // Boutons de numéros
    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.innerText = i;
        btn.className = `w-12 h-12 rounded-2xl font-black text-xs transition-all ${i === currentPage ? 'bg-indigo-600 text-white shadow-xl' : 'bg-white text-slate-500'}`;
        btn.onclick = () => { currentPage = i; initPagination(); };
        container.appendChild(btn);
    }

    // Bouton Suivant
    const next = document.createElement('button');
    next.innerHTML = '<i class="fas fa-chevron-right"></i>';
    next.className = `w-12 h-12 rounded-2xl flex items-center justify-center transition-all ${currentPage === totalPages ? 'text-slate-200' : 'bg-white text-indigo-600 shadow-sm'}`;
    next.onclick = () => { if(currentPage < totalPages) { currentPage++; initPagination(); } };
    container.appendChild(next);
};

// Lancement au chargement
document.addEventListener('DOMContentLoaded', initPagination);

// Ecouteur pour la recherche
document.addEventListener('input', function(e) {
    if (e.target && e.target.id === 'searchInput') {
        window.filterTable();
    }
});
</script>

<script>
/**
 * Action pour le bouton individuel (District)
 */
function envoyerVersDrenSeul(im, alerteId) {
    if(!confirm("Envoyer cette demande de numéro de dossier à la DREN ?")) return;
    
    fetch('actions/dossiers/save_dos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'transferer_tout_district_vers_region', // On utilise la logique existante de transfert
            im: im,
            district: '<?= $lieuResponsable ?>' 
        })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            Swal.fire('Succès', 'Demande envoyée à la DREN', 'success');
            // Recharger la vue actuelle via votre fonction AJAX habituelle
            if(window.showSubPage) showSubPage('pages/dossiers/liste_demandes_dos.php'); 
        } else {
            Swal.fire('Erreur', data.message, 'error');
        }
    });
}

/**
 * Action pour le bouton global (District)
 */
function transfererToutVersDren(districtName) {
    Swal.fire({
        title: 'Transférer tout ?',
        text: `Toutes les demandes en attente de ${districtName} seront envoyées à la DREN.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Oui, transférer tout'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('actions/dossiers/save_dos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'transferer_tout_district_vers_region',
                    district: districtName
                })
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    Swal.fire('Transféré !', 'Toutes les demandes ont été envoyées.', 'success');
                    if(window.showSubPage) showSubPage('pages/dossiers/liste_demandes_dos.php');
                } else {
                    Swal.fire('Erreur', data.message, 'error');
                }
            });
        }
    });
}
</script>

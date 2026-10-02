<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';

$anneeCourante = date('Y'); 
$type = $_GET['type'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// =========================================================================
// 1. CAS SPÉCIFIQUE : AGENTS À RÉGULARISER
// =========================================================================
if ($type === 'alertes_all') {
    // 1. On définit la base sans les colonnes pour le COUNT
    $sqlBase = "FROM v_moteur_alertes v
                INNER JOIN personnel_situation_actuelle psa ON v.im = psa.im
                LEFT JOIN personnel_poste_actuel ppa ON v.im = ppa.im
                LEFT JOIN personnel_etat_civil pec ON v.im = pec.im
                WHERE v.date_reception_technique <= CURRENT_DATE";

    // 2. On compte le nombre d'IM uniques pour la pagination
    $total = $pdo->query("SELECT COUNT(DISTINCT v.im) $sqlBase")->fetchColumn();
    $total_pages = ceil($total / $limit);

    // 3. Requête principale avec GROUP_CONCAT et GROUP BY
    // On concatène les titres des alertes avec une virgule et un espace
    $query = "SELECT 
                v.im, 
                CONCAT(UPPER(COALESCE(pec.nom, '')), ' ', COALESCE(pec.prenoms, '')) as nom_complet,
                COALESCE(ppa.nom_etablissement, ppa.nom_zap, ppa.nom_district, ppa.type_direction) as lieu_service,
                GROUP_CONCAT(v.titre SEPARATOR '|||') as titres,
                GROUP_CONCAT(v.alerte_id SEPARATOR '|||') as ids
              $sqlBase 
              GROUP BY v.im, pec.nom, pec.prenoms, ppa.nom_etablissement, ppa.nom_zap, ppa.nom_district, ppa.type_direction
              ORDER BY v.date_reception_technique ASC 
              LIMIT $limit OFFSET $offset";
    
    $agents = $pdo->query($query)->fetchAll();

    if ($total > 0) {
        echo '<div class="overflow-x-auto">
                <table class="w-full text-left border-separate border-spacing-y-2" id="table-personnel">
                    <thead>
                        <tr class="bg-rose-600 text-white shadow-md">
                            <th class="p-2 first:rounded-l-xl text-[10px] font-black uppercase tracking-widest">N°</th>
                            <th class="p-2 text-[10px] font-black uppercase tracking-widest">IM</th>
                            <th class="p-2 text-[10px] font-black uppercase tracking-widest">NOM et PRENOMS</th>
                            <th class="p-2 text-[10px] font-black uppercase tracking-widest">LIEU DE SERVICE</th>
                            <th class="p-2 text-[10px] font-black uppercase tracking-widest">NATURES DE RÉGULARISATION</th>
                        </tr>
                    </thead>
                    <tbody>';

        $n = $offset + 1;
        // ... (Code précédent inchangé jusqu'à la boucle des agents)

foreach ($agents as $r) {
    echo "<tr class='bg-white hover:bg-rose-50 transition-all shadow-sm group'>";
    echo "<td class='px-3 py-1 rounded-l-xl font-bold text-slate-400 text-sm'>$n</td>";
    echo "<td class='px-3 py-1 font-mono text-rose-600 font-bold text-sm'>{$r['im']}</td>";
    echo "<td class='px-3 py-1 font-black text-slate-900 uppercase text-sm'>" . ($r['nom_complet'] ?: 'INCONNU') . "</td>";
    echo "<td class='px-3 py-1 font-bold text-slate-500 uppercase text-sm'>" . ($r['lieu_service'] ?: 'NON RENSEIGNÉ') . "</td>";
    
    // COLONNE NATURE : On affiche tout SAUF les 'hidden'
    echo "<td class='px-3 py-1 rounded-r-xl'><div class='flex flex-wrap gap-1'>";
    
    $liste_titres = explode('|||', $r['titres']);
    $liste_ids = explode('|||', $r['ids']);

    foreach($liste_ids as $index => $id) {
        // CONDITION : On n'affiche que si 'hidden' n'est PAS dans l'ID
        if (strpos(strtolower($id), 'hidden') === false) {
            
            $label = $liste_titres[$index];
            
            // Distinction visuelle pour l'avancement de groupe
            $is_groupe = (strpos($id, '_AVANCEMENT_GROUPE') !== false);
            $bg_color = $is_groupe ? 'bg-slate-100 text-slate-600 border-slate-200' : 'bg-rose-100 text-rose-700 border-rose-200';
            
            // Badge cliquable pour ouvrir le formulaire
            echo "<button onclick=\"ouvrirFormulaire('{$r['im']}', '{$id}')\" 
                      class='{$bg_color} px-2 py-0.5 rounded text-[11px] font-black uppercase border border-rose-200 hover:bg-rose-600 hover:text-white transition-all'>
                $label
              </button>";
        }
    }
    
    echo "</div></td>";
    echo "</tr>";
    $n++;
}
        echo '</tbody></table></div>';
        renderPaginationUI($type, $page, $total_pages);
    } else {
        echo "<div class='p-20 text-center font-black text-slate-300 uppercase text-xs tracking-widest'>Aucun agent à régulariser</div>";
    }
    exit;
}

// =========================================================================
// 2. LOGIQUE PRÉCÉDENTE (FPE et Structures)
// =========================================================================
$where = " WHERE 1=1 ";
switch ($type) {
    case 'crfrp':
        $where .= " AND p.crfrp = 1";
        $cols = ['N°', 'REGION', 'DISTRICT', 'IM', 'NOM ET PRENOMS', 'FONCTION', 'MODULE ENSEIGNÉ'];
        break;
    case 'bureau_d':
        $where .= " AND p.bureau_d = 1";
        $cols = ['N°', 'DREN', 'SERVICE', 'IM', 'NOM ET PRENOMS', 'FONCTION'];
        break;
    case 'bureau_z':
        $where .= " AND p.bureau_z = 1";
        $cols = ['N°', 'DREN', 'CISCO', 'ZAP', 'IM', 'NOM ET PRENOMS'];
        break;
    case 'bureau_c':
    case (str_starts_with($type, 'cisco_')):
        if (str_starts_with($type, 'cisco_')) {
            $c = urldecode(substr($type, 6));
            $where .= " AND p.nom_cisco = " . $pdo->quote($c);
        }
        $where .= " AND p.bureau_c = 1 AND (p.eec=0 AND p.presco=0 AND p.primaire=0 AND p.college=0 AND p.lycee=0)";
        $cols = ['N°', 'DREN', 'CISCO', 'DIVISION', 'IM', 'NOM ET PRENOMS', 'FONCTION'];
        break;
    case 'enseignants':
        $where .= " AND (p.eec=1 OR p.presco=1 OR p.primaire=1 OR p.college=1 OR p.lycee=1)";
        $cols = ['N°', 'DREN', 'CISCO', 'ZAP', 'CODE ETAB', 'NOM ETAB', 'IM', 'NOM ET PRENOMS'];
        break;
    case 'retraite_now':
        $where .= " AND p.date_naissance IS NOT NULL AND YEAR(p.date_naissance) = ($anneeCourante - 60)";
        $cols = ['N°', 'DREN', 'CISCO', 'ZAP', 'CODE ETAB', 'NOM ETAB', 'IM', 'NOM ET PRENOMS', 'DATE DE NAISSANCE'];
        break;
    case 'retraite_next':
        $where .= " AND p.date_naissance IS NOT NULL AND YEAR(p.date_naissance) = ($anneeCourante - 59)";
        $cols = ['N°', 'DREN', 'CISCO', 'ZAP', 'CODE ETAB', 'NOM ETAB', 'IM', 'NOM ET PRENOMS', 'DATE DE NAISSANCE'];
        break;
    default:
        $cols = ['N°', 'DREN', 'CISCO', 'IM', 'NOM ET PRENOMS'];
}

$join = " LEFT JOIN personnel_situation_actuelle psa ON p.im = psa.im ";
$join .= " LEFT JOIN personnel_poste_actuel ppa ON p.im = ppa.im ";

$sql = "SELECT p.*, p.im as im_fpe, p.nom_zap as zap_bdd, p.nom_etablissement as etab_bdd, 
               ppa.nom_service, ppa.nom_fonction, ppa.nom_matiere, psa.im as situ_exists 
        FROM personnel_fpe p 
        $join $where 
        ORDER BY p.nom_et_prenoms ASC LIMIT $limit OFFSET $offset";

$agents = $pdo->query($sql)->fetchAll();
$total = $pdo->query("SELECT COUNT(*) FROM personnel_fpe p $where")->fetchColumn();
$total_pages = ceil($total / $limit);

if ($total > 0) {
    echo '<div class="overflow-x-auto">';
    echo '<table class="w-full text-left border-separate border-spacing-y-2" id="table-personnel">';
    echo '<thead><tr class="bg-sky-600 text-white shadow-md">';
    foreach ($cols as $index => $c) {
        $rounded = ($index === 0) ? 'first:rounded-l-xl' : '';
        echo "<th class='p-4 $rounded text-xs font-black uppercase tracking-wider'>$c</th>";
    }
    echo '<th class="p-4 last:rounded-r-xl text-xs font-black uppercase tracking-wider text-right">SITUATION</th>';
    echo '</tr></thead><tbody id="body-personnel">';

    $n = $offset + 1;
    foreach ($agents as $r) {
        $aJour = !empty($r['situ_exists']);
        $statusLabel = $aJour ? 'À JOUR' : 'À COMPLÉTER';
        $statusColor = $aJour ? 'bg-emerald-500' : 'bg-rose-500';

        echo '<tr class="bg-white hover:bg-sky-50 transition-all shadow-sm row-agent">';
        echo "<td class='p-4 rounded-l-xl text-xs font-bold text-slate-400'>$n</td>";
        echo "<td class='p-4 text-xs font-bold uppercase text-slate-700'>{$r['nom_dren']}</td>";
        
        if(in_array('DISTRICT', $cols) || in_array('CISCO', $cols)) {
            echo "<td class='p-4 text-xs font-bold uppercase text-slate-700 data-cisco'>{$r['nom_cisco']}</td>";
        } else {
            echo "<td class='hidden data-cisco'>{$r['nom_cisco']}</td>";
        }
        
        if(in_array('SERVICE', $cols)) echo "<td class='p-4 text-xs font-bold text-sky-800'>" . ($r['nom_service'] ?? '---') . "</td>";
        if(in_array('DIVISION', $cols)) echo "<td class='p-4 text-slate-400 text-xs italic font-medium'>---</td>";
        
        if(in_array('ZAP', $cols)) {
            echo "<td class='p-4 text-xs font-bold uppercase text-slate-700 data-zap'>" . ($r['zap_bdd'] ?? '---') . "</td>";
        } else {
            echo "<td class='hidden data-zap'>" . ($r['zap_bdd'] ?? '---') . "</td>";
        }
        
        if(in_array('CODE ETAB', $cols)) echo "<td class='p-4 text-xs font-mono font-bold text-slate-500'>{$r['code_etab']}</td>";
        if(in_array('NOM ETAB', $cols)) echo "<td class='p-4 text-xs font-medium uppercase text-slate-600'>{$r['etab_bdd']}</td>";
        
        echo "<td class='p-4 font-mono text-sky-600 font-bold text-sm data-im'>{$r['im_fpe']}</td>";
        echo "<td class='p-4 font-black text-slate-900 uppercase text-sm data-nom'>{$r['nom_et_prenoms']}</td>";
        
        if(in_array('DATE DE NAISSANCE', $cols)) {
            $date_val = $r['date_naissance'] ?? null;
            $date_f = ($date_val && $date_val != '0000-00-00') ? date('d/m/Y', strtotime($date_val)) : '---';
            echo "<td class='p-4 text-xs font-black text-slate-700'>$date_f</td>";
        }
        
        if(in_array('FONCTION', $cols)) echo "<td class='p-4 text-xs font-bold text-sky-700 uppercase'>" . ($r['nom_fonction'] ?? '---') . "</td>";
        if(in_array('MODULE ENSEIGNÉ', $cols)) echo "<td class='p-4 text-xs font-bold text-emerald-700 uppercase'>" . ($r['nom_matiere'] ?? '---') . "</td>";
        
        echo "<td class='p-4 rounded-r-xl text-right'>";
        echo "<span class='text-[10px] font-black text-white $statusColor px-4 py-1.5 rounded-full shadow-sm'>$statusLabel</span>";
        echo "</td></tr>";
        $n++;
    }
    echo '</tbody></table></div>';
    renderPaginationUI($type, $page, $total_pages);
} else {
    echo "<div class='p-20 text-center font-black text-slate-300 uppercase text-xs tracking-widest'>Aucune donnée trouvée</div>";
}

function renderPaginationUI($type, $page, $total_pages) {
    echo "###PAGINATION###";
    if($total_pages > 1) {
        $prev_dis = ($page <= 1) ? 'disabled opacity-20' : "onclick=\"changePage('$type', ".($page-1).")\"";
        $next_dis = ($page >= $total_pages) ? 'disabled opacity-20' : "onclick=\"changePage('$type', ".($page+1).")\"";
        echo "<div class='flex items-center gap-6 p-4'>";
        echo "<button $prev_dis class='bg-slate-900 text-white px-6 py-2.5 rounded-xl text-xs font-black hover:bg-sky-500 transition-all'>PRÉCÉDENT</button>";
        echo "<span class='text-xs font-black text-slate-400'>PAGE $page / $total_pages</span>";
        echo "<button $next_dis class='bg-slate-900 text-white px-6 py-2.5 rounded-xl text-xs font-black hover:bg-sky-500 transition-all'>SUIVANT</button>";
        echo "</div>";
    }
}
?>

<style>
    #table-personnel {
        border-spacing: 0 2px !important;
        border-collapse: separate !important;
    }

    /* Force la réduction de TOUTES les cellules */
    #table-personnel td, 
    #table-personnel th { 
        padding: 4px 12px !important; /* Réduction drastique du padding vertical */
        height: 30px !important;      /* Hauteur totale ligne */
        line-height: 1 !important;
        box-sizing: border-box !important;
    }

    /* Ajustement des badges de régularisation */
    .bg-rose-100 {
        padding: 1px 6px !important;
        font-size: 9px !important;
        height: 16px !important;
        display: inline-flex !important;
        align-items: center !important;
        line-height: 1 !important;
    }

    /* Réduction de la taille de l'icône dans le bouton */
    #table-personnel button i {
        font-size: 10px !important;
    }
</style>
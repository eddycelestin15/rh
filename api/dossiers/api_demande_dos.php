<?php
// api_demande_dos.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

// Forcer le bon format de réponse
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action == 'list_demandes_en_attente') {
    try {
        $sql = "SELECT d.im, d.type_dos, d.date_demande, d.dos_region,
                       ec.nom, ec.prenoms, sa.corps_actuel, sa.grade_actuel
                FROM demandes_numeros_dos d
                INNER JOIN personnel_etat_civil ec ON d.im = ec.im
                INNER JOIN personnel_situation_actuelle sa ON d.im = sa.im
                WHERE d.statut = 'EN_ATTENTE'
                ORDER BY d.date_demande ASC";

        $stmt = $pdo->query($sql);
        $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        $i = 1;

        foreach ($demandes as $d) {
            $isDejaEnvoye = !empty($d['dos_region']);
            
            $actionHtml = '';
            if ($isDejaEnvoye) {
                $actionHtml = '<div class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 rounded-lg font-bold text-[11px] uppercase border border-emerald-100"><i class="fas fa-check-circle text-emerald-500"></i> Transmis DREN</div>';
            } else {
                $actionHtml = '<button onclick="traiterDemande(\''.addslashes($d['im']).'\')" class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-bold text-xs uppercase hover:bg-indigo-700 transition-all shadow-md shadow-indigo-100"><i class="fas fa-cog mr-1.5"></i> Traiter</button>';
            }

            $data[] = [
                "index"        => $i++,
                "date_demande" => $d['date_demande'],
                "date_aff"     => date('d/m/Y H:i', strtotime($d['date_demande'])),
                "objet"        => '<div class="text-[13px] font-black text-indigo-600 uppercase leading-tight">' . htmlspecialchars($d['type_dos'] ?? '') . '</div>',
                "nom_complet"  => '<div class="text-[13px] font-black text-slate-800 uppercase">' . htmlspecialchars(($d['nom'] ?? '') . ' ' . ($d['prenoms'] ?? '')) . '</div>',
                "im"           => '<div class="text-[13px] font-black text-slate-700 text-center">' . htmlspecialchars($d['im'] ?? '') . '</div>',
                "corps_grade"  => '<div class="text-[13px] font-black text-slate-600 uppercase leading-tight">' . htmlspecialchars($d['corps_actuel'] ?? '') . '</div><div class="text-[11px] text-slate-400 font-bold uppercase">' . htmlspecialchars($d['grade_actuel'] ?? '') . '</div>',
                "action"       => $actionHtml
            ];
        }

        echo json_encode(['success' => true, 'data' => $data]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
    }
    exit;
}
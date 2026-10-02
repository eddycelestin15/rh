<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$userIm = $_SESSION['user_im'];

// Récupération de la région / affectation de l'utilisateur
$stmtUser = $pdo->prepare("SELECT code_lieu_affectation FROM utilisateurs WHERE im = ?");
$stmtUser->execute([$userIm]);
$user = $stmtUser->fetch();
$regionUser = $user['code_lieu_affectation'] ?? 'CENTRAL';

// Traitement Ajax de sauvegarde ou récupération du texte
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'get_texte') {
        $corpsId = intval($_POST['corps_id']);
        $stmt = $pdo->prepare("SELECT texte FROM configuration_decision WHERE region = ? AND corps_id = ?");
        $stmt->execute([$regionUser, $corpsId]);
        $row = $stmt->fetch();
        echo json_encode(['success' => true, 'texte' => $row['texte'] ?? '']);
        exit;
    }

    if ($action === 'save_texte') {
        $corpsId = intval($_POST['corps_id']);
        $texte = trim($_POST['texte']);

        $stmt = $pdo->prepare("INSERT INTO configuration_decision (region, corps_id, texte) 
                               VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE texte = VALUES(texte)");
        $res = $stmt->execute([$regionUser, $corpsId, $texte]);
        echo json_encode(['success' => $res]);
        exit;
    }
}

// Récupération de la liste des corps
$corpsList = $pdo->query("SELECT id, libelle_corps, categorie FROM ref_corps ORDER BY libelle_corps ASC")->fetchAll();
?>

<div class="p-8 max-w-4xl mx-auto">
    <div class="bg-white rounded-3xl p-8 shadow-xl border border-slate-100">
        <div class="flex items-center gap-4 mb-6 border-b border-slate-100 pb-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold">
                <i class="fas fa-file-signature"></i>
            </div>
            <div>
                <h2 class="text-xl font-black text-slate-800 uppercase">Configuration — Projet de décision</h2>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Région : <span class="text-sky-600"><?= htmlspecialchars($regionUser) ?></span></p>
            </div>
        </div>

        <div class="space-y-6">
            <div>
                <label class="block text-xs font-black text-slate-600 uppercase mb-2">Sélectionner un corps :</label>
                <select id="select_corps_dec" class="w-full bg-slate-50 border-2 border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 outline-none focus:border-indigo-500 transition-all">
                    <option value="">-- Choisir un corps --</option>
                    <?php foreach ($corpsList as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['libelle_corps']) ?> (Catégorie : <?= $c['categorie'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button onclick="ouvrirModalDecision()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 rounded-xl text-xs uppercase tracking-widest shadow-lg shadow-indigo-200 transition-all flex items-center justify-center gap-2">
                <i class="fas fa-sliders-h"></i> Configurer les considérants
            </button>
        </div>
    </div>
</div>

<!-- Modal Modificatrice -->
<div id="modalConfigDec" class="fixed inset-0 z-[9999] hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-5xl overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200">
        <div class="bg-indigo-600 p-6 text-white flex justify-between items-center">
            <div>
                <h3 class="font-black text-lg uppercase tracking-tight">Considérants de Décision</h3>
                <p id="modal_dec_corps_title" class="text-xs text-indigo-100 font-bold uppercase"></p>
            </div>
            <button onclick="fermerModalDec()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <textarea id="texte_considerants_dec" rows="12" class="w-full bg-slate-50 border-2 border-slate-200 rounded-2xl p-4 text-[16px] font-medium text-slate-800 leading-relaxed outline-none focus:border-indigo-500 focus:bg-white transition-all" placeholder="Saisir les considérants..."></textarea>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
            <button onclick="fermerModalDec()" class="px-6 py-3 rounded-xl bg-slate-200 text-slate-600 font-black text-xs uppercase hover:bg-slate-300">Annuler</button>
            <button onclick="enregistrerConsiderantsDec()" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-black text-xs uppercase hover:bg-indigo-700 shadow-lg shadow-indigo-200">Valider</button>
        </div>
    </div>
</div>

<script>
window.ouvrirModalDecision = function() {
    const corpsId = document.getElementById('select_corps_dec').value;
    if (!corpsId) {
        Swal.fire('Attention', 'Veuillez sélectionner un corps dans la liste.', 'warning');
        return;
    }

    const corpsText = document.getElementById('select_corps_dec').options[document.getElementById('select_corps_dec').selectedIndex].text;
    document.getElementById('modal_dec_corps_title').innerText = corpsText;

    const fd = new FormData();
    fd.append('action', 'get_texte');
    fd.append('corps_id', corpsId);

    fetch('api/dossiers/gerer_considerants_decision.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            document.getElementById('texte_considerants_dec').value = d.texte || '';
            document.getElementById('modalConfigDec').classList.remove('hidden');
        });
};

window.fermerModalDec = function() {
    document.getElementById('modalConfigDec').classList.add('hidden');
};

window.enregistrerConsiderantsDec = function() {
    const corpsId = document.getElementById('select_corps_dec').value;
    const texte = document.getElementById('texte_considerants_dec').value;

    const fd = new FormData();
    fd.append('action', 'save_texte');
    fd.append('corps_id', corpsId);
    fd.append('texte', texte);

    fetch('api/dossiers/gerer_considerants_decision.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Enregistré avec succès !', timer: 1500, showConfirmButton: false });
                window.fermerModalDec();
            } else {
                Swal.fire('Erreur', 'Échec de la sauvegarde.', 'error');
            }
        });
};
</script>
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php'; 

$alerte_id = isset($_GET['alerte_id']) ? trim($_GET['alerte_id']) : '';
$user_im   = isset($_SESSION['user_im']) ? trim($_SESSION['user_im']) : ''; 

if (empty($alerte_id) || empty($user_im)) {
    die("<div class='p-20 text-center font-black text-rose-500 uppercase tracking-widest'>Erreur : Session ou Alerte manquante</div>");
}

try {
    // 1. Récupération de la notification depuis la vue
    $stmt = $pdo->prepare("SELECT * FROM v_moteur_alertes WHERE alerte_id = ?");
    $stmt->execute([$alerte_id]);
    $notif = $stmt->fetch();

    if (!$notif) {
        die("<div class='p-20 text-center font-black text-slate-400 uppercase tracking-widest'>Notification introuvable</div>");
    }

    // --- LOGIQUE DE MASQUAGE DU BOUTON
    $masquerBouton = false;
    $motsClesMasquage = ['_DREN', '_FOP', '_CDE', '_SOLDE', '_PREFET', '_DRH', '_MTEFOP', '_PRIMATURE', '_SORTIE_ACTE', '_STEP', '_REJET'];
    foreach ($motsClesMasquage as $motCle) {
        if (str_contains($alerte_id, $motCle)) {
            $masquerBouton = true;
            break;
        }
    }

    // --- DÉTERMINATION DYNAMIQUE DU STYLE ET DES ÉTATS (REJET, STEP, SORTIE) ---
    $typeKey = $notif['type_key'] ?? '';
    
    // Valeurs par défaut issues de la notification
    $icone = $notif['icone'] ?? 'fas fa-bell';
    $couleurBase = $notif['couleur_code'] ?? 'slate';
    $badgeText = "Notification";

    // 1. Cas REJET (Alerte Rouge)
    if ($typeKey === 'DOS_REJET' || str_contains($alerte_id, '_REJET')) {
        $couleurBase = 'red';
        $icone = 'fas fa-times-circle';
        $badgeText = 'Dossier Rejeté';
    } 
    // 2. Cas STEP (Étape Bleue)
    elseif ($typeKey === 'DOS_STEP' || str_contains($alerte_id, '_STEP')) {
        $couleurBase = 'blue';
        $icone = !empty($notif['icone']) ? $notif['icone'] : 'fas fa-route';
        $badgeText = 'Étape de Traitement';
    } 
    // 3. Cas SORTIE (Validation Verte)
    elseif ($typeKey === 'SORTIE' || str_contains($alerte_id, '_SORTIE')) {
        $couleurBase = 'green';
        $icone = 'fas fa-check-double';
        $badgeText = 'Dossier Finalisé';
    }

    // Map de thèmes Tailwind
    $themes = [
        'blue'   => [
            'bg'          => 'bg-blue-50/70',
            'border'      => 'border-blue-200',
            'text'        => 'text-blue-900',
            'badge'       => 'bg-blue-600',
            'badge_light' => 'bg-blue-100 text-blue-700 border-blue-200',
            'ring'        => 'ring-blue-100'
        ],
        'red'    => [
            'bg'          => 'bg-rose-50/70',
            'border'      => 'border-rose-200',
            'text'        => 'text-rose-900',
            'badge'       => 'bg-rose-600',
            'badge_light' => 'bg-rose-100 text-rose-700 border-rose-200',
            'ring'        => 'ring-rose-100'
        ],
        'purple' => [
            'bg'          => 'bg-purple-50/70',
            'border'      => 'border-purple-200',
            'text'        => 'text-purple-900',
            'badge'       => 'bg-purple-600',
            'badge_light' => 'bg-purple-100 text-purple-700 border-purple-200',
            'ring'        => 'ring-purple-100'
        ],
        'cyan'   => [
            'bg'          => 'bg-cyan-50/70',
            'border'      => 'border-cyan-200',
            'text'        => 'text-cyan-900',
            'badge'       => 'bg-cyan-600',
            'badge_light' => 'bg-cyan-100 text-cyan-700 border-cyan-200',
            'ring'        => 'ring-cyan-100'
        ],
        'indigo' => [
            'bg'          => 'bg-indigo-50/70',
            'border'      => 'border-indigo-200',
            'text'        => 'text-indigo-900',
            'badge'       => 'bg-indigo-600',
            'badge_light' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
            'ring'        => 'ring-indigo-100'
        ],
        'orange' => [
            'bg'          => 'bg-amber-50/70',
            'border'      => 'border-amber-200',
            'text'        => 'text-amber-900',
            'badge'       => 'bg-amber-600',
            'badge_light' => 'bg-amber-100 text-amber-700 border-amber-200',
            'ring'        => 'ring-amber-100'
        ],
        'green'  => [
            'bg'          => 'bg-emerald-50/70',
            'border'      => 'border-emerald-200',
            'text'        => 'text-emerald-900',
            'badge'       => 'bg-emerald-600',
            'badge_light' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'ring'        => 'ring-emerald-100'
        ],
        'sky'    => [
            'bg'          => 'bg-sky-50/70',
            'border'      => 'border-sky-200',
            'text'        => 'text-sky-900',
            'badge'       => 'bg-sky-600',
            'badge_light' => 'bg-sky-100 text-sky-700 border-sky-200',
            'ring'        => 'ring-sky-100'
        ],
    ];

    $theme = $themes[$couleurBase] ?? [
        'bg'          => 'bg-slate-50/70',
        'border'      => 'border-slate-200',
        'text'        => 'text-slate-900',
        'badge'       => 'bg-slate-800',
        'badge_light' => 'bg-slate-100 text-slate-700 border-slate-200',
        'ring'        => 'ring-slate-100'
    ];

    // --- LOGIQUE DE REDIRECTION INTELLIGENTE ---
    $estTypeDos = ($typeKey === 'DOS');
    $id_cible = $alerte_id; 
    $numero_trouve = "";
    $estAttribue = false;

    if (!$masquerBouton) {
        if ($estTypeDos) {
            $stmt_orig = $pdo->prepare("SELECT alerte_id, numero_dos FROM demandes_numeros_dos 
                                         WHERE im = ? AND numero_dos IS NOT NULL AND numero_dos != ''
                                         ORDER BY date_attribution DESC LIMIT 1");
            $stmt_orig->execute([$user_im]);
            $dossier_orig = $stmt_orig->fetch();
            
            if ($dossier_orig) {
                $id_cible = $dossier_orig['alerte_id'];
                $numero_trouve = $dossier_orig['numero_dos'];
            }
            $estAttribue = true;
        } else {
            $stmt_check = $pdo->prepare("SELECT numero_dos FROM demandes_numeros_dos 
                                         WHERE im = ? AND alerte_id = ? 
                                         AND numero_dos IS NOT NULL AND numero_dos != '' LIMIT 1");
            $stmt_check->execute([$user_im, $alerte_id]);
            $check = $stmt_check->fetch();
            
            $estAttribue = ($check) ? true : false;
            $numero_trouve = $check['numero_dos'] ?? "";
        }

        if ($estAttribue) {
            $libelleBouton = "Finaliser la demande";
            $iconeBouton = "fa-file-signature";
            $styleBtn = "bg-emerald-600 hover:bg-emerald-700 shadow-emerald-200";
            $urlCible = "pages/dossiers/formulaire_demande.php?im=$user_im&alerte_id=$id_cible&mode=finaliser&num=$numero_trouve";
        } else {
            $libelleBouton = "Faire la demande";
            $iconeBouton = "fa-paper-plane";
            $styleBtn = "bg-indigo-600 hover:bg-indigo-700 shadow-indigo-200";
            $urlCible = "pages/dossiers/formulaire_demande.php?im=$user_im&alerte_id=$alerte_id";
        }
    }

} catch (Exception $e) {
    die("Erreur SQL : " . $e->getMessage());
}
?>

<div class="max-w-3xl mx-auto p-4 md:p-8 animate-in fade-in duration-500">
    <div class="bg-white rounded-[2rem] shadow-xl shadow-slate-100 border border-slate-100 overflow-hidden relative">    
        
        <!-- En-tête de la notification -->
        <div class="p-6 md:p-8 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 <?= $theme['badge'] ?> text-white rounded-2xl flex items-center justify-center shadow-lg ring-4 <?= $theme['ring'] ?> shrink-0">
                    <i class="<?= htmlspecialchars($icone) ?> text-2xl"></i>
                </div>
                <div class="space-y-1">
                    <span class="inline-block text-[10px] font-black uppercase tracking-widest px-2.5 py-0.5 rounded-full border <?= $theme['badge_light'] ?>">
                        <?= $badgeText ?>
                    </span>
                    <h1 class="text-lg md:text-xl font-black text-slate-800 tracking-tight leading-tight uppercase italic">
                        <?= htmlspecialchars($notif['titre'] ?? '') ?>
                    </h1>
                </div>
            </div>

            <?php if (!empty($notif['date_reception_technique'])): ?>
                <div class="text-xs text-slate-400 font-bold shrink-0 self-end sm:self-center bg-white px-3 py-1.5 rounded-xl border border-slate-100 shadow-sm">
                    <i class="far fa-clock mr-1 text-slate-400"></i>
                    <?= date('d/m/Y à H:i', strtotime($notif['date_reception_technique'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Corps du message -->
        <div class="p-6 md:p-8">
            <div class="p-6 rounded-2xl border-l-8 <?= $theme['border'] ?> <?= $theme['bg'] ?> transition-all duration-300">
                <p class="<?= $theme['text'] ?> text-base md:text-lg text-justify leading-relaxed font-semibold tracking-wide whitespace-pre-line"><?php 
                    $msgFinal = isset($notif['message']) ? trim($notif['message']) : '';
                    if (!empty($msgFinal)) {
                        echo nl2br(htmlspecialchars($msgFinal));
                    } else {
                        echo "Votre dossier concernant l'étape « " . htmlspecialchars($notif['titre'] ?? 'Suivi') . " » a bien été mis à jour par nos services.";
                    }
                ?></p>
            </div>

            <!-- Section du Bouton d'action / Note informative -->
            <?php if (!$masquerBouton): ?>
                <div class="flex flex-col items-center pt-8 border-t border-slate-100 mt-6">
                    <button onclick="loadPage('<?= $urlCible ?>', 'Détails de la demande')" 
                            class="w-full sm:w-auto px-10 py-4 <?= $styleBtn ?> text-white rounded-2xl font-black text-xs shadow-xl transition-all transform hover:-translate-y-0.5 active:scale-95 flex items-center justify-center gap-3 tracking-widest uppercase">
                        <i class="fas <?= $iconeBouton ?> text-sm"></i>
                        <span><?= $libelleBouton ?></span>
                    </button>
                </div>
            <?php else: ?>
                <div class="flex items-center gap-3 pt-6 text-slate-400 text-xs font-bold uppercase tracking-wider justify-center mt-4">
                    <i class="fas fa-info-circle"></i>
                    <span>Notification — Suivi de dossier</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

if (($_SESSION['user_type'] ?? '') !== 'admin') {
    echo "<div class='p-6 bg-rose-50 text-rose-600 rounded-2xl font-bold'>Accès refusé. Réservé à l'administrateur.</div>";
    exit;
}

try {
    $sql = "SELECT 
                s.id,
                s.sender_im,
                s.contenu AS message,
                s.statut,
                s.created_at,
                e.nom AS sender_nom, 
                e.prenoms AS sender_prenoms,
                p.lieu_de_service,
                p.type_etablissement,
                p.nom_etablissement,
                p.nom_fonction,
                p.nom_direction,
                p.nom_service,
                p.nom_division,
                d.sigle AS sigle_direction,
                sd.sigle AS sigle_service_dirmen,
                srv.sigle AS sigle_service,
                dv.sigle AS sigle_division,
                u.role_specifique,
                u.niveau
            FROM suggestions s
            LEFT JOIN personnel_etat_civil e ON s.sender_im = e.im
            LEFT JOIN personnel_poste_actuel p ON e.im = p.im
            LEFT JOIN utilisateurs u ON s.sender_im = u.im
            LEFT JOIN ref_directions d ON TRIM(p.nom_direction) = TRIM(d.nom_direction)
            LEFT JOIN ref_services_dirmen sd ON TRIM(p.nom_service) = TRIM(sd.nom_service)
            LEFT JOIN ref_services srv ON TRIM(p.nom_service) = TRIM(srv.nom)
            LEFT JOIN ref_divisions dv ON TRIM(p.nom_division) = TRIM(dv.nom)
            ORDER BY s.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $suggestions = [];
}

function FormaterIdentifiantEmetteur($msg) {
    $prenoms = !empty($msg['sender_prenoms']) ? trim($msg['sender_prenoms']) : (!empty($msg['sender_nom']) ? trim($msg['sender_nom']) : "Agent ({$msg['sender_im']})");
    $roleSpecifique = trim($msg['role_specifique'] ?? '');
    $niveau = strtolower(trim($msg['niveau'] ?? ''));

    if ($roleSpecifique === 'admin') {
        return 'Administrateur';
    }

    $lieuRaw = trim($msg['lieu_de_service'] ?? '');
    $typeEtab = strtoupper(trim($msg['type_etablissement'] ?? ''));
    $fonctionUpper = strtoupper(trim($msg['nom_fonction'] ?? ''));
    $nomEtab = trim($msg['nom_etablissement'] ?? '');

    $sigleDir = trim($msg['sigle_direction'] ?? $msg['nom_direction'] ?? '');
    $sigleServDirmen = trim($msg['sigle_service_dirmen'] ?? $msg['nom_service'] ?? '');
    $sigleService = trim($msg['sigle_service'] ?? $msg['nom_service'] ?? '');
    $sigleDivision = trim($msg['sigle_division'] ?? $msg['nom_division'] ?? '');

    $identifiantStructure = '';

    if ($roleSpecifique === 'resp_personnel_crfrp' && $niveau === 'crfrp') {
        $identifiantStructure = "Responsable Personnel {$lieuRaw}";
    } elseif (in_array($roleSpecifique, ['resp_non_encadre', 'resp_encadre', 'resp_solde', 'resp_retraite', 'resp_conge'])) {
        $roles = [
            'resp_non_encadre' => 'Responsable Non Encadré',
            'resp_encadre'     => 'Responsable Encadré',
            'resp_solde'       => 'Responsable Solde',
            'resp_retraite'    => 'Responsable Retraite',
            'resp_conge'       => 'Responsable Congé'
        ];
        $libelleRole = $roles[$roleSpecifique] ?? 'Responsable';

        if ($niveau === 'central') {
            $identifiantStructure = "{$libelleRole} DRH";
        } elseif (in_array($niveau, ['district', 'regional'])) {
            $identifiantStructure = "{$libelleRole} {$lieuRaw}";
        }
    }

    if (!$identifiantStructure) {
        if ($typeEtab === 'MEN CENTRAL') {
            if (str_contains($fonctionUpper, 'DIRECTEUR')) {
                $identifiantStructure = "{$sigleDir} (MEN)";
            } elseif (str_contains($fonctionUpper, 'CHEF DE SERVICE')) {
                $identifiantStructure = "Chef de Service {$sigleServDirmen} - {$sigleDir} (MEN)";
            } else {
                $identifiantStructure = "Personnel {$sigleServDirmen} - {$sigleDir} (MEN)";
            }
        } elseif ($typeEtab === 'DREN') {
            if (str_contains($fonctionUpper, 'DIRECTEUR')) {
                $identifiantStructure = $lieuRaw;
            } elseif (str_contains($fonctionUpper, 'CHEF DE SERVICE')) {
                $identifiantStructure = "Chef de Service {$sigleService} - {$lieuRaw}";
            } else {
                $identifiantStructure = "Personnel {$sigleService} - {$lieuRaw}";
            }
        } elseif ($typeEtab === 'CISCO') {
            if (str_contains($fonctionUpper, 'CHEF CISCO')) {
                $identifiantStructure = "Chef Cisco - {$lieuRaw}";
            } elseif (str_contains($fonctionUpper, 'CHEF DE DIVISION')) {
                $identifiantStructure = "Chef de Division {$sigleDivision} - {$lieuRaw}";
            } else {
                $identifiantStructure = "Personnel {$sigleDivision} - {$lieuRaw}";
            }
        } elseif (in_array($typeEtab, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
            $identifiantStructure = ($nomEtab && $lieuRaw) ? "{$nomEtab}/{$lieuRaw}" : ($nomEtab ?: $lieuRaw);
        } elseif ($typeEtab === 'CRFRP') {
            $identifiantStructure = $lieuRaw;
        } else {
            $identifiantStructure = $lieuRaw ?: $nomEtab;
        }
    }

    return !empty($identifiantStructure) ? "{$prenoms} (" . trim($identifiantStructure) . ")" : $prenoms;
}
?>

<!-- Ajout de p-[4mm] (ou style="padding: 4mm;") sur le conteneur principal -->
<div class="p-[4mm] space-y-6 animate-in fade-in duration-500" style="padding: 4mm;">
    <div class="flex justify-between items-center">
        <div>
            <!-- Titre en 16px -->
            <h2 class="text-[16px] font-black uppercase text-slate-800 tracking-tight">Suggestions des utilisateurs</h2>
            <p class="text-[15px] text-slate-500">Seul l'administrateur peut consulter les retours et suggestions.</p>
        </div>
        <span class="px-3 py-1 bg-amber-100 text-amber-700 font-bold rounded-full text-xs">
            <?= count($suggestions) ?> suggestion(s)
        </span>
    </div>

    <div class="grid grid-cols-1 gap-4">
        <?php if (empty($suggestions)): ?>
            <div class="bg-white p-8 rounded-2xl border border-slate-100 text-center text-slate-400 text-[15px]">
                <i class="fas fa-lightbulb text-3xl mb-2 text-slate-300"></i>
                <p>Aucune suggestion enregistrée pour le moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($suggestions as $item): ?>
                <?php 
                    $photoSrc = 'images/' . $item['sender_im'] . '.jpg';
                    $senderDisplay = FormaterIdentifiantEmetteur($item);
                ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex gap-4 items-start">
                    <img src="<?= $photoSrc ?>" onerror="this.src='images/default.png'" class="w-10 h-10 rounded-full object-cover border border-slate-200 shrink-0">
                    <div class="flex-1">
                        <div class="flex justify-between items-center mb-1">
                            <!-- Nom émetteur en 16px -->
                            <span class="text-[16px] font-bold text-slate-800"><?= htmlspecialchars($senderDisplay) ?></span>
                            <span class="text-[11px] text-slate-400"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></span>
                        </div>
                        <!-- Contenu en 15px -->
                        <p class="text-[15px] text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-100 leading-relaxed mt-2">
                            <?= nl2br(htmlspecialchars($item['message'])) ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
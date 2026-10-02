<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$im =$_SESSION['user_im'];

try {
    $stmt =$pdo->prepare("
        SELECT ec.nom, ec.prenoms, ec.date_naiss, sa.date_entree_admin 
        FROM personnel_etat_civil ec
        JOIN personnel_situation_actuelle sa ON ec.im = sa.im
        WHERE ec.im = ?
    ");
    $stmt->execute([$im]);
    $agent =$stmt->fetch();

    if (!$agent) die("Données agent introuvables.");

    $dateNais = new DateTime($agent['date_naiss']);
    $dateRetraite = (clone$dateNais)->modify('+60 years');
    $anneeRetraite = (int)$dateRetraite->format('Y');
    $isNeLe1er = ($dateNais->format('m-d') === '01-01');

    // Vérification de l'ancienneté (1 an minimum)
    $dateEntree = new DateTime($agent['date_entree_admin']);
    $aujourdhui = new DateTime();$interval = $dateEntree->diff($aujourdhui);
    $aMoinsDUnAn = ($interval->y < 1);

    // RÉCUPÉRATION DES ANNEES ET DE LEUR ETAT DE DECISION
    $stmt =$pdo->prepare("SELECT annee, num_decision FROM personnel_conges WHERE im = ?");
    $stmt->execute([$im]);
    $congesData =$stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indexer par année pour un accès rapide
    $dejaImprime = [];$avecNumDecision = [];
    foreach ($congesData as$row) {
        $dejaImprime[] = (int)$row['annee'];
        if (!empty($row['num_decision'])) {
            $avecNumDecision[] = (int)$row['annee'];
        }
    }

    $anneesEligibles = [];$anneeActuelle = (int)date('Y');
    
    // Logique de l'année de départ
    $anneeDepart = ($anneeActuelle >= $anneeRetraite && !$isNeLe1er) ? $anneeRetraite :$anneeActuelle - 1;

    // Limitation stricte à 3 années maximum
    if (!$aMoinsDUnAn) {
        for ($i = 0; $i < 3; $i++) {$an = $anneeDepart -$i;
            if ($an < (int)$dateEntree->format('Y')) break;

            // SI LA DÉCISION EXISTE DÉJÀ (num_decision RENSEIGNÉ), ON MASQUE/IGNORE CETTE ANNÉE
            if (in_array($an,$avecNumDecision)) {
                continue; 
            }

            $jours = 30;
            if ($an ==$anneeRetraite && !$isNeLe1er) {$m = (int)$dateRetraite->format('m');$d = (int)$dateRetraite->format('d');$f = ($d >= 28) ? 2.5 : (($d >= 21) ? 2 : (($d >= 16) ? 1.5 : (($d >= 9) ? 1 : (($d >= 4) ? 0.5 : 0))));$jours = (($m - 1) * 2.5) +$f;
            }
            
            $estDejaImprime = in_array($an, $dejaImprime);$anneesEligibles[] = [
                'annee' => $an, 
                'jours' => $jours,
                'deja_imprime' => $estDejaImprime
            ];
        }
    }

    $enCoursSansNum = array_diff($dejaImprime, $avecNumDecision);
    $nbDecisionsEnCours = count($enCoursSansNum);
    $qtyProjet = ($nbDecisionsEnCours > 0) ? ($nbDecisionsEnCours * 5) : 5;

} catch (Exception $e) { 
    die($e->getMessage()); 
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="assets/css/demande_conge.css">
    <style>
        .year-card.disabled-year {
            opacity: 0.55;
            pointer-events: none;
            background-color: #f1f5f9;
            border-color: #cbd5e1;
        }
        .year-card.disabled-year button {
            background-color: #94a3b8 !important;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="agent-header">
        <div class="main-line">
            <div class="agent-info">
                <span class="agent-name"><?= htmlspecialchars($agent['nom'].' '.$agent['prenoms']) ?></span>
                <span class="im-badge">IM: <?= $im ?></span>
            </div>
            <div style="font-weight: 700; color: var(--danger); font-size: 0.95rem;">
                <i class="fas fa-clock"></i> Retraite : <?= $dateRetraite->format('d/m/Y') ?>
            </div>
        </div>
        <div class="sub-details">
            <span><i class="fas fa-birthday-cake"></i> Date de naissance : <b><?= $dateNais->format('d/m/Y') ?></b></span>
            <span><i class="fas fa-sign-in-alt"></i> Date prise de service : <b><?= $dateEntree->format('d/m/Y') ?></b></span>
        </div>
    </div>

    <?php if ($aMoinsDUnAn): ?>
        <div class="empty-state">
            <i class="fas fa-user-shield" style="font-size: 2.6rem; margin-bottom: 12px; color: #cbd5e1;"></i>
            <h3>Accès restreint</h3>
            <p>L'agent doit avoir au moins 1 an de service pour bénéficier d'un congé.<br>
            Ancienneté actuelle : <b><?= $interval->y ?> an, <?= $interval->m ?> mois et <?= $interval->d ?> jours.</b></p>
        </div>
    <?php elseif (empty($anneesEligibles)): ?>
        <div class="empty-state" style="padding: 30px; text-align: center; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1; margin-top: 15px;">
            <i class="fas fa-check-double" style="font-size: 2.5rem; color: #16a34a; margin-bottom: 10px;"></i>
            <h3 style="color: #0f172a; margin: 0 0 6px 0;">Aucune année disponible</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0;">
                Vous n'avez pas de congé à demander en ce moment. Veuillez attendre l'année prochaine.
            </p>
        </div>
    <?php else: ?>
        <p style="font-size: 0.85rem; font-weight: 600; color: var(--slate); margin: 4px 0;">
            <i class="fas fa-hand-pointer"></i> Cliquez sur une année pour imprimer la décision correspondante :
        </p>

        <div class="year-grid">
            <?php foreach ($anneesEligibles as$ae): ?>
            <div class="year-card <?= $ae['deja_imprime'] ? 'disabled-year printed' : '' ?>" id="card-<?= $ae['annee'] ?>" data-annee="<?= $ae['annee'] ?>" onclick="selectYear('<?= $ae['annee'] ?>')">
                <span class="year-label"><?= $ae['annee'] ?></span>
                <div class="days-info"><?= str_replace('.', ',', $ae['jours']) ?> JOURS ACQUIS</div>
                
                <?php if ($ae['deja_imprime']): ?>
                    <div class="status-msg" style="display: block; color: #0284c7; font-weight: 700; margin-top: 8px;">
                        <i class="fas fa-check-circle"></i> Déjà imprimé
                    </div>
                <?php else: ?>
                    <button type="button"
                            class="btn-print-dec btn-annee-conge"
                            data-annee="<?= $ae['annee'] ?>"
                            onclick="doPrint(event, '<?= $ae['annee'] ?>', <?=$ae['jours'] ?>)">
                        <i class="fas fa-print"></i>
                        Imprimer Décision <?= $ae['annee'] ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div><br>

        <div class="footer-pieces-white">
            <div class="pieces-content">
                <h3 class="pieces-title-pro" style="margin-bottom: 6px; display: block;">
                    <i class="fas fa-folder-open"></i> Liste des pièces à fournir                    
                </h3>
                <ul class="pieces-list-2-cols">
                    <li>Demande avec avis du Chef hiérarchique <span class="qty">(03)</span></li>
                    <li>Projet de Décision de congé <span class="qty" id="qtyProjetDisplay">(<?= str_pad($qtyProjet, 2, '0', STR_PAD_LEFT) ?>)</span></li>                    
                    <li class="text-red">Photocopie certifiée dernier avancement <span class="qty">(03)</span></li>                    
                    <li>Attestation de non jouissance <span class="qty">(03)</span></li>
                    <li>Attestation de non interruption de service <span class="qty">(03)</span></li>
                    <li>Certificat administratif <span class="qty">(03)</span></li>                    
                    <li class="text-red">Souche BC ou avis de crédit <span class="qty">(03)</span></li>
                    <li class="text-red">Photocopie CIN <span class="qty">(03)</span></li>
                </ul>
            </div>
            
            <div class="action-footer">
                <button type="button" class="btn-full-green" onclick="verifierEtOuvrirDossier()">
                    <i class="fas fa-print"></i> IMPRIMER LE DOSSIER
                </button>
                <span style="font-weight: normal; font-size: 0.75rem; color: var(--danger); margin-left: 2px;">
                    <br>(N.B : Les pièces en rouge sont des pièces provenant de votre part, à joindre au dossier.)
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- MODALE D'AVERTISSEMENT : AUCUNE ANNÉE IMPRIMÉE -->
<div id="modalAvertissementAnnee" class="fixed inset-0 z-[400] hidden">
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-md"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md p-8 text-center">
            <div style="color: #eab308; font-size: 3rem; margin-bottom: 12px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 style="margin: 0; color: #0f172a; font-size: 1.15rem; font-weight: 800;">Aucune année choisie</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin: 12px 0 24px; line-height: 1.4;">
                Veuillez d'abord cliquer sur le bouton <b>"Imprimer Décision"</b> de l'année de congé que vous souhaitez demander avant d'imprimer le dossier.
            </p>
            <button type="button" onclick="fermerModalAvertissement()" 
                    class="w-full py-3.5 rounded-2xl bg-amber-500 text-white font-bold hover:bg-amber-600 transition-colors">
                Compris
            </button>
        </div>
    </div>
</div>

<!-- MODALE DE CONFIRMATION / SUITE DOSSIER -->
<div id="modalChoixSuiteConge" class="fixed inset-0 z-[350] hidden">
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-md"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md p-8 text-center">
            <div class="modal-confirm-icon icon-info" style="color: #0284c7; font-size: 2.5rem; margin-bottom: 15px;">
                <i class="fas fa-question-circle"></i>
            </div>
            <h3 style="margin: 0; color: #0f172a; font-size: 1.15rem; font-weight: 800;">Imprimer le dossier complet</h3>
            <p id="txtSuiteConge" style="color: #64748b; font-size: 0.88rem; margin: 12px 0 24px; line-height: 1.4;">
                Souhaitez-vous demander d'autres années ou finaliser l'impression du dossier pour l'année sélectionnée ?
            </p>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="flex-1 py-3 px-2 rounded-xl border border-slate-300 font-bold text-slate-700 hover:bg-slate-50 transition-colors" onclick="continuerAutresConges()">
                    <i class="fas fa-plus"></i> Demander d'autres
                </button>
                <button type="button" id="btnFinaliserSuiteConge" class="flex-1 py-3 px-2 rounded-xl bg-sky-600 text-white font-bold hover:bg-sky-700 transition-colors" onclick="poursuivreVersPrefet()">
                    Poursuivre <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODALE DE SELECTION ADRESSE PREFET -->
<div id="modalAdressePrefet" class="fixed inset-0 z-[300] hidden">
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-md"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md p-8">
            <h3 class="text-xl font-black text-slate-900 mb-6 text-center">Adressé à</h3>
            
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-2">Civilité</label>
                    <select id="selectGenre" onchange="actualiserLibelleDestinataire()" class="w-full border border-slate-300 rounded-2xl px-4 py-3 focus:outline-none focus:border-emerald-500">
                        <option value="Mr">Monsieur</option>
                        <option value="Mme">Madame</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-2">Destinataire</label>
                    <input id="inputDestinataire" type="text" readonly 
                           class="w-full border border-slate-200 bg-slate-50 rounded-2xl px-4 py-3 text-slate-700 font-semibold">
                </div>
            </div>

            <div class="flex gap-3 mt-8">
                <button type="button" onclick="fermerModalAdresse()" 
                    class="flex-1 py-4 rounded-2xl border border-slate-300 font-bold text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
                <button type="button" onclick="validerAdresseEtImprimer()" 
                    class="flex-1 py-4 rounded-2xl bg-emerald-600 text-white font-black hover:bg-emerald-700">
                    Valider et Imprimer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.anneesImprimees = new Set([
    <?php 
    $impSansNum = array_filter($anneesEligibles, fn($a) =>$a['deja_imprime']);
    echo implode(',', array_column($impSansNum, 'annee'));
    ?>
]);

function mettreAJourNombrePieces() {
    const count = window.anneesImprimees.size;
    const qty = (count > 0) ? (count * 5) : 5;
    const el = document.getElementById('qtyProjetDisplay');
    if (el) {
        el.textContent = `(${String(qty).padStart(2, '0')})`;
    }
}

window.selectYear = function (year) {
    const card = document.getElementById('card-' + year);
    if (card && !card.classList.contains('disabled-year')) {
        document.querySelectorAll('.year-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
    }
};

window.doPrint = function (event, year, joursTotal) {
    if (event) event.stopPropagation();

    // 1. Inscription AJAX dans la table personnel_conges
    const formData = new FormData();
    formData.append('im', '<?= $im ?>');
    formData.append('annee', year);
    formData.append('jours_total', joursTotal);

    fetch('documents/conges/enregistrer_conge.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // 2. Marquer l'année dans l'interface et la griser
            window.anneesImprimees.add(String(year));
            desactiverAnneeUI(year);
            mettreAJourNombrePieces();

            // 3. Télécharger le document de décision
            const downloadUrl = `documents/conges/generate_conge.php?im=<?= $im ?>&years=${year}&mode=decision`;
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = downloadUrl;
            document.body.appendChild(iframe);

            setTimeout(() => {
                if (iframe.parentNode) document.body.removeChild(iframe);
            }, 2000);
        } else {
            alert("Erreur lors de l'enregistrement : " + (data.error || "Erreur inconnue"));
        }
    })
    .catch(err => {
        console.error(err);
        alert("Erreur réseau lors de la sauvegarde.");
    });
};

function desactiverAnneeUI(annee) {
    const card = document.getElementById('card-' + annee);
    if (card) {
        card.classList.add('disabled-year', 'printed');
        card.onclick = null;
        
        // Cacher le bouton et afficher la mention "Déjà imprimé"
        const btn = card.querySelector('button');
        if (btn) btn.remove();

        if (!card.querySelector('.status-msg')) {
            const statusDiv = document.createElement('div');
            statusDiv.className = 'status-msg';
            statusDiv.style.cssText = 'display: block; color: #0284c7; font-weight: 700; margin-top: 8px;';
            statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> Déjà imprimé';
            card.appendChild(statusDiv);
        }
    }
}

window.verifierEtOuvrirDossier = function() {
    if (!window.anneesImprimees || window.anneesImprimees.size === 0) {
        window.ouvrirModalAvertissement();
    } else {
        window.ouvrirModalChoixSuite();
    }
};

window.ouvrirModalAvertissement = function() {
    document.getElementById('modalAvertissementAnnee').classList.remove('hidden');
};

window.fermerModalAvertissement = function() {
    document.getElementById('modalAvertissementAnnee').classList.add('hidden');
};

window.ouvrirModalChoixSuite = function() {
    const listAnnees = Array.from(window.anneesImprimees);
    listAnnees.sort((a, b) => a - b);
    const count = listAnnees.length;
    const anneesStr = listAnnees.join(', ');

    const btnFinaliser = document.getElementById('btnFinaliserSuiteConge');
    const txtSuite = document.getElementById('txtSuiteConge');

    if (count === 1) {
        if (btnFinaliser) btnFinaliser.innerHTML = `Imprimer l'année (${anneesStr}) <i class="fas fa-arrow-right"></i>`;
        if (txtSuite) txtSuite.innerHTML = `Vous avez sélectionné la décision de congé pour l'année <b>${anneesStr}</b>. Souhaitez-vous imprimer le dossier pour cette année ou en ajouter d'autres ?`;
    } else {
        if (btnFinaliser) btnFinaliser.innerHTML = `Imprimer ces ${count} années (${anneesStr}) <i class="fas fa-arrow-right"></i>`;
        if (txtSuite) txtSuite.innerHTML = `Vous avez imprimé les décisions pour les années <b>${anneesStr}</b>. Souhaitez-vous poursuivre l'impression du dossier pour ces années ?`;
    }

    document.getElementById('modalChoixSuiteConge').classList.remove('hidden');
};

window.fermerModalChoixSuite = function() {
    document.getElementById('modalChoixSuiteConge').classList.add('hidden');
};

window.continuerAutresConges = function() {
    window.fermerModalChoixSuite();
};

window.poursuivreVersPrefet = function() {
    window.fermerModalChoixSuite();
    window.ouvrirModalAdresse();
};

window.ouvrirModalAdresse = function() {
    actualiserLibelleDestinataire();
    document.getElementById('modalAdressePrefet').classList.remove('hidden');
};

window.fermerModalAdresse = function() {
    document.getElementById('modalAdressePrefet').classList.add('hidden');
};

window.actualiserLibelleDestinataire = function() {
    const genre = document.getElementById('selectGenre').value;
    const inputDest = document.getElementById('inputDestinataire');
    
    if (genre === 'Mme') {
        inputDest.value = "Madame Le Préfet";
    } else {
        inputDest.value = "Monsieur Le Préfet";
    }
};

window.validerAdresseEtImprimer = function() {
    const genre = document.getElementById('selectGenre').value;
    fermerModalAdresse();
    printAllPieces(genre);
};

window.printAllPieces = function (genre = 'Mr') {
    let selectedYears = Array.from(window.anneesImprimees);
    
    if (selectedYears.length > 0) {
        const downloadUrl = `documents/conges/generate_conge.php?im=<?= $im ?>&years=${selectedYears.join(',')}&mode=pieces&genre=${genre}`;
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = downloadUrl;
        document.body.appendChild(iframe);

        setTimeout(() => {
            if (iframe.parentNode) document.body.removeChild(iframe);
            if (typeof loadPage === 'function') {
                loadPage('pages/conges/demande_conge.php', 'Demande congé');
            }
        }, 3000); 
    }
};
</script>
</body>
</html>
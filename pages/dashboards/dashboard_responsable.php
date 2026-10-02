<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$user_im = $_SESSION['user_im'];

try {
    // 1. Récupération des informations du responsable connecté
    $stmt = $pdo->prepare("SELECT niveau, code_lieu_affectation, role_specifique FROM utilisateurs WHERE im = ?");
    $stmt->execute([$user_im]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $niv  = $user['niveau']; 
    $lieu = $user['code_lieu_affectation']; 
    $role = $user['role_specifique'];

    // Validation et restriction des onglets selon le rôle
    $active_tab = $_GET['tab'] ?? null;
    if (!$active_tab) {
        if (in_array($role, ['resp_non_encadre', 'resp_solde'])) {
            $active_tab = 'efa';
        } elseif ($role === 'resp_retraite') {
            $active_tab = 'admission_retraite';
        } else {
            $active_tab = 'fonc';
        }
    }

    // Sécurisation des accès par onglet
    if ($role === 'resp_non_encadre' && $active_tab === 'fonc') {
        $active_tab = 'efa';
    } elseif ($role === 'resp_encadre' && $active_tab === 'efa') {
        $active_tab = 'fonc';
    } elseif ($role === 'resp_retraite' && !in_array($active_tab, ['admission_retraite', 'compensatrice', 'installation'])) {
        $active_tab = 'admission_retraite';
    }

    // Condition de filtrage strict par statut d'agent selon le rôle responsable
    $condStatutRole = "1=1";
    if ($role === 'resp_non_encadre') {
        // Uniquement les agents dont la situation actuelle est Contractuel EFA
        $condStatutRole = "sa.statut_actuel = 'Contractuel EFA'";
    } elseif ($role === 'resp_encadre') {
        // Uniquement les agents dont la situation actuelle est Fonctionnaire
        $condStatutRole = "sa.statut_actuel = 'Fonctionnaire'";
    }

    // Définition des conditions de lieu
    if ($niv === 'district') {
        $condLieuPoste    = "ppa.nom_district = " . $pdo->quote($lieu) . " AND (ppa.type_etablissement IS NULL OR ppa.type_etablissement != 'CRFRP')";
        $condLieuPosteDos = $condLieuPoste;
        $colDosLieu       = "(d.dos_region IS NOT NULL OR d.dos_central IS NOT NULL)";
    } elseif ($niv === 'regional') {
        // Exclut le CRFRP pour le compteur d'agents général
        $condLieuPoste    = "ppa.nom_region = " . $pdo->quote($lieu) . " AND (ppa.type_etablissement IS NULL OR ppa.type_etablissement != 'CRFRP')";
        $condLieuPosteDos = "ppa.nom_region = " . $pdo->quote($lieu);
        $colDosLieu       = "d.dos_region";
    } elseif ($niv === 'central') {
        $condLieuPoste    = "ppa.lieu_de_service = " . $pdo->quote($lieu);
        $condLieuPosteDos = $condLieuPoste;
        $colDosLieu       = "d.dos_central";
    } else { // crfrp
        $condLieuPoste    = "ppa.nom_etablissement = " . $pdo->quote($lieu) . " AND ppa.type_etablissement = 'CRFRP'";
        $condLieuPosteDos = $condLieuPoste;
        $colDosLieu       = "(d.dos_region IS NOT NULL OR d.dos_central IS NOT NULL)";
    }

    // 2. Calcul des compteurs des cartes
    $countEFA = 0;
    $countFonc = 0;
    $countDemandeDos = 0;
    $countDosRecus = 0;
    $countDosEnvoyes = 0;
    $countRetraite = 0;

    // Compteurs spécifiques au responsable retraite
    $countAdmissionRetraite = 0;
    $countCompensatrice = 0;
    $countInstallation = 0;

    if ($role === 'resp_retraite') {
        // Condition de base pour les compteurs Retraite
        if ($niv === 'regional') {
            // Au niveau RÉGIONAL : Compter uniquement les agents ayant une demande de numéro DOS EN_ATTENTE
            $sqlRetraiteBase = "SELECT COUNT(DISTINCT sa.im) 
                                FROM personnel_situation_actuelle sa
                                JOIN personnel_poste_actuel ppa ON sa.im = ppa.im
                                JOIN v_moteur_alertes v ON sa.im = v.im
                                JOIN demandes_numeros_dos d ON sa.im = d.im 
                                    AND d.alerte_id = v.alerte_id 
                                    AND (d.statut = 'EN_ATTENTE' OR d.statut = 'ATTRIBUE')
                                    AND d.type_dos = :type_dos
                                WHERE v.alerte_id LIKE :suffixe 
                                AND $condLieuPoste";

            // 1. Admission retraite
            $stmtAdmission = $pdo->prepare(str_replace([':suffixe', ':type_dos'], ["'%_ADMISSION_RETRAITE'", "'Admission_retraite'"], $sqlRetraiteBase));
            $stmtAdmission->execute();
            $countAdmissionRetraite = (int)$stmtAdmission->fetchColumn();

            // 2. Compensatrice
            $stmtCompensatrice = $pdo->prepare(str_replace([':suffixe', ':type_dos'], ["'%_COMPENSATRICE'", "'Compensatrice'"], $sqlRetraiteBase));
            $stmtCompensatrice->execute();
            $countCompensatrice = (int)$stmtCompensatrice->fetchColumn();

            // 3. Installation
            $stmtInstallation = $pdo->prepare(str_replace([':suffixe', ':type_dos'], ["'%_INSTALLATION'", "'Installation'"], $sqlRetraiteBase));
            $stmtInstallation->execute();
            $countInstallation = (int)$stmtInstallation->fetchColumn();

        } else {
            // Pour les autres niveaux (District, Central, CRFRP) : logique existante
            $sqlRetraiteBase = "SELECT COUNT(DISTINCT sa.im) 
                                FROM personnel_situation_actuelle sa
                                JOIN personnel_poste_actuel ppa ON sa.im = ppa.im
                                JOIN v_moteur_alertes v ON sa.im = v.im
                                WHERE v.alerte_id LIKE :suffixe";

            if ($niv !== 'central') {
                $sqlRetraiteBase .= " AND $condLieuPoste";
            }

            // 1. Admission retraite
            $stmtAdmission = $pdo->prepare(str_replace(':suffixe', "'%_ADMISSION_RETRAITE'", $sqlRetraiteBase));
            $stmtAdmission->execute();
            $countAdmissionRetraite = (int)$stmtAdmission->fetchColumn();

            // 2. Compensatrice (Restriction MEN CENTRAL au niveau Central)
            $sqlCompensatrice = str_replace(':suffixe', "'%_COMPENSATRICE'", $sqlRetraiteBase);
            if ($niv === 'central') {
                $sqlCompensatrice .= " AND UPPER(TRIM(ppa.type_etablissement)) = 'MEN CENTRAL'";
            }
            $stmtCompensatrice = $pdo->prepare($sqlCompensatrice);
            $stmtCompensatrice->execute();
            $countCompensatrice = (int)$stmtCompensatrice->fetchColumn();

            // 3. Installation
            $stmtInstallation = $pdo->prepare(str_replace(':suffixe', "'%_INSTALLATION'", $sqlRetraiteBase));
            $stmtInstallation->execute();
            $countInstallation = (int)$stmtInstallation->fetchColumn();
        }
    } else {
        // --- 1. Contractuel EFA
        // --- 1. Contractuel EFA
        if (in_array($role, ['resp_non_encadre', 'resp_solde', 'resp_personnel_crfrp'])) {
            if ($role === 'resp_solde') {
                $sqlEfa = "SELECT COUNT(DISTINCT s.im_agent) 
                        FROM suivi_agents_bordereau s 
                        JOIN personnel_situation_actuelle psa ON s.im_agent = psa.im
                        JOIN personnel_poste_actuel ppa ON s.im_agent = ppa.im 
                        JOIN personnel_etat_civil pec ON s.im_agent = pec.im
                        WHERE psa.statut_actuel = 'Contractuel EFA' 
                            AND TIMESTAMPDIFF(YEAR, pec.date_naiss, CURDATE()) < 59
                            AND (s.statut_prefet = 'valide' OR s.statut_primature = 'valide' OR s.statut_men = 'valide') 
                            AND $condLieuPoste";
            } else {
                $sqlEfa = "SELECT COUNT(DISTINCT psa.im) 
                        FROM personnel_situation_actuelle psa 
                        JOIN personnel_poste_actuel ppa ON psa.im = ppa.im 
                        JOIN personnel_etat_civil pec ON psa.im = pec.im
                        WHERE psa.statut_actuel = 'Contractuel EFA' 
                            AND TIMESTAMPDIFF(YEAR, pec.date_naiss, CURDATE()) < 59
                            AND $condLieuPoste";
            }
            $countEFA = (int)$pdo->query($sqlEfa)->fetchColumn();
        }

        // --- 2. Fonctionnaire
        if (in_array($role, ['resp_encadre', 'resp_solde', 'resp_personnel_crfrp'])) {
            if ($role === 'resp_solde') {
                $sqlFonc = "SELECT COUNT(DISTINCT s.im_agent) 
                            FROM suivi_agents_bordereau s 
                            JOIN personnel_situation_actuelle psa ON s.im_agent = psa.im
                            JOIN personnel_poste_actuel ppa ON s.im_agent = ppa.im 
                            JOIN personnel_etat_civil pec ON s.im_agent = pec.im
                            WHERE psa.statut_actuel = 'Fonctionnaire' 
                                AND TIMESTAMPDIFF(YEAR, pec.date_naiss, CURDATE()) < 59
                                AND (s.statut_prefet = 'valide' OR s.statut_primature = 'valide' OR s.statut_men = 'valide') 
                                AND $condLieuPoste";
            } else {
                $sqlFonc = "SELECT COUNT(DISTINCT psa.im) 
                            FROM personnel_situation_actuelle psa 
                            JOIN personnel_poste_actuel ppa ON psa.im = ppa.im 
                            JOIN personnel_etat_civil pec ON psa.im = pec.im
                            WHERE psa.statut_actuel = 'Fonctionnaire' 
                                AND TIMESTAMPDIFF(YEAR, pec.date_naiss, CURDATE()) < 59
                                AND $condLieuPoste";
            }
            $countFonc = (int)$pdo->query($sqlFonc)->fetchColumn();
        }

        // --- 3. Retraité (>= 60 ans) - Uniquement pour resp_solde
        if ($role === 'resp_solde') {
            $sqlRetr = "SELECT COUNT(DISTINCT s.im_agent) 
                        FROM suivi_agents_bordereau s 
                        JOIN personnel_etat_civil pec ON s.im_agent = pec.im
                        JOIN personnel_poste_actuel ppa ON s.im_agent = ppa.im 
                        WHERE TIMESTAMPDIFF(YEAR, pec.date_naiss, CURDATE()) >= 60 
                        AND (s.statut_prefet = 'valide' OR s.statut_primature = 'valide') 
                        AND $condLieuPoste";
            $countRetraite = (int)$pdo->query($sqlRetr)->fetchColumn();
        }

        // --- Demande numéro DOS (EN_ATTENTE)
        $sqlDemDos = "SELECT COUNT(DISTINCT d.im) 
                    FROM demandes_numeros_dos d 
                    JOIN personnel_poste_actuel ppa ON d.im = ppa.im 
                    JOIN personnel_situation_actuelle sa ON d.im = sa.im
                    WHERE $colDosLieu IS NOT NULL AND d.statut = 'EN_ATTENTE' AND $condLieuPosteDos AND $condStatutRole";
        
        $countDemandeDos = (int)$pdo->query($sqlDemDos)->fetchColumn();

        // --- Numéro DOS reçus
        if (in_array($niv, ['district', 'crfrp'])) {
            if ($role === 'resp_personnel_crfrp') {
                $sqlRecus = "SELECT COUNT(d.id) 
                            FROM demandes_numeros_dos d 
                            JOIN personnel_poste_actuel ppa ON d.im = ppa.im 
                            JOIN personnel_situation_actuelle sa ON d.im = sa.im
                            WHERE $colDosLieu IS NOT NULL AND d.statut = 'ATTRIBUE' AND $condLieuPosteDos AND $condStatutRole";
            } else {
                $sqlRecus = "SELECT COUNT(DISTINCT d.im) 
                            FROM demandes_numeros_dos d 
                            JOIN personnel_poste_actuel ppa ON d.im = ppa.im 
                            JOIN personnel_situation_actuelle sa ON d.im = sa.im
                            WHERE $colDosLieu IS NOT NULL AND d.statut = 'ATTRIBUE' AND $condLieuPosteDos AND $condStatutRole";
            }
            $countDosRecus = (int)$pdo->query($sqlRecus)->fetchColumn();
        }

        // --- Numéro DOS envoyés
        if (in_array($niv, ['regional', 'central'])) {
            $sqlEnvoyes = "SELECT COUNT(DISTINCT d.im) 
                        FROM demandes_numeros_dos d 
                        JOIN personnel_poste_actuel ppa ON d.im = ppa.im 
                        JOIN personnel_situation_actuelle sa ON d.im = sa.im
                        WHERE $colDosLieu IS NOT NULL AND d.statut = 'ATTRIBUE' AND $condLieuPosteDos AND $condStatutRole";
            $countDosEnvoyes = (int)$pdo->query($sqlEnvoyes)->fetchColumn();
        }
    }

    // Messages pour tableau vide
    $emptyMessages = [
        'demande_dos'         => 'Aucun agent demandant numéro DOS pour le moment',
        'dos_recus'           => 'Aucun agent n\'a reçu de numéro DOS pour le moment',
        'dos_envoyes'         => 'Aucun numéro DOS n\'a été envoyé pour le moment',
        'efa'                 => 'Aucun agent contractuel EFA recensé pour le moment',
        'fonc'                => 'Aucun agent fonctionnaire recensé pour le moment',
        'retraite'            => 'Aucun agent retraité pour le moment',
        'admission_retraite'  => 'Aucun agent en admission à la retraite pour le moment',
        'compensatrice'       => 'Aucun agent éligible à la compensatrice pour le moment',
        'installation'        => 'Aucun agent éligible à l\'installation pour le moment'
    ];
    $messageVideActuel = $emptyMessages[$active_tab] ?? 'Aucun agent disponible pour le moment';

} catch (Exception $e) {
    die("Erreur de chargement des données : " . $e->getMessage());
}
?>

<!-- Styles CSS spécifiques pour la pagination personnalisée DataTables (rond de la couleur du header) -->
<style>
    /* Couleur du fond selon le rôle ou l'en-tête actif */
    <?php if ($role === 'resp_retraite'): ?>
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #b45309 !important; /* amber-700 */
            color: white !important;
            border-radius: 9999px !important;
            border: 1px solid #b45309 !important;
        }
    <?php elseif ($role === 'resp_solde'): ?>
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #0369a1 !important; /* sky-700 / blue */
            color: white !important;
            border-radius: 9999px !important;
            border: 1px solid #0369a1 !important;
        }
    <?php else: ?>
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #0284c7 !important; /* sky-600 */
            color: white !important;
            border-radius: 9999px !important;
            border: 1px solid #0284c7 !important;
        }
    <?php endif; ?>

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 9999px !important;
        padding: 0.3em 0.8em !important;
        margin: 0 2px;
    }
</style>

<div id="dashboard-view" class="p-4 space-y-6 flex flex-col h-full overflow-hidden">

    <!-- ================= 1. CARTES DE DASHBOARD ================= -->
    <div class="grid grid-cols-1 md:grid-cols-<?= ($role === 'resp_personnel_crfrp') ? '4' : '3' ?> lg:grid-cols-<?= ($role === 'resp_personnel_crfrp') ? '4' : '3' ?> gap-4 flex-none">
        
        <?php if ($role === 'resp_retraite'): ?>
            <!-- 1. Admission Retraite -->
            <div onclick="filtrerOnglet('admission_retraite')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-amber-600 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'admission_retraite' ? 'ring-4 ring-amber-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-amber-600 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-user-clock text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Admission Retraite</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-amber-600 tracking-tighter"><?= number_format($countAdmissionRetraite, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase text-right italic pointer-events-none">Demandes à traiter</p>
            </div>

            <!-- 2. Compensatrice -->
            <div onclick="filtrerOnglet('compensatrice')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-indigo-600 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'compensatrice' ? 'ring-4 ring-indigo-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-indigo-600 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-coins text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Compensatrice</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-indigo-600 tracking-tighter"><?= number_format($countCompensatrice, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase text-right italic pointer-events-none">Indemnités compensatrices</p>
            </div>

            <!-- 3. Installation -->
            <div onclick="filtrerOnglet('installation')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-emerald-600 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'installation' ? 'ring-4 ring-emerald-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-emerald-600 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-house-user text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Installation</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-emerald-600 tracking-tighter"><?= number_format($countInstallation, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase text-right italic pointer-events-none">Indemnités installation</p>
            </div>

        <?php elseif ($role === 'resp_non_encadre'): ?>
            <!-- Contractuel EFA -->
            <div onclick="filtrerOnglet('efa')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-sky-600 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'efa' ? 'ring-4 ring-sky-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-sky-600 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-users text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Contractuel EFA</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-sky-600 tracking-tighter"><?= number_format($countEFA, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase text-right italic pointer-events-none">Agents recensés</p>
            </div>

            <!-- Demande numéro DOS -->
            <div onclick="filtrerOnglet('demande_dos')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-rose-500 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'demande_dos' ? 'ring-4 ring-rose-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-rose-500 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-file-signature text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Demande numéro DOS</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-rose-500 tracking-tighter"><?= number_format($countDemandeDos, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-rose-400 uppercase text-right italic underline decoration-rose-100 pointer-events-none">En attente</p>
            </div>

            <!-- Numero DOS reçus / envoyés -->
            <?php if (in_array($niv, ['district', 'crfrp'])): ?>
                <div onclick="filtrerOnglet('dos_recus')" 
                    class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-emerald-500 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'dos_recus' ? 'ring-4 ring-emerald-200' : '' ?>">
                    <div class="absolute top-0 left-0 bg-emerald-500 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                        <i class="fas fa-box-open text-xs"></i>
                        <span class="text-xs font-black uppercase tracking-widest">Numero dos reçus</span>
                    </div>
                    <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                        <h3 class="text-5xl font-black text-emerald-500 tracking-tighter"><?= number_format($countDosRecus, 0, ',', ' ') ?></h3>
                    </div>
                    <p class="text-xs font-bold text-emerald-400 uppercase text-right italic pointer-events-none">Dos reçus</p>
                </div>
            <?php else: ?>
                <div onclick="filtrerOnglet('dos_envoyes')" 
                    class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-emerald-500 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'dos_envoyes' ? 'ring-4 ring-emerald-200' : '' ?>">
                    <div class="absolute top-0 left-0 bg-emerald-500 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                        <i class="fas fa-paper-plane text-xs"></i>
                        <span class="text-xs font-black uppercase tracking-widest">Numero dos envoyés</span>
                    </div>
                    <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                        <h3 class="text-5xl font-black text-emerald-500 tracking-tighter"><?= number_format($countDosEnvoyes, 0, ',', ' ') ?></h3>
                    </div>
                    <p class="text-xs font-bold text-emerald-400 uppercase text-right italic pointer-events-none">Dossiers traités</p>
                </div>
            <?php endif; ?>

        <?php elseif ($role === 'resp_encadre'): ?>
            <!-- Fonctionnaire -->
            <div onclick="filtrerOnglet('fonc')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-sky-600 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'fonc' ? 'ring-4 ring-sky-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-sky-600 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-user-tie text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Fonctionnaire</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-sky-600 tracking-tighter"><?= number_format($countFonc, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase text-right italic pointer-events-none">Agents recensés</p>
            </div>

            <!-- Demande numéro DOS -->
            <div onclick="filtrerOnglet('demande_dos')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-rose-500 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'demande_dos' ? 'ring-4 ring-rose-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-rose-500 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-file-signature text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Demande numéro DOS</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-rose-500 tracking-tighter"><?= number_format($countDemandeDos, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-rose-400 uppercase text-right italic underline decoration-rose-100 pointer-events-none">En attente</p>
            </div>

            <!-- Numero DOS envoyés -->
            <?php if (in_array($niv, ['district', 'crfrp'])): ?>                
                <div onclick="filtrerOnglet('dos_recus')" 
                    class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-emerald-500 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'dos_recus' ? 'ring-4 ring-emerald-200' : '' ?>">
                    <div class="absolute top-0 left-0 bg-emerald-500 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                        <i class="fas fa-box-open text-xs"></i>
                        <span class="text-xs font-black uppercase tracking-widest">Numero dos reçus</span>
                    </div>
                    <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                        <h3 class="text-5xl font-black text-emerald-500 tracking-tighter"><?= number_format($countDosRecus, 0, ',', ' ') ?></h3>
                    </div>
                    <p class="text-xs font-bold text-emerald-400 uppercase text-right italic pointer-events-none">Dos reçus</p>
                </div>
            <?php else: ?>
                <div onclick="filtrerOnglet('dos_envoyes')" 
                    class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-emerald-500 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'dos_envoyes' ? 'ring-4 ring-emerald-200' : '' ?>">
                    <div class="absolute top-0 left-0 bg-emerald-500 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                        <i class="fas fa-paper-plane text-xs"></i>
                        <span class="text-xs font-black uppercase tracking-widest">Numero dos envoyés</span>
                    </div>
                    <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                        <h3 class="text-5xl font-black text-emerald-500 tracking-tighter"><?= number_format($countDosEnvoyes, 0, ',', ' ') ?></h3>
                    </div>
                    <p class="text-xs font-bold text-emerald-400 uppercase text-right italic pointer-events-none">Dossiers traités</p>
                </div>
            <?php endif; ?>
        <?php elseif ($role === 'resp_solde'): ?>
            <!-- Contractuel EFA -->
            <div onclick="filtrerOnglet('efa')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-sky-600 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'efa' ? 'ring-4 ring-sky-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-sky-600 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-users text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Contractuel EFA</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-sky-600 tracking-tighter"><?= number_format($countEFA, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-sky-600 uppercase text-right italic pointer-events-none">À mandater (Validés)</p>
            </div>

            <!-- Fonctionnaire -->
            <div onclick="filtrerOnglet('fonc')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-blue-600 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'fonc' ? 'ring-4 ring-blue-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-blue-600 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-user-tie text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Fonctionnaire</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-blue-600 tracking-tighter"><?= number_format($countFonc, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-blue-600 uppercase text-right italic pointer-events-none">À mandater (Validés)</p>
            </div>

            <!-- Retraité -->
            <div onclick="filtrerOnglet('retraite')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-orange-500 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'retraite' ? 'ring-4 ring-orange-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-orange-500 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-blind text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Retraité (+60 ans)</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-orange-500 tracking-tighter"><?= number_format($countRetraite, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-orange-500 uppercase text-right italic pointer-events-none">Mandatement fin de carrière</p>
            </div>

        <?php elseif ($role === 'resp_personnel_crfrp'): ?>
            <!-- Contractuel EFA -->
            <div onclick="filtrerOnglet('efa')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-sky-600 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'efa' ? 'ring-4 ring-sky-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-sky-600 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-users text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Contractuel EFA</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-sky-600 tracking-tighter"><?= number_format($countEFA, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase text-right italic pointer-events-none">Agents recensés</p>
            </div>

            <!-- Fonctionnaire -->
            <div onclick="filtrerOnglet('fonc')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-sky-600 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'fonc' ? 'ring-4 ring-sky-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-sky-600 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-user-tie text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Fonctionnaire</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-sky-600 tracking-tighter"><?= number_format($countFonc, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase text-right italic pointer-events-none">Agents recensés</p>
            </div>

            <!-- Demande numéro DOS -->
            <div onclick="filtrerOnglet('demande_dos')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-rose-500 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'demande_dos' ? 'ring-4 ring-rose-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-rose-500 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-file-signature text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Demande numéro DOS</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-rose-500 tracking-tighter"><?= number_format($countDemandeDos, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-rose-400 uppercase text-right italic underline decoration-rose-100 pointer-events-none">En attente</p>
            </div>

            <!-- Numero DOS reçus -->
            <div onclick="filtrerOnglet('dos_recus')" 
                class="relative bg-white p-4 rounded-xl shadow-lg border-2 border-emerald-500 cursor-pointer hover:scale-[1.02] transition-all flex flex-col justify-between h-32 group overflow-hidden select-none <?= $active_tab === 'dos_recus' ? 'ring-4 ring-emerald-200' : '' ?>">
                <div class="absolute top-0 left-0 bg-emerald-500 text-white px-4 py-1.5 rounded-br-xl flex items-center gap-2 pointer-events-none">
                    <i class="fas fa-box-open text-xs"></i>
                    <span class="text-xs font-black uppercase tracking-widest">Numero dos reçus</span>
                </div>
                <div class="flex justify-end items-center h-full pt-6 pointer-events-none">
                    <h3 class="text-5xl font-black text-emerald-500 tracking-tighter"><?= number_format($countDosRecus, 0, ',', ' ') ?></h3>
                </div>
                <p class="text-xs font-bold text-emerald-400 uppercase text-right italic pointer-events-none">Dos reçus</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================= 2. CONTENU DU TABLEAU DYNAMIQUE ================= -->
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 flex-1 flex flex-col overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center">
            <?php
                if ($role === 'resp_retraite') {
                    switch ($active_tab) {
                        case 'admission_retraite':
                            $titre_onglet = "Liste des agents éligibles à l'admission à la retraite";
                            break;
                        case 'compensatrice':
                            $titre_onglet = "Liste des agents pour indemnité compensatrice";
                            break;
                        case 'installation':
                            $titre_onglet = "Liste des agents pour indemnité d'installation";
                            break;
                        default:
                            $titre_onglet = "Gestion des retraites";
                            break;
                    }
                } elseif ($role === 'resp_solde') {
                    switch ($active_tab) {
                        case 'efa':
                            $titre_onglet = "Liste des agents contractuels EFA éligibles au mandatement";
                            break;
                        case 'fonc':
                            $titre_onglet = "Liste des agents fonctionnaires éligibles au mandatement";
                            break;
                        case 'retraite':
                            $titre_onglet = "Liste des agents retraités éligibles au mandatement";
                            break;
                        default:
                            $titre_onglet = "Liste des agents éligibles au mandatement";
                            break;
                    }
                } else {
                    switch ($active_tab) {
                        case 'efa':
                            $titre_onglet = "Liste des agents contractuels EFA";
                            break;
                        case 'fonc':
                            $titre_onglet = "Liste des agents fonctionnaires";
                            break;
                        case 'demande_dos':
                            $titre_onglet = "Liste des agents en demande de numéro DOS";
                            break;
                        case 'dos_recus':
                            $titre_onglet = "Liste des agents ayant reçus de numéros DOS";
                            break;
                        case 'dos_envoyes':
                            $titre_onglet = "Liste des agents ayant reçus de numéros DOS";
                            break;
                        default:
                            $titre_onglet = "Liste des agents";
                            break;
                    }
                }
            ?>

            <h2 class="text-base font-black text-slate-800 uppercase tracking-wide">
                <?= $titre_onglet ?>
            </h2>
            <div class="relative max-w-xs w-full">
                <input type="text" id="tableSearch" placeholder="Rechercher par IM, Nom..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-sky-500">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            </div>
        </div>

        <div class="flex-1 p-5 overflow-auto">
            <?php if ($role === 'resp_retraite'): 
                $suffixeAlerte = '%_ADMISSION_RETRAITE';
                $typeDosCorrespondant = 'Admission_retraite';

                if ($active_tab === 'compensatrice') {
                    $suffixeAlerte = '%_COMPENSATRICE';
                    $typeDosCorrespondant = 'Compensatrice';
                } elseif ($active_tab === 'installation') {
                    $suffixeAlerte = '%_INSTALLATION';
                    $typeDosCorrespondant = 'Installation';
                }

                if ($niv === 'central') {
                    if ($active_tab === 'compensatrice') {
                        $whereRetraite = "v.alerte_id LIKE " . $pdo->quote($suffixeAlerte) . " AND UPPER(TRIM(ppa.type_etablissement)) = 'MEN CENTRAL'";
                    } else {
                        $whereRetraite = "v.alerte_id LIKE " . $pdo->quote($suffixeAlerte);
                    }
                } else {
                    $whereRetraite = "v.alerte_id LIKE " . $pdo->quote($suffixeAlerte) . " AND $condLieuPoste";
                    if ($niv === 'regional') {
                        $whereRetraite .= " AND (d.statut = 'EN_ATTENTE' OR d.statut = 'ATTRIBUE')";
                    }
                }
                $joinType = ($niv === 'regional') ? "INNER JOIN" : "LEFT JOIN";

                $sqlRetraite = "SELECT 
                                    ec.nom, 
                                    ec.prenoms, 
                                    ec.im, 
                                    sa.corps_actuel, 
                                    sa.grade_actuel, 
                                    ppa.type_etablissement, 
                                    ppa.nom_district, 
                                    ppa.nom_region, 
                                    ppa.nom_zap, 
                                    ppa.nom_etablissement, 
                                    ppa.nom_direction, 
                                    v.alerte_id,
                                    d.id AS dos_id,
                                    d.statut AS statut_dos
                                FROM personnel_situation_actuelle sa
                                INNER JOIN personnel_etat_civil ec ON sa.im = ec.im
                                INNER JOIN personnel_poste_actuel ppa ON sa.im = ppa.im
                                INNER JOIN v_moteur_alertes v ON sa.im = v.im
                                $joinType demandes_numeros_dos d 
                                    ON sa.im = d.im 
                                    AND d.alerte_id = v.alerte_id 
                                    AND d.type_dos = " . $pdo->quote($typeDosCorrespondant) . "
                                WHERE $whereRetraite
                                GROUP BY ec.im, v.alerte_id, d.id
                                ORDER BY ec.im ASC";

                $stmtRetraite = $pdo->query($sqlRetraite);
                $agentsRetraite = $stmtRetraite->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <table id="mainTable" class="w-full" style="font-size: 13px;">
                    <thead>
                        <tr class="bg-amber-700 text-white uppercase font-bold" style="font-size: 14px;">
                            <th class="p-3 text-center">N°</th>
                            <th class="p-3 text-center">Nom et prénoms</th>
                            <th class="p-3 text-center">IM</th>
                            <th class="p-3 text-center">Corps et Grade</th>
                            <th class="p-3 text-center">Localité de service</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($agentsRetraite)): $no = 1; foreach ($agentsRetraite as $ag): 
                            $current_im = $ag['im'] ?? '';
                            $nom_complet = ($ag['nom'] ?? '') . ' ' . ($ag['prenoms'] ?? '');
                            $current_alerte = $ag['alerte_id'] ?? '';
                            $current_dos_id = $ag['dos_id'] ?? '';
                            
                            $type_etab = strtoupper(trim($ag['type_etablissement'] ?? ''));
                            $localite = '-';

                            if ($niv === 'district') {
                                if ($type_etab === 'CISCO') {
                                    $localite = "BUREAU CISCO " . $ag['nom_district'];
                                } elseif (in_array($type_etab, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
                                    $localite = "ZAP " . $ag['nom_zap'] . " / " . $ag['nom_etablissement'];
                                } 
                            } elseif ($niv === 'regional') {
                                if ($type_etab === 'DREN') {
                                    $localite = "BUREAU DREN " . $ag['nom_region'];
                                } elseif ($type_etab === 'CISCO') {
                                    $localite = "BUREAU CISCO " . $ag['nom_district'];
                                } elseif (in_array($type_etab, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
                                    $localite = "CISCO " . $ag['nom_district'] . " / ZAP " . $ag['nom_zap'] . " / " . $ag['nom_etablissement'];
                                } 
                            } elseif ($niv === 'central') {
                                if ($type_etab === 'MEN CENTRAL') {
                                    $localite = !empty($ag['nom_direction']) ? $ag['nom_direction'] : 'MEN CENTRAL';
                                } elseif ($type_etab === 'DREN') {
                                    $localite = "DREN " . $ag['nom_region'];
                                } elseif ($type_etab === 'CISCO') {
                                    $localite = "CISCO " . $ag['nom_district'] . " (" . $ag['nom_region'] . ")";
                                } elseif ($type_etab === 'CRFRP') {
                                    $localite = $ag['nom_etablissement'];
                                } else {
                                    $localite = "CISCO " . $ag['nom_district'] . " / ZAP " . $ag['nom_zap'] . " / " . $ag['nom_etablissement'];
                                }
                            } elseif ($niv === 'crfrp') {
                                if ($type_etab === 'CRFRP') {
                                    $localite = $ag['nom_etablissement'];
                                }
                            }
                        ?>
                            <tr class="hover:bg-slate-50 transition-colors text-black" style="font-size: 13px;">
                                <td class="p-3 text-center font-bold text-black"><?= $no++ ?></td>
                                <td class="p-3 text-left font-semibold text-black"><?= htmlspecialchars($ag['nom'] . ' ' . $ag['prenoms']) ?></td>
                                <td class="p-3 text-center font-semibold text-black"><?= htmlspecialchars($ag['im']) ?></td>
                                <td class="p-3 text-left">
                                    <div class="font-semibold text-black"><?= htmlspecialchars($ag['corps_actuel'] ?? '-') ?></div>
                                    <div class="font-semibold text-black"><?= htmlspecialchars($ag['grade_actuel'] ?? '-') ?></div>
                                </td>
                                <td class="p-3 text-left font-medium text-black"><?= htmlspecialchars($localite) ?></td>
                                <td class="p-3 text-left">
                                    <?php if (in_array($active_tab, ['admission_retraite', 'compensatrice', 'installation'])): ?>
                                        <?php 
                                            $type_etab_agent = strtoupper(trim($ag['type_etablissement'] ?? ''));
                                            $statutDos = $ag['statut_dos'] ?? 'EN_ATTENTE';
                                        ?>

                                        <?php if ($niv === 'central'): ?>
                                            <!-- NIVEAU CENTRAL -->
                                            <?php if ($statutDos === 'ATTRIBUE'): ?>
                                                <button onclick="afficherModaleNumeroAttribue('<?= addslashes($current_im) ?>', '<?= htmlspecialchars(addslashes($nom_complet)) ?>', '<?= addslashes($current_alerte) ?>', '<?= (int)($current_dos_id ?? 0) ?>')" 
                                                        class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                                    <i class="fas fa-eye"></i> Voir N° DOS
                                                </button>
                                            <?php else: ?>
                                                <button onclick="voirHistoriqueAgent('<?= addslashes($current_im) ?>', '<?= addslashes($current_alerte) ?>')" 
                                                        class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                                    <i class="fas fa-check-circle"></i> Donner N° DOS
                                                </button>
                                            <?php endif; ?>

                                        <?php elseif ($niv === 'regional'): ?>
                                            <!-- NIVEAU REGIONAL -->
                                            <?php if ($active_tab === 'compensatrice'): ?>
                                                <?php if ($statutDos === 'ATTRIBUE'): ?>
                                                    <button onclick="afficherModaleNumeroAttribue('<?= addslashes($current_im) ?>', '<?= htmlspecialchars(addslashes($nom_complet)) ?>', '<?= addslashes($current_alerte) ?>', '<?= (int)($current_dos_id ?? 0) ?>')" 
                                                            class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                                        <i class="fas fa-eye"></i> Voir N° DOS
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="voirHistoriqueAgent('<?= addslashes($current_im) ?>', '<?= addslashes($current_alerte) ?>')" 
                                                            class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                                        <i class="fas fa-check-circle"></i> Donner N° DOS
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- Pour admission_retraite et installation au niveau régional -->
                                                <button onclick="afficherProjetActes('<?= addslashes($current_im) ?>', '<?= htmlspecialchars(addslashes($nom_complet)) ?>', '<?= addslashes($current_alerte) ?>')" 
                                                        class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                                    <i class="fas fa-tasks"></i> Traiter
                                                </button>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <!-- NIVEAU DISTRICT (ou tout autre niveau) : Toujours 'Traiter' -->
                                            <button onclick="afficherProjetActes('<?= addslashes($current_im) ?>', '<?= htmlspecialchars(addslashes($nom_complet)) ?>', '<?= addslashes($current_alerte) ?>')" 
                                                    class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                                <i class="fas fa-tasks"></i> Traiter
                                            </button>
                                        <?php endif; ?>

                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <?php elseif ($role === 'resp_solde'): 
                if ($active_tab === 'efa') {
                    $sqlSolde = "SELECT s.*, psa.statut_actuel, pec.nom, pec.prenoms, psa.corps_actuel, psa.grade_actuel
                                FROM suivi_agents_bordereau s
                                JOIN personnel_situation_actuelle psa ON s.im_agent = psa.im
                                JOIN personnel_etat_civil pec ON psa.im = pec.im
                                JOIN personnel_poste_actuel ppa ON s.im_agent = ppa.im
                                WHERE psa.statut_actuel = 'Contractuel EFA'
                                  AND (s.statut_prefet = 'valide' OR s.statut_primature = 'valide' OR s.statut_men = 'valide')
                                  AND LOWER(TRIM(s.type_bordereau)) IN ('renouvellement', 'avenant', 'integration')
                                  AND $condLieuPoste
                                GROUP BY s.im_agent";
                } elseif ($active_tab === 'fonc') {
                    $sqlSolde = "SELECT s.*, psa.statut_actuel, pec.nom, pec.prenoms, psa.corps_actuel, psa.grade_actuel
                                FROM suivi_agents_bordereau s
                                JOIN personnel_situation_actuelle psa ON s.im_agent = psa.im
                                JOIN personnel_etat_civil pec ON psa.im = pec.im
                                JOIN personnel_poste_actuel ppa ON s.im_agent = ppa.im
                                WHERE psa.statut_actuel = 'Fonctionnaire'
                                  AND (s.statut_prefet = 'valide' OR s.statut_primature = 'valide' OR s.statut_men = 'valide')
                                  AND LOWER(TRIM(s.type_bordereau)) IN ('titularisation', 'avancement_classe', 'avancement_echelon')
                                  AND $condLieuPoste
                                GROUP BY s.im_agent";
                } else {
                    $sqlSolde = "SELECT s.*, psa.statut_actuel, pec.nom, pec.prenoms, psa.corps_actuel, psa.grade_actuel
                                FROM suivi_agents_bordereau s
                                JOIN personnel_situation_actuelle psa ON s.im_agent = psa.im
                                JOIN personnel_etat_civil pec ON psa.im = pec.im
                                JOIN personnel_poste_actuel ppa ON s.im_agent = ppa.im
                                WHERE TIMESTAMPDIFF(YEAR, pec.date_naiss, CURDATE()) >= 60
                                  AND (s.statut_prefet = 'valide' OR s.statut_primature = 'valide' OR s.statut_men = 'valide')
                                  AND LOWER(TRIM(s.type_bordereau)) IN ('compensatrice', 'installation')
                                  AND $condLieuPoste";
                }

                $stmtSolde = $pdo->query($sqlSolde);
                $agentsSolde = $stmtSolde->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <table id="mainTable" class="w-full" style="font-size: 13px;">
                    <thead>
                        <tr class="bg-sky-700 text-white uppercase font-bold" style="font-size: 14px;">
                            <th class="p-3 text-center">N°</th>
                            <th class="p-3 text-center">Type demande</th>                            
                            <th class="p-3 text-center">Nom et prénoms</th>
                            <th class="p-3 text-center">IM</th>
                            <th class="p-3 text-center">Corps & Grade</th>
                            <th class="p-3 text-center">Situation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($agentsSolde)): $no = 1; foreach ($agentsSolde as $ag): 
                            $raw_type = strtolower(trim($ag['type_bordereau'] ?? ''));
                            $type_demande = match ($raw_type) {
                                'renouvellement'     => 'Renouvellement de contrat',
                                'avancement_classe'  => 'Avancement de classe',
                                'avancement_echelon' => "Avancement d'échelon",
                                'avenant'            => 'Avenant',
                                'integration'        => 'Intégration',
                                'titularisation'     => 'Titularisation',
                                'compensatrice'      => 'Indemnité de Compensatrice',
                                'installation'       => "Indemnité d'Installation",
                                default              => !empty($ag['type_bordereau']) ? $ag['type_bordereau'] : '-'
                            };
                        ?>
                            <tr class="hover:bg-slate-50 transition-colors text-black" style="font-size: 13px;">
                                <td class="p-3 text-center font-bold text-black"><?= $no++ ?></td>
                                <td class="p-3 text-left font-semibold text-black"><?= htmlspecialchars($type_demande) ?></td>
                                <td class="p-3 text-left font-semibold text-black"><?= htmlspecialchars($ag['nom'] . ' ' . $ag['prenoms']) ?></td>
                                <td class="p-3 text-center font-semibold text-black"><?= htmlspecialchars($ag['im_agent']) ?></td>
                                <td class="p-3 text-left">
                                    <div class="font-semibold text-black"><?= htmlspecialchars($ag['corps_actuel'] ?? '-') ?></div>
                                    <div class="font-semibold text-black"><?= htmlspecialchars($ag['grade_actuel'] ?? '-') ?></div>
                                </td>
                                <td class="p-3 text-left">
                                    <span class="font-bold text-green-600">En attente de mandatement</span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <?php elseif (in_array($active_tab, ['efa', 'fonc'])): 
                $statut_filtre = ($active_tab === 'efa') ? 'Contractuel EFA' : 'Fonctionnaire';

                $queryAgents = "
                    SELECT 
                        ec.nom, 
                        ec.prenoms, 
                        ec.im, 
                        ec.date_naiss,
                        sa.corps_actuel, 
                        sa.grade_actuel, 
                        ppa.type_etablissement, 
                        ppa.nom_district, 
                        ppa.nom_region, 
                        ppa.nom_zap, 
                        ppa.nom_etablissement, 
                        ppa.nom_direction, 
                        v.alerte_id
                    FROM personnel_situation_actuelle sa
                    INNER JOIN personnel_etat_civil ec ON sa.im = ec.im
                    INNER JOIN personnel_poste_actuel ppa ON sa.im = ppa.im
                    LEFT JOIN v_moteur_alertes v ON sa.im = v.im
                    WHERE sa.statut_actuel = ? AND $condLieuPoste
                    GROUP BY ec.im
                    ORDER BY ec.im ASC
                ";

                $stmtAgents = $pdo->prepare($queryAgents);
                $stmtAgents->execute([$statut_filtre]);
                $agents = $stmtAgents->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <table id="mainTable" class="w-full" style="font-size: 13px;">
                    <thead>
                        <tr class="bg-sky-700 text-white uppercase font-bold" style="font-size: 14px;">
                            <th class="p-3 text-center">N°</th>
                            <th class="p-3 text-center">Nom et prénoms</th>
                            <th class="p-3 text-center">IM</th>
                            <th class="p-3 text-center">Corps et Grade</th>
                            <th class="p-3 text-center">Localité de service</th>
                            <th class="p-3 text-center">Situation actuelle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($agents)): $no = 1; foreach ($agents as $ag): 
                            $type_etab = strtoupper(trim($ag['type_etablissement'] ?? ''));
                            $localite = '-';

                            if ($niv === 'district') {
                                if ($type_etab === 'CISCO') {
                                    $localite = "BUREAU CISCO " . $ag['nom_district'];
                                } elseif (in_array($type_etab, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
                                    $localite = "ZAP " . $ag['nom_zap'] . " / " . $ag['nom_etablissement'];
                                } 
                            } elseif ($niv === 'regional') {
                                if ($type_etab === 'DREN') {
                                    $localite = "BUREAU DREN " . $ag['nom_region'];
                                } elseif ($type_etab === 'CISCO') {
                                    $localite = "BUREAU CISCO " . $ag['nom_district'];
                                } elseif (in_array($type_etab, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
                                    $localite = "CISCO " . $ag['nom_district'] . " / ZAP " . $ag['nom_zap'] . " / " . $ag['nom_etablissement'];
                                } 
                            } elseif ($niv === 'central') {
                                if ($type_etab === 'MEN CENTRAL') {
                                    $localite = !empty($ag['nom_direction']) ? $ag['nom_direction'] : 'MEN CENTRAL';
                                } elseif ($type_etab === 'DREN') {
                                    $localite = "DREN " . $ag['nom_region'];
                                } elseif ($type_etab === 'CISCO') {
                                    $localite = "CISCO " . $ag['nom_district'] . " (" . $ag['nom_region'] . ")";
                                } elseif ($type_etab === 'CRFRP') {
                                    $localite = $ag['nom_etablissement'];
                                } else {
                                    $localite = "CISCO " . $ag['nom_district'] . " / ZAP " . $ag['nom_zap'] . " / " . $ag['nom_etablissement'];
                                }
                            } elseif ($niv === 'crfrp') {
                                if ($type_etab === 'CRFRP') {
                                    $localite = $ag['nom_etablissement'];
                                }
                            }
                        ?>
                            <tr class="hover:bg-slate-50 transition-colors text-black" style="font-size: 13px;">
                                <td class="p-3 text-center font-bold text-black"><?= $no++ ?></td>
                                <td class="p-3 text-left font-semibold text-black"><?= htmlspecialchars($ag['nom'] . ' ' . $ag['prenoms']) ?></td>
                                <td class="p-3 text-center font-semibold text-black"><?= htmlspecialchars($ag['im']) ?></td>
                                <td class="p-3 text-left">
                                    <div class="font-semibold text-black"><?= htmlspecialchars($ag['corps_actuel'] ?? '-') ?></div>
                                    <div class="font-semibold text-black"><?= htmlspecialchars($ag['grade_actuel'] ?? '-') ?></div>
                                </td>
                                <td class="p-3 text-left font-medium text-black"><?= htmlspecialchars($localite) ?></td>
                                <td class="p-3 text-left font-bold">
                                    <?php 
                                        $alerte = $ag['alerte_id'] ?? '';
                                        $estRetraite = false;
                                        $dateRetraiteFormat = '';

                                        if (!empty($ag['date_naiss'])) {
                                            $dateNaiss = new DateTime($ag['date_naiss']);
                                            $dateSoixanteAns = (clone $dateNaiss)->modify('+60 years');
                                            $aujourdhui = new DateTime();

                                            if ($aujourdhui >= $dateSoixanteAns) {
                                                $estRetraite = true;
                                                $dateRetraiteFormat = $dateSoixanteAns->format('d/m/Y');
                                            }
                                        }

                                        if ($estRetraite): 
                                    ?>
                                        <span class="font-bold text-red-600">Retraité le <?= $dateRetraiteFormat ?></span>
                                    <?php elseif (empty($alerte) || strpos($alerte, '_STEP_MANDATEMENT') !== false): ?>
                                        <span class="font-bold text-green-600">Situation à jour</span>
                                    <?php else: ?>
                                        <span class="font-bold text-red-600">Retard d'avancement</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <?php elseif (in_array($active_tab, ['demande_dos', 'dos_recus', 'dos_envoyes'])): 
                $statutDosFiltre = ($active_tab === 'demande_dos') ? 'EN_ATTENTE' : 'ATTRIBUE';
                
                $sqlDos = "SELECT 
                            MAX(d.id) AS id,
                            d.im, 
                            MAX(d.type_dos) AS type_dos, 
                            MAX(d.type_titre) AS type_titre, 
                            MAX(d.numero_dos) AS numero_dos, 
                            d.statut,
                            MAX(d.dos_region) AS dos_region, 
                            MAX(d.dos_district) AS dos_district, 
                            MAX(d.dos_crfrp) AS dos_crfrp, 
                            MAX(d.alerte_id) AS alerte_id, 
                            MAX(d.date_demande) AS date_demande, 
                            ec.nom, 
                            ec.prenoms, 
                            sa.corps_actuel, 
                            sa.grade_actuel, 
                            sa.statut_actuel, 
                            sa.date_entree_admin,
                            MAX(v.titre) AS objet_alerte
                        FROM demandes_numeros_dos d
                        INNER JOIN personnel_etat_civil ec ON d.im = ec.im
                        INNER JOIN personnel_situation_actuelle sa ON d.im = sa.im
                        LEFT JOIN personnel_poste_actuel ppa ON d.im = ppa.im
                        LEFT JOIN v_moteur_alertes v ON d.im = v.im AND d.alerte_id = v.alerte_id
                        WHERE d.statut = ? AND $condLieuPosteDos AND $colDosLieu IS NOT NULL AND $condStatutRole
                        GROUP BY d.im, d.statut, ec.nom, ec.prenoms, sa.corps_actuel, sa.grade_actuel, sa.statut_actuel, sa.date_entree_admin
                        ORDER BY date_demande ASC";

                $stmtDos = $pdo->prepare($sqlDos);
                $stmtDos->execute([$statutDosFiltre]);
                $demandes = $stmtDos->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <table id="mainTable" class="w-full" style="font-size: 13px;">
                    <thead>
                        <tr class="bg-[#0284c7] text-white uppercase font-bold" style="font-size: 14px;">
                            <th class="p-3 text-center">N°</th>
                            <th class="p-3 text-center">Date</th>
                            <th class="p-3 text-center">Objet de demande</th>
                            <th class="p-3 text-center">Nom et prénoms</th>
                            <th class="p-3 text-center">IM</th>
                            <th class="p-3 text-center">Corps & Grade</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($demandes)): $i = 1; foreach($demandes as $d): 
                            $current_alerte = $d['alerte_id'] ?? '';
                            $current_im = $d['im'] ?? '';
                            $current_statut = $d['statut_actuel'] ?? '';
                            $libelleAffichage = $d['objet_alerte'];
                            $type_dos = $d['type_dos'];
                            $type_titre = $d['type_titre'] ?? '';

                            if ($type_dos === 'Admission_retraite') {
                                $libelleAffichage = "Demande d'admission à la retraite";
                            } elseif ($type_dos === 'Compensatrice') {
                                $libelleAffichage = "Demande de compensatrice";
                            } elseif ($type_dos === 'Installation') {
                                $libelleAffichage = "Demande d'installation";
                            } elseif ($current_statut === 'Contractuel EFA') {
                                $ancienneteAns = 0;
                                if (!empty($d['date_entree_admin'])) {
                                    $dateEntree = new DateTime($d['date_entree_admin']);
                                    $dateAujourdhui = new DateTime();
                                    $interval = $dateEntree->diff($dateAujourdhui);
                                    $ancienneteAns = $interval->y; 
                                }
                                if ($type_dos === 'Intégration') {
                                    $libelleAffichage = "Demande d'intégration";
                                } elseif (strpos($current_alerte, 'RNC1') !== false || strpos($current_alerte, 'RNC2') !== false) {
                                    $libelleAffichage = "Demande de renouvellement de contrat";
                                } elseif (($type_dos === 'Avenant1' || $type_dos === 'Avenant2') && $ancienneteAns >= 6) {
                                    $libelleAffichage = "Demande d'avenant";
                                } elseif (preg_match('/Avenant([3-9]|[1-9][0-9]+)/', $type_dos)) {
                                    $libelleAffichage = "Demande d'avenant";
                                }
                            } elseif ($current_statut === 'Fonctionnaire') {
                                if ($type_dos === 'Titularisation') {
                                    $libelleAffichage = "Demande de titularisation";
                                } elseif ($type_dos === 'Avancement_classe') {
                                    $libelleAffichage = "Demande d'avancement de classe";
                                } elseif ($type_dos === 'Avancement_echelon') {
                                    $libelleAffichage = "Demande d'avancement d'échelon";
                                }
                            }
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100 text-black" style="font-size: 13px;">
                            <td class="p-3 text-center font-bold text-black"><?= $i++ ?></td>
                            <td class="p-3 text-left font-medium text-black"><?= date('d/m/Y', strtotime($d['date_demande'])) ?></td>
                            <td class="p-3 text-left font-semibold text-black"><?= htmlspecialchars($libelleAffichage) ?></td>
                            <td class="p-3 text-left font-semibold text-black"><?= htmlspecialchars($d['nom'].' '.$d['prenoms']) ?></td>
                            <td class="p-3 text-center font-semibold text-black"><?= htmlspecialchars($current_im) ?></td>
                            <td class="p-3 text-left">
                                <div class="font-medium text-black"><?= htmlspecialchars($d['corps_actuel'] ?? '') ?></div>
                                <div class="font-semibold text-black"><?= htmlspecialchars($d['grade_actuel'] ?? '') ?></div>
                            </td>
                            <td class="p-3 text-left">
                                <?php if ($statutDosFiltre === 'ATTRIBUE'): ?>
                                    <button onclick="afficherModaleNumeroAttribue('<?= addslashes($current_im) ?>', '<?= htmlspecialchars(addslashes($d['nom'].' '.$d['prenoms'])) ?>', '<?= addslashes($current_alerte) ?>', '<?= (int)$d['id'] ?>')" 
                                            class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                        <i class="fas fa-eye"></i> Voir N° DOS
                                    </button>
                                <?php else: ?>
                                    <?php if ($niv === 'central' || $niv === 'regional'): ?>
                                        <button onclick="voirHistoriqueAgent('<?= addslashes($current_im) ?>', '<?= addslashes($current_alerte) ?>')" 
                                                class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                            <i class="fas fa-check-circle"></i> Donner N° DOS
                                        </button>
                                    <?php else: ?>
                                        <?php if ($d['statut'] === 'EN_ATTENTE'): ?>
                                            <button onclick="afficherProjetActes('<?= addslashes($current_im) ?>', '<?= htmlspecialchars(addslashes($d['nom'].' '.$d['prenoms'])) ?>', '<?= addslashes($current_alerte) ?>')" 
                                                    class="bg-sky-600 hover:bg-sky-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                                <i class="fas fa-tasks"></i> Traiter
                                            </button>
                                        <?php else: ?>
                                            <span class="font-bold text-green-600">DOS reçu</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>
<script src="assets/js/historique.js"></script>
<script src="assets/js/dos.js"></script>
<link rel="stylesheet" href="assets/css/tailwind.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" onerror="this.onerror=null;this.src='assets/js/sweetalert2.all.min.js';"></script>
<script>
window.filtrerOnglet = function(tabName) {
    if (typeof loadPage === 'function') {
        loadPage(`pages/dashboards/dashboard_responsable.php?tab=${tabName}`, 'Tableau de bord', false);
    } else {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        window.location.href = url.toString();
    }
};

window.demanderDecision = function(im, typeDemande) {
    Swal.fire({
        title: 'Demande de décision',
        text: `Souhaitez-vous soumettre une demande de décision pour l'agent IM : ${im} (${typeDemande.replace('_', ' ')}) ?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, demander',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#0284c7'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Demande envoyée !',
                text: 'La demande de décision a été transmise avec succès.',
                icon: 'success',
                confirmButtonColor: '#0284c7'
            });
        }
    });
};

window.afficherModaleNumeroAttribue = function(im, nomAgent, alerteId, dosId) {
    Swal.fire({
        width: '700px',
        background: '#ffffff',
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-folder-open mr-2"></i> Visualiser les pièces',
        confirmButtonColor: '#0284c7',
        showCancelButton: true,
        cancelButtonText: 'Fermer',
        cancelButtonColor: '#94a3b8',
        html: `
            <div class="p-2 text-left">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">Numéro DOS Attribué</h3>
                        <p class="text-xs text-slate-400">${nomAgent} — IM : ${im}</p>
                    </div>
                </div>
                <div id="liste-numeros-dos-modal" class="overflow-x-auto">
                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <span class="text-sm">Chargement du numéro DOS...</span>
                    </div>
                </div>
            </div>
        `,
        didOpen: () => {
            fetch(`api/agents/fetch_data_agent.php?action=get_dossiers_attribues&im=${encodeURIComponent(im)}`)
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('liste-numeros-dos-modal');
                    if (!data.success || !data.dossiers || data.dossiers.length === 0) {
                        container.innerHTML = `
                            <div class="py-8 text-center text-slate-400 italic">
                                Aucun numéro DOS attribué trouvé pour cette demande.
                            </div>`;
                        return;
                    }

                    let rows = '';
                    data.dossiers.forEach((dos, index) => {
                        rows += `
                            <tr class="hover:bg-slate-50 border-b border-slate-100">
                                <td class="p-3 text-center font-bold text-slate-500">${index + 1}</td>
                                <td class="p-3 font-semibold text-slate-700">${dos.type_titre || '—'}</td>
                                <td class="p-3 font-medium text-indigo-600">${dos.type_dos || '—'}</td>
                                <td class="p-3 font-mono font-black text-emerald-600 text-center">${dos.numero_dos || '—'}</td>
                            </tr>`;
                    });

                    container.innerHTML = `
                        <table class="w-full text-sm border border-slate-200 rounded-xl overflow-hidden">
                            <thead>
                                <tr class="bg-[#0284c7] text-white">
                                    <th class="p-3 text-center">N°</th>
                                    <th class="p-3 text-center">Type Titre</th>
                                    <th class="p-3 text-center">Type DOS</th>
                                    <th class="p-3 text-center">Numéro DOS</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>`;
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('liste-numeros-dos-modal').innerHTML = `
                        <div class="py-8 text-center text-rose-500 font-bold">
                            Erreur de chargement des données.
                        </div>`;
                });
        },
    }).then((result) => {
        if (result.isConfirmed) {
            const url = `pages/dossiers/formulaire_demande.php?im=${encodeURIComponent(im)}&alerte_id=${encodeURIComponent(alerteId || '')}`;
            if (typeof loadPage === 'function') {
                loadPage(url, 'Génération des dossiers');
            } else {
                window.location.href = url;
            }
        }
    });
};

$(document).ready(function() {
    if ($('#mainTable').length > 0) {
        if ($.fn.DataTable.isDataTable('#mainTable')) {
            $('#mainTable').DataTable().clear().destroy();
        }

        var emptyMessageText = <?= json_encode($messageVideActuel) ?>;

        var emptyMessageHTML = `
            <div class="flex flex-col items-center justify-center py-10 text-slate-400 space-y-3">
                <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-300 text-3xl shadow-inner">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="text-center">
                    <p class="text-sm font-bold text-slate-600">${emptyMessageText}</p>
                </div>
            </div>
        `;

        var table = $('#mainTable').DataTable({
            language: { 
                url: "https://cdn.datatables.net/plug-ins/1.10.24/i18n/French.json",
                zeroRecords: emptyMessageHTML,
                emptyTable: emptyMessageHTML
            },
            pageLength: 10,
            searching: true,
            lengthChange: false,
            dom: 'rtip'
        });

        $('#tableSearch').on('keyup', function() {
            table.search(this.value).draw();
        });
    }
});

window.afficherProjetActes = function(im, nomAgent, alerteId) {
    const url = `pages/dossiers/formulaire_demande.php?im=${encodeURIComponent(im)}&alerte_id=${encodeURIComponent(alerteId || '')}`;

    if (typeof loadPage === 'function') {
        loadPage(url, 'Génération des dossiers');
    } else if (typeof chargerPage === 'function') {
        chargerPage(url);
    } else {
        window.location.href = url;
    }
};
</script>
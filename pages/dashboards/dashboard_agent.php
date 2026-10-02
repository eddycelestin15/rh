<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$im_user = $_SESSION['user_im'] ?? ''; 
if (empty($im_user)) { die("Session expirée."); }

// --- INITIALISATION DES VARIABLES GLOBALES ---
$nom_complet = trim(($_SESSION['user_nom'] ?? '') . ' ' . ($_SESSION['user_prenoms'] ?? ''));
$nom_brut = $_SESSION['user_nom'] ?? '';
$nom_seul = explode(' ', trim($nom_brut))[0]; 

// Vérification de la Situation Actuelle
$stmtSit = $pdo->prepare("SELECT * FROM personnel_situation_actuelle WHERE im = ? LIMIT 1");
$stmtSit->execute([$im_user]);
$sit = $stmtSit->fetch(PDO::FETCH_ASSOC);

// On définit $nom_corps même si $sit est vide pour éviter l'erreur Warning
$nom_corps = $sit['corps_actuel'] ?? 'Non renseigné';

if (!$sit): ?> 
<div class="w-full bg-slate-100 px-4 py-6 flex justify-center">

    <div class="relative bg-white border border-slate-100 shadow-2xl rounded-[1rem] px-6 md:px-10 py-4 md:py-6 max-w-6xl w-full text-center flex flex-col items-center">
        <div class="absolute -top-20 -right-20 w-64 h-64 bg-sky-50 rounded-full blur-3xl opacity-50"></div>
        <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-indigo-50 rounded-full blur-3xl opacity-50"></div>

        <div class="relative z-10 mb-2">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-sky-600 rounded-2xl shadow-lg">
                <i class="fas fa-user-shield text-white text-xl"></i>
            </div>
        </div>

        <div class="relative z-10 w-full mb-4">
            <h1 class="text-2xl md:text-4xl font-black text-slate-800 tracking-tight mb-2">
                Bienvenue sur votre espace personnel, <span class="text-sky-600 uppercase"><?= htmlspecialchars($nom_seul) ?></span>.
            </h1>
            <p class="text-slate-600 text-sm md:text-lg font-medium max-w-4xl mx-auto leading-tight">
                Ce compte a été conçu pour vous permettre de suivre automatiquement l’évolution de votre carrière (avancement de carrière).
            </p>
        </div>

        <div class="relative z-10 w-full mb-4">
            <p class="text-slate-600 text-sm md:text-lg font-medium max-w-4xl mx-auto leading-tight">
                Afin de profiter pleinement des fonctionnalités du système, nous vous invitons, dans un premier temps, à renseigner l’ensemble de vos informations personnelles, notamment :
            </p><br>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 px-2">
                <div class="flex flex-col items-center justify-center py-4 px-2 bg-slate-50 rounded-2xl border border-slate-100 shadow-sm hover:bg-white transition-colors">
                    <i class="fas fa-id-card text-sky-500 text-xl mb-2"></i>
                    <span class="text-[10px] font-black text-slate-700 uppercase text-center leading-tight">Votre état civil</span>
                </div>

                <div class="flex flex-col items-center justify-center py-4 px-2 bg-slate-50 rounded-2xl border border-slate-100 shadow-sm hover:bg-white transition-colors">
                    <i class="fas fa-graduation-cap text-sky-500 text-xl mb-2"></i>
                    <span class="text-[10px] font-black text-slate-700 uppercase text-center leading-tight">Vos diplômes<br>(Académiques & Professionnels)</span>
                </div>

                <div class="flex flex-col items-center justify-center py-4 px-2 bg-sky-50 rounded-2xl border border-sky-100 shadow-md">
                    <i class="fas fa-briefcase text-sky-600 text-xl mb-2"></i>
                    <span class="text-[10px] font-black text-sky-800 uppercase text-center leading-tight">Situation<br>Administrative</span>
                </div>

                <div class="flex flex-col items-center justify-center py-4 px-2 bg-slate-50 rounded-2xl border border-slate-100 shadow-sm hover:bg-white transition-colors">
                    <i class="fas fa-laptop-code text-sky-500 text-xl mb-2"></i>
                    <span class="text-[10px] font-black text-slate-700 uppercase text-center leading-tight">Compétences<br>Informatique</span>
                </div>

                <div class="flex flex-col items-center justify-center py-4 px-2 bg-slate-50 rounded-2xl border border-slate-100 shadow-sm hover:bg-white transition-colors">
                    <i class="fas fa-map-marker-alt text-sky-500 text-xl mb-2"></i>
                    <span class="text-[10px] font-black text-slate-700 uppercase text-center leading-tight">Lieu<br>d’affectation</span>
                </div>
            </div>
        </div><br>

        <div class="relative z-10 w-full flex flex-col items-center">
            <p class="text-slate-500 text-sm md:text-base font-medium italic mb-4">
                Pour compléter ces informations, veuillez cliquer sur le bouton ci-dessous.
            </p>
            <button onclick="loadPage('pages/personnel/formulaire.php', 'Information personnel')" 
                    class="group flex items-center gap-4 bg-[#0f172a] text-white py-4 px-14 rounded-2xl font-black uppercase tracking-[0.2em] text-[11px] hover:bg-sky-600 transition-all duration-300 active:scale-95 shadow-2xl shadow-sky-100">                    
                <i class="fas fa-file-signature text-sky-400 group-hover:text-white transition-colors text-lg"></i>                    
                <span>Ouvrir le formulaire</span>                    
                <i class="fas fa-chevron-right text-[10px] group-hover:translate-x-2 transition-transform opacity-50"></i>
            </button>
        </div>
    </div>
</div>
<?php return; endif; ?>
<?php

// Récupération du matricule de l'agent connecté
$userIm = $_SESSION['user_im'];

try {
    // 1. Récupération de l'État Civil
    $stmtCivil = $pdo->prepare("SELECT nom, prenoms, date_naiss, lieu_naiss, cin, date_cin, lieu_cin, num_whatsapp, adress_mail, adresse FROM personnel_etat_civil WHERE im = ?");
    $stmtCivil->execute([$userIm]);
    $civil = $stmtCivil->fetch(PDO::FETCH_ASSOC);

    // 2. Récupération de la Situation Administrative (Corps, Grade, Entrée Admin)
    $stmtAdmin = $pdo->prepare("SELECT corps_actuel, grade_actuel, date_entree_admin, statut_actuel FROM personnel_situation_actuelle WHERE im = ?");
    $stmtAdmin->execute([$userIm]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    // 3. Récupération du Poste, Fonction et Établissement
    $stmtPoste = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
    $stmtPoste->execute([$userIm]);
    $poste = $stmtPoste->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<div class='p-4 text-rose-700 font-extrabold text-lg'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

// ---- LOGIQUE DE CALCUL DE LA RETRAITE (60 ANS) ----
$dateRetraiteAffichage = "";
if (!empty($civil['date_naiss'])) {
    $dateNaissance = new DateTime($civil['date_naiss']);
    $dateNaissance->modify('+60 years');
    $dateRetraiteAffichage = $dateNaissance->format('d/m/Y');
}

// ---- LOGIQUE D'AFFICHAGE DYNAMIQUE DES MATIÈRES / MODULES ----
$labelMatiere = "";
$valeurMatiere = "";

$fonctionCode = mb_strtolower($poste['nom_fonction'] ?? '');
$typeFonction = mb_strtolower($poste['type_fonction'] ?? '');

if (str_contains($fonctionCode, 'formateur')) {
    $labelMatiere = "Module enseigné";
    $valeurMatiere = $poste['nom_matiere'] ?? '';
} elseif (str_contains($typeFonction, 'enseignant')) {
    $labelMatiere = "Matière enseignée";
    $valeurMatiere = $poste['nom_matiere'] ?? '';
}

// ---- LIEU DE SERVICE
$typeEtab   = $poste['type_etablissement'] ?? '';
$nomRegion  = $poste['nom_region'] ?? '';
$nomDistrict= $poste['nom_district'] ?? '';
$nomZap    = $poste['nom_zap'] ?? '';
$nomEtab    = $poste['nom_etablissement'] ?? '';
$nomDir     = $poste['nom_direction'] ?? '';

$lieuService = "";
if ($typeEtab === 'MEN CENTRAL') {
    $lieuService = !empty($nomDir) ? $nomDir : '-';
} elseif ($typeEtab === 'DREN') {
    $lieuService = "BUREAU DREN " . $nomRegion;
} elseif ($typeEtab === 'CISCO') {
    $lieuService = "BUREAU CISCO " . $nomDistrict;
} elseif ($typeEtab === 'CRFRP') {
    $lieuService = !empty($nomEtab) ? $nomEtab : '-';
} elseif (in_array($typeEtab, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
    $lieuService = "ZAP " . $nomZap . " / " . $nomEtab;
} else {
    $lieuService = !empty($nomEtab) ? $nomEtab : '-';
}

// Gestion de la photo de profil
$photoRelative = 'images/' . $userIm . '.jpg';
$displayPhoto = file_exists(APP_ROOT . '/' . $photoRelative)
    ? $photoRelative
    : "https://ui-avatars.com/api/?name=" . urlencode($civil['nom'] ?? 'Agent') . "&background=0ea5e9&color=fff";
?>

<div class="px-2 py-2 w-full max-w-full animate-in fade-in duration-200">
    
    <div class="bg-white rounded-2xl shadow-xl border border-slate-300 p-6 w-full">
        
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-950 rounded-xl p-6 text-white flex flex-col lg:flex-row items-center lg:items-end justify-between gap-6 border border-slate-800 mb-6 w-full">
            <!-- Partie Gauche : Photo + Infos Textes -->
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 w-full lg:w-auto">
                <img src="<?= $displayPhoto ?>" alt="Photo" class="w-24 h-24 rounded-xl object-cover ring-4 ring-sky-500 shadow-2xl shrink-0">
                
                <div class="flex flex-col gap-2 text-center sm:text-left w-full">
                    <!-- Nom et IM -->
                    <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3">
                        <h2 class="text-2xl font-black uppercase text-white tracking-wide">
                            <?= htmlspecialchars(($civil['nom'] ?? '') . ' ' . ($civil['prenoms'] ?? '')) ?>
                        </h2>
                        <span class="italic text-sky-200 text-sm">
                            (IM: <?= htmlspecialchars($userIm) ?>)
                        </span>
                    </div> 
                    
                    <!-- STATUT (Ajouté au-dessus de corps et grade) -->
                    <div class="text-base font-bold text-slate-300 mt-1">
                        <span class="uppercase tracking-wider">Statut :</span>
                        <span class="text-sky-200 font-medium">
                            <?= htmlspecialchars($admin['statut_actuel'] ?? 'Statut non défini') ?>
                        </span>
                    </div>

                    <!-- Corps et Grade -->
                    <div class="text-base font-bold text-slate-300 mt-1">
                        <span>Corps et grade :</span>
                        <span class="text-sky-200 font-medium">
                            <?= htmlspecialchars($admin['corps_actuel'] ?? 'Corps non défini') ?>,
                        </span>
                        <span class="text-sky-200 font-medium">
                            <?= htmlspecialchars($admin['grade_actuel'] ?? 'Grade non défini') ?>
                        </span>                        
                    </div>

                    <!-- Date de retraite visible UNIQUEMENT sur mobile/tablette (masquée sur PC) -->
                    <div class="text-base font-bold text-slate-300 lg:hidden mt-1">
                        <span>Date de retraite : </span>
                        <span class="uppercase text-sky-200"><?= $dateRetraiteAffichage ?></span>
                    </div>
                </div>
            </div>          
            
            <!-- Partie Droite : Date de retraite alignée à droite (visible UNIQUEMENT sur PC/grand écran) -->
            <div class="hidden lg:flex flex-col items-end text-right border-l border-slate-800 pl-6 h-full justify-center shrink-0">
                <span class="text-xs uppercase tracking-widest text-slate-400 font-bold mb-1">Date de retraite</span>
                <span class="text-xl font-black text-sky-400 font-mono tracking-wide bg-sky-950/40 px-3 py-1.5 rounded-lg border border-sky-900/50 shadow-inner">
                    <?= $dateRetraiteAffichage ?>
                </span>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-6 text-base font-bold text-slate-950">
            
            <div class="space-y-4">
                <h3 class="text-lg font-black uppercase tracking-wider text-indigo-700 border-b-2 border-slate-200 pb-2 flex items-center gap-2">
                    <i class="fas fa-user-circle"></i> Renseignements Civils & Contacts
                </h3>
                
                <div class="flex justify-between items-center py-2 border-b border-slate-100 whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider">Date & Lieu de Naissance</span>
                    <span>
                        <?= $civil['date_naiss'] ? date('d/m/Y', strtotime($civil['date_naiss'])) : '-' ?>
                        à <?= htmlspecialchars($civil['lieu_naiss'] ?? '-') ?></span>
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-slate-100 whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider">Carte d'Identité (CIN)</span>
                    <span>
                        N° <?= htmlspecialchars($civil['cin'] ?? '-') ?> du <?= $civil['date_cin'] ? date('d/m/Y', strtotime($civil['date_cin'])) : '-' ?> à <?= htmlspecialchars($civil['lieu_cin'] ?? '-') ?></span>
                    </span>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-slate-100 whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider">Téléphone / WhatsApp</span>
                    </i> <?= htmlspecialchars($civil['num_whatsapp'] ?? '-') ?>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-slate-100 whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider">Adresse E-mail</span>
                    <?= htmlspecialchars($civil['adress_mail'] ?? '-') ?>
                </div>

                <div class="flex justify-between items-center py-2 whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider">Adresse Résidentielle</span>
                    <?= htmlspecialchars($civil['adresse'] ?? '-') ?>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-black uppercase tracking-wider text-sky-700 border-b-2 border-slate-200 pb-2 flex items-center gap-2">
                    <i class="fas fa-gavel"></i> Situation Administrative & Poste
                </h3>

                <div class="flex justify-between items-center py-2 border-b border-slate-100 whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider">Entrée dans l'Administration</span>
                    <?= !empty($admin['date_entree_admin']) ? date('d/m/Y', strtotime($admin['date_entree_admin'])) : '-' ?>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-slate-100 whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider">Type de Fonction</span>
                    <?= htmlspecialchars($poste['type_fonction'] ?? '-') ?>
                </div>

                <div class="flex justify-between items-center py-2 border-b border-slate-100 whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider">Fonction Assignée</span>
                    <?= htmlspecialchars($poste['nom_fonction'] ?? '-') ?>
                </div>

                <div class="flex justify-between items-center py-2 <?= !empty($labelMatiere) ? 'border-b border-slate-100' : '' ?> whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider">Localité de Service</span>
                    <?= htmlspecialchars($lieuService) ?>
                </div>

                <?php if (!empty($labelMatiere)): ?>
                <div class="flex justify-between items-center py-2 whitespace-nowrap">
                    <span class="text-slate-500 uppercase text-xs tracking-wider"><?= $labelMatiere ?></span>
                    <?= htmlspecialchars($valeurMatiere) ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
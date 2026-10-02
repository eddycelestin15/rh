<?php 
    require_once __DIR__ . '/includes/bootstrap.php';
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/check_session.php'; 

    $userIm = $_SESSION['user_im'];

    try {
        $stmt = $pdo->prepare("SELECT code_lieu_affectation, niveau FROM utilisateurs WHERE im = ?");
        $stmt->execute([$userIm]);
        $user = $stmt->fetch();
        
        $lieuResponsable = $user['code_lieu_affectation'] ?? '';
        $userNiveau = isset($user['niveau']) ? trim($user['niveau']) : ''; 

    } catch (Exception $e) {
        $lieuResponsable = "";
        $userNiveau = "";
    }

    $userNom     = $_SESSION['user_nom'];
    $userPrenoms = $_SESSION['user_prenoms'] ?? '';
    $userType    = trim($_SESSION['user_type'] ?? '');
    $userRole    = trim($_SESSION['user_role'] ?? '');
    $userRoleAgent = $_SESSION['user_role'] ?? 'agent';

    $titresResponsabilite = [
        'admin'                => 'Administrateur Système',
        'chef_service'         => 'Chef de Service',
        'chef_division'        => 'Chef de Division',
        'resp_encadre'         => 'Responsable personnel encadré',
        'resp_non_encadre'     => 'Responsable personnel non encadré',
        'resp_solde'           => 'Responsable Solde',       
        'resp_retraite'        => 'Responsable Retraite',
        'resp_personnel_crfrp' => 'Responsable Personnel',
        'agent'                => 'Compte Agent'
    ];
    $userTitreResponsabilite = $titresResponsabilite[$userRole] ?? 'Utilisateur';

    $photoRelative = 'images/' . $userIm . '.jpg';
        $defaultPhoto  = 'images/default.png';

        $displayPhoto = file_exists(APP_ROOT . '/' . $photoRelative)
            ? $photoRelative
            : $defaultPhoto;

    $isAdmin = ($userType === 'admin');
    $isSuperResp = (in_array($userRole, ['chef_service', 'chef_division', 'resp_personnel_crfrp']) || $isAdmin);

    $isRespNonEncadre = ($userRole === 'resp_non_encadre' || $isSuperResp);
    $isRespEncadre = ($userRole === 'resp_encadre' || $isSuperResp);
    $isRespRetraite = ($userRole === 'resp_retraite' || $isSuperResp);
    $isRespSolde = ($userRole === 'resp_solde' || $isSuperResp);
    $isRespPersCRFRP = ($userRole === 'resp_personnel_crfrp' || $isSuperResp);
    
    $nbMessages = 0; 
    $nbNotifs = 0;

    // =========================================================================
    // SELECTION DU TABLEAU DE BORD EN FONCTION DU MODE DE VUE ($activeView)
    // =========================================================================
    if ($activeView === 'agent') {
        $dashboardPage = 'pages/dashboards/dashboard_agent.php';
    } else {
        if ($isAdmin) {
            $dashboardPage = 'pages/dashboards/dashboard_admin.php'; 
        } elseif (in_array($userRole, ['chef_service', 'chef_division'])) {
            $dashboardPage = 'pages/dashboards/dashboard_principal.php'; 
        } elseif (in_array($userRole, ['resp_personnel_crfrp', 'resp_encadre', 'resp_non_encadre', 'resp_solde', 'resp_retraite'])) {
            $dashboardPage = 'pages/dashboards/dashboard_responsable.php';
        } else {
            $dashboardPage = 'pages/dashboards/dashboard_agent.php';
        }
    }
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="assets/images/grh.png">
    <title>Gestion personnel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="assets/css/index.css?v=<?php echo time(); ?>">
</head>
<body class="loading bg-slate-50 min-h-screen flex flex-col">
    <div id="refresh-loader">
        <img src="assets/images/grh.png" alt="Logo" class="loader-logo">
        <div class="loader-text">
            Chargement en cours...
        </div>
    </div>
    
    <aside class="sidebar fixed inset-y-0 left-0 bg-white text-slate-600 z-30 flex flex-col shadow-xl border-r border-slate-100">
        <div class="h-20 flex items-center px-4 gap-3 border-b border-slate-100 shrink-0">
            <img src="Logo/grh.png" alt="Logo GRH" class="w-9 h-9 object-contain shrink-0">
            
            <div class="flex flex-col justify-center leading-tight min-w-0 flex-1">
                <?php if ($activeView !== 'agent'): ?>
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider truncate">
                        Ressources Humaines
                    </span>
                <?php else: ?>
                    <span class="text-[10px] font-bold text-sky-600 uppercase tracking-wider truncate">
                        Espace Agent
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($lieuResponsable)): ?>
                    <span class="logo-text font-extrabold text-base tracking-tighter text-sky-600 italic uppercase leading-tight break-words">
                        <?= htmlspecialchars($lieuResponsable) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <nav class="flex-1 mt-1 px-2 overflow-y-auto">
            <button onclick="loadPage('<?php echo $dashboardPage; ?>', 'Tableau de bord')" class="nav-item flex items-center gap-4 w-full p-2 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                <i class="fas fa-home w-6 text-center text-lg text-sky-400 group-hover:text-sky-300"></i>                
                <span class="menu-text font-bold text-xs uppercase tracking-widest text-sky-900/80 group-hover:text-white">
                    Tableau de bord
                </span>
            </button>

            <!-- ========================================================================= -->
            <!-- AFFICHAGE DES MENUS SELON $activeView -->
            <!-- ========================================================================= -->

            <?php if ($activeView === 'agent'): ?>

                <!-- MENUS AGENT COMPLET -->
                <div class="section-title text-[12px] uppercase font-black px-4 pt-2 pb-2 text-slate-600 tracking-[0.2em] menu-text border-b-2 border-sky-500/40 mb-3">
                    Espace personnel
                </div>
                
                <div class="mb-1.5">
                    <div class="submenu-container">
                        <button onclick="toggleAccordion(this, 'sub-renseignement')" class="submenu-btn nav-item flex items-center gap-2 w-full p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition-all group">
                            <i class="fas fa-edit w-6 text-center text-sky-500"></i>
                            <span class="menu-text font-semibold text-sm flex-1 text-left">Renseignements</span>
                            <i class="fas fa-chevron-down text-[10px] menu-text transition-transform duration-300"></i>
                        </button>
                        <div id="sub-renseignement" class="submenu-content ml-4 border-l-4 border-sky-500">
                            <button onclick="handleSubClick('pages/updates/etat_civil.php', 'Etat civil')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Etat civil</button>
                            <button onclick="handleSubClick('pages/updates/diplomes.php', 'Diplomes')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Diplomes</button>
                            <button onclick="handleSubClick('pages/updates/situation_admin.php', 'Situation administratif actuelle')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Situation administratif</button>
                            <button onclick="handleSubClick('pages/updates/poste_actuel.php', 'Localité de service')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Poste actuel</button>
                        </div>
                    </div>
                </div>

                <div class="mb-1.5">
                    <div class="submenu-container">
                        <button onclick="toggleAccordion(this, 'sub-hist')" class="submenu-btn nav-item flex items-center gap-2 w-full p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition-all group">
                            <i class="fas fa-file-invoice w-6 text-center text-sky-500"></i>
                            <span class="menu-text font-semibold text-sm flex-1 text-left">Historiques</span>
                            <i class="fas fa-chevron-down text-[10px] menu-text transition-transform duration-300"></i>
                        </button>
                        <div id="sub-hist" class="submenu-content ml-4 border-l-4 border-sky-500">
                            <button onclick="handleSubClick('pages/personnel/avancements.php', 'Avancement')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Avancement</button>
                            <button onclick="handleSubClick('pages/personnel/affectations.php', 'Affectation')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Affectation</button>
                        </div>
                    </div>
                </div>

                <div class="mb-1.5">
                    <div class="submenu-container">
                        <button onclick="toggleAccordion(this, 'sub-conge')" class="submenu-btn nav-item flex items-center gap-2 w-full p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition-all group">
                            <i class="fas fa-calendar-alt w-6 text-center text-emerald-500"></i>
                            <span class="menu-text font-semibold text-sm flex-1 text-left">Congés</span>
                            <i class="fas fa-chevron-down text-[10px] menu-text transition-transform duration-300"></i>
                        </button>
                        <div id="sub-conge" class="submenu-content ml-4 border-l-4 border-sky-500">
                            <button onclick="handleSubClick('pages/conges/historique_conge.php', 'Historique congé')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Historiques</button>
                            <button onclick="handleSubClick('pages/conges/demande_conge.php', 'Demande congé')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Demande décision</button>
                            <button onclick="handleSubClick('pages/conges/prendre_conge.php', 'Prendre congé')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Prendre congés</button>
                        </div>
                    </div>
                </div>

                <div class="mb-1.5">
                    <div class="submenu-container">
                        <button onclick="toggleAccordion(this, 'sub-dist')" class="submenu-btn nav-item flex items-center gap-2 w-full p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition-all group">
                            <i class="fas fa-medal w-6 text-center text-amber-500"></i>
                            <span class="menu-text font-semibold text-sm flex-1 text-left">Distinction honorifiques</span>
                            <i class="fas fa-chevron-down text-[10px] menu-text transition-transform duration-300"></i>
                        </button>
                        <div id="sub-dist" class="submenu-content ml-4 border-l-4 border-sky-500">
                            <button onclick="handleSubClick('pages/personnel/demande_distinction.php', 'Demande de distinction')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Demande</button>
                            <button onclick="handleSubClick('pages/personnel/historique_distinction.php', 'Historique des distinctions')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">Historique</button>
                        </div>
                    </div>
                </div>

                <div class="mb-1.5">
                    <button onclick="closeAllMenus(); loadPage('pages/personnel/allocation_familiale.php', 'Allocations familiales')" class="nav-item flex items-center gap-2 w-full p-2 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                        <i class="fas fa-users w-6 text-center text-rose-400"></i>
                        <span class="menu-text font-semibold text-sm">Allocation familliale</span>
                    </button>
                </div>

                <div class="mb-1.5">
                    <button onclick="closeAllMenus(); loadPage('pages/profil/documents.php', 'Documents Administratifs')" class="nav-item flex items-center gap-2 w-full p-2 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                        <i class="fas fa-print w-6 text-center text-slate-400 group-hover:text-sky-400"></i>
                        <span class="menu-text font-semibold text-sm">Documents administratifs</span>
                    </button>
                </div>

                <div class="section-title text-[12px] uppercase font-black px-2 pt-4 pb-2 text-slate-600 tracking-[0.2em] menu-text border-b-2 border-sky-500/40 mb-3">
                    Suivre des dossiers
                </div>
                <button onclick="loadPage('pages/dossiers/dossiers_en_cours.php', 'Mes dossiers en cours')" class="nav-item flex items-center gap-2 w-full p-2 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                    <i class="fas fa-eye w-6 text-center text-sky-400"></i>                   
                    <span class="menu-text font-semibold text-sm">Dossiers en cours</span>
                </button>

            <?php else: ?>

                <!-- MENUS RESPONSABLE & ADMIN -->
                <?php if ($isAdmin): ?>
                    <div class="section-title text-[12px] uppercase font-black px-4 pt-4 pb-2 text-slate-600 tracking-[0.2em] menu-text border-b-2 border-sky-500/40 mb-3">
                        Administration
                    </div>

                    <div class="mb-1.5">
                        <div class="submenu-container">
                            <button onclick="toggleAccordion(this, 'sub-import')" class="submenu-btn nav-item flex items-center gap-2 w-full p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition-all group">
                                <i class="fas fa-file-import w-6 text-center text-sky-500"></i>
                                <span class="menu-text font-semibold text-sm flex-1 text-left">Importation de données</span>
                                <i class="fas fa-chevron-down text-[10px] menu-text transition-transform duration-300"></i>
                            </button>
                            <div id="sub-import" class="submenu-content ml-4 border-l-4 border-sky-500">
                                <button onclick="handleSubClick('actions/personnel/import_donnees.php', 'Importer des données')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-sky-50 hover:text-sky-600 transition-colors font-medium">
                                    <i class="fas fa-upload mr-2 text-[10px]"></i>Importer
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-1.5">
                        <div class="submenu-container">
                            <button onclick="toggleAccordion(this, 'sub-creation-compte')" class="submenu-btn nav-item flex items-center gap-2 w-full p-2 rounded-xl hover:bg-slate-100 hover:text-slate-800 transition-all group">
                                <i class="fas fa-user-plus w-6 text-center text-indigo-500"></i>
                                <span class="menu-text font-semibold text-sm flex-1 text-left">Création compte</span>
                                <i class="fas fa-chevron-down text-[10px] menu-text transition-transform duration-300"></i>
                            </button>
                            <div id="sub-creation-compte" class="submenu-content ml-4 border-l-4 border-indigo-500">
                                <button onclick="handleSubClick('creation_compte_dren.php', 'Nouveau Compte Direction')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors font-medium">
                                    <i class="fas fa-building mr-2 text-[10px]"></i>Direction
                                </button>
                                <button onclick="handleSubClick('creation_compte_cisco.php', 'Nouveau Compte CISCO')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors font-medium">
                                    <i class="fas fa-school mr-2 text-[10px]"></i>Cisco
                                </button>
                                <button onclick="handleSubClick('pages/admin/creer_responsable.php', 'Nouveau Compte Responsable RH')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors font-medium">
                                    <i class="fas fa-user-shield mr-2 text-[10px]"></i>Responsable RH
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (($isRespEncadre || $isRespNonEncadre) && ($userNiveau === 'regional' || $userNiveau === 'central') && !$isAdmin): ?>
                    <div class="section-title text-[12px] uppercase font-black px-2 pt-4 pb-2 text-slate-600 tracking-[0.2em] menu-text border-b-2 border-sky-500/40 mb-3">
                        Configuration
                    </div>

                    <!-- Affiché pour les deux rôles (RespEncadre ET RespNonEncadre) -->
                    <button onclick="loadPage('api/dossiers/gerer_considerants_decision.php', 'Projet de décision')" 
                            class="nav-item flex items-center gap-4 w-full p-3.5 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                        <i class="fas fa-file-signature w-6 text-center text-indigo-400 group-hover:text-sky-400 transition-colors"></i>
                        <span class="menu-text font-semibold text-sm flex-1 text-left">Projet de décision</span>
                    </button> 

                    <!-- Affiché UNIQUEMENT si l'utilisateur est RespEncadre -->
                    <?php if ($isRespEncadre): ?>
                        <div class="mb-1.5">
                            <div class="submenu-container">
                                <button onclick="toggleAccordion(this, 'sub-arrete')" class="submenu-btn nav-item flex items-center gap-2 w-full p-3.5 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                                    <i class="fas fa-scroll w-6 text-center text-indigo-400 group-hover:text-sky-400 transition-colors"></i>
                                    <span class="menu-text font-semibold text-sm flex-1 text-left">Projet d'arrêté</span>
                                    <i class="fas fa-chevron-down text-[10px] menu-text transition-transform duration-300"></i>
                                </button>
                                <div id="sub-arrete" class="submenu-content ml-4 border-l-4 border-indigo-500">
                                    <button onclick="handleSubClick('api/dossiers/gerer_considerants_arrete.php?type=classe', 'Projet d\'arrêté - Classe')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors font-medium">
                                        <i class="fas fa-award mr-2 text-[10px]"></i>Avancement de classe
                                    </button>
                                    <button onclick="handleSubClick('api/dossiers/gerer_considerants_arrete.php?type=echelon', 'Projet d\'arrêté - Échelon')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors font-medium">
                                        <i class="fas fa-step-forward mr-2 text-[10px]"></i>Avancement d'échelon
                                    </button>
                                    <button onclick="handleSubClick('api/dossiers/gerer_considerants_arrete.php?type=titularisation', 'Projet d\'arrêté - Titularisation')" class="block w-full text-left p-2 pl-8 text-xs text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors font-medium">
                                        <i class="fas fa-user-check mr-2 text-[10px]"></i>Titularisation
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="section-title text-[12px] uppercase font-black px-2 pt-4 pb-2 text-slate-600 tracking-[0.2em] menu-text border-b-2 border-sky-500/40 mb-3">
                    Suivre des dossiers
                </div>

                <button onclick="loadPage('pages/dossiers/bordereau_envoi.php', 'Bordereau d\'envoi')" class="nav-item flex items-center gap-2 w-full p-3.5 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                    <i class="fas fa-shipping-fast w-6 text-center text-amber-500"></i>
                    <span class="menu-text font-semibold text-sm">Bordereau d'envoi</span>
                </button>

                <?php if ($isRespSolde || $userNiveau === 'regional' || $userNiveau === 'central' || $userRole === 'resp_personnel_crfrp' || $userNiveau === 'district'): ?>
                    <button onclick="loadPage('pages/dossiers/reference_dossiers.php', 'Référence de dossiers')" class="nav-item flex items-center gap-2 w-full p-3.5 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                        <i class="fas fa-folder-plus w-6 text-center text-emerald-400"></i>
                        <span class="menu-text font-semibold text-sm">Référence des dossiers</span>
                    </button>
                <?php endif; ?>

                <?php if ($userRole !== 'resp_solde'): ?>
                    <?php if ($isRespPersCRFRP || in_array($userNiveau, ['regional', 'central', 'district'])): ?>
                        <button onclick="loadPage('pages/dossiers/traitement_dossiers.php', 'Traitement de dossiers')" class="nav-item flex items-center gap-2 w-full p-3.5 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                            <i class="fas fa-tasks w-6 text-center text-blue-400"></i>
                            <span class="menu-text font-semibold text-sm">Traitement des dossiers</span>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (($isRespSolde || $isRespPersCRFRP) && !$isAdmin): ?>
                    <div class="section-title text-[12px] uppercase font-black px-2 pt-4 pb-2 text-slate-600 tracking-[0.2em] menu-text border-b-2 border-sky-500/40 mb-3">Service Solde</div>
                    <button onclick="loadPage('pages/dossiers/mandatement.php', 'Mandatement')" class="nav-item flex items-center gap-4 w-full p-3.5 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                        <i class="fas fa-file-invoice-dollar w-6 text-center text-emerald-500"></i>
                        <span class="menu-text font-semibold text-sm">Mandatement</span>
                    </button>
                <?php endif; ?>              

            <?php endif; ?>
            <div class="section-title text-[12px] uppercase font-black px-2 pt-4 pb-2 text-slate-600 tracking-[0.2em] menu-text border-b-2 border-sky-500/40 mb-3">
                Centre d'aide
            </div>

            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                <!-- Affiché uniquement pour l'Administrateur -->
                <button onclick="loadPage('pages/admin/suggestions.php', 'Suggestions des utilisateurs')" class="nav-item flex items-center gap-4 w-full p-3.5 rounded-xl hover:bg-slate-800 hover:text-white transition-all group mb-1.5">
                    <i class="fas fa-lightbulb w-6 text-center text-amber-400"></i>
                    <span class="menu-text font-semibold text-sm">Suggestions</span>
                </button>
            <?php else: ?>
                <!-- Affiché pour tous les autres utilisateurs (non admin) -->
                <button onclick="loadPage('pages/aide/guide.php', 'Guide d\'utilisation')" class="nav-item flex items-center gap-4 w-full p-3.5 rounded-xl hover:bg-slate-800 hover:text-white transition-all group">
                    <i class="fas fa-book-reader w-6 text-center text-amber-400"></i>
                    <span class="menu-text font-semibold text-sm">Guide d'utilisation</span>
                </button>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="content-area h-screen flex flex-col overflow-hidden">       
        <header class="h-20 bg-white border-b border-slate-100 flex items-center justify-between px-8 shrink-0 z-20">
            <div class="flex items-center gap-6">
                <button id="btnToggle" class="w-10 h-10 flex flex-col items-center justify-center gap-1.5 hover:bg-slate-50 rounded-xl transition-all">
                    <span class="w-6 h-0.5 bg-slate-800 rounded-full"></span>
                    <span class="w-6 h-0.5 bg-slate-800 rounded-full"></span>
                    <span class="w-6 h-0.5 bg-slate-800 rounded-full"></span>
                </button>
                <h1 id="page-title" class="text-sm font-black uppercase tracking-widest text-slate-400 italic">Tableau de bord</h1>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 pr-4 border-r border-slate-100">
                    <div class="text-right hidden sm:block pl-2">
                        <p class="text-[11px] font-black text-slate-800 uppercase">
                            <?php echo $userNom.' '.$userPrenoms; ?>
                        </p>
                        <p class="text-[11px] font-bold text-sky-500 uppercase italic">
                            <?php echo htmlspecialchars($userTitreResponsabilite); ?>
                        </p>
                    </div>                  
                </div>

                <input type="file" id="inputPhotoGlobale" style="display:none;" accept="image/*" onchange="executerUploadGlobale()">

                <div class="relative">
                    <div class="flex items-center gap-3 p-1 rounded-2xl">
                        <button onclick="loadPage('pages/notifications/messagerie.php', 'Messages')" class="relative w-10 h-10 flex items-center justify-center text-slate-400 hover:bg-sky-50 hover:text-sky-600 rounded-xl transition-all" id="msg-container">
                            <i class="far fa-envelope text-xl" id="msg-icon"></i>
                            <span id="msg-badge" class="badge bg-sky-500 text-white rounded-full">0</span>
                        </button>
                        <div class="relative inline-block text-left" id="notif-container">
                            <button onclick="toggleNotif()" class="p-2 transition-colors relative">
                                <i class="fas fa-bell text-xl text-sky-500"></i> <span id="notif-count" class="absolute top-0 right-0 bg-sky-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full flex items-center justify-center">0</span>
                            </button>

                            <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-slate-200 z-50">
                                <div class="p-3 border-b border-slate-100 font-bold text-slate-700 flex justify-between items-center">
                                    <span class="text-sm">Notifications <span id="read-count" class="text-slate-400 font-normal">(0)</span></span>
                                </div>
                                <div id="notif-list" class="max-h-96 overflow-y-auto"></div>
                                <div class="p-2 border-t border-slate-50 bg-slate-50/50">
                                    <button onclick="document.getElementById('notif-dropdown').classList.add('hidden'); loadPage('pages/notifications/archives_notifs.php', 'Archives')" 
                                            class="w-full py-2 text-center text-[10px] font-bold text-slate-500 hover:text-sky-600 uppercase tracking-widest transition-colors cursor-pointer">
                                        <i class="fas fa-history mr-1"></i> Voir tout l'historique
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="relative group cursor-pointer" onclick="toggleUserMenu();"> 
                            <img id="headerPhoto" src="<?php echo $displayPhoto; ?>" onerror="this.onerror=null; this.src='images/default.png';" class="w-11 h-11 rounded-xl object-cover ring-2 ring-white shadow-md group-hover:ring-sky-500 transition-all">
                            <div class="absolute inset-0 bg-black/40 rounded-xl opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                            </div>
                        </div>
                    </div>

                    <div id="userDropdown" class="absolute right-0 mt-3 w-64 bg-white rounded-[0.5rem] shadow-2xl border border-slate-100 p-3 z-50">
                        <button onclick="loadPage('pages/profil/profil_photo.php', 'Photo de Profil')" class="flex items-center gap-3 w-full p-4 rounded-xl hover:bg-slate-50 text-slate-600 text-xs font-bold">
                            <i class="fas fa-camera text-lg text-sky-400"></i> Modifier photo de profil
                        </button>    
                        <button onclick="loadPage('pages/profil/profil_password.php', 'Mise à jour du compte')" class="flex items-center gap-3 w-full p-4 rounded-xl hover:bg-slate-50 text-slate-600 text-xs font-bold">
                            <i class="fas fa-user-circle text-lg text-slate-300"></i> Modifier mon compte
                        </button>
                        
                        <?php if($isAdmin): ?>
                        <button onclick="loadPage('pages/admin/creer_responsable.php', 'Nouveau Responsable')" class="flex items-center gap-3 w-full p-4 rounded-xl hover:bg-slate-50 text-slate-600 text-xs font-bold">
                            <i class="fas fa-user-plus text-lg text-slate-300"></i> Créer Compte Responsable
                        </button>
                        <?php endif; ?>

                        <div class="h-px bg-slate-100 my-2"></div>

                        <!-- BOUTON DYNAMIQUE POUR BASCULER DE VUE -->
                        <?php if ($userType !== 'agent' && $userRole !== 'agent'): ?>
                            <?php if ($activeView === 'responsable'): ?>
                                <a href="?switch_view=agent" class="flex items-center gap-3 w-full p-4 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold transition-all">
                                    <i class="fas fa-user-tie text-lg text-sky-500"></i> Basculer en vue Agent
                                </a>
                            <?php else: ?>
                                <a href="?switch_view=responsable" class="flex items-center gap-3 w-full p-4 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition-all">
                                    <i class="fas fa-user-shield text-lg text-indigo-500"></i> Revenir en vue Responsable
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="h-px bg-slate-50 my-2"></div>
                        
                        <a href="auth/logout.php" class="flex items-center gap-3 w-full p-4 rounded-xl bg-rose-50 text-rose-600 text-xs font-black uppercase tracking-widest hover:bg-rose-600 hover:text-white transition-all">
                            <i class="fas fa-power-off"></i> Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div id="layoutContainer" class="flex-1 flex flex-col min-h-0 overflow-hidden">        
            <div id="mainContainer" class="flex-1 h-screen overflow-y-auto bg-slate-50">
                <div id="pageContent" class="w-full">
                </div>
            </div>

            <footer class="bg-white border-t border-slate-200 px-6 py-3 shrink-0 shadow-[0_-4px_10px_rgba(0,0,0,0.03)]">
                <div class="flex flex-col md:flex-row justify-between items-center gap-2">
                    <p class="text-slate-500 text-xs uppercase font-bold tracking-widest">
                        &copy; <?= date('Y') ?> — <span class="text-slate-900">Gestion des Ressources Humaines</span>
                    </p>
                </div>
            </footer>
        </div>
    </main>
<div id="modalDetailDos" class="fixed inset-0 z-[200] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="fermerDetail()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-4xl p-10 animate-in zoom-in-95 duration-300">
            <div id="contenuHistorique"></div>
        </div>
    </div>
</div>
<div id="modal-historique" class="fixed inset-0 z-[9999] hidden bg-slate-900/60 backdrop-blur-sm items-center justify-center p-4">
    <div class="bg-white w-full max-w-4xl max-h-[90vh] rounded-[2rem] shadow-2xl flex flex-col overflow-hidden animate-pop-in">    
        <div class="px-8 py-5 border-b border-slate-100 bg-white" id="modal-header-info">
            </div>

        <div id="modal-historique-body" class="overflow-y-auto flex-grow bg-slate-50/30 p-8">
            </div>

        <div class="px-8 py-4 bg-white border-t border-slate-100 flex justify-between items-center">
            <div id="modal-footer-pagination" class="flex items-center gap-4"></div>

            <div class="flex items-center gap-3" id="modal-footer-actions">
                </div>
        </div>
    </div>
</div>
<div id="modalSaisieNumero" class="fixed inset-0 z-[20000] hidden overflow-y-auto">
    <div class="fixed inset-0 bg-slate-900/95 backdrop-blur-md"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-xl transform overflow-hidden rounded-3xl bg-white shadow-2xl transition-all">
            
            <div class="bg-indigo-600 px-6 py-4 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-black uppercase tracking-tighter leading-none">
                            Attribution numéro DOS
                        </h3>
                    </div>
                    <button onclick="document.getElementById('modalSaisieNumero').classList.add('hidden')" 
                            class="rounded-full bg-white/10 p-2 hover:bg-white/20 transition-colors">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>

            <div id="container_saisie_numeros" class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                </div>

            <div class="p-4 bg-slate-50 flex gap-3">
                <button onclick="document.getElementById('modalSaisieNumero').classList.add('hidden')" 
                        class="flex-1 rounded-xl bg-rose-600 py-3 text-[11px] font-black uppercase text-white shadow-lg shadow-rose-200 hover:bg-rose-700 transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-times"></i> Annuler
                </button>

                <button onclick="validerAttribution(event)" 
                        class="flex-1 rounded-xl bg-indigo-600 py-3 text-[11px] font-black uppercase text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-check"></i> Confirmer
                </button>
            </div>

            <input type="hidden" id="modal_im_hidden">
            <input type="hidden" id="modal_alerte_hidden">
        </div>
    </div>
</div>
<div id="modalErreurSaisie" class="fixed inset-0 z-[30000] hidden">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xs overflow-hidden animate-in fade-in zoom-in-95 duration-200 border border-rose-100">
            <div class="p-6 text-center">
                <div class="w-12 h-12 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                </div>
                <h5 class="text-sm font-black uppercase text-slate-800 mb-2">Champ obligatoire</h5>
                <p class="text-[11px] font-bold text-slate-400 uppercase leading-relaxed mb-6">
                    Veuillez saisir un numéro DOS avant de valider l'envoi.
                </p>
                <button onclick="document.getElementById('modalErreurSaisie').classList.add('hidden')" 
                        class="w-full bg-slate-900 text-white py-3 rounded-lg font-black text-[10px] uppercase shadow-lg active:scale-95 transition-all">
                    J'ai compris
                </button>
            </div>
        </div>
    </div>
</div>
<div id="modalSuccessDOS" class="fixed inset-0 z-[1000] hidden">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-sm p-10 text-center animate-in zoom-in-95 duration-300">
            <div class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-emerald-100">
                <i class="fas fa-check text-3xl"></i>
            </div>
            <p class="text-slate-500 text-xl font-bold mb-8 leading-relaxed">
                Le numéro DOS a été enregistré avec succès.
            </p>
            
            <button onclick="fermerSuccesEtRafraichir()" 
                    class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-xs uppercase shadow-xl hover:bg-indigo-600 transition-all">
                Continuer
            </button>
        </div>
    </div>
</div>
<div id="modalAfficherNumerosDos" class="fixed inset-0 z-[20000] hidden overflow-y-auto">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="fermerModalAfficherNumeros()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-2xl transform overflow-hidden rounded-3xl bg-white shadow-2xl transition-all">
            
            <div class="bg-sky-600 px-6 py-4 text-white flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-black uppercase tracking-tight">Liste des numéros DOS attribués</h3>
                    <p id="modal_afficher_dos_agent_info" class="text-xs text-sky-100 font-bold uppercase mt-1"></p>
                </div>
                <button onclick="fermerModalAfficherNumeros()" class="rounded-full bg-white/10 p-2 hover:bg-white/20 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <div class="p-6">
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-900 text-white">
                                <th class="p-3 text-[10px] font-bold uppercase tracking-widest">N°</th>
                                <th class="p-3 text-[10px] font-bold uppercase tracking-widest">Type Titre</th>
                                <th class="p-3 text-[10px] font-bold uppercase tracking-widest">Type DOS</th>
                                <th class="p-3 text-[10px] font-bold uppercase tracking-widest text-center">Numéro DOS</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_afficher_numeros_dos" class="divide-y divide-slate-100 text-xs"></tbody>
                    </table>
                </div>
            </div>

            <div class="p-4 bg-slate-50 flex justify-end">
                <button onclick="fermerModalAfficherNumeros()" class="px-6 py-2.5 rounded-xl bg-slate-800 text-white font-black text-xs uppercase hover:bg-slate-700 transition-all">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>
<div id="modal-quitter" class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl shadow-2xl p-8 max-w-sm w-full mx-4">
        <div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-sign-out-alt text-2xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800 uppercase mb-2">Quitter l'application ?</h3>
            <p class="text-slate-500 text-sm mb-8">Vos modifications non enregistrées pourraient être perdues.</p>
            <div class="flex gap-3 w-full">
                <button onclick="fermerModalQuitter()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-xl font-black uppercase text-[10px]">Annuler</button>
                <button onclick="confirmerQuitter()" class="flex-1 px-6 py-3 bg-rose-500 text-white rounded-xl font-black uppercase text-[10px]">Quitter</button>
            </div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="assets/css/tailwind.css">
<script src="assets/js/chart.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> 
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>Chart.register(ChartDataLabels);</script>
<script src="assets/js/navigation.js"></script>
<script src="assets/js/notifications.js"></script>
<script src="assets/js/historique.js"></script>
<script src="assets/js/dos.js"></script>
<script src="assets/js/upload.js"></script>
<script>window.defaultDashboardPage = "<?php echo $dashboardPage; ?>";</script>
<script src="assets/js/chargement.js"></script>
<?php if (isset($_GET['switch_view'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const activeView = "<?= htmlspecialchars($activeView) ?>";
        const isAgent = (activeView === 'agent');

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: isAgent ? 'info' : 'success',
            title: isAgent ? 'Espace Personnel' : 'Espace Responsable',
            text: isAgent ? 'Vous êtes maintenant en Vue Agent.' : 'Vous êtes de retour en Vue Responsable.',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            customClass: {
                popup: 'rounded-xl shadow-xl border border-slate-100'
            }
        });

        if (window.history.replaceState) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
        }
    });
</script>
<?php endif; ?>

<script>
function toggleChatWindow() {
    const box = document.getElementById('chat-box');
    const badge = document.getElementById('chat-badge-top');
    const icon = document.getElementById('chat-toggle-icon');
    
    box.classList.toggle('hidden');
    
    if (!box.classList.contains('hidden')) {
        if (badge) badge.classList.add('hidden');
        icon.className = 'fas fa-chevron-down text-2xl';
        chargerChatMessages();
        
        // Charger le nombre de personnes connectées
        const headerGreeting = document.getElementById('chat-header-greeting');
        const headerTitle = document.getElementById('chat-header-title');
        
        $.getJSON('api/chat/get_online_users.php', function(data) {
            if (data.success) {
                if (headerGreeting) headerGreeting.innerText = 'Discussion en ligne 💬';
                if (headerTitle) {
                    headerTitle.innerHTML = `<span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>${data.count} personne(s) connectée(s)`;
                }
            }
        });
    } else {
        if (badge) badge.classList.remove('hidden');
        icon.className = 'fas fa-comments text-2xl';
    }
}

function envoyerChatMessage(e, type = 'message') {
    e.preventDefault();
    let input, message;

    if (type === 'suggestion') {
        input = document.getElementById('chat-suggestion-input');
    } else {
        input = document.getElementById('chat-input');
    }

    message = input.value.trim();
    if (!message) return;

    $.post('api/chat/envoyer_message.php', {
        message: message,
        type: type
    }, function(response) {
        if (response.success) {
            if (type === 'suggestion') {
                // Conserve la suggestion envoyée dans la zone de texte avec un indicateur
                input.value = "📌 Suggestion envoyée :\n\n" + message;
                input.readOnly = true; // Empêche la modification accidentelle juste après l'envoi

                Swal.fire({
                    icon: 'success',
                    title: 'Suggestion envoyée !',
                    text: 'Votre suggestion a été transmise à l\'administrateur.',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                input.value = '';
                chargerChatMessages();
            }
        } else {
            Swal.fire({ icon: 'error', title: 'Erreur', text: response.message });
        }
    }, 'json');
}

function chargerChatMessages() {
    $.getJSON('api/chat/get_messages.php')
        .done(function(data) {
            const container = document.getElementById('chat-messages-container');
            if (!container) return;

            if (data.success) {
                if (!data.messages || data.messages.length === 0) {
                    container.innerHTML = '<p class="text-center text-slate-400 text-[14px] my-4">Aucun message pour le moment.</p>';
                    return;
                }

                let html = '';

                data.messages.forEach(msg => {
                    const isMe = msg.is_me;

                    // Gestion Nom / Prénom
                    const prenoms = msg.sender_prenoms ? msg.sender_prenoms.trim() : (msg.sender_nom ? msg.sender_nom.trim() : `Agent (${msg.sender_im})`);

                    const typeEtab = (msg.type_etablissement || '').toUpperCase().trim();
                    const fonction = (msg.nom_fonction || '').trim();
                    const fonctionUpper = fonction.toUpperCase();
                    const lieuRaw = (msg.lieu_de_service || '').trim();
                    const nomEtab = (msg.nom_etablissement || '').trim();

                    const sigleDir = (msg.sigle_direction || msg.nom_direction || '').trim();
                    const sigleServDirmen = (msg.sigle_service_dirmen || msg.nom_service || '').trim();
                    const sigleService = (msg.sigle_service || msg.nom_service || '').trim();
                    const sigleDivision = (msg.sigle_division || msg.nom_division || '').trim();

                    const roleSpecifique = (msg.role_specifique || '').trim();
                    const niveau = (msg.niveau || '').trim().toLowerCase();

                    // Image par défaut et sécurisation du chemin d'accès
                    const defaultAvatar = 'images/default.png';
                    let photoSrc = defaultAvatar;

                    if (msg.photo && msg.photo.trim() !== '') {
                        photoSrc = msg.photo.startsWith('images/') ? msg.photo : 'images/' + msg.photo;
                    }

                    // HTML de l'avatar miniature avec infobulle agrandie au survol
                    const avatarHtml = `
                        <div class="relative group inline-block mr-1.5 shrink-0">
                            <!-- Miniature dans le message -->
                            <img src="${photoSrc}" alt="Avatar" 
                                 class="w-6 h-6 rounded-full object-cover border border-slate-200 bg-slate-100 shadow-sm cursor-pointer transition-transform duration-200 group-hover:scale-110" 
                                 onerror="this.onerror=null; this.src='${defaultAvatar}';">
                            
                            <!-- Infobulle / Grand format au survol (hover) -->
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:flex flex-col items-center z-50 pointer-events-none transition-all duration-200 opacity-0 group-hover:opacity-100">
                                <div class="bg-white p-1 rounded-xl shadow-xl border border-slate-200">
                                    <img src="${photoSrc}" alt="Aperçu" 
                                         class="w-24 h-24 rounded-lg object-cover bg-slate-100" 
                                         onerror="this.onerror=null; this.src='${defaultAvatar}';">
                                </div>
                                <!-- Petite flèche du tooltip -->
                                <div class="w-2 h-2 bg-white border-r border-b border-slate-200 rotate-45 -mt-1"></div>
                            </div>
                        </div>
                    `;

                    let senderDisplay = '';

                    // 1. CAS ADMINISTRATEUR
                    if (roleSpecifique === 'admin') {
                        senderDisplay = 'Administrateur';
                    } else {
                        let identifiantStructure = '';

                        // 2. RÔLES RESPONSABLES
                        if (roleSpecifique === 'resp_personnel_crfrp' && niveau === 'crfrp') {
                            identifiantStructure = `Responsable Personnel ${lieuRaw}`.trim();
                        } 
                        else if (['resp_non_encadre', 'resp_encadre', 'resp_solde', 'resp_retraite', 'resp_conge'].includes(roleSpecifique)) {
                            let libelleRole = '';
                            switch (roleSpecifique) {
                                case 'resp_non_encadre': libelleRole = 'Responsable Non Encadré'; break;
                                case 'resp_encadre':     libelleRole = 'Responsable Encadré'; break;
                                case 'resp_solde':       libelleRole = 'Responsable Solde'; break;
                                case 'resp_retraite':    libelleRole = 'Responsable Retraite'; break;
                                case 'resp_conge':       libelleRole = 'Responsable Congé'; break;
                            }

                            if (niveau === 'central') {
                                identifiantStructure = `${libelleRole} DRH`;
                            } else if (['district', 'regional'].includes(niveau)) {
                                identifiantStructure = `${libelleRole} ${lieuRaw}`.trim();
                            }
                        }

                        // 3. RÔLES AGENTS
                        if (!identifiantStructure) {
                            if (typeEtab === 'MEN CENTRAL') {
                                if (fonctionUpper.includes('DIRECTEUR')) {
                                    identifiantStructure = `${sigleDir} (MEN)`;
                                } else if (fonctionUpper.includes('CHEF DE SERVICE')) {
                                    identifiantStructure = `Chef de Service ${sigleServDirmen} - ${sigleDir} (MEN)`;
                                } else {
                                    identifiantStructure = `Personnel ${sigleServDirmen} - ${sigleDir} (MEN)`;
                                }
                            } else if (typeEtab === 'DREN') {
                                if (fonctionUpper.includes('DIRECTEUR')) {
                                    identifiantStructure = lieuRaw;
                                } else if (fonctionUpper.includes('CHEF DE SERVICE')) {
                                    identifiantStructure = `Chef de Service ${sigleService} - ${lieuRaw}`;
                                } else {
                                    identifiantStructure = `Personnel ${sigleService} - ${lieuRaw}`;
                                }
                            } else if (typeEtab === 'CISCO') {
                                if (fonctionUpper.includes('CHEF CISCO')) {
                                    identifiantStructure = `Chef Cisco - ${lieuRaw}`;
                                } else if (fonctionUpper.includes('CHEF DE DIVISION')) {
                                    identifiantStructure = `Chef de Division ${sigleDivision} - ${lieuRaw}`;
                                } else {
                                    identifiantStructure = `Personnel ${sigleDivision} - ${lieuRaw}`;
                                }
                            } else if (['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'].includes(typeEtab)) {
                                identifiantStructure = (nomEtab && lieuRaw) ? `${nomEtab}/${lieuRaw}` : (nomEtab || lieuRaw);
                            } else if (typeEtab === 'CRFRP') {
                                identifiantStructure = lieuRaw;
                            } else {
                                identifiantStructure = lieuRaw || nomEtab;
                            }
                        }

                        senderDisplay = identifiantStructure ? `${prenoms} (${identifiantStructure.trim()})` : prenoms;
                    }

                    const cleanMessage = $('<div>').text(msg.message).html();

                    html += `
                        <div class="flex flex-col ${isMe ? 'items-end' : 'items-start'} mb-2">
                            <div class="max-w-[85%] rounded-2xl p-3 ${isMe ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-white border border-slate-200 text-slate-800 rounded-bl-none shadow-sm'}">
                                <span class="flex items-center text-[16px] font-bold opacity-90 mb-0.5">
                                    ${avatarHtml}
                                    <span>${senderDisplay}</span>
                                </span>
                                <p class="text-[15px] leading-relaxed mt-1">${cleanMessage}</p>
                            </div>
                            <span class="text-[13px] text-slate-400 mt-0.5 px-1">${msg.created_at}</span>
                        </div>
                    `;
                });

                if (container.innerHTML !== html) {
                    const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
                    container.innerHTML = html;
                    if (isAtBottom) {
                        container.scrollTop = container.scrollHeight;
                    }
                }
            } else {
                container.innerHTML = `<p class="text-center text-rose-500 text-[13px] my-4">Erreur: ${data.message || 'Impossible de charger'}</p>`;
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            const container = document.getElementById('chat-messages-container');
            if (container) {
                container.innerHTML = `<p class="text-center text-rose-500 text-[13px] my-4"><i class="fas fa-exclamation-triangle mr-1"></i> Erreur réseau / serveur (${jqXHR.status})</p>`;
            }
        });
}

function switchChatTab(tab) {
    const tabMessage = document.getElementById('tab-content-message');
    const tabSuggestion = document.getElementById('tab-content-suggestion');
    const btnMessage = document.getElementById('btn-tab-message');
    const btnSuggestion = document.getElementById('btn-tab-suggestion');

    const headerGreeting = document.getElementById('chat-header-greeting');
    const headerTitle = document.getElementById('chat-header-title');

    if (tab === 'suggestion') {
        tabMessage.classList.add('hidden');
        tabSuggestion.classList.remove('hidden');

        btnMessage.className = "flex flex-col items-center gap-1 text-slate-400 hover:text-indigo-600 font-bold transition-colors";
        btnSuggestion.className = "flex flex-col items-center gap-1 text-amber-500 font-bold transition-colors";

        // Titre avec icône et sous-titre dans l'en-tête
        if (headerGreeting) headerGreeting.innerHTML = '<i class="fas fa-lightbulb text-amber-500 mr-1.5"></i>Proposez une amélioration ou une idée.';
        if (headerTitle) headerTitle.innerText = "Seul l'administrateur pourra lire votre suggestion.";

        // Déverrouille la zone de texte si l'utilisateur clique dessus pour réécrire une suggestion
        const inputSuggestion = document.getElementById('chat-suggestion-input');
        if (inputSuggestion) {
            inputSuggestion.onclick = function() {
                if (this.readOnly) {
                    this.value = '';
                    this.readOnly = false;
                }
            };
        }
    } else {
        tabSuggestion.classList.add('hidden');
        tabMessage.classList.remove('hidden');

        btnMessage.className = "flex flex-col items-center gap-1 text-indigo-600 font-bold transition-colors";
        btnSuggestion.className = "flex flex-col items-center gap-1 text-slate-400 hover:text-amber-500 font-bold transition-colors";

        // Réinitialisation de l'en-tête pour l'onglet discussion
        if (headerGreeting) headerGreeting.innerText = "Discussion en ligne 💬";
        
        $.getJSON('api/chat/get_online_users.php', function(data) {
            if (data.success && headerTitle) {
                headerTitle.innerHTML = `<span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>${data.count} personne(s) connectée(s)`;
            }
        });

        chargerChatMessages();
    }
}

function envoyerChatMessage(e, type = 'message') {
    e.preventDefault();
    let input, message;

    if (type === 'suggestion') {
        input = document.getElementById('chat-suggestion-input');
    } else {
        input = document.getElementById('chat-input');
    }

    message = input.value.trim();
    if (!message) return;

    $.post('api/chat/envoyer_message.php', {
        message: message,
        type: type
    }, function(response) {
        if (response.success) {
            if (type === 'suggestion') {
                // Conserve la suggestion envoyée dans le champ avec indicateur
                input.value = "📌 Suggestion envoyée :\n\n" + message;
                input.readOnly = true;

                Swal.fire({
                    icon: 'success',
                    title: 'Suggestion envoyée !',
                    text: 'Votre suggestion a été transmise à l\'administrateur.',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                input.value = '';
                chargerChatMessages();
            }
        } else {
            Swal.fire({ icon: 'error', title: 'Erreur', text: response.message });
        }
    }, 'json');
}

// Rafraîchissement automatique des messages toutes les 5 secondes
setInterval(() => {
    const box = document.getElementById('chat-box');
    if (box && !box.classList.contains('hidden')) {
        chargerChatMessages();
    }
}, 5000);
</script>

<div id="chat-widget" class="flex flex-col items-end" style="position: fixed !important; bottom: 24px !important; right: 24px !important; z-index: 99999 !important;">

    <!-- FENÊTRE DE CHAT DYNAMIQUE AVEC ONGLETS -->
    <div id="chat-box"
     class="hidden mb-3 h-[420px] max-h-[60vh] max-w-[90vw] shrink-0 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col transition-all duration-300"
     style="width: 420px !important; height: 420px !important; max-height: 60vh !important; flex-shrink: 0 !important;">    
        
        <!-- En-tête -->
        <div class="p-3.5 bg-white border-b border-slate-100 flex justify-between items-center shrink-0">
            <div>
                <h3 id="chat-header-greeting" class="font-black text-[16px] text-slate-800 leading-tight">Proposez une amélioration ou une idée.</h3>
                <p id="chat-header-title" class="font-medium text-[15px] text-slate-500 mt-0.5">Seul l'administrateur pourra lire votre suggestion.</p>
            </div>
            <button onclick="toggleChatWindow()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <!-- ZONE DE CONTENU / ONGLETS -->
        <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-50">
            
            <!-- Onglet 1 : Discussion générale -->
            <div id="tab-content-message" class="flex-1 flex flex-col min-h-0 overflow-hidden">
                <div id="chat-messages-container" class="flex-1 p-3 overflow-y-auto space-y-3 text-[15px]">
                    <div class="text-center text-slate-400 my-4">Chargement de la discussion...</div>
                </div>
                <form id="chat-form" onsubmit="envoyerChatMessage(event, 'message')" class="p-2.5 bg-white border-t border-slate-100 flex gap-2 shrink-0">
                    <input type="text" id="chat-input" placeholder="Écrivez votre message..." required
                        class="flex-1 bg-slate-100 border-0 rounded-xl px-3 py-2 text-[15px] focus:ring-2 focus:ring-indigo-500 outline-none">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white w-9 h-9 rounded-xl text-xs font-bold flex items-center justify-center transition-all shadow-md active:scale-95 shrink-0">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>

            <!-- Onglet 2 : Suggestions -->
            <div id="tab-content-suggestion" class="hidden flex-1 flex flex-col p-4 bg-slate-50 overflow-y-auto">
                <form onsubmit="envoyerChatMessage(event, 'suggestion')" class="flex-1 flex flex-col gap-3">
                    <textarea id="chat-suggestion-input" rows="5" required placeholder="Décrivez votre suggestion ici..."
                            class="w-full bg-white border border-slate-200 rounded-xl p-3 text-[15px] focus:ring-2 focus:ring-amber-500 outline-none resize-none"></textarea>
                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white py-2.5 rounded-xl text-[15px] font-bold transition-all shadow-md flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i> Envoyer la suggestion
                    </button>
                </form>
            </div>
        </div>

        <!-- BARRE DE NAVIGATION EN BAS -->
        <div class="bg-white border-t border-slate-200 px-3 py-2 flex justify-around items-center shrink-0">
            <button type="button" onclick="switchChatTab('message')" id="btn-tab-message" 
                    class="flex flex-col items-center gap-1 text-indigo-600 font-bold transition-colors">
                <i class="fas fa-comments text-base"></i>
                <span class="text-[10px] uppercase tracking-wider">Discussion</span>
            </button>

            <button type="button" onclick="switchChatTab('suggestion')" id="btn-tab-suggestion" 
                    class="flex flex-col items-center gap-1 text-slate-400 hover:text-amber-500 font-bold transition-colors">
                <i class="fas fa-lightbulb text-base"></i>
                <span class="text-[10px] uppercase tracking-wider">Suggestions</span>
            </button>
        </div>
    </div>

    <!-- BOUTON FLOTTANT + BADGE -->
    <div class="relative flex flex-col items-center">
        <div id="chat-badge-top" class="mb-2 bg-slate-900 text-white text-[14px] font-bold px-3 py-1.5 rounded-full shadow-lg flex items-center gap-2 relative animate-bounce select-none">
            <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full animate-ping"></span>
            <span>Chat direct</span>
            <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-l-transparent border-r-4 border-r-transparent border-t-4 border-t-slate-900"></div>
        </div>
        <button onclick="toggleChatWindow()" 
                class="bg-indigo-600 hover:bg-indigo-700 text-white w-14 h-14 rounded-full shadow-2xl flex items-center justify-center transition-all duration-300 transform hover:scale-110 active:scale-95">
            <i id="chat-toggle-icon" class="fas fa-comments text-2xl"></i>
        </button>
    </div>
</div>
</body>
</html>
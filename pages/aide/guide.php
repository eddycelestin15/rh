<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

// Récupération du rôle spécifique depuis la session
$roleSpecifique = $_SESSION['user_role'] ?? 'agent';

// Titres et contenus personnalisés selon 'role_specifique'
$titreRole = "Agent";
$guides = [];

switch ($roleSpecifique) {

    case 'admin':
        $titreRole = "Administrateur Système";
        $guides = [
            [
                'id' => 'adm-1',
                'title' => "Gestion et création de comptes",
                'icon' => "fas fa-user-plus",
                'content' => "En tant qu'administrateur, vous pouvez créer de nouveaux comptes pour les directions (DREN), les CISCO ou les responsables RH depuis la section <b>Administration</b> du menu."
            ],
            [
                'id' => 'adm-2',
                'title' => "Importation massive de données",
                'icon' => "fas fa-file-import",
                'content' => "Accédez au menu d'importation pour injecter les fiches de personnel, les actes administratifs ou mettre à jour les historiques depuis des fichiers structurés Excel/CSV."
            ],
            [
                'id' => 'adm-3',
                'title' => "Gestion globale des accès et paramètres",
                'icon' => "fas fa-cogs",
                'content' => "Vous disposez d'un contrôle total sur les profils d'utilisateurs, le réinitialisation des mots de passe et la supervision de l'ensemble des modules du système."
            ]
        ];
        break;

    case 'chef_service':
    case 'chef_division':
        $titreRole = ($roleSpecifique === 'chef_service') ? "Chef de Service" : "Chef de Division";
        $guides = [
            [
                'id' => 'chef-1',
                'title' => "Validation des actes et décisions",
                'icon' => "fas fa-check-double",
                'content' => "Supervisez le flux de validation des dossiers transmis par les responsables sous votre tutelle. Accédez à l'onglet <b>Traitement des dossiers</b> pour valider, ajourner ou rejeter les demandes."
            ],
            [
                'id' => 'chef-2',
                'title' => "Suivi analytique et tableaux de bord",
                'icon' => "fas fa-chart-line",
                'content' => "Votre tableau de bord vous présente une vision synthétique des effectifs, de la répartition des dossiers en cours et des statistiques d'avancement par région ou district."
            ]
        ];
        break;

    case 'resp_encadre':
        $titreRole = "Responsable Personnel Encadré";
        $guides = [
            [
                'id' => 'enc-1',
                'title' => "Projets d'Arrêtés et Décisions",
                'icon' => "fas fa-scroll",
                'content' => "Vous avez accès à la configuration des considérants pour les projets d'arrêté : <b>Avancement de classe</b>, <b>Avancement d'échelon</b> et <b>Titularisation</b>."
            ],
            [
                'id' => 'enc-2',
                'title' => "Gestion du personnel encadré",
                'icon' => "fas fa-user-shield",
                'content' => "Traitez et vérifiez les dossiers de carrière des enseignants et personnels encadrés. Attribuez les références et préparez les actes administratifs."
            ]
        ];
        break;

    case 'resp_non_encadre':
        $titreRole = "Responsable Personnel Non Encadré";
        $guides = [
            [
                'id' => 'nonenc-1',
                'title' => "Projets de Décision",
                'icon' => "fas fa-file-signature",
                'content' => "Accédez à la gestion des considérants pour créer et modifier les modèles de projets de décision applicables au personnel non encadré."
            ],
            [
                'id' => 'nonenc-2',
                'title' => "Traitement des dossiers",
                'icon' => "fas fa-tasks",
                'content' => "Assurez le contrôle de conformité des demandes du personnel non encadré (administratifs, agents d'exécution) avant transmission."
            ]
        ];
        break;

    case 'resp_solde':
        $titreRole = "Responsable Solde";
        $guides = [
            [
                'id' => 'solde-1',
                'title' => "Gestion des mandatements",
                'icon' => "fas fa-file-invoice-dollar",
                'content' => "Rendez-vous dans la rubrique <b>Service Solde > Mandatement</b> pour traiter le paiement des actes administratifs, valider les prises en charge financières et mettre à jour l'état des paiements."
            ],
            [
                'id' => 'solde-2',
                'title' => "Consultation des références de dossiers",
                'icon' => "fas fa-folder-plus",
                'content' => "Consultez et attribuez les numéros et références de dossier nécessaires au suivi budgétaire et financier des agents."
            ]
        ];
        break;

    case 'resp_personnel_crfrp':
        $titreRole = "Responsable Personnel CRFRP";
        $guides = [
            [
                'id' => 'crfrp-1',
                'title' => "Gestion du personnel local / CRFRP",
                'icon' => "fas fa-users-cog",
                'content' => "Gérez directement les dossiers des agents rattachés au centre CRFRP, attribuez les numéros DOS et contrôlez l'exactitude des pièces fournies."
            ],
            [
                'id' => 'crfrp-2',
                'title' => "Traitement et mandatement",
                'icon' => "fas fa-tasks",
                'content' => "Vous cumulez le droit de traiter les dossiers ainsi que l'accès au service solde pour la gestion des mandatements locaux."
            ]
        ];
        break;

    case 'resp_retraite':
        $titreRole = "Responsable Retraite";
        $guides = [
            [
                'id' => 'retraite-1',
                'title' => "Traitement des dossiers de départ en retraite",
                'icon' => "fas fa-user-clock",
                'content' => "Suivez les carrières des agents en limite d'âge, préparez les arrêtés d'admission à la retraite et éditez les décomptes de services."
            ]
        ];
        break;

    case 'resp_conge':
        $titreRole = "Responsable Congés";
        $guides = [
            [
                'id' => 'conge-1',
                'title' => "Instruction des demandes de congé",
                'icon' => "fas fa-calendar-check",
                'content' => "Examinez les demandes de congés annuels, congés de maternité ou autorisations d'absence soumises par les agents et générez les décisions correspondantes."
            ]
        ];
        break;

    case 'agent':
    default:
        $titreRole = "Agent";
        $guides = [
            [
                'id' => 'ag-1',
                'title' => "Renseignements personnels et fiche d'état civil",
                'icon' => "fas fa-edit",
                'content' => "Consultez et demandez la mise à jour de vos données d'état civil, vos diplômes, votre situation administrative actuelle et votre poste de service depuis le menu <b>Espace personnel > Renseignements</b>."
            ],
            [
                'id' => 'ag-sit-admin',
                'title' => "Situation Actuelle & Administrative",
                'icon' => "fas fa-user-shield",
                'content' => "
                    <p class='mb-2'>Ce guide vous détaille la procédure de remplissage du 2<sup>e</sup> onglet du formulaire administratif :</p>
                    <ul class='list-disc pl-5 space-y-1 mb-3'>
                        <li><b>Statut Actuel :</b> Sélectionnez votre statut (<i>Fonctionnaire</i>, <i>Stagiaire</i>, <i>EFA</i> ou <i>ELD</i>).</li>
                        <li><b>Corps & Grade :</b> Choisissez votre corps d'appartenance pour charger dynamiquement la liste des grades associés.</li>
                        <li><b>Dates d'entrée et de dernier acte :</b> Indiquez la date d'entrée dans l'administration ainsi que la nature, le numéro et les dates de votre dernier acte de grade.</li>
                        <li><b>Acte d'Intégration / Titularisation :</b> S'affiche exclusivement si le statut est <b>Fonctionnaire</b>. Remplissez le corps, le grade ainsi que le numéro et les dates de l'acte de titularisation.</li>
                    </ul>
                    <div class='p-3 bg-amber-50 border-l-4 border-amber-400 text-amber-800 text-xs rounded'>
                        <b>Règle de contrôle :</b> La <i>Date d'effet du grade</i> ne peut pas être antérieure à la <i>Date d'entrée dans l'administration</i>.
                    </div>
                "
            ],
            [
                'id' => 'ag-2',
                'title' => "Consulter mes historiques de carrière",
                'icon' => "fas fa-file-invoice",
                'content' => "Accédez à l'historique complet de vos actes d'avancements (échelon, classe) et de vos affectations passées."
            ],
            [
                'id' => 'ag-3',
                'title' => "Demandes de congés et distinctions",
                'icon' => "fas fa-calendar-alt",
                'content' => "Soumettez une demande de décision de congé ou de distinction honorifique en remplissant les formulaires dédiés et en téléchargeant vos pièces justificatives."
            ],
            [
                'id' => 'ag-4',
                'title' => "Suivi de mes dossiers en cours",
                'icon' => "fas fa-eye",
                'content' => "Consultez à tout moment l'état d'avancement de vos demandes (en attente, validé, numéro DOS attribué) dans l'onglet <b>Dossiers en cours</b>."
            ]
        ];
        break;
}
?>

<!-- Conteneur principal avec marges CSS explicites de 3mm de chaque côté -->
<div style="padding: 3mm !important; margin: 0 !important; width: 100% !important; box-sizing: border-box !important;" class="space-y-4">

    <!-- 1. En-tête / Titre -->
    <div class="border-b border-slate-200 pb-3">
        <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight flex items-center gap-3">
            <i class="fas fa-book-reader text-amber-500"></i>
            Guide d'utilisation <span class="text-sky-600 italic"> (<?= htmlspecialchars($titreRole) ?>)</span>
        </h1>
        <p class="mt-1 text-sm font-medium text-slate-500">
            Consultez les instructions et l'aide adaptées à vos attributions et votre rôle sur la plateforme.
        </p>
    </div>

    <!-- 2. Barre de recherche pleine largeur -->
    <div class="relative w-full">
        <input type="text" id="searchInputGuide" 
               placeholder="Rechercher une rubrique, un mot-clé..." 
               class="w-full pl-4 pr-11 py-3 bg-white border border-slate-300 rounded-xl shadow-sm text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all">
        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
            <i class="fas fa-search text-base"></i>
        </div>
    </div>

    <!-- Message si aucun résultat trouvé -->
    <div id="noResultsGuide" class="hidden text-center py-10 bg-white rounded-xl border border-slate-200 text-slate-400 text-sm font-bold uppercase tracking-wider">
        <i class="fas fa-search-minus text-3xl mb-2 text-slate-300 block"></i>
        Aucun guide ne correspond à votre recherche.
    </div>

    <!-- 3. Liste des sujets en Accordéon -->
    <div class="space-y-3" id="guideAccordion">
        <?php foreach ($guides as $item): ?>
            <div class="accordion-item bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden transition-all duration-200 hover:border-sky-300">
                <!-- Bouton Header -->
                <button type="button" 
                        class="accordion-btn w-full px-5 py-4 flex items-center justify-between text-left transition-colors hover:bg-slate-50 focus:outline-none"
                        data-guide-id="<?= $item['id'] ?>">
                    
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                            <i class="<?= $item['icon'] ?> text-lg"></i>
                        </div>
                        <!-- Titre fixé à 16px -->
                        <span class="guide-item-title font-bold text-slate-800" style="font-size: 16px !important;">
                            <?= htmlspecialchars($item['title']) ?>
                        </span>
                    </div>

                    <i id="arrow-<?= $item['id'] ?>" class="fas fa-chevron-down text-xs text-slate-400 transition-transform duration-300 shrink-0"></i>
                </button>

                <!-- Contenu fixé à 15px -->
                <div id="content-<?= $item['id'] ?>" class="hidden px-6 pb-5 pt-3 text-slate-600 border-t border-slate-100 bg-slate-50/50 leading-relaxed" style="font-size: 15px !important;">
                    <?= $item['content'] ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
window.toggleGuideAccordion = function(id) {
    const content = document.getElementById('content-' + id);
    const arrow = document.getElementById('arrow-' + id);
    
    if (!content || !arrow) return;

    if (content.classList.contains('hidden')) {
        document.querySelectorAll('[id^="content-"]').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('[id^="arrow-"]').forEach(el => el.classList.remove('rotate-180'));

        content.classList.remove('hidden');
        arrow.classList.add('rotate-180');
    } else {
        content.classList.add('hidden');
        arrow.classList.remove('rotate-180');
    }
};

document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-guide-id]');
    if (!btn) return;

    const id = btn.getAttribute('data-guide-id');
    window.toggleGuideAccordion(id);
});

document.addEventListener('keyup', function(e) {
    if (e.target && e.target.id === 'searchInputGuide') {
        const val = e.target.value.toLowerCase().trim();
        let count = 0;

        document.querySelectorAll('.accordion-item').forEach(function(item) {
            const text = item.innerText.toLowerCase();
            if (text.includes(val)) {
                item.classList.remove('hidden');
                count++;
            } else {
                item.classList.add('hidden');
            }
        });

        const noRes = document.getElementById('noResultsGuide');
        if (noRes) {
            if (count === 0) {
                noRes.classList.remove('hidden');
            } else {
                noRes.classList.add('hidden');
            }
        }
    }
});
</script>
<?php
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/check_session.php';

    $user_im = $_SESSION['user_im'];

    $stmtNiv = $pdo->prepare("SELECT niveau FROM utilisateurs WHERE im = ?");
    $stmtNiv->execute([$user_im]);
    $niveauUtilisateur = $stmtNiv->fetchColumn();

    // 1. Détermination du cursus et récupération des infos agent (incluant type_etablissement)
    $stmtPers = $pdo->prepare("SELECT s.corps_actuel, s.grade_actuel, p.type_direction, p.type_etablissement 
                              FROM personnel_situation_actuelle s
                              LEFT JOIN personnel_poste_actuel p ON s.im = p.im
                              WHERE s.im = ?");
    $stmtPers->execute([$user_im]);
    $pers = $stmtPers->fetch();

    $corpsActuel = $pers['corps_actuel'] ?? 'Non défini';
    $gradeActuel = $pers['grade_actuel'] ?? 'Non défini';
    
    // Récupération du type de direction et du type d'établissement
    $typeDirection     = $pers['type_direction'] ?? 'DREN';
    $typeEtablissement = strtoupper($pers['type_etablissement'] ?? 'CISCO');

    // Récupération des alertes de l'agent
    $stmtAlerteList = $pdo->prepare("SELECT alerte_id, type_key FROM v_moteur_alertes WHERE im = ?");
    $stmtAlerteList->execute([$user_im]);
    $alertesList = $stmtAlerteList->fetchAll(PDO::FETCH_ASSOC);

    $hasAlerte = count($alertesList) > 0;

    // Vérification si la SEULE alerte de l'agent est un STEP_MANDATEMENT
    $isMandatementSeul = false;
    $infosMandatement = null;

    if ($hasAlerte && count($alertesList) === 1) {
        $typeKey = $alertesList[0]['type_key'] ?? '';
        $alerteId = $alertesList[0]['alerte_id'] ?? '';
        
        if ($typeKey === 'STEP_MANDATEMENT' || strpos($alerteId, 'STEP_MANDATEMENT') !== false) {
            $isMandatementSeul = true;
            $stmtMand = $pdo->prepare("SELECT 
                numero_complet, 
                DATE_FORMAT(date_envoi_bordereau, '%d/%m/%Y') as date_bordereau,
                reference_destination,
                DATE_FORMAT(date_reference_destination, '%d/%m/%Y') as date_ref_dest
            FROM archives_bordereaux_solde 
            WHERE liste_agents LIKE ? AND type_bordereau = 'mandatement' 
            ORDER BY id DESC LIMIT 1");

            $stmtMand->execute(['%' . $user_im . '%']);
            $infosMandatement = $stmtMand->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Vérifier si un dossier est en cours dans suivi_agents_bordereau
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM suivi_agents_bordereau WHERE im_agent = ?");
    $checkStmt->execute([$user_im]);
    $hasDossier = $checkStmt->fetchColumn() > 0;

    // Condition pour déterminer si la situation administrative est considérée "à jour"
    $isSituationAjour = (!$hasAlerte && !$hasDossier) || ($isMandatementSeul && !$hasDossier);

    // Requête de suivi AVEC TOUTES LES JOINTURES D'ARCHIVES
    $sql = "SELECT 
        s.*,
        -- CISCO / CRFRP -> DREN
        ad.numero_complet as num_cisco_dren, 
        DATE_FORMAT(ad.date_envoi_bordereau, '%d/%m/%Y') as date_cisco_dren,
        
        acr.numero_complet as num_crfrp_dren,
        DATE_FORMAT(acr.date_envoi_bordereau, '%d/%m/%Y') as date_crfrp_dren,

        -- DREN / CRFRP / DRH -> SOLDE
        asol.numero_complet as num_dren_solde, 
        DATE_FORMAT(asol.date_envoi_bordereau, '%d/%m/%Y') as date_dren_solde,
        asol.numero_complet as num_crfrp_solde,
        DATE_FORMAT(asol.date_envoi_bordereau, '%d/%m/%Y') as date_crfrp_solde,
        asol.numero_complet as num_drh_solde,
        DATE_FORMAT(asol.date_envoi_bordereau, '%d/%m/%Y') as date_drh_solde,

        -- DREN / CRFRP / DRH -> CDE
        acde.numero_complet as num_dren_cde, 
        DATE_FORMAT(acde.date_envoi_bordereau, '%d/%m/%Y') as date_dren_cde,
        acde.numero_complet as num_crfrp_cde,
        DATE_FORMAT(acde.date_envoi_bordereau, '%d/%m/%Y') as date_crfrp_cde,
        acde.numero_complet as num_drh_cde,
        DATE_FORMAT(acde.date_envoi_bordereau, '%d/%m/%Y') as date_drh_cde,

        -- DREN / CRFRP -> PREFET
        apre.numero_complet as num_dren_prefet, 
        DATE_FORMAT(apre.date_envoi_bordereau, '%d/%m/%Y') as date_dren_prefet,
        apre.numero_complet as num_crfrp_prefet,
        DATE_FORMAT(apre.date_envoi_bordereau, '%d/%m/%Y') as date_crfrp_prefet,

        -- CISCO / DREN / CRFRP / DRH -> FOP
        afp.numero_complet as num_cisco_fop,
        DATE_FORMAT(afp.date_envoi_bordereau, '%d/%m/%Y') as date_cisco_fop,
        afp.numero_complet as num_dren_fop, 
        DATE_FORMAT(afp.date_envoi_bordereau, '%d/%m/%Y') as date_dren_fop,
        afp.numero_complet as num_crfrp_fop,
        DATE_FORMAT(afp.date_envoi_bordereau, '%d/%m/%Y') as date_crfrp_fop,
        afp.numero_complet as num_drh_fop,
        DATE_FORMAT(afp.date_envoi_bordereau, '%d/%m/%Y') as date_drh_fop,

        -- CISCO / DREN / CRFRP -> DRH
        adrh.numero_complet as num_cisco_drh,
        DATE_FORMAT(adrh.date_envoi_bordereau, '%d/%m/%Y') as date_cisco_drh,
        adrh.numero_complet as num_dren_drh,
        DATE_FORMAT(adrh.date_envoi_bordereau, '%d/%m/%Y') as date_dren_drh,
        adrh.numero_complet as num_crfrp_drh,
        DATE_FORMAT(adrh.date_envoi_bordereau, '%d/%m/%Y') as date_crfrp_drh,

        -- DRH -> MTEFOP
        amte.numero_complet as num_drh_mtefop,
        DATE_FORMAT(amte.date_envoi_bordereau, '%d/%m/%Y') as date_drh_mtefop,

        -- DRH -> PRIMATURE
        aprim.numero_complet as num_drh_primature,
        DATE_FORMAT(aprim.date_envoi_bordereau, '%d/%m/%Y') as date_drh_primature

    FROM suivi_agents_bordereau s
    LEFT JOIN archives_bordereaux_dren ad ON s.bordereau_cisco_dren = ad.numero_complet
    LEFT JOIN archives_bordereaux_dren acr ON s.bordereau_crfrp_dren = acr.numero_complet
    LEFT JOIN archives_bordereaux_solde asol ON (s.bordereau_dren_solde = asol.numero_complet OR s.bordereau_crfrp_solde = asol.numero_complet OR s.bordereau_drh_solde = asol.numero_complet)
    LEFT JOIN archives_bordereaux_cde acde ON (s.bordereau_dren_cde = acde.numero_complet OR s.bordereau_crfrp_cde = acde.numero_complet OR s.bordereau_drh_cde = acde.numero_complet)
    LEFT JOIN archives_bordereaux_prefet apre ON (s.bordereau_dren_prefet = apre.numero_complet OR s.bordereau_crfrp_prefet = apre.numero_complet)
    LEFT JOIN archives_bordereaux_fop afp ON (s.bordereau_cisco_fop = afp.numero_complet OR s.bordereau_dren_fop = afp.numero_complet OR s.bordereau_crfrp_fop = afp.numero_complet OR s.bordereau_drh_fop = afp.numero_complet)
    LEFT JOIN archives_bordereaux_drh adrh ON (s.bordereau_cisco_drh = adrh.numero_complet OR s.bordereau_dren_drh = adrh.numero_complet OR s.bordereau_crfrp_drh = adrh.numero_complet)
    LEFT JOIN archives_bordereaux_mtefop amte ON s.bordereau_drh_mtefop = amte.numero_complet
    LEFT JOIN archives_bordereaux_primature aprim ON s.bordereau_drh_primature = aprim.numero_complet
    WHERE s.im_agent = ? 
    ORDER BY s.id DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_im]);
    $suivi = $stmt->fetch();

    // 3. Titre et type de bordereau
    $titreCursus = "Consultation de l'historique et des références de transmission."; 
    $typeB = "";

    if ($suivi) {
        $typeB = $suivi['type_bordereau'];

        if ($typeB === 'renouvellement') {
            $titreCursus = "Demande de renouvellement de contrat";
        } elseif ($typeB === 'avenant') {
            $titreCursus = "Demande d'avenant";
        } elseif ($typeB === 'avancement_classe') {
            $titreCursus = "Demande d'avancement de classe et echelon";
        } elseif ($typeB === 'avancement_echelon') {
            $titreCursus = "Demande d'avancement d'echelon";
        } elseif ($typeB === 'integration') {
            $titreCursus = "Demande d'intégration";
        } elseif ($typeB === 'titularisation') {
            $titreCursus = "Demande de titularisation";
        } elseif ($typeB === 'admission_retraite') {
            $titreCursus = "Demande d'admission à la retraite";
        } elseif ($typeB === 'compensatrice') {
            $titreCursus = "Demande de compensatrice";
        } elseif ($typeB === 'installation') {
            $titreCursus = "Demande d'installation";
        }
    }

    // 4. Cartographie dynamique des circuits d'expéditeurs & destinations
    function getCircuitConfiguration($typeEtab, $typeB) {
        $isDistrict = in_array($typeEtab, ['PRESCOLAIRE', 'PRIMAIRE', 'COLLEGE', 'LYCEE', 'CISCO']);
        
        // Configuration pour District / Établissements scolaires
        if ($isDistrict) {
            if ($typeB === 'admission_retraite') {
                return [
                    'expediteurs' => [
                        'cisco' => ['label' => 'EXPÉDITEUR CISCO', 'destinations' => ['fop', 'drh']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['fop', 'drh']],
                        'drh'   => ['label' => 'EXPÉDITEUR DRH',   'destinations' => ['solde', 'cde', 'mtefop', 'primature']]
                    ]
                ];
            } elseif ($typeB === 'compensatrice') {
                return [
                    'expediteurs' => [
                        'cisco' => ['label' => 'EXPÉDITEUR CISCO', 'destinations' => ['dren']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['solde', 'cde', 'prefet']]
                    ]
                ];
            } elseif ($typeB === 'installation') {
                return [
                    'expediteurs' => [
                        'cisco' => ['label' => 'EXPÉDITEUR CISCO', 'destinations' => ['fop', 'solde', 'drh']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['fop', 'solde', 'drh']],
                        'drh'   => ['label' => 'EXPÉDITEUR DRH',   'destinations' => ['cde', 'men']]
                    ]
                ];
            } elseif (in_array($typeB, ['renouvellement', 'avenant'])) {
                return [
                    'expediteurs' => [
                        'cisco' => ['label' => 'EXPÉDITEUR CISCO', 'destinations' => ['dren']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['solde', 'cde', 'prefet']]
                    ]
                ];
            } elseif (in_array($typeB, ['avancement_classe', 'avancement_echelon'])) {
                return [
                    'expediteurs' => [
                        'cisco' => ['label' => 'EXPÉDITEUR CISCO', 'destinations' => ['dren']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['fop', 'solde', 'cde', 'prefet']]
                    ]
                ];
            } elseif ($typeB === 'integration') {
                return [
                    'expediteurs' => [
                        'cisco' => ['label' => 'EXPÉDITEUR CISCO', 'destinations' => ['dren']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['fop', 'solde', 'cde', 'drh']],
                        'drh'   => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['mtefop', 'primature']]
                    ]
                ];
            } elseif ($typeB === 'titularisation') {
                return [
                    'expediteurs' => [
                        'cisco' => ['label' => 'EXPÉDITEUR CISCO', 'destinations' => ['dren']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['fop', 'solde', 'drh']],
                        'drh'   => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['cde', 'mtefop', 'primature']]
                    ]
                ];
            }
        }

        // Configuration pour CRFRP
        if ($typeEtab === 'CRFRP') {
            if ($typeB === 'admission_retraite') {
                return [
                    'expediteurs' => [
                        'crfrp' => ['label' => 'EXPÉDITEUR CRFRP', 'destinations' => ['fop', 'drh']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['fop', 'drh']],
                        'drh'   => ['label' => 'EXPÉDITEUR DRH',   'destinations' => ['solde', 'cde', 'mtefop', 'primature']]
                    ]
                ];
            } elseif ($typeB === 'compensatrice') {
                return [
                    'expediteurs' => [
                        'crfrp' => ['label' => 'EXPÉDITEUR CRFRP', 'destinations' => ['dren', 'solde', 'cde', 'prefet']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['solde', 'cde', 'prefet']]
                    ]
                ];
            } elseif ($typeB === 'installation') {
                return [
                    'expediteurs' => [
                        'crfrp' => ['label' => 'EXPÉDITEUR CRFRP', 'destinations' => ['fop', 'solde', 'drh']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['fop', 'solde', 'drh']],
                        'drh'   => ['label' => 'EXPÉDITEUR DRH',   'destinations' => ['cde', 'men']]
                    ]
                ];
            } elseif (in_array($typeB, ['renouvellement', 'avenant'])) {
                return [
                    'expediteurs' => [
                        'crfrp' => ['label' => 'EXPÉDITEUR CRFRP', 'destinations' => ['dren', 'solde', 'cde', 'prefet']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['solde', 'cde', 'prefet']]
                    ]
                ];
            } elseif (in_array($typeB, ['avancement_classe', 'avancement_echelon'])) {
                return [
                    'expediteurs' => [
                        'crfrp' => ['label' => 'EXPÉDITEUR CRFRP', 'destinations' => ['dren', 'fop', 'solde', 'cde', 'prefet']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['fop', 'solde', 'cde', 'prefet']]
                    ]
                ];
            } elseif ($typeB === 'integration') {
                return [
                    'expediteurs' => [
                        'crfrp' => ['label' => 'EXPÉDITEUR CRFRP', 'destinations' => ['dren', 'fop', 'solde', 'cde', 'drh']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['fop', 'solde', 'cde', 'drh']],
                        'drh'   => ['label' => 'EXPÉDITEUR DRH',   'destinations' => ['mtefop', 'primature']]
                    ]
                ];
            } elseif ($typeB === 'titularisation') {
                return [
                    'expediteurs' => [
                        'crfrp' => ['label' => 'EXPÉDITEUR CRFRP', 'destinations' => ['dren', 'fop', 'solde', 'drh']],
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['fop', 'solde', 'drh']],
                        'drh'   => ['label' => 'EXPÉDITEUR DRH',   'destinations' => ['cde', 'mtefop', 'primature']]
                    ]
                ];
            }
        }

        // Configuration pour DREN
        if ($typeEtab === 'DREN') {
            if ($typeB === 'admission_retraite') {
                return [
                    'expediteurs' => [
                        'dren' => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['fop', 'drh']],
                        'drh'  => ['label' => 'EXPÉDITEUR DRH',  'destinations' => ['solde', 'cde', 'mtefop', 'primature']]
                    ]
                ];
            } elseif ($typeB === 'compensatrice') {
                return [
                    'expediteurs' => [
                        'dren' => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['solde', 'cde', 'prefet']]
                    ]
                ];
            } elseif ($typeB === 'installation') {
                return [
                    'expediteurs' => [
                        'dren'  => ['label' => 'EXPÉDITEUR DREN',  'destinations' => ['fop', 'solde', 'drh']],
                        'drh'   => ['label' => 'EXPÉDITEUR DRH',   'destinations' => ['cde', 'men']]
                    ]
                ];
            } elseif (in_array($typeB, ['renouvellement', 'avenant'])) {
                return [
                    'expediteurs' => [
                        'dren' => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['solde', 'cde', 'prefet']]
                    ]
                ];
            } elseif (in_array($typeB, ['avancement_classe', 'avancement_echelon'])) {
                return [
                    'expediteurs' => [
                        'dren' => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['fop', 'solde', 'cde', 'prefet']]
                    ]
                ];
            } elseif ($typeB === 'integration') {
                return [
                    'expediteurs' => [
                        'dren' => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['fop', 'solde', 'cde', 'drh']],
                        'drh'  => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['mtefop', 'primature']]
                    ]
                ];
            } elseif ($typeB === 'titularisation') {
                return [
                    'expediteurs' => [
                        'dren' => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['fop', 'solde', 'drh']],
                        'drh'  => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['cde', 'mtefop', 'primature']]
                    ]
                ];
            }
        }

        // Configuration pour MEN CENTRAL
        if ($typeEtab === 'MEN CENTRAL') {
            if ($typeB === 'admission_retraite') {
                return [
                    'expediteurs' => [
                        'drh' => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['fop', 'drh', 'solde', 'cde', 'mtefop', 'primature']]
                    ]
                ];
            } elseif ($typeB === 'compensatrice') {
                return [
                    'expediteurs' => [
                        'drh' => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['drh', 'solde', 'cde', 'men']]
                    ]
                ];
            } elseif ($typeB === 'installation') {
                return [
                    'expediteurs' => [
                        'drh' => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['fop', 'solde', 'cde', 'men']]
                    ]
                ];
            } elseif (in_array($typeB, ['renouvellement', 'avenant'])) {
                return [
                    'expediteurs' => [
                        'drh' => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['solde', 'cde', 'men']]
                    ]
                ];
            } elseif (in_array($typeB, ['avancement_classe', 'avancement_echelon'])) {
                return [
                    'expediteurs' => [
                        'drh' => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['fop', 'solde', 'cde', 'mtefop', 'primature']]
                    ]
                ];
            } elseif ($typeB === 'integration' || $typeB === 'titularisation') {
                return [
                    'expediteurs' => [
                        'drh' => ['label' => 'EXPÉDITEUR DRH', 'destinations' => ['drh', 'fop', 'solde', 'cde', 'mtefop', 'primature']]
                    ]
                ];
            }
        }

        // Fallback standard
        return [
            'expediteurs' => [
                'cisco' => ['label' => 'EXPÉDITEUR CISCO', 'destinations' => ['dren']],
                'dren'  => ['label' => 'EXPÉDITEUR DREN', 'destinations' => ['solde', 'cde', 'prefet']]
            ]
        ];
    }

    $circuitConfig = getCircuitConfiguration($typeEtablissement, $typeB);

    // Labels des destinations pour l'affichage
    $destLabels = [
        'dren'      => 'DREN',
        'fop'       => 'FONCTION PUBLIQUE',
        'solde'     => 'SOLDE ET PENSIONS (AUGURE DSP)',
        'cde'       => 'CONTROLE FINANCIER (AUGURE CF)',
        'prefet'    => 'PREFECTURE',
        'drh'       => 'DRH',
        'men'       => 'MEN',
        'mtefop'    => 'MTEFOP',
        'primature' => 'PRIMATURE'
    ];

    function getStepStyle($exists, $status, $defaultIcon) {
        if (!$exists) {
            return ['bg' => 'bg-slate-300', 'text' => 'text-slate-400', 'icon' => $defaultIcon];
        }
        
        switch ($status) {
            case 'valide':
                return ['bg' => 'bg-emerald-500', 'text' => 'text-emerald-600', 'icon' => 'fas fa-check'];
            case 'rejete':
                return ['bg' => 'bg-rose-500', 'text' => 'text-rose-600', 'icon' => 'fas fa-times'];
            default:
                return ['bg' => 'bg-blue-600', 'text' => 'text-blue-700', 'icon' => $defaultIcon];
        }
    }

    $stepDren      = getStepStyle(!empty($suivi['bordereau_cisco_dren']) || !empty($suivi['bordereau_crfrp_dren']), $suivi['statut_dren'] ?? '', 'fas fa-file-import');
    $stepSolde     = getStepStyle(!empty($suivi['bordereau_dren_solde']) || !empty($suivi['bordereau_crfrp_solde']), $suivi['statut_solde'] ?? '', 'fas fa-money-check-alt');
    $stepCde       = getStepStyle(!empty($suivi['bordereau_dren_cde']) || !empty($suivi['bordereau_crfrp_cde']),   $suivi['statut_cde'] ?? '', 'fas fa-shield-check');
    $stepPrefet    = getStepStyle(!empty($suivi['bordereau_dren_prefet']) || !empty($suivi['bordereau_crfrp_prefet']),$suivi['statut_prefet'] ?? '', 'fas fa-stamp');
    $stepFp        = getStepStyle(!empty($suivi['bordereau_cisco_fop']) || !empty($suivi['bordereau_dren_fop']) || !empty($suivi['bordereau_crfrp_fop']), $suivi['statut_fop'] ?? '', 'fas fa-user-shield');
    $stepDrh       = getStepStyle(!empty($suivi['bordereau_cisco_drh']) || !empty($suivi['bordereau_dren_drh']) || !empty($suivi['bordereau_crfrp_drh']), $suivi['statut_drh'] ?? '', 'fas fa-building');
    $stepMtefop    = getStepStyle(!empty($suivi['bordereau_drh_mtefop']), $suivi['statut_mtefop'] ?? '', 'fas fa-briefcase');
    $stepPrimature = getStepStyle(!empty($suivi['bordereau_drh_primature']), $suivi['statut_primature'] ?? '', 'fas fa-landmark');

    // CAS 1 : Agent introuvable
    if (!$pers):
?>
    <div class="max-w-4xl mx-auto mt-12 px-4">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100/80 p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-rose-50 text-rose-500 mb-6 shadow-inner animate-pulse">
                <i class="fas fa-user-times text-3xl"></i>
            </div>
            <h2 class="text-3xl font-black text-slate-800 tracking-tight mb-4">
                Agent introuvable
            </h2>
            <p class="text-base text-slate-500 max-w-lg mx-auto leading-relaxed mb-8">
                Aucune information n'a été trouvée pour cette immatriculation dans la base de données.
            </p>
        </div>
    </div>

<?php 
    // CAS 2 : Situation à jour
    elseif ($isSituationAjour): 
?>
    <div class="max-w-4xl mx-auto mt-12 px-4">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100/80 p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-emerald-50 text-emerald-500 mb-6 shadow-inner animate-pulse">
                <i class="fas fa-check-circle text-3xl"></i>
            </div>
            <h2 class="text-3xl font-black text-slate-800 tracking-tight mb-4">
                Situation administrative à jour
            </h2>

            <?php if ($isMandatementSeul): ?>
                <p class="text-base text-slate-600 max-w-2xl mx-auto leading-relaxed mb-4 text-justify">
                    Votre avancement a été Terminé et traité avec succès. Votre situation administrative est à jour. Nous sommes actuellement <strong>en attente du mandatement du nouveau grade</strong> auprès du Service de Solde.
                </p>
                <h4 class="text-sm font-black uppercase text-slate-800 tracking-wider underline underline-offset-4 mb-3 text-left max-w-2xl mx-auto">
                    Référence du dossier de Mandatement :
                </h4>

                <?php if ($infosMandatement): ?>
                    <ul class="list-disc list-inside text-sm font-semibold text-slate-700 space-y-1.5 text-left max-w-2xl mx-auto">
                        <li>
                            <span>Bordereau N° : </span><span class="font-extrabold text-emerald-700"><?= htmlspecialchars($infosMandatement['numero_complet']) ?></span><?php if (!empty($infosMandatement['date_bordereau'])): ?><span class="font-extrabold text-emerald-700"> du <?= htmlspecialchars($infosMandatement['date_bordereau']) ?></span><?php endif; ?>
                        </li>
                        <?php if (!empty($infosMandatement['reference_destination'])): ?>
                            <li>
                                <span>Référence N° : </span><span class="font-extrabold text-emerald-700"><?= htmlspecialchars($infosMandatement['reference_destination']) ?></span><?php if (!empty($infosMandatement['date_ref_dest'])): ?><span class="font-extrabold text-emerald-700"> du <?= htmlspecialchars($infosMandatement['date_ref_dest']) ?></span><?php endif; ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-base text-slate-500 max-w-lg mx-auto leading-relaxed mb-8">
                    La situation de l'agent est à jour et ne possède aucun retard d'avancement. Il n'y a donc pas de dossier de suivi en cours.
                </p>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($hasAlerte && !$hasDossier): ?>
    <div class="max-w-4xl mx-auto mt-16 px-6">
        <div class="bg-gradient-to-br from-white to-amber-50/30 rounded-3xl shadow-2xl border border-amber-200/50 p-14 text-center backdrop-blur-sm transition-all duration-300 hover:shadow-amber-100/40">
            
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-3xl bg-gradient-to-br from-amber-50 to-amber-100 text-amber-600 mb-8 shadow-inner animate-bounce">
                <i class="fas fa-exclamation-triangle text-4xl"></i>
            </div>
            
            <h2 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-5">
                Action requise : Demande en attente
            </h2>
            
            <p class="text-xl text-slate-600 max-w-2xl mx-auto leading-relaxed mb-6">
                Votre dossier présente un retard d'avancement nécessitant une régularisation. 
                Veuillez consulter la <span class="font-bold text-amber-600">notification</span> pour identifier et soumettre la demande correspondante.
            </p>

            <div class="w-16 h-1 bg-amber-500/20 rounded-full mx-auto"></div>
        </div>
    </div>
<?php 
    // CAS 4 : Dossier en cours (Génération dynamique du tableau)
    else: 
?>
<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .animate-spin-custom {
        animation: spin 1.5s linear infinite;
        display: inline-block;
    }
    .fa-spin-slow { animation: spin 3s linear infinite; }
</style>
<div class="m-[2mm] shadow-2xl rounded-1xl overflow-hidden border border-slate-200 bg-white">    
    <div class="p-4 border-b flex justify-between items-center bg-white">
        <div>
            <div class="flex items-baseline gap-2 mb-2">
                <h1 class="text-lg font-black text-slate-800 uppercase tracking-tighter leading-none">
                    Suivi du cursus de mon dossier :
                </h1>
                <span class="text-blue-600 text-xl font-extrabold italic leading-none">
                    <?= $titreCursus ?>
                </span>
            </div>            
            <p class="text-slate-400 text-[14px] uppercase font-bold">
                Corps et grade : <span class="text-slate-600"><?= $corpsActuel ?>, <?= $gradeActuel ?></span>
            </p>
        </div>        
        <div class="text-right">
            <span class="text-[11px] font-bold text-slate-400 block uppercase">Matricule Agent</span>
            <span class="font-black text-blue-600 text-lg"><?php echo $user_im; ?></span>
        </div>
    </div>

    <div class="p-1 overflow-x-auto"> 
        <table class="w-full border-collapse border border-slate-200 text-center">
            <thead class="font-bold">
                <tr class="text-[14px] uppercase">
                    <th rowspan="2" class="border border-slate-200 p-2 font-bold bg-slate-50 text-slate-700 min-w-[140px]">Destination</th>
                    <?php foreach ($circuitConfig['expediteurs'] as $expKey => $expData): ?>
                        <th colspan="3" class="border border-slate-700 p-2 bg-sky-700 text-white">
                            <i class="fas fa-university mr-2"></i><?= $expData['label'] ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
                <tr class="text-[13px] uppercase text-slate-700">
                    <?php foreach ($circuitConfig['expediteurs'] as $expKey => $expData): ?>
                        <th class="border border-slate-200 p-2 bg-sky-50">Bordereau</th>
                        <th class="border border-slate-200 p-2 bg-sky-50">Référence</th>
                        <th class="border border-slate-200 p-2 bg-sky-50">Traitement</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="text-[13px] uppercase">
                <?php 
                // Récupération globale de toutes les destinations possibles pour cette configuration
                $allDestinations = [];
                foreach ($circuitConfig['expediteurs'] as $expData) {
                    foreach ($expData['destinations'] as $dest) {
                        if (!in_array($dest, $allDestinations)) {
                            $allDestinations[] = $dest;
                        }
                    }
                }

                foreach ($allDestinations as $destKey): 
                ?>
                    <tr>
                        <td class="border border-slate-200 p-2 font-bold bg-slate-50 text-left text-slate-700">
                            <?= $destLabels[$destKey] ?? strtoupper($destKey) ?>
                        </td>

                        <?php foreach ($circuitConfig['expediteurs'] as $expKey => $expData): ?>
                            <?php if (in_array($destKey, $expData['destinations'])): ?>
                                <?php 
                                    // Mapping dynamique des colonnes de la table suivi_agents_bordereau
                                    $colBord = "bordereau_{$expKey}_{$destKey}";
                                    $colRef  = "ref_{$destKey}";
                                    if ($destKey === 'prefet') { $colRef = 'ref_prefecture'; }
                                    
                                    $colStatut = "statut_{$destKey}";
                                    $colMotif  = "motif_{$destKey}";
                                    
                                    $numBord = '';
                                    if (!empty($suivi[$colBord])) {
                                        $numBord = !empty($suivi["num_{$expKey}_{$destKey}"]) ? $suivi["num_{$expKey}_{$destKey}"] : $suivi[$colBord];
                                    }

                                    $dateBord = $suivi["date_{$expKey}_{$destKey}"] ?? '';
                                    $refBord  = $suivi[$colRef] ?? '';
                                    $statut   = $suivi[$colStatut] ?? '';
                                    $motif    = $suivi[$colMotif] ?? '';
                                ?>
                                <!-- Bordereau -->
                                <td class="border border-slate-200 p-1">
                                    <?php if (!empty($numBord)): ?>
                                        <?= "N° ".$numBord ?><?= !empty($dateBord) ? "<br>du : ".$dateBord : '' ?>
                                    <?php else: ?>
                                        <span class="text-slate-300 italic">---</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Référence -->
                                <td class="border border-slate-200 p-1 text-center">
                                    <?php if (empty($numBord)): ?>
                                        <span class="text-slate-300 italic">---</span>
                                    <?php elseif (empty($refBord)): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-amber-500 text-white rounded-full text-[11px] font-bold border border-amber-600 shadow-sm">
                                            <i class="fas fa-circle-notch animate-spin-custom"></i> EN ATTENTE
                                        </span>
                                    <?php else: ?>
                                        <span class="font-bold text-slate-700">N°<?= $refBord ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Traitement -->
                                <td class="border border-slate-200 p-1 text-center">
                                    <?php if (!empty($numBord)): ?>
                                        <?php if($statut == 'en_attente' && !empty($refBord)): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-amber-500 text-white rounded-full text-[11px] font-bold border border-amber-600 shadow-sm">
                                                <i class="fas fa-sync-alt animate-spin-custom"></i> EN COURS
                                            </span>
                                        <?php elseif($statut == 'rejete'): ?>
                                            <button onclick="voirMotifRejet('<?= addslashes($motif) ?>')" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-rose-600 text-white rounded font-bold text-[12px] hover:bg-rose-700 transition-colors">
                                                <i class="fas fa-eye"></i> REJET
                                            </button>
                                        <?php elseif($statut == 'valide'): ?>
                                            <span class="inline-flex items-center gap-1 text-emerald-600 font-bold text-[13px]">
                                                <i class="fas fa-check-circle"></i> Terminé
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-300 italic">---</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-300 italic">---</span>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <!-- Cellules grisées si cette destination ne concerne pas cet expéditeur -->
                                <td class="border border-slate-200 p-1 bg-slate-100 text-slate-300 text-center italic">---</td>
                                <td class="border border-slate-200 p-1 bg-slate-100 text-slate-300 text-center italic">---</td>
                                <td class="border border-slate-200 p-1 bg-slate-100 text-slate-300 text-center italic">---</td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Timeline/Stepper du Cursus Dynamique -->
    <?php
    $stepperSteps = [];
    $isDistrictEtab = in_array($typeEtablissement, ['PRESCOLAIRE', 'PRIMAIRE', 'COLLEGE', 'LYCEE', 'CISCO', 'CRFRP']);
    $startNode = ($typeEtablissement === 'CRFRP') ? 'crfrp' : 'cisco';

    if ($typeB === 'admission_retraite') {
        if ($isDistrictEtab) {
            $stepperSteps = [$startNode, 'dren', 'fop', 'drh', 'solde', 'cde', 'mtefop', 'primature'];
        } elseif ($typeEtablissement === 'DREN') {
            $stepperSteps = ['dren', 'fop', 'drh', 'solde', 'cde', 'mtefop', 'primature'];
        } elseif ($typeEtablissement === 'MEN CENTRAL') {
            $stepperSteps = ['drh', 'fop', 'solde', 'cde', 'mtefop', 'primature'];
        }
    } elseif ($typeB === 'compensatrice') {
        if ($isDistrictEtab) {
            $stepperSteps = [$startNode, 'dren', 'solde', 'cde', 'prefet'];
        } elseif ($typeEtablissement === 'DREN') {
            $stepperSteps = ['dren', 'solde', 'cde', 'prefet'];
        } elseif ($typeEtablissement === 'MEN CENTRAL') {
            $stepperSteps = ['drh', 'solde', 'cde', 'men'];
        }
    } elseif ($typeB === 'installation') {
        if ($isDistrictEtab) {
            $stepperSteps = [$startNode, 'dren', 'fop', 'solde', 'drh', 'cde', 'men'];
        } elseif ($typeEtablissement === 'DREN') {
            $stepperSteps = ['dren', 'fop', 'solde', 'drh', 'cde', 'men'];
        } elseif ($typeEtablissement === 'MEN CENTRAL') {
            $stepperSteps = ['drh', 'fop', 'solde', 'cde', 'men'];
        }
    } elseif (in_array($typeB, ['renouvellement', 'avenant'])) {
        if ($isDistrictEtab) {
            $stepperSteps = [$startNode, 'dren', 'solde', 'cde', 'prefet'];
        } elseif ($typeEtablissement === 'DREN') {
            $stepperSteps = ['dren', 'solde', 'cde', 'prefet'];
        } elseif ($typeEtablissement === 'MEN CENTRAL') {
            $stepperSteps = ['drh', 'solde', 'cde', 'men'];
        }
    } elseif (in_array($typeB, ['avancement_classe', 'avancement_echelon'])) {
        if ($isDistrictEtab) {
            $stepperSteps = [$startNode, 'dren', 'fop', 'solde', 'cde', 'prefet'];
        } elseif ($typeEtablissement === 'DREN') {
            $stepperSteps = ['dren', 'fop', 'solde', 'cde', 'prefet'];
        } elseif ($typeEtablissement === 'MEN CENTRAL') {
            $stepperSteps = ['drh', 'fop', 'solde', 'cde', 'mtefop', 'primature'];
        }
    } elseif ($typeB === 'integration') {
        if ($isDistrictEtab) {
            $stepperSteps = [$startNode, 'dren', 'fop', 'solde', 'cde', 'drh', 'mtefop', 'primature'];
        } elseif ($typeEtablissement === 'DREN') {
            $stepperSteps = ['dren', 'fop', 'solde', 'cde', 'drh', 'mtefop', 'primature'];
        } elseif ($typeEtablissement === 'MEN CENTRAL') {
            $stepperSteps = ['drh', 'fop', 'solde', 'cde', 'mtefop', 'primature'];
        }
    } elseif ($typeB === 'titularisation') {
        if ($isDistrictEtab) {
            $stepperSteps = [$startNode, 'dren', 'fop', 'solde', 'drh', 'cde', 'mtefop', 'primature'];
        } elseif ($typeEtablissement === 'DREN') {
            $stepperSteps = ['dren', 'fop', 'solde', 'drh', 'cde', 'mtefop', 'primature'];
        } elseif ($typeEtablissement === 'MEN CENTRAL') {
            $stepperSteps = ['drh', 'fop', 'solde', 'cde', 'mtefop', 'primature'];
        }
    }

    if (empty($stepperSteps)) {
        $stepperSteps = ['cisco', 'dren', 'solde', 'cde', 'prefet'];
    }

    // Fonction calculant la couleur et le style visuel de chaque étape destination
    function computeStepStyle($numBord, $refDest, $statut, $defaultIcon) {
        // Validation prioritaire
        if ($statut === 'valide') {
            return ['bg' => 'bg-emerald-500', 'text' => 'text-emerald-600', 'icon' => 'fas fa-check'];
        }
        // Rejet
        if ($statut === 'rejete') {
            return ['bg' => 'bg-rose-500', 'text' => 'text-rose-600', 'icon' => 'fas fa-times'];
        }
        // Référence créée / existante
        if (!empty($refDest)) {
            return ['bg' => 'bg-blue-600', 'text' => 'text-blue-700', 'icon' => $defaultIcon];
        }
        // Bordereau transmis mais référence non créée
        if (!empty($numBord)) {
            return ['bg' => 'bg-slate-400', 'text' => 'text-slate-500', 'icon' => $defaultIcon];
        }
        // Étape non atteinte
        return ['bg' => 'bg-slate-300', 'text' => 'text-slate-400', 'icon' => $defaultIcon];
    }

    // Identification de la clé du départ selon l'établissement
    $departKey = 'cisco';
    if ($typeEtablissement === 'CRFRP') { $departKey = 'crfrp'; }
    elseif ($typeEtablissement === 'DREN') { $departKey = 'dren'; }
    elseif ($typeEtablissement === 'MEN CENTRAL') { $departKey = 'drh'; }

    // Mappage dynamique des données de suivi pour chaque destination
    $stepDetails = [
        'cisco' => [
            'label' => 'Départ CISCO',
            'style' => ['bg' => 'bg-blue-600', 'text' => 'text-blue-700', 'icon' => 'fas fa-university']
        ],
        'crfrp' => [
            'label' => 'Départ CRFRP',
            'style' => ['bg' => 'bg-blue-600', 'text' => 'text-blue-700', 'icon' => 'fas fa-graduation-cap']
        ],
        'dren' => [
            'label' => ($departKey === 'dren' ? 'Départ DREN' : 'DREN'),
            'style' => ($departKey === 'dren' 
                ? ['bg' => 'bg-blue-600', 'text' => 'text-blue-700', 'icon' => 'fas fa-building'] 
                : computeStepStyle(
                    $suivi['bordereau_cisco_dren'] ?? $suivi['bordereau_crfrp_dren'] ?? '', 
                    $suivi['ref_dren'] ?? '', 
                    $suivi['statut_dren'] ?? '', 
                    'fas fa-file-import'
                ))
        ],
        'drh' => [
            'label' => ($departKey === 'drh' ? 'Départ DRH' : 'DRH'),
            'style' => ($departKey === 'drh' 
                ? ['bg' => 'bg-blue-600', 'text' => 'text-blue-700', 'icon' => 'fas fa-building'] 
                : computeStepStyle(
                    $suivi['bordereau_cisco_drh'] ?? $suivi['bordereau_dren_drh'] ?? $suivi['bordereau_crfrp_drh'] ?? '', 
                    $suivi['ref_drh'] ?? '', 
                    $suivi['statut_drh'] ?? '', 
                    'fas fa-building'
                ))
        ],
        'fop' => [
            'label' => 'Fonction Publique',
            'style' => computeStepStyle(
                $suivi['bordereau_cisco_fop'] ?? $suivi['bordereau_dren_fop'] ?? $suivi['bordereau_crfrp_fop'] ?? $suivi['bordereau_drh_fop'] ?? '', 
                $suivi['ref_fop'] ?? '', 
                $suivi['statut_fop'] ?? '', 
                'fas fa-user-shield'
            )
        ],
        'solde' => [
            'label' => 'Solde et Pensions (AUGURE DSP)',
            'style' => computeStepStyle(
                $suivi['bordereau_dren_solde'] ?? $suivi['bordereau_crfrp_solde'] ?? $suivi['bordereau_drh_solde'] ?? '', 
                $suivi['ref_solde'] ?? '', 
                $suivi['statut_solde'] ?? '', 
                'fas fa-money-check-alt'
            )
        ],
        'cde' => [
            'label' => 'Contrôle Financier (AUGURE CF)',
            'style' => computeStepStyle(
                $suivi['bordereau_dren_cde'] ?? $suivi['bordereau_crfrp_cde'] ?? $suivi['bordereau_drh_cde'] ?? '', 
                $suivi['ref_cde'] ?? '', 
                $suivi['statut_cde'] ?? '', 
                'fas fa-shield-check'
            )
        ],
        'prefet' => [
            'label' => 'Préfecture',
            'style' => computeStepStyle(
                $suivi['bordereau_dren_prefet'] ?? $suivi['bordereau_crfrp_prefet'] ?? '', 
                $suivi['ref_prefecture'] ?? '', 
                $suivi['statut_prefet'] ?? '', 
                'fas fa-stamp'
            )
        ],
        'men' => [
            'label' => 'MEN',
            'style' => computeStepStyle(
                $suivi['bordereau_drh_men'] ?? '', 
                $suivi['ref_men'] ?? '', 
                $suivi['statut_men'] ?? '', 
                'fas fa-building'
            )
        ],
        'mtefop' => [
            'label' => 'MTEFOP',
            'style' => computeStepStyle(
                $suivi['bordereau_drh_mtefop'] ?? '', 
                $suivi['ref_mtefop'] ?? '', 
                $suivi['statut_mtefop'] ?? '', 
                'fas fa-briefcase'
            )
        ],
        'primature' => [
            'label' => 'Primature',
            'style' => computeStepStyle(
                $suivi['bordereau_drh_primature'] ?? '', 
                $suivi['ref_primature'] ?? '', 
                $suivi['statut_primature'] ?? '', 
                'fas fa-landmark'
            )
        ]
    ];
    ?>

    <div class="px-6 py-4 bg-slate-50/50 border-b">
        <div class="flex items-center justify-between max-w-7xl mx-auto relative px-4 py-2">
            <!-- Ligne horizontale de fond -->
            <div class="absolute top-[28px] left-0 w-full h-0.5 bg-slate-200 -translate-y-1/2 z-0"></div>            
            
            <?php foreach ($stepperSteps as $stepKey): ?>
                <?php 
                    $stepInfo = $stepDetails[$stepKey] ?? [
                        'label' => strtoupper($stepKey), 
                        'style' => ['bg' => 'bg-slate-300', 'text' => 'text-slate-400', 'icon' => 'fas fa-circle']
                    ];
                    $style = $stepInfo['style'];
                ?>
                <div class="relative z-10 flex flex-col items-center group">
                    <div class="w-10 h-10 rounded-full <?= $style['bg'] ?> flex items-center justify-center text-white text-xs shadow-lg border-4 border-white transition-all duration-500">
                        <i class="<?= $style['icon'] ?>"></i>
                    </div>
                    <span class="text-[11px] font-black uppercase mt-2 <?= $style['text'] ?> tracking-tighter text-center max-w-[90px] leading-tight">
                        <?= htmlspecialchars($stepInfo['label']) ?>
                    </span>
                </div>
            <?php endforeach; ?>

        </div>
    </div>
    
    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
            <h3 class="text-[10px] font-black uppercase text-slate-400 mb-3 tracking-widest">Légende du suivi</h3>
            <div class="flex flex-wrap gap-4">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-blue-500 rounded-full shadow-sm"></div>
                    <span class="text-[11px] font-bold text-slate-600">Arrivé</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-amber-500 rounded-full shadow-sm"></div>
                    <span class="text-[11px] font-bold text-slate-600">En attente (En cours)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-emerald-500 rounded-full shadow-sm"></div>
                    <span class="text-[11px] font-bold text-slate-600">Terminé</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-rose-500 rounded-full shadow-sm"></div>
                    <span class="text-[11px] font-bold text-slate-600">Rejet</span>
                </div>
            </div>
        </div>

        <div class="bg-blue-50 p-4 rounded-2xl border border-blue-100 flex items-start gap-3">
            <div class="bg-blue-600 text-white p-2 rounded-lg shadow-lg">
                <i class="fas fa-info-circle text-sm"></i>
            </div>
            <div>
                <p class="text-[14px] text-blue-800 leading-relaxed italic">
                    Si votre dossier affiche <span class="text-rose-600 font-bold uppercase">"REJET"</span>, cliquez sur l'icône <i class="fas fa-eye mx-1"></i> pour lire le motif. 
                    Vous devrez contacter le responsable pour apporter les corrections nécessaires.
                </p>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
window.voirMotifRejet = function(motif) {
    Swal.fire({
        title: '<span class="text-rose-600 font-black uppercase tracking-tighter">Détails du Rejet</span>',
        html: `
            <div class="p-6 bg-rose-50 rounded-2xl border-2 border-rose-100 text-rose-900 text-sm italic font-bold shadow-inner">
                <i class="fas fa-exclamation-triangle mr-2 text-rose-600"></i>${motif}
            </div>
        `,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'Fermer',
        customClass: { popup: 'rounded-3xl border-4 border-rose-50' }
    });
}
</script>
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');

$userIm = $_SESSION['user_im'];
$action = $_POST['action'] ?? '';

if ($action === 'list_agents') {
    $typeBordereau = $_POST['type_bordereau'] ?? '';
    $filterTypeDos = $_POST['filter_type_dos'] ?? '';
    $dest          = $_POST['service_destination'] ?? ''; 

    $stmtUser = $pdo->prepare("SELECT code_lieu_affectation, niveau, role_specifique FROM utilisateurs WHERE im = ?");
    $stmtUser->execute([$userIm]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        ob_clean();
        echo json_encode(['data' => []]);
        exit;
    }

    $niveauRaw   = $user['niveau'] ?? '';
    $niveauClean = strtolower(trim($niveauRaw));
    $niveauClean = str_replace(['é', 'è', 'ê'], 'e', $niveauClean);

    // =========================================================================
    // CAS SPÉCIFIQUE : avenant_avec_contrat
    // =========================================================================
    if ($filterTypeDos === 'avenant_avec_contrat') {
        try {
            $sql = "SELECT p.*, e.nom, e.prenoms, s.corps_actuel, s.grade_actuel, af.im,
                           af.solde_et_pensions_mandatement AS ajoute_au_bordereau
                    FROM acte_formate_av_cont af
                    JOIN personnel_poste_actuel p ON af.im = p.im
                    JOIN personnel_etat_civil e ON af.im = e.im
                    JOIN personnel_situation_actuelle s ON af.im = s.im
                    WHERE af.statut = 'valide'
                      AND (af.deja_imprime_mandatement = 0 OR af.deja_imprime_mandatement IS NULL)
                    GROUP BY af.im
                    ORDER BY e.nom ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $output = [];
            foreach ($agents as $index => $a) {
                $dejaAjouteAuBordereau = (isset($a['ajoute_au_bordereau']) && $a['ajoute_au_bordereau'] == 1);

                $typeEtab   = $a['type_etablissement'] ?? '';
                $nomRegion  = $a['nom_region'] ?? '';
                $nomDistrict= $a['nom_district'] ?? '';
                $nomZap     = $a['nom_zap'] ?? '';
                $nomEtab    = $a['nom_etablissement'] ?? '';
                $nomDir     = $a['nom_direction'] ?? '';

                if ($typeEtab === 'MEN CENTRAL') {
                    $lieuService = !empty($nomDir) ? $nomDir : '-';
                } elseif ($typeEtab === 'DREN') {
                    $lieuService = "BUREAU DREN " . $nomRegion;
                } elseif ($typeEtab === 'CISCO') {
                    $lieuService = "BUREAU CISCO " . $nomDistrict;
                } elseif ($typeEtab === 'CRFRP') {
                    $lieuService = !empty($nomEtab) ? $nomEtab : '-';
                } elseif (in_array($typeEtab, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
                    $lieuService = "CISCO " . $nomDistrict . " / ZAP " . $nomZap . " / " . $nomEtab;
                } else {
                    $lieuService = !empty($nomEtab) ? $nomEtab : '-';
                }

                if ($dejaAjouteAuBordereau) {
                    $actionBtn = '
                    <div class="flex items-center justify-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 border border-emerald-200" title="Sélectionné">
                            <i class="fas fa-check-circle text-lg"></i>
                        </div>
                        <button onclick="confirmerActionAgent(\''.$a['im'].'\', \'solde_et_pensions_mandatement\', 0)" class="text-red-500 hover:text-red-700 p-2" title="Retirer de la sélection">
                            <i class="fas fa-trash-alt text-xl"></i>
                        </button>
                    </div>';
                } else {
                    $actionBtn = '
                    <div class="flex justify-center">
                        <button onclick="confirmerActionAgent(\''.$a['im'].'\', \'solde_et_pensions_mandatement\', 1)" class="text-blue-600 hover:text-blue-800 transition-transform hover:scale-110" title="Ajouter au bordereau">
                            <i class="fas fa-plus-circle text-3xl"></i>
                        </button>
                    </div>';
                }

                $row = ['num' => $index + 1];

                if ($niveauClean === 'central') {
                    $row['lieu_service'] = $lieuService;
                    $row['nom_complet']  = ($a['nom'] ?? '') . ' ' . ($a['prenoms'] ?? '');
                    $row['im']           = $a['im'] ?? '';
                    $row['corps_grade']  = ($a['corps_actuel'] ?? '') . ', ' . ($a['grade_actuel'] ?? '');
                    $row['action']       = $actionBtn;
                } elseif ($niveauClean === 'regional') {
                    $row['cisco']        = $a['nom_district'] ?? '-';
                    $row['zap']          = $a['nom_zap'] ?? '-';
                    $row['lieu_service'] = $lieuService;
                    $row['nom_complet']  = ($a['nom'] ?? '') . ' ' . ($a['prenoms'] ?? '');
                    $row['im']           = $a['im'] ?? '';
                    $row['corps_grade']  = ($a['corps_actuel'] ?? '') . ', ' . ($a['grade_actuel'] ?? '');
                    $row['action']       = $actionBtn;
                } elseif ($niveauClean === 'district') {
                    $row['zap']          = $a['nom_zap'] ?? '-';
                    $row['lieu_service'] = $lieuService;
                    $row['nom_complet']  = ($a['nom'] ?? '') . ' ' . ($a['prenoms'] ?? '');
                    $row['im']           = $a['im'] ?? '';
                    $row['corps_grade']  = ($a['corps_actuel'] ?? '') . ', ' . ($a['grade_actuel'] ?? '');
                    $row['action']       = $actionBtn;
                } else {
                    $row['nom_complet']  = ($a['nom'] ?? '') . ' ' . ($a['prenoms'] ?? '');
                    $row['im']           = $a['im'] ?? '';
                    $row['corps_grade']  = ($a['corps_actuel'] ?? '') . ', ' . ($a['grade_actuel'] ?? '');
                    $row['action']       = $actionBtn;
                }

                $output[] = $row;
            }

            ob_clean();
            echo json_encode(['data' => $output]);
            exit;

        } catch (Exception $e) {
            ob_clean();
            echo json_encode(['data' => [], 'error' => "Erreur SQL : " . $e->getMessage()]);
            exit;
        }
    }

    $role   = $user['role_specifique'] ?? '';
    $niveau = $user['niveau'] ?? '';

    $isRespSolde      = ($role === 'resp_solde');
    $isRespNonEncadre = ($role === 'resp_non_encadre');
    $isRespEncadre    = ($role === 'resp_encadre');
    $isRespCrfrp      = ($role === 'resp_personnel_crfrp');
    $isRespRetraite   = ($role === 'resp_retraite');

    $isDistrict = ($niveau === 'district');
    $isRegional = ($niveau === 'regional');
    $isCrfrp    = ($niveau === 'crfrp');
    $isCentral  = ($niveau === 'central');

    // Cartographie dynamique du niveau
    [$colonne_filtre, $param_valeur] = match ($niveau) {
        'district' => ['p.nom_district', $user['code_lieu_affectation']],
        'regional' => ['p.nom_region', $user['code_lieu_affectation']],
        'crfrp'    => ['p.type_etablissement', 'CRFRP'],
        'central'  => ['p.ministere', 'MEN'],
        default    => ['', null]
    };

    try {
        $cond_deja_imprime = "";
        $mapping_impressions = [
            'augure_dren'                   => 'deja_imprime_dren',
            'augure_dsp'                    => 'deja_imprime_solde',
            'augure_cf'                     => 'deja_imprime_cde',
            'augure_fop'                    => 'deja_imprime_fop',
            'prefecture'                    => 'deja_imprime_prefet',            
            'drh'                           => 'deja_imprime_drh',
            'mtefop'                        => 'deja_imprime_mtefop',
            'primature'                     => 'deja_imprime_primature',
            'solde_et_pensions_mandatement' => 'deja_imprime_mandatement'
        ];

        if (!empty($dest) && isset($mapping_impressions[$dest])) {
            $cond_deja_imprime = " AND d." . $mapping_impressions[$dest] . " = 0 ";
        }

        // RESP_SOLDE
        if ($isRespSolde) {
            $geoCondition = "";
            $params = [$filterTypeDos];

            if ($isDistrict) {
                $geoCondition = " AND p.nom_district = ? AND p.type_etablissement <> 'CRFRP' ";
                $params[] = $user['code_lieu_affectation'];
            } elseif ($isRegional) {
                $geoCondition = " AND p.nom_region = ? AND p.type_etablissement <> 'CRFRP' ";
                $params[] = $user['code_lieu_affectation'];
            } elseif ($isCentral) {
                $geoCondition = " AND p.type_etablissement = ? ";
                $params[] = 'MEN CENTRAL'; 
            }

            $sql = "SELECT p.*, e.nom, e.prenoms, s.corps_actuel, s.grade_actuel, 
                           af.im, af.solde_et_pensions_mandatement, af.deja_imprime_mandatement
                    FROM acte_formate af
                    JOIN personnel_poste_actuel p ON af.im = p.im
                    JOIN personnel_etat_civil e ON af.im = e.im
                    JOIN personnel_situation_actuelle s ON af.im = s.im
                    WHERE af.statut = 'termine'
                    AND af.deja_imprime_mandatement = 0
                    AND af.type_demande = ?
                    $geoCondition
                    GROUP BY af.im
                    ORDER BY e.nom ASC";

        // RESP_PERSONNEL_CRFRP
        } elseif ($isRespCrfrp) {
            if ($dest === 'augure_dsp' && $typeBordereau === 'mandatement') {
                $sql = "SELECT p.*, e.nom, e.prenoms, s.corps_actuel, s.grade_actuel, 
                               af.im, af.solde_et_pensions_mandatement, af.deja_imprime_mandatement
                        FROM acte_formate af
                        JOIN personnel_poste_actuel p ON af.im = p.im
                        JOIN personnel_etat_civil e ON af.im = e.im
                        JOIN personnel_situation_actuelle s ON af.im = s.im
                        WHERE af.statut = 'termine'
                        AND af.deja_imprime_mandatement = 0
                        AND af.type_demande = ?
                        AND p.nom_etablissement = ?
                        GROUP BY af.im
                        ORDER BY e.nom ASC";
                $params = [$filterTypeDos, $user['code_lieu_affectation']];
            
            } else {
                $sql = "SELECT p.*, d.*, e.nom, e.prenoms, s.corps_actuel, s.grade_actuel, s.statut_actuel, d.im
                        FROM demandes_numeros_dos d
                        JOIN personnel_poste_actuel p ON p.im = d.im
                        JOIN personnel_etat_civil e ON p.im = e.im
                        JOIN personnel_situation_actuelle s ON p.im = s.im
                        LEFT JOIN suivi_agents_bordereau sb ON p.im = sb.im_agent
                        WHERE d.dos_crfrp = ? 
                        AND d.statut IN ('EN_ATTENTE', 'ATTRIBUE') " . $cond_deja_imprime;

                $params = [$user['code_lieu_affectation']];

                $typeDosMapping = [
                    'titularisation'     => 'Titularisation',
                    'avancement_classe'  => 'Avancement_classe',
                    'avancement_echelon' => 'Avancement_echelon',
                    'admission_retraite' => 'Admission_retraite',
                    'installation'       => 'Installation',
                    'compensatrice'      => 'Compensatrice',
                ];

                $destStatusMapping = [
                    'augure_fop' => " AND sb.statut_dren IN ('valide', 'en_attente') ",
                    'augure_dsp' => " AND sb.statut_fop = 'valide' ",
                    'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                    'prefecture' => " AND sb.statut_cde = 'valide' ",
                    'drh'        => " AND sb.statut_cde = 'valide' ",
                ];

                switch ($filterTypeDos) {
                    case 'renouvellement':
                        $sql .= " AND (d.alerte_id LIKE '%RNC1%' OR d.alerte_id LIKE '%RNC2%') ";
                        $sql .= match ($dest) {
                            'augure_dsp' => " AND sb.statut_dren IN ('valide', 'en_attente') ",
                            'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                            'prefecture' => " AND sb.statut_cde = 'valide' ",
                            default      => "",
                        };
                        break;

                    case 'avenant':
                        $sql .= " AND s.statut_actuel = 'Contractuel EFA' AND d.alerte_id LIKE '%HIDDEN_%_AVANCEMENT_%' ";
                        $sql .= match ($dest) {
                            'augure_dsp' => " AND sb.statut_dren IN ('valide', 'en_attente') ",
                            'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                            'prefecture' => " AND sb.statut_cde = 'valide' ",
                            default      => "",
                        };
                        break;

                    case 'integration':
                        $sql .= " AND d.alerte_id LIKE '%INTG%' ";
                        $sql .= match ($dest) {
                            'augure_fop' => " AND sb.statut_dren IN ('valide', 'en_attente') ",
                            'augure_dsp' => " AND sb.statut_fop = 'valide' ",
                            'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                            'drh'        => " AND sb.statut_cde = 'valide' ",
                            default      => "",
                        };
                        break;

                    case 'titularisation':
                    case 'avancement_classe':
                    case 'avancement_echelon':
                        $sql .= " AND d.type_dos = ? ";
                        $params[] = $typeDosMapping[$filterTypeDos];

                        if ($filterTypeDos === 'titularisation' && $dest === 'drh') {
                            $sql .= " AND sb.statut_solde = 'valide' ";
                        } elseif (isset($destStatusMapping[$dest])) {
                            $sql .= $destStatusMapping[$dest];
                        }
                        break;

                    case 'admission_retraite':
                        $sql .= " AND d.type_dos = ? ";
                        $params[] = $typeDosMapping[$filterTypeDos];
                        if ($dest === 'drh') {
                            $sql .= " AND sb.statut_fop = 'valide' ";
                        }
                        break;

                    case 'installation':
                        $sql .= " AND d.type_dos = ? ";
                        $params[] = $typeDosMapping[$filterTypeDos];
                        if ($dest === 'augure_dsp') {
                            $sql .= " AND sb.statut_fop = 'valide' ";
                        } elseif ($dest === 'drh') {
                            $sql .= " AND sb.statut_solde = 'valide' ";
                        }
                        break;

                    case 'compensatrice':
                        $sql .= " AND d.type_dos = ? ";
                        $params[] = $typeDosMapping[$filterTypeDos];
                        $sql .= match ($dest) {
                            'augure_dren' => " AND (sb.statut_dren IS NULL OR sb.statut_dren = 'en_attente') ",
                            'augure_dsp'  => " AND sb.statut_dren = 'valide' ",
                            'augure_cf'   => " AND sb.statut_solde = 'valide' ",
                            'prefecture'  => " AND sb.statut_cde = 'valide' ",
                            default       => "",
                        };
                        break;
                }
            }

        // RESP_NON_ENCADRE - RESP_ENCADRE - RESP_RETRAITE
        } else {
            $mapTypeBordereau = [
                'avancement_classe'  => 'avancement_classe',
                'avancement_echelon' => 'avancement_echelon',
                'integration'        => 'integration',
                'titularisation'     => 'titularisation',
                'renouvellement'     => 'renouvellement',
                'avenant'            => 'avenant',
                'admission_retraite' => 'admission_retraite',
                'compensatrice'      => 'compensatrice',
                'installation'       => 'installation'
            ];
            $typeBordereauSql = $mapTypeBordereau[$filterTypeDos] ?? $filterTypeDos;

            $sql = "SELECT p.*, d.*, e.nom, e.prenoms, s.corps_actuel, s.grade_actuel, s.statut_actuel, d.im
                    FROM personnel_poste_actuel p
                    JOIN personnel_etat_civil e ON p.im = e.im
                    JOIN personnel_situation_actuelle s ON p.im = s.im
                    JOIN demandes_numeros_dos d ON p.im = d.im
                    LEFT JOIN suivi_agents_bordereau sb 
                           ON p.im = sb.im_agent 
                          AND sb.type_bordereau = '" . $typeBordereauSql . "'
                    WHERE $colonne_filtre = ? 
                      AND (d.statut = 'EN_ATTENTE' OR d.statut = 'ATTRIBUE') " . $cond_deja_imprime;

            $params = [$param_valeur];

            // NIVEAU = DISTRICT
            if ($isDistrict) {
                if ($isRespNonEncadre) {
                    $sql .= " AND s.statut_actuel = 'Contractuel EFA' ";

                    $alerteFilters = [
                        'renouvellement' => "(d.alerte_id LIKE '%RNC1%' OR d.alerte_id LIKE '%RNC2%')",
                        'avenant'        => "d.alerte_id LIKE '%HIDDEN_%_AVANCEMENT_%'",
                        'integration'    => "d.alerte_id LIKE '%INTG%'"
                    ];

                    if (isset($alerteFilters[$filterTypeDos])) {
                        $sql .= " AND " . $alerteFilters[$filterTypeDos] . " ";
                    }

                } elseif ($isRespEncadre) {
                    $mapEncadre = [
                        'avancement_classe'  => 'Avancement_classe', 
                        'avancement_echelon' => 'Avancement_echelon', 
                        'titularisation'     => 'Titularisation'
                    ]; 

                    if (isset($mapEncadre[$filterTypeDos])) {
                        $sql .= " AND s.statut_actuel = 'Fonctionnaire' ";
                        $sql .= " AND d.type_dos = '" . $mapEncadre[$filterTypeDos] . "' ";
                    }

                } elseif ($isRespRetraite || $isRespCrfrp) {
                    $mapRetraite = [
                        'admission_retraite' => 'Admission_retraite',
                        'compensatrice'      => 'Compensatrice',
                        'installation'       => 'Installation'
                    ];

                    if (isset($mapRetraite[$filterTypeDos])) {
                        $sql .= " AND d.type_dos = '" . $mapRetraite[$filterTypeDos] . "' ";

                        if ($filterTypeDos === 'admission_retraite') {
                            if ($dest === 'drh') {
                                $sql .= " AND sb.statut_fop = 'valide' ";
                            }
                        } elseif ($filterTypeDos === 'installation') {
                            if ($dest === 'augure_dsp') {
                                $sql .= " AND sb.statut_fop = 'valide' ";
                            } elseif ($dest === 'drh') {
                                $sql .= " AND sb.statut_solde = 'valide' ";
                            }
                        } elseif ($filterTypeDos === 'compensatrice') {
                            if ($dest === 'augure_dren') {
                                $sql .= " AND (sb.statut_dren IS NULL OR sb.statut_dren = 'en_attente') ";
                            }
                        }
                    }
                }
            } 
            // NIVEAU = CENTRAL
            elseif ($isCentral) {
                $typeDosMapping = [
                    'avancement_classe'  => 'Avancement_classe',
                    'avancement_echelon' => 'Avancement_echelon',
                    'titularisation'     => 'Titularisation',
                    'admission_retraite' => 'Admission_retraite',
                    'compensatrice'      => 'Compensatrice',
                    'installation'       => 'Installation',
                ];

                if ($isRespNonEncadre) {
                    $sql .= " AND s.statut_actuel = 'Contractuel EFA' ";

                    switch ($filterTypeDos) {
                        case 'renouvellement':
                            $sql .= " AND p.lieu_de_service = 'ANTANANARIVO' AND (d.alerte_id LIKE '%RNC1%' OR d.alerte_id LIKE '%RNC2%') ";
                            break;

                        case 'avenant':
                            $sql .= " AND p.lieu_de_service = 'ANTANANARIVO' AND d.alerte_id LIKE '%HIDDEN_%_AVANCEMENT_%' ";
                            break;

                        case 'integration':
                            $sql .= " AND d.alerte_id LIKE '%INTG%' AND (
                                        p.lieu_de_service = 'ANTANANARIVO' 
                                        OR (p.ministere = 'MEN' AND sb.statut_cde = 'valide')
                                    ) ";
                            
                            $sql .= match ($dest) {
                                'mtefop'    => " AND sb.statut_cde = 'valide' ",
                                'primature' => " AND sb.statut_mtefop = 'valide' ",
                                default     => "",
                            };
                            break;
                    }
                }

                elseif ($isRespEncadre) {
                    if ($filterTypeDos === 'titularisation') {
                        $sql .= " AND s.statut_actuel = 'Fonctionnaire' ";
                    }

                    if (isset($typeDosMapping[$filterTypeDos])) {
                        $sql .= " AND d.type_dos = ? ";
                        $params[] = $typeDosMapping[$filterTypeDos];

                        if ($filterTypeDos === 'titularisation') {
                            $sql .= " AND sb.statut_solde = 'valide' ";
                        } else {
                            $sql .= match ($dest) {
                                'augure_fop' => " AND (sb.statut_drh = 'valide' OR sb.id IS NULL) ",
                                'augure_dsp' => " AND sb.statut_fop = 'valide' ",
                                'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                                'mtefop', 'primature' => " AND p.ministere = 'MEN' AND (
                                                            (p.type_etablissement = 'MEN CENTRAL' AND sb.statut_cde = 'valide')
                                                            OR (p.type_etablissement != 'MEN CENTRAL' AND sb.statut_drh = 'valide')
                                                        ) ",
                                default => "",
                            };
                        }
                    }
                }

                elseif ($isRespRetraite || in_array($filterTypeDos, ['admission_retraite', 'compensatrice', 'installation'])) {
                    if (isset($typeDosMapping[$filterTypeDos])) {
                        $sql .= " AND d.type_dos = ? ";
                        $params[] = $typeDosMapping[$filterTypeDos];

                        if (in_array($filterTypeDos, ['admission_retraite', 'installation'])) {
                            $sql .= " AND p.ministere = 'MEN' ";

                            if ($filterTypeDos === 'admission_retraite') {
                                // Dissociation des agents MEN CENTRAL et des agents des structures déconcentrées (DREN, CISCO, CRFRP, etc.)
                                $sql .= " AND (
                                    (
                                        p.type_etablissement = 'MEN CENTRAL' 
                                        AND " . match ($dest) {
                                            'augure_dsp' => "(sb.statut_drh = 'valide' OR sb.id IS NULL)",
                                            'augure_cf'  => "sb.statut_solde = 'valide'",
                                            'mtefop', 'primature' => "sb.statut_cde = 'valide'",
                                            default => "1=1",
                                        } . "
                                    ) 
                                    OR 
                                    (
                                        p.type_etablissement != 'MEN CENTRAL' 
                                        AND sb.statut_drh = 'valide'
                                        " . match ($dest) {
                                            'augure_dsp' => " AND (sb.statut_solde = 'en_attente' OR sb.statut_solde IS NULL OR sb.statut_solde = 'valide')",
                                            'augure_cf'  => " AND sb.statut_solde = 'valide'",
                                            'mtefop', 'primature' => " AND sb.statut_cde = 'valide'",
                                            default => "",
                                        } . "
                                    )
                                ) ";

                            } elseif ($filterTypeDos === 'installation') {
                                // Pour les agents hors MEN CENTRAL, l'étape passe par augure_fop -> augure_dsp -> drh, 
                                // donc l'agent n'est visible au niveau central qu'à partir du DRH
                                $sql .= " AND (
                                    (
                                        p.type_etablissement = 'MEN CENTRAL'
                                        AND " . match ($dest) {
                                            'augure_dsp' => "(sb.statut_fop = 'valide' OR sb.id IS NULL)",
                                            'drh'        => "sb.statut_solde = 'valide'",
                                            default => "1=1",
                                        } . "
                                    )
                                    OR
                                    (
                                        p.type_etablissement != 'MEN CENTRAL'
                                        AND sb.statut_drh = 'valide'
                                    )
                                ) ";
                            }

                        } elseif ($filterTypeDos === 'compensatrice') {
                            $sql .= match ($dest) {
                                'augure_dsp' => " AND (sb.statut_drh = 'valide' OR sb.id IS NULL) ",
                                'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                                'drh'        => " AND sb.statut_cde = 'valide' ",
                                default      => "",
                            };
                        }
                    }
                }
            } 
            // NIVEAU REGIONAL
            else {
                $typeDosMapping = [
                    'avancement_classe'  => 'Avancement_classe',
                    'avancement_echelon' => 'Avancement_echelon',
                    'titularisation'     => 'Titularisation',
                    'admission_retraite' => 'Admission_retraite',
                    'compensatrice'      => 'Compensatrice',
                    'installation'       => 'Installation',
                ];

                if ($isRespNonEncadre) {
                    $sql .= " AND s.statut_actuel = 'Contractuel EFA' ";

                    switch ($filterTypeDos) {
                        case 'renouvellement':
                            $sql .= " AND (d.alerte_id LIKE '%RNC1%' OR d.alerte_id LIKE '%RNC2%') ";
                            $sql .= match ($dest) {
                                'augure_dsp' => " AND (sb.statut_dren IN ('valide', 'en_attente') OR sb.id IS NULL) ",
                                'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                                'prefecture' => " AND sb.statut_cde = 'valide' ",
                                default      => "",
                            };
                            break;

                        case 'avenant':
                            $sql .= " AND d.alerte_id LIKE '%HIDDEN_%_AVANCEMENT_%' ";
                            $sql .= match ($dest) {
                                'augure_dsp' => " AND (sb.statut_dren IN ('valide', 'en_attente') OR sb.id IS NULL) ",
                                'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                                'prefecture' => " AND sb.statut_cde = 'valide' ",
                                default      => "",
                            };
                            break;

                        case 'integration':
                            $sql .= " AND d.alerte_id LIKE '%INTG' ";
                            $sql .= match ($dest) {
                                'augure_fop' => " AND (sb.statut_dren IN ('valide', 'en_attente') OR sb.id IS NULL) ",
                                'augure_dsp' => " AND sb.statut_fop = 'valide' ",
                                'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                                'drh'        => " AND sb.statut_cde = 'valide' ",
                                'mtefop'     => " AND sb.statut_drh = 'valide' ",
                                default      => "",
                            };
                            break;
                    }
                } 
                
                elseif ($isRespEncadre) {
                    if ($filterTypeDos === 'titularisation') {
                        $sql .= " AND s.statut_actuel = 'Fonctionnaire' ";
                    }

                    if (isset($typeDosMapping[$filterTypeDos])) {
                        $sql .= " AND d.type_dos = ? ";
                        $params[] = $typeDosMapping[$filterTypeDos];

                        if ($filterTypeDos === 'titularisation') {
                            $sql .= match ($dest) {
                                'augure_fop' => " AND (sb.statut_dren IN ('valide', 'en_attente') OR sb.id IS NULL) ",
                                'augure_dsp' => " AND sb.statut_fop = 'valide' ",
                                'drh'        => " AND sb.statut_solde = 'valide' ",
                                'dgcf'       => " AND sb.statut_drh = 'valide' ",
                                'mtefop'     => " AND sb.statut_cde = 'valide' ",
                                default      => "",
                            };
                        } else {
                            $sql .= match ($dest) {
                                'augure_fop' => " AND (sb.statut_dren IN ('valide', 'en_attente') OR sb.id IS NULL) ",
                                'augure_dsp' => " AND sb.statut_fop = 'valide' ",
                                'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                                'prefecture' => " AND sb.statut_cde = 'valide' ",
                                default      => "",
                            };
                        }
                    }
                } 
                
                elseif ($isRespRetraite || $isRespCrfrp || in_array($filterTypeDos, ['admission_retraite', 'compensatrice', 'installation'])) {
                    if (isset($typeDosMapping[$filterTypeDos])) {
                        $sql .= " AND d.type_dos = ? ";
                        $params[] = $typeDosMapping[$filterTypeDos];

                        switch ($filterTypeDos) {
                            case 'admission_retraite':
                                if ($dest === 'drh') {
                                    $sql .= " AND (sb.statut_fop = 'valide' OR sb.id IS NULL OR sb.statut_fop IS NULL) ";
                                }
                                break;

                            case 'installation':
                                $sql .= match ($dest) {
                                    'augure_dsp' => " AND sb.statut_fop = 'valide' ",
                                    'drh'        => " AND sb.statut_solde = 'valide' ",
                                    default      => "",
                                };
                                break;

                            case 'compensatrice':
                                if ($isRespCrfrp) {
                                    $sql .= match ($dest) {
                                        'augure_dren' => " AND (sb.statut_dren IS NULL OR sb.statut_dren = 'en_attente') ",
                                        'augure_dsp'  => " AND sb.statut_dren = 'valide' ",
                                        'augure_cf'   => " AND sb.statut_solde = 'valide' ",
                                        'prefecture'  => " AND sb.statut_cde = 'valide' ",
                                        default       => "",
                                    };
                                } elseif ($isRespRetraite) {
                                    $sql .= match ($dest) {
                                        'augure_dsp' => " AND (sb.statut_solde IS NULL OR sb.statut_solde = 'en_attente') ",
                                        'augure_cf'  => " AND sb.statut_solde = 'valide' ",
                                        'prefecture' => " AND sb.statut_cde = 'valide' ",
                                        default      => "",
                                    };
                                }
                                break;
                        }
                    }
                }
            }
        }

        // FINALISATION DE LA REQUETE
        $isMandatement = (
            $isRespSolde
            || (
                $isRespCrfrp
                && $dest === 'augure_dsp'
                && $typeBordereau === 'mandatement'
            )
        );

        if (!$isMandatement) {
            if ($dest === 'dren') {
                $sql .= " AND d.dos_region IS NOT NULL AND TRIM(d.dos_region) != '' ";
            }
            $sql .= " GROUP BY d.im ORDER BY e.nom ASC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // BOUTONS ET COLONNES
        $output = [];
        foreach ($agents as $index => $a) {
            
            if ($isMandatement) {
                $targetCol = 'solde_et_pensions_mandatement';
                $dejaAjouteAuBordereau = (isset($a['solde_et_pensions_mandatement']) && $a['solde_et_pensions_mandatement'] == 1);
            } else {
                $targetCol = $dest;
                $dejaAjouteAuBordereau = (isset($a[$targetCol]) && $a[$targetCol] == 1);
            }

            $typeEtab   = $a['type_etablissement'] ?? '';
            $nomRegion  = $a['nom_region'] ?? '';
            $nomDistrict= $a['nom_district'] ?? '';
            $nomZap     = $a['nom_zap'] ?? '';
            $nomEtab    = $a['nom_etablissement'] ?? '';
            $nomDir     = $a['nom_direction'] ?? '';

            if ($typeEtab === 'MEN CENTRAL') {
                $lieuService = !empty($nomDir) ? $nomDir : '-';
            } elseif ($typeEtab === 'DREN') {
                $lieuService = "BUREAU DREN " . $nomRegion;
            } elseif ($typeEtab === 'CISCO') {
                $lieuService = "BUREAU CISCO " . $nomDistrict;
            } elseif ($typeEtab === 'CRFRP') {
                $lieuService = !empty($nomEtab) ? $nomEtab : '-';
            } elseif (in_array($typeEtab, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
                $lieuService = "CISCO " . $nomDistrict . " / ZAP " . $nomZap . " / " . $nomEtab;
            } else {
                $lieuService = !empty($nomEtab) ? $nomEtab : '-';
            }

            // BOUTON ACTION
            if ($dejaAjouteAuBordereau) {
                $actionBtn = '
                <div class="flex items-center justify-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 border border-emerald-200" title="Sélectionné">
                        <i class="fas fa-check-circle text-lg"></i>
                    </div>
                    <button onclick="confirmerActionAgent(\''.$a['im'].'\', \''.$targetCol.'\', 0)" class="text-red-500 hover:text-red-700 p-2" title="Retirer de la sélection">
                        <i class="fas fa-trash-alt text-xl"></i>
                    </button>
                </div>';
            } else {
                $actionBtn = '
                <div class="flex justify-center">
                    <button onclick="confirmerActionAgent(\''.$a['im'].'\', \''.$targetCol.'\', 1)" class="text-blue-600 hover:text-blue-800 transition-transform hover:scale-110" title="Ajouter au bordereau">
                        <i class="fas fa-plus-circle text-3xl"></i>
                    </button>
                </div>';
            }

            $niveauRaw = $user['niveau'] ?? '';
            $niveauClean = strtolower(trim($niveauRaw));
            $niveauClean = str_replace(['é', 'è', 'ê'], 'e', $niveauClean);

            $row = ['num' => $index + 1];
            if ($niveauClean === 'central') {
                $row['lieu_service'] = $lieuService;
                $row['nom_complet']  = ($a['nom'] ?? '') . ' ' . ($a['prenoms'] ?? '');
                $row['im']           = $a['im'] ?? '';
                $row['corps_grade']  = ($a['corps_actuel'] ?? '') . ', ' . ($a['grade_actuel'] ?? '');
                $row['action']       = $actionBtn;
            } elseif ($niveauClean === 'regional') {                
                $row['cisco']        = $a['nom_district'] ?? '-';
                $row['zap']          = $a['nom_zap'] ?? '-';
                $row['lieu_service'] = $lieuService;
                $row['nom_complet']  = ($a['nom'] ?? '') . ' ' . ($a['prenoms'] ?? '');
                $row['im']           = $a['im'] ?? '';
                $row['corps_grade']  = ($a['corps_actuel'] ?? '') . ', ' . ($a['grade_actuel'] ?? '');
                $row['action']       = $actionBtn;
            } elseif ($niveauClean === 'district') {
                $row['zap']          = $a['nom_zap'] ?? '-';
                $row['lieu_service'] = $lieuService;
                $row['nom_complet']  = ($a['nom'] ?? '') . ' ' . ($a['prenoms'] ?? '');
                $row['im']           = $a['im'] ?? '';
                $row['corps_grade']  = ($a['corps_actuel'] ?? '') . ', ' . ($a['grade_actuel'] ?? '');
                $row['action']       = $actionBtn;
            } else {
                $row['nom_complet']  = ($a['nom'] ?? '') . ' ' . ($a['prenoms'] ?? '');
                $row['im']           = $a['im'] ?? '';
                $row['corps_grade']  = ($a['corps_actuel'] ?? '') . ', ' . ($a['grade_actuel'] ?? '');
                $row['action']       = $actionBtn;
            }

            $output[] = $row;
        }
        
        ob_clean();
        echo json_encode(['data' => $output]);
        exit;

    } catch (Exception $e) {
        ob_clean();
        echo json_encode([
            'data'  => [],
            'error' => "Erreur SQL : " . $e->getMessage()
        ]);
        exit;
    }
}

if ($action === 'ajout_agent') {
    ob_clean();
    $im            = $_POST['im'] ?? '';
    $destination   = $_POST['destination'] ?? ''; 
    $valeur        = intval($_POST['valeur'] ?? 0); 
    $filterTypeDos = $_POST['filter_type_dos'] ?? $_POST['type_dos'] ?? ''; 

    if (empty($im) || empty($destination)) {
        echo json_encode(['success' => false, 'message' => 'Données incomplètes']);
        exit;
    }

    if ($filterTypeDos === 'avenant_avec_contrat') {
        $sql = "UPDATE acte_formate_av_cont 
                SET solde_et_pensions_mandatement = ? 
                WHERE im = ? AND statut = 'valide'";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$valeur, $im])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
        }
        exit;
    }

    if ($destination === 'solde_et_pensions_mandatement') {
        $sql = "UPDATE acte_formate
                SET solde_et_pensions_mandatement = ? 
                WHERE im = ? AND statut = 'termine'";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$valeur, $im]) && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Aucune ligne mise à jour dans acte_formate (IM ou statut incorrect)'
            ]);
        }
        exit;
    }

    // === CAS NORMAL → demandes_numeros_dos ===
    $allowed_cols = [
        'augure_dren', 'augure_fop', 'augure_dsp', 'augure_cf', 
        'prefecture', 'drh', 'mtefop', 'primature'
    ];
    
    if (!in_array($destination, $allowed_cols)) {
        echo json_encode(['success' => false, 'message' => 'Destination invalide']);
        exit;
    }

    $mapTypeDos = [
        'admission_retraite' => 'Admission_retraite',
        'compensatrice'      => 'Compensatrice',
        'installation'       => 'Installation',
        'avancement_classe'  => 'Avancement_classe',
        'avancement_echelon' => 'Avancement_echelon',
        'integration'        => 'Intégration',
        'titularisation'     => 'Titularisation',
        'renouvellement'     => 'Renouvellement',   
        'avenant'            => 'Avenant',
        'conge_annuel'       => 'Conge_annuel'
    ];

    $typesAvecTypeDos = [
        'avancement_classe',
        'avancement_echelon',
        'integration',
        'titularisation',
        'admission_retraite',
        'compensatrice',
        'installation'
    ];

    $params = [$valeur, $im];
    $sqlTypeDosCondition = "";

    if (!empty($filterTypeDos) 
        && in_array($filterTypeDos, $typesAvecTypeDos) 
        && isset($mapTypeDos[$filterTypeDos])) {
        
        $sqlTypeDosCondition = " AND type_dos = ? ";
        $params[] = $mapTypeDos[$filterTypeDos];
    }

    $sql = "UPDATE demandes_numeros_dos 
            SET `$destination` = ? 
            WHERE im = ? $sqlTypeDosCondition";
            
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute($params)) {
        $affected =$stmt->rowCount();
        
        if ($affected > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Aucune ligne mise à jour. Vérifiez l\'IM ou le type de dossier.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur SQL lors de la mise à jour'
        ]);
    }
    exit;
}
?>
<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/alertes_notifications.php';

$action = $_POST['action'] ?? '';

if ($action === 'list_bordereau') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $typeDemande       = trim($_POST['type'] ?? '');
        $typeBordereauCat  = trim($_POST['type_bordereau_cat'] ?? '');
        $destinationSelect = trim($_POST['destination'] ?? '');
        $userIm            = $_SESSION['user_im'] ?? '';
        $userNiveau        = strtolower(trim($_SESSION['user_niveau'] ?? ''));

        $roleSpecifique = '';
        $codeLieu = '';
        try {
            $stmtRole = $pdo->prepare("SELECT role_specifique, niveau, code_lieu_affectation FROM utilisateurs WHERE im = ?");
            $stmtRole->execute([$userIm]);
            $userU = $stmtRole->fetch(PDO::FETCH_ASSOC);
            if ($userU) {
                $roleSpecifique = trim($userU['role_specifique'] ?? '');
                $userNiveau     = strtolower(trim($userU['niveau'] ?? $userNiveau));
                $codeLieu       = trim($userU['code_lieu_affectation'] ?? '');
            }
        } catch (Exception $e) {
            $roleSpecifique = '';
            $codeLieu = '';
        }

        $isAdmin      = in_array($roleSpecifique, ['admin', 'chef_service', 'chef_division']);
        $isSolde      = ($roleSpecifique === 'resp_solde');
        $isNonEncadre = ($roleSpecifique === 'resp_non_encadre');
        $isEncadre    = ($roleSpecifique === 'resp_encadre');
        $isRetraite    = ($roleSpecifique === 'resp_retraite');
        $isCRFRP      = ($roleSpecifique === 'resp_personnel_crfrp');
        $isDistrict   = ($userNiveau === 'district');

        // Mapping des libellés de destinations
        $destLabels = [
            'augure_dren' => 'DREN',
            'augure_fop'  => 'Fonction Publique (DRHEFOP)',
            'augure_dsp'  => 'Solde et Pensions (DSP)',
            'augure_cf'   => 'Contrôle Financier (DGCF)',
            'prefecture'  => 'Préfecture',
            'drh'         => 'Direction des Ressources Humaines (DRH)',
            'mtefop'      => 'Fonction publique (MTeFOP)',
            'primature'   => 'Primature'
        ];

        // Mapping des libellés des demandes
        $demandeLabels = [
            'renouvellement'     => 'Renouvellement de contrat',
            'avenant'            => 'Avenant',
            'avenant_avec_contrat' => 'Avenant (Rappel différentiel moins perçu)',
            'avancement_echelon' => "Avancement d'échelon",
            'avancement_classe'  => 'Avancement de classe',
            'integration'        => 'Intégration',
            'titularisation'     => 'Titularisation',
            'admission_retraite' => 'Admission à la retraite',
            'compensatrice'      => 'Compensatrice',
            'installation'       => 'Installation',
            'conge_annuel'       => 'Congé annuel'
        ];

        // === CAS SPÉCIFIQUE : RESPONSABLE SOLDE ET PENSIONS (DISTRICT & REGIONAL) ===
        if ($isSolde) {
            $typeBordereauCat  = 'mandatement';
            $destinationSelect = 'augure_dsp';
            
            $allowedDemandes = ['renouvellement', 'avenant', 'avenant_avec_contrat', 'avancement_classe', 'avancement_echelon', 'integration', 'titularisation', 'compensatrice', 'installation'];
            
            if (!empty($typeDemande) && !in_array($typeDemande, $allowedDemandes)) {
                ob_clean();
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
        }
        // === CAS SPÉCIFIQUE : AUTRES RÔLES AU NIVEAU DISTRICT ===
        elseif ($isDistrict) {
            $typeBordereauCat = 'creation_projet';            
            if ($isNonEncadre) {
                $allowedDemandes = ['renouvellement', 'avenant', 'integration'];
            } elseif ($isEncadre) {
                $allowedDemandes = ['avancement_echelon', 'avancement_classe', 'titularisation'];
            } elseif ($isRetraite) {
                $allowedDemandes = ['admission_retraite', 'compensatrice', 'installation'];
            } else {
                // Ajout explicite de admission_retraite et installation
                $allowedDemandes = ['renouvellement', 'avenant', 'avancement_echelon', 'avancement_classe', 'integration', 'titularisation', 'admission_retraite', 'compensatrice', 'installation'];
            }
        } 
        // === NIVEAUX NON-DISTRICT (Autres rôles) ===
        else {
            $allowedCats = [];
            if ($isNonEncadre || $isEncadre) {
                $allowedCats = ['creation_projet'];
            } elseif ($isCRFRP || $isRetraite || $isAdmin) {
                $allowedCats = ['creation_projet', 'mandatement', 'conge'];
            } else {
                ob_clean();
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            if (!in_array($typeBordereauCat, $allowedCats)) {
                ob_clean();
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            $allowedDemandes = [];
            if ($isNonEncadre) {
                $allowedDemandes = ['renouvellement', 'avenant', 'integration'];
            } elseif ($isEncadre) {
                $allowedDemandes = ['avancement_classe', 'avancement_echelon', 'titularisation'];
            } elseif ($isRetraite) {
                $allowedDemandes = ['admission_retraite', 'compensatrice', 'installation'];
            } elseif ($isCRFRP || $isAdmin) {
                $allowedDemandes = ['renouvellement', 'avenant', 'avancement_classe', 'avancement_echelon', 'titularisation', 'integration', 'conge_annuel', 'admission_retraite', 'compensatrice', 'installation'];
            }

            if (!empty($typeDemande) && !in_array($typeDemande, $allowedDemandes)) {
                ob_clean();
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
        }

        // Sélection de la ou des tables cibles
        if ($isSolde) {
            $allDestinations = [
                'augure_dsp' => ['table' => 'archives_bordereaux_solde']
            ];
        } else {
            $allDestinations = [
                'augure_dren' => ['table' => 'archives_bordereaux_dren'],
                'augure_fop'  => ['table' => 'archives_bordereaux_fop'],
                'augure_dsp'  => ['table' => 'archives_bordereaux_solde'],
                'augure_cf'   => ['table' => 'archives_bordereaux_cde'],
                'prefecture'  => ['table' => 'archives_bordereaux_prefet'],
                'drh'         => ['table' => 'archives_bordereaux_drh'],                
                'mtefop'      => ['table' => 'archives_bordereaux_mtefop'],
                'primature'   => ['table' => 'archives_bordereaux_primature']
            ];
        }

        // Construction des requêtes UNION
        $unionQueries = [];
        foreach ($allDestinations as $key => $config) {
            if (!$isSolde && !empty($destinationSelect) && $destinationSelect !== $key) {
                continue;
            }

            $table = $config['table'];
            $unionQueries[] = "
                SELECT 
                    a.id, 
                    a.numero_complet, 
                    a.date_envoi_bordereau, 
                    a.type_bordereau, 
                    a.type_demande, 
                    a.type_etablissement, 
                    a.expediteur,
                    a.reference_destination, 
                    a.date_reference_destination, 
                    a.statut_bordereau,
                    a.liste_agents,
                    a.nombre_agents,
                    '$key' as dest_key
                FROM $table a
                WHERE a.statut_bordereau IN ('en_attente')
            ";
        }

        if (empty($unionQueries)) {
            ob_clean();
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $baseQuery = implode(" UNION ALL ", $unionQueries);

        $conditions = [];
        $params = [];

        // Filtre par catégorie de bordereau
        if (!empty($typeBordereauCat)) {
            $conditions[] = "type_bordereau = ?";
            $params[] = $typeBordereauCat;
        }

        if (!empty($typeDemande)) {
            if (!in_array($typeDemande, $allowedDemandes)) {
                ob_clean();
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $conditions[] = "type_demande = ?";
            $params[] = $typeDemande;
        } elseif ($isDistrict && !$isSolde) {
            $placeholders = implode(',', array_fill(0, count($allowedDemandes), '?'));
            $conditions[] = "type_demande IN ($placeholders)";
            $params = array_merge($params, $allowedDemandes);
        }

        // --- FILTRE D'ÉTABLISSEMENT SPÉCIFIQUE POUR RESP_SOLDE ---
        if ($isSolde) {
            if ($userNiveau === 'regional') {
                $conditions[] = "UPPER(type_etablissement) = 'DREN'";
            } elseif ($userNiveau === 'district') {
                $conditions[] = "UPPER(type_etablissement) = 'CISCO'";
            }
        }

        $sql = "SELECT * FROM ($baseQuery) AS all_bordereaux";
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY date_envoi_bordereau ASC, id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nomRegionUser = '';
        if ($isDistrict && !empty($codeLieu)) {
            $sqlReg = "SELECT r.nom_region 
                    FROM ref_districts d
                    JOIN ref_regions r ON d.region_id = r.id
                    WHERE d.nom_district = ? OR d.code_district = ?
                    LIMIT 1";
                    
            $stmtReg = $pdo->prepare($sqlReg);
            $stmtReg->execute([$codeLieu, $codeLieu]);
            $nomRegionUser = $stmtReg->fetchColumn() ?: '';
        }

        $dataFormatted = [];
        foreach ($rows as $row) {
            $destKey = $row['dest_key'];
            $listeIm = array_filter(array_map('trim', explode(',', $row['liste_agents'] ?? '')));

            // --- FILTRAGE DE LIEU ---
            if (!empty($codeLieu) && !empty($listeIm) && in_array($userNiveau, ['crfrp', 'district', 'regional'])) {
                $typeEtab = strtoupper(trim($row['type_etablissement'] ?? ''));
                $expediteurBrut = strtoupper(trim($row['expediteur'] ?? ''));

                // 1. ISOLATION STRICTE DU CRFRP
                if ($userNiveau === 'crfrp') {
                    // Le CRFRP ne voit QUE les bordereaux émis par le CRFRP ou d'un établissement CRFRP
                    if ($typeEtab !== 'CRFRP' && $expediteurBrut !== strtoupper($codeLieu)) {
                        continue;
                    }

                    $placeholders = implode(',', array_fill(0, count($listeIm), '?'));
                    $sqlLieu = "SELECT im FROM personnel_poste_actuel WHERE im IN ($placeholders) AND nom_etablissement = ?";
                    $stmtLieu = $pdo->prepare($sqlLieu);
                    $stmtLieu->execute([...$listeIm, $codeLieu]);

                } else {
                    $placeholders = implode(',', array_fill(0, count($listeIm), '?'));

                    if ($userNiveau === 'district') {
                        if (in_array($destKey, ['augure_dsp', 'augure_cf', 'prefecture']) && !empty($nomRegionUser)) {
                            $sqlLieu = "SELECT im FROM personnel_poste_actuel 
                                        WHERE im IN ($placeholders) 
                                        AND (nom_district = ? OR nom_region = ?) 
                                        AND (type_etablissement IS NULL OR UPPER(type_etablissement) <> 'CRFRP')";
                            $stmtLieu = $pdo->prepare($sqlLieu);
                            $stmtLieu->execute([...$listeIm, $codeLieu, $nomRegionUser]);
                        } else {
                            $sqlLieu = "SELECT im FROM personnel_poste_actuel 
                                        WHERE im IN ($placeholders) 
                                        AND nom_district = ? 
                                        AND (type_etablissement IS NULL OR UPPER(type_etablissement) <> 'CRFRP')";
                            $stmtLieu = $pdo->prepare($sqlLieu);
                            $stmtLieu->execute([...$listeIm, $codeLieu]);
                        }

                    } elseif ($userNiveau === 'regional') {
                        if ($destKey === 'augure_dren') {
                            $sqlLieu = "SELECT im FROM personnel_poste_actuel 
                                        WHERE im IN ($placeholders) 
                                        AND nom_region = ?";
                            $stmtLieu = $pdo->prepare($sqlLieu);
                            $stmtLieu->execute([...$listeIm, $codeLieu]);
                        } else {
                            $sqlLieu = "SELECT im FROM personnel_poste_actuel 
                                        WHERE im IN ($placeholders) 
                                        AND nom_region = ?";
                            $stmtLieu = $pdo->prepare($sqlLieu);
                            $stmtLieu->execute([...$listeIm, $codeLieu]);
                        }
                    }
                }

                $filteredIms = $stmtLieu->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $filteredIms = $listeIm;
            }

            if (empty($filteredIms)) {
                continue;
            }

            // Récupération des noms/prénoms
            $formattedAgents = '---';
            if (!empty($filteredIms)) {
                $placeholders = implode(',', array_fill(0, count($filteredIms), '?'));
                $stmtAgents = $pdo->prepare("
                    SELECT im, nom, prenoms 
                    FROM personnel_etat_civil 
                    WHERE im IN ($placeholders)
                    ORDER BY nom ASC, prenoms ASC
                ");
                $stmtAgents->execute($filteredIms);
                $agents = $stmtAgents->fetchAll(PDO::FETCH_ASSOC);

                $parts = [];
                foreach ($agents as $ag) {
                    $parts[] = trim(($ag['nom'] ?? '') . ' ' . ($ag['prenoms'] ?? '')) . ', IM : ' . $ag['im'];
                }
                $foundIms = array_column($agents, 'im');
                foreach ($filteredIms as $im) {
                    if (!in_array($im, $foundIms)) {
                        $parts[] = 'Agent inconnu, IM : ' . $im;
                    }
                }
                $formattedAgents = !empty($parts) ? implode('<br>', $parts) : '---';
            }

            $nbAgents = count($filteredIms);
            $typeEtab = strtoupper(trim($row['type_etablissement'] ?? ''));
            $expediteurBrut = trim($row['expediteur'] ?? '');

            $destExceptions = ['augure_dren', 'drh'];

            if (!in_array($destKey, $destExceptions)) {
                if (!empty($codeLieu) && strtolower($expediteurBrut) !== strtolower($codeLieu)) {
                    continue; 
                }
            }

            if ($typeEtab === 'MEN CENTRAL') {
                $expediteurFormate = 'DRH';
            } elseif ($typeEtab === 'DREN') {
                $expediteurFormate = 'DREN ' . $expediteurBrut;
            } elseif ($typeEtab === 'CISCO') {
                $expediteurFormate = 'CISCO ' . $expediteurBrut;
            } else {
                $expediteurFormate = $expediteurBrut;
            }

            $expediteurFormate = trim(preg_replace('/\s+/', ' ', $expediteurFormate));

            $rawTypeDemande = $row['type_demande'] ?? '';
            $labelDemande = $demandeLabels[$rawTypeDemande] ?? ucfirst(str_replace('_', ' ', $rawTypeDemande));

            $item = [
                'id'                    => $row['id'],
                'type_demande_libelle'  => $labelDemande,
                'numero_complet'        => $row['numero_complet'],
                'nombre_agents'         => $nbAgents,
                'formatted_agents_list' => $formattedAgents,
                'date_envoi_bordereau'  => $row['date_envoi_bordereau'],
                'type_bordereau'        => $row['type_demande'] ?? $row['type_bordereau'],
                'type_etablissement'    => $row['type_etablissement'],
                'expediteur'            => $expediteurFormate,
                'destinataire'          => $destLabels[$destKey] ?? strtoupper($destKey),
                'dest_key'              => $destKey,
                'augure_dren'           => null,
                'augure_fop'            => null,
                'augure_dsp'            => null,
                'augure_cf'             => null,
                'prefecture'            => null,
                'drh'                   => null,
                'mtefop'                => null,
                'primature'             => null
            ];

            $item[$destKey] = [
                'reference' => $row['reference_destination'],
                'date_ref'  => $row['date_reference_destination'],
                'statut'    => $row['statut_bordereau']
            ];

            $dataFormatted[] = $item;
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'data'    => $dataFormatted
        ]);
        exit;
    } catch (Exception $e) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'data'    => [],
            'error'   => $e->getMessage()
        ]);
        exit;
    }
}

if ($action === 'add_reference') {
    header('Content-Type: application/json; charset=utf-8');
    $id        = intval($_POST['id'] ?? 0);
    $destKey   = trim($_POST['dest_key'] ?? ''); 
    $ref_brute = trim($_POST['reference'] ?? ''); 
    $date_ref  = trim($_POST['date_ref'] ?? ''); 

    if (!$id || empty($destKey) || empty($ref_brute) || empty($date_ref)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
        exit;
    }

    $date_formatee = date('d/m/Y', strtotime($date_ref));
    $ref_complete  = $ref_brute . " du " . $date_formatee;

    // Configuration des correspondances
    $destinationsConfig = [
        'augure_dren' => [
            'table_archive' => 'archives_bordereaux_dren',
            'col_suivi'     => 'ref_dren',
            'cols_complet'  => ['bordereau_cisco_dren', 'bordereau_crfrp_dren']
        ],
        'augure_fop' => [
            'table_archive' => 'archives_bordereaux_fop',
            'col_suivi'     => 'ref_fop',
            'cols_complet'  => ['bordereau_cisco_fop', 'bordereau_dren_fop', 'bordereau_crfrp_fop', 'bordereau_drh_fop']
        ],
        'augure_dsp' => [
            'table_archive' => 'archives_bordereaux_solde',
            'col_suivi'     => 'ref_solde',
            'cols_complet'  => ['bordereau_cisco_solde', 'bordereau_dren_solde', 'bordereau_crfrp_solde', 'bordereau_drh_solde']
        ],
        'augure_cf' => [
            'table_archive' => 'archives_bordereaux_cde',
            'col_suivi'     => 'ref_cde',
            'cols_complet'  => ['bordereau_dren_cde', 'bordereau_crfrp_cde', 'bordereau_drh_cde']
        ],
        'prefecture' => [
            'table_archive' => 'archives_bordereaux_prefet',
            'col_suivi'     => 'ref_prefecture',
            'cols_complet'  => ['bordereau_dren_prefet', 'bordereau_crfrp_prefet']
        ],
        'drh' => [
            'table_archive' => 'archives_bordereaux_drh',
            'col_suivi'     => 'ref_drh',
            'cols_complet'  => ['bordereau_cisco_drh', 'bordereau_dren_drh', 'bordereau_crfrp_drh']
        ],
        'primature' => [
            'table_archive' => 'archives_bordereaux_primature',
            'col_suivi'     => 'ref_primature',
            'cols_complet'  => ['bordereau_drh_primature']
        ],
        'mtefop' => [
            'table_archive' => 'archives_bordereaux_mtefop',
            'col_suivi'     => 'ref_mtefop',
            'cols_complet'  => ['bordereau_drh_mtefop']
        ]
    ];

    if (!isset($destinationsConfig[$destKey])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Destination invalide.']);
        exit;
    }

    $config = $destinationsConfig[$destKey];

    try {
        $pdo->beginTransaction();
        
        // 1. Récupération du bordereau archivé
        $sqlGet = "SELECT * FROM {$config['table_archive']} WHERE id = :id";
        $stmtGet = $pdo->prepare($sqlGet);
        $stmtGet->execute([':id' => $id]);
        $bordereau = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if ($bordereau) {
            $numComplet    = trim($bordereau['numero_complet']);
            $typeBordereau = $bordereau['type_bordereau'] ?? '';
            $listeAgents   = $bordereau['liste_agents'] ?? '';
            $typeDemande   = $bordereau['type_demande'] ?? '';

            // 2. Mise à jour de la table d'archive
            $sqlUpdateArch = "UPDATE {$config['table_archive']} 
                              SET reference_destination = :ref, 
                                  date_reference_destination = :date_ref, 
                                  statut_bordereau = 'reference_valide' 
                              WHERE id = :id";
            $stmtUpdateArch = $pdo->prepare($sqlUpdateArch);
            $stmtUpdateArch->execute([
                ':ref'      => $ref_brute,
                ':date_ref' => $date_ref,
                ':id'       => $id
            ]);

            // 3. Mise à jour de suivi_agents_bordereau UNIQUEMENT SI ce n'est PAS un mandatement
            if ($typeBordereau !== 'mandatement') {
                $whereConditions = [];
                $paramsSuivi = [':ref_complete' => $ref_complete];

                foreach ($config['cols_complet'] as $i => $col) {
                    $paramName = ":num_" . $i;
                    $whereConditions[] = "LOWER(REPLACE({$col}, ' ', '')) = LOWER(REPLACE({$paramName}, ' ', ''))";
                    $paramsSuivi[$paramName] = $numComplet;
                }

                if (!empty($whereConditions)) {
                    $sqlSuivi = "UPDATE suivi_agents_bordereau 
                                 SET {$config['col_suivi']} = :ref_complete 
                                 WHERE " . implode(' OR ', $whereConditions);
                                 
                    $stmtSuivi = $pdo->prepare($sqlSuivi);
                    $stmtSuivi->execute($paramsSuivi);

                    // Si aucune ligne n'a été mise à jour
                    if ($stmtSuivi->rowCount() === 0) {
                        $checkConditions = [];
                        $checkParams = [];
                        foreach ($config['cols_complet'] as $i => $col) {
                            $pName = ":chk_" . $i;
                            $checkConditions[] = "{$col} LIKE {$pName}";
                            $checkParams[$pName] = '%' . $numComplet . '%';
                        }

                        $checkSql = "SELECT id FROM suivi_agents_bordereau WHERE " . implode(' OR ', $checkConditions);
                        $checkStmt = $pdo->prepare($checkSql);
                        $checkStmt->execute($checkParams);
                        $existe = $checkStmt->fetch(PDO::FETCH_ASSOC);

                        if (!$existe) {
                            throw new Exception("Numéro de bordereau '{$numComplet}' introuvable dans la table suivi_agents_bordereau.");
                        }
                    }
                }
            }

            // 4. Traitement spécifique des bordereaux de Mandatement
            if ($typeBordereau === 'mandatement' && !empty($listeAgents)) {
                $agentsArray = array_filter(array_map('trim', explode(',', $listeAgents)));

                if (!empty($agentsArray)) {
                    $placeholders = implode(',', array_fill(0, count($agentsArray), '?'));
                    if ($typeDemande === 'avenant_avec_contrat') {
                        $params = array_merge([$ref_complete, $date_ref], $agentsArray);

                        $sqlUpdateActe = "UPDATE acte_formate_av_cont 
                                          SET ref_mandatement = ?, date_reference_bordereau = ? 
                                          WHERE im IN ($placeholders)";
                        $stmtActe = $pdo->prepare($sqlUpdateActe);
                        $stmtActe->execute($params);

                        $sqlDeleteActe = "DELETE FROM acte_formate_av_cont 
                                          WHERE new_grade = '2°CLASSE/2°ECHELON' AND im IN ($placeholders)";
                        $stmtDeleteActe = $pdo->prepare($sqlDeleteActe);
                        $stmtDeleteActe->execute($agentsArray);
                    } else {
                        $params = array_merge([$ref_complete, $date_ref, $typeDemande], $agentsArray);

                        $sqlUpdateActe = "UPDATE acte_formate 
                                          SET ref_mandatement = ?, date_reference_bordereau = ? 
                                          WHERE type_demande = ? AND im IN ($placeholders)";
                        $stmtActe = $pdo->prepare($sqlUpdateActe);
                        $stmtActe->execute($params);
                    }
                }
            }
            
            $pdo->commit();  

            $response = json_encode(['success' => true]);

            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Length: ' . strlen($response));
            header('Connection: close');
            echo $response;
            
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request(); 
            } else {
                ob_end_flush();
                flush(); 
            }
            
            declencherAlertesInstantanees($pdo);
            exit;
        } else {
            throw new Exception("Bordereau introuvable dans la table d'archive.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ob_clean();
        echo json_encode(['success' => false, 'message' => "Erreur : " . $e->getMessage()]);
        exit;
    }
}

if ($action === 'get_next_dren_ref') {
    $type_demande = $_POST['type_demande'] ?? '';
    $currentYear = date('Y'); 
    
    $stmt = $pdo->prepare("
        SELECT reference_destination, type_demande, annee_bordereau
        FROM archives_bordereaux_dren 
        WHERE destination = 'augure_dren' 
          AND type_demande = :type 
          AND annee_bordereau = :year 
          AND reference_destination IS NOT NULL 
          AND reference_destination != ''
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute(['type' => $type_demande, 'year' => $currentYear]);
    $lastRecord = $stmt->fetch();

    if ($lastRecord && !empty($lastRecord['reference_destination'])) {
        preg_match('/^(\d+)/', $lastRecord['reference_destination'], $matches);
        $lastNumber = isset($matches[1]) ? intval($matches[1]) : 0;
        $nextNumber = $lastNumber + 1;
        $lastRef = $lastRecord['reference_destination'];
        $lastRefStr = str_pad($lastRef, 3, '0', STR_PAD_LEFT);
    } else {
        $nextNumber = 1;
        $lastRefStr = "Aucune pour " . $currentYear;
    }
    $nextRefStr = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    echo json_encode([
        'success' => true,
        'last_ref' => $lastRefStr,
        'next_ref' => $nextRefStr,
        'year' => $currentYear
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
ob_clean();
echo json_encode(['success' => false, 'data' => [], 'message' => 'Action non reconnue.']);
exit;
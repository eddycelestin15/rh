<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/alertes_notifications.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

// ==========================================
// ACTION 1 : LISTER LES DOSSIERS A TRAITER
// ==========================================
if ($action === 'list_traitement') {
    $type = trim($_POST['type'] ?? '');

    if (empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Type de demande manquant.']);
        exit;
    }

    try {
        // === 1. Récupération du rôle et du niveau de l'utilisateur connecté ===
        $userIm = $_SESSION['user_im'] ?? '';
        $roleSpecifique = '';
        $userNiveau = strtolower(trim($_SESSION['user_niveau'] ?? ''));
        $codeLieu = '';

        $stmtRole = $pdo->prepare("SELECT role_specifique, niveau, code_lieu_affectation FROM utilisateurs WHERE im = ?");
        $stmtRole->execute([$userIm]);
        $userU = $stmtRole->fetch(PDO::FETCH_ASSOC);
        if ($userU) {
            $roleSpecifique = trim($userU['role_specifique'] ?? '');
            $userNiveau     = strtolower(trim($userU['niveau'] ?? $userNiveau));
            $codeLieu       = trim($userU['code_lieu_affectation'] ?? '');
        }
        
        $nomRegionDistrict = '';
        if ($userNiveau === 'district' && !empty($codeLieu)) {
            $sqlReg = "SELECT r.nom_region 
                    FROM ref_districts d
                    JOIN ref_regions r ON d.region_id = r.id
                    WHERE d.nom_district = ? OR d.code_district = ?
                    LIMIT 1";
            $stmtReg = $pdo->prepare($sqlReg);
            $stmtReg->execute([$codeLieu, $codeLieu]);
            $nomRegionDistrict = $stmtReg->fetchColumn() ?: '';
        }

        $isAdmin      = in_array($roleSpecifique, ['admin', 'chef_service', 'chef_division']);
        $isSolde      = ($roleSpecifique === 'resp_solde');
        $isNonEncadre = ($roleSpecifique === 'resp_non_encadre');
        $isEncadre    = ($roleSpecifique === 'resp_encadre');
        $isCRFRP      = ($roleSpecifique === 'resp_personnel_crfrp');
        $isRetraite   = ($roleSpecifique === 'resp_retraite');

        // === 2. Types de demande autorisés selon le rôle ===
        $allowedTypes = [];

        if ($isSolde) {
            $allowedTypes = ['renouvellement', 'avenant', 'avancement_classe', 'avancement_echelon', 'integration', 'titularisation', 'admission_retraite', 'compensatrice', 'installation'];
        } elseif ($isNonEncadre) {
            $allowedTypes = ['renouvellement', 'avenant', 'integration'];
        } elseif ($isEncadre) {
            $allowedTypes = ['titularisation', 'avancement_classe', 'avancement_echelon'];
        } elseif ($isRetraite) {
            $allowedTypes = ['admission_retraite', 'compensatrice', 'installation'];
        } elseif ($isCRFRP || $isAdmin) {
            $allowedTypes = ['renouvellement', 'avenant', 'avancement_classe', 'avancement_echelon', 'titularisation', 'integration', 'conge_annuel', 'admission_retraite', 'compensatrice', 'installation'];
        } else {
            echo json_encode(['data' => []]);
            exit;
        }

        if (!in_array($type, $allowedTypes)) {
            echo json_encode(['data' => []]);
            exit;
        }

        // === 3. Requête principale ===
        $sql = "SELECT 
                    s.*, 
                    CONCAT(e.nom, ' ', e.prenoms) AS nom_complet,
                    CONCAT(sit.corps_actuel, ', ', sit.grade_actuel) AS corps_grade,
                    p.type_etablissement,
                    p.ministere
                FROM suivi_agents_bordereau s
                LEFT JOIN personnel_etat_civil e ON s.im_agent = e.im
                LEFT JOIN personnel_situation_actuelle sit ON s.im_agent = sit.im
                LEFT JOIN personnel_poste_actuel p ON s.im_agent = p.im
                WHERE s.type_bordereau = :type
                  AND (s.statut_finale IS NULL OR s.statut_finale <> 'valide')";
        $params = [':type' => $type];

        // --- FILTRE SPÉCIFIQUE NIVEAU CENTRAL ---
        if ($userNiveau === 'central') {

            // 1. REGLES POUR RESP_ENCADRE & RESP_NON_ENCADRE
            if ($type === 'integration') {
                // Intégration : MEN CENTRAL direct OU (Autre MEN ET ref_cde renseigné)
                $sql .= " AND (
                            UPPER(TRIM(p.type_etablissement)) = 'MEN CENTRAL'
                            OR 
                            (
                                UPPER(TRIM(p.ministere)) = 'MEN' 
                                AND s.ref_cde IS NOT NULL 
                                AND TRIM(s.ref_cde) <> ''
                            )
                        )";
            } 
            elseif ($type === 'titularisation') {
                // Titularisation : MEN CENTRAL direct OU (Autre MEN ET ref_solde renseigné)
                $sql .= " AND (
                            UPPER(TRIM(p.type_etablissement)) = 'MEN CENTRAL'
                            OR 
                            (
                                UPPER(TRIM(p.ministere)) = 'MEN' 
                                AND s.ref_solde IS NOT NULL 
                                AND TRIM(s.ref_solde) <> ''
                            )
                        )";
            }

            // 2. REGLES POUR RESP_RETRAITE
            if ($isRetraite || in_array($type, ['admission_retraite', 'compensatrice', 'installation'])) {
                if ($type === 'compensatrice') {
                    // Compensatrice au niveau Central : UNIQUEMENT MEN CENTRAL
                    $sql .= " AND UPPER(TRIM(p.type_etablissement)) = 'MEN CENTRAL'";
                } 
                elseif (in_array($type, ['admission_retraite', 'installation'])) {
                    // Admission à la retraite et Installation : TOUT LE MINISTERE MEN
                    $sql .= " AND UPPER(TRIM(p.ministere)) = 'MEN'";
                }
            }
        }

        // Filtre géographique selon le niveau
        if (!empty($codeLieu) && $userNiveau !== 'central') {
            if ($userNiveau === 'crfrp') {
                $sql .= " AND p.nom_etablissement = :code_lieu";
                $params[':code_lieu'] = $codeLieu;
            } elseif ($userNiveau === 'district') {
                $sql .= " AND p.nom_district = :code_lieu 
                        AND (p.type_etablissement IS NULL OR UPPER(TRIM(p.type_etablissement)) <> 'CRFRP')";
                $params[':code_lieu'] = $codeLieu;
            } elseif ($userNiveau === 'regional') {
                $sql .= " AND p.nom_region = :code_lieu";
                $params[':code_lieu'] = $codeLieu;
            }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            $im = $row['im_agent'];

            $refs = [
                $row['ref_dren'] ?? null,
                $row['ref_fop'] ?? null,
                $row['ref_solde'] ?? null,
                $row['ref_cde'] ?? null,
                $row['ref_prefecture'] ?? null,
                $row['ref_drh'] ?? null,
                $row['ref_mtefop'] ?? null,
                $row['ref_primature'] ?? null
            ];

            $hasAtLeastOneRef = false;
            foreach ($refs as $ref) {
                if (!empty(trim((string)$ref))) {
                    $hasAtLeastOneRef = true;
                    break;
                }
            }
            if (!$hasAtLeastOneRef) {
                continue;
            }

            $genererCellule = function($ref, $statut, $motif, $dest, $forceDisplay = false) use ($id, $im, $userNiveau, $type) {
                $statut = trim($statut ?? 'en_attente');  

                if (empty($ref) && !$forceDisplay) {
                    return '<span class="text-slate-400 italic text-xs">Non reçu</span>';
                }

                if ($statut === 'en_attente') {
                    // Définition des destinations réservées exclusivement au niveau Central par type de demande
                    $destinationsCentralesParType = [
                        'integration'       => ['drh', 'mtefop', 'primature'],
                        'titularisation'    => ['drh', 'cde', 'mtefop', 'primature'],
                        'admission_retraite'=> ['drh', 'solde', 'cde', 'mtefop', 'primature'],
                        'installation'      => ['drh', 'cde']
                    ];

                    // Vérification si la destination actuelle est restreinte pour l'utilisateur non-central
                    $isDestRestreinte = false;

                    if (isset($destinationsCentralesParType[$type])) {
                        $destinationsRestreintes = $destinationsCentralesParType[$type];
                        if (in_array($dest, $destinationsRestreintes) && $userNiveau !== 'central') {
                            $isDestRestreinte = true;
                        }
                    }

                    // --- RÈGLE 1 : District pour Compensatrice (DREN en attente) ---
                    if ($type === 'compensatrice' && $userNiveau === 'district' && $dest === 'dren') {
                        $isDestRestreinte = true;
                    }

                    // --- RÈGLE 2 : CRFRP (Seule la destination DREN est en attente, le reste est traitable) ---
                    if ($userNiveau === 'crfrp') {
                        if ($dest === 'dren') {
                            $isDestRestreinte = true;
                        } else {
                            $isDestRestreinte = false;
                        }
                    }

                    // On n'affiche le badge "En attente" QUE si la destination est restreinte
                    if ($isDestRestreinte) {
                        return '<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-50 text-amber-600 border border-amber-200/60 shadow-sm mx-auto cursor-default">
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500 border border-amber-600 border-dashed animate-spin"></span>
                                    </span>
                                    En attente
                                </span>';
                    }

                    // Affichage du bouton "Traiter" pour les niveaux autorisés
                    return '<button onclick="ouvrirModalTraitement(' . $id . ', \'' . $dest . '\', \'' . $im . '\')" 
                            class="bg-blue-600 text-white font-semibold text-xs px-3 py-1.5 rounded-lg hover:bg-blue-700 transition-all shadow-sm flex items-center gap-1 mx-auto">
                            <i class="fas fa-gavel text-[10px]"></i> Traiter</button>';
                } 
                elseif ($statut === 'valide') {
                    return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 mx-auto">
                            <i class="fas fa-check-circle text-[10px]"></i> Terminé</span>';
                } 
                elseif ($statut === 'rejete') {
                    $motifEscaped = htmlspecialchars($motif ?? '', ENT_QUOTES, 'UTF-8');
                    return '<button data-motif="' . $motifEscaped . '" onclick="voirMotifRejet(this, ' . $id . ', \'' . $dest . '\')" 
                            class="bg-red-600 text-white font-semibold text-xs px-3 py-1.5 rounded-lg hover:bg-red-700 transition-all shadow-sm inline-flex items-center gap-1 mx-auto">
                            <i class="fas fa-eye text-[10px]"></i> Rejeté</button>';
                }

                return '<span class="text-amber-600 text-xs">' . htmlspecialchars($statut) . '</span>';
            };

            // 2. Définition des types de dossiers qui se traitent directement au MEN après validation CDE
            $typesMenDirects = [
                'compensatrice', 
                'installation', 
                'renouvellement', 
                'avenant', 
                'avancement_classe', 
                'avancement_echelon'
            ];

            // 3. Condition d'affichage pour la colonne MEN
            $statutCdeValide = (trim($row['statut_cde'] ?? '') === 'valide');
            $typeDossier = trim($row['type_bordereau'] ?? $type ?? '');

            $celluleMEN = '';

            if (!$statutCdeValide) {
                // Tant que statut_cde n'est pas 'valide' (ex: 'en_attente' ou 'rejete') -> Afficher "Non reçu"
                $celluleMEN = '<span class="text-slate-400 italic text-xs">Non reçu</span>';
            } else {
                // Si statut_cde est 'valide', on vérifie si le type fait partie des traitements directs au MEN
                $isDirectMen = in_array($typeDossier, $typesMenDirects);

                $celluleMEN = $genererCellule(
                    $row['ref_men'] ?? null, 
                    $row['statut_men'] ?? 'en_attente', 
                    $row['motif_men'] ?? '', 
                    'men',
                    $isDirectMen // Forcer l'affichage du bouton Traiter dès validation CDE
                );
            }

            $data[] = [
                'id'          => $id,
                'im_agent'    => $im,
                'nom_complet' => mb_upper(htmlspecialchars($row['nom_complet'] ?? '')),
                'corps_grade' => htmlspecialchars($row['corps_grade'] ?? '---'),
                'type_etablissement' => $row['type_etablissement'] ?? '',
                'dren'        => $genererCellule($row['ref_dren'] ?? null, $row['statut_dren'] ?? 'en_attente', $row['motif_dren'] ?? '', 'dren'),
                'fop'         => $genererCellule($row['ref_fop'] ?? null, $row['statut_fop'] ?? 'en_attente', $row['motif_fop'] ?? '', 'fop'),
                'solde'       => $genererCellule($row['ref_solde'] ?? null, $row['statut_solde'] ?? 'en_attente', $row['motif_solde'] ?? '', 'solde'),
                'cde'         => $genererCellule($row['ref_cde'] ?? null, $row['statut_cde'] ?? 'en_attente', $row['motif_cde'] ?? '', 'cde'),
                'men'         => $celluleMEN, 
                'prefet'      => $genererCellule($row['ref_prefecture'] ?? null, $row['statut_prefet'] ?? 'en_attente', $row['motif_prefet'] ?? '', 'prefet'),
                'drh'         => $genererCellule($row['ref_drh'] ?? null, $row['statut_drh'] ?? 'en_attente', $row['motif_drh'] ?? '', 'drh'),
                'mtefop'      => $genererCellule($row['ref_mtefop'] ?? null, $row['statut_mtefop'] ?? 'en_attente', $row['motif_mtefop'] ?? '', 'mtefop'),
                'primature'   => $genererCellule($row['ref_primature'] ?? null, $row['statut_primature'] ?? 'en_attente', $row['motif_primature'] ?? '', 'primature'),
            ];
        }

        echo json_encode(['data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['data' => [], 'error' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// ACTION 2 : METTRE À JOUR LE STATUT (VALIDER OU REJETER)
// ==========================================
elseif ($action == 'update_statut_agent') {
    header('Content-Type: application/json');
    $id     = $_POST['id'] ?? '';
    $dest   = $_POST['destination'] ?? '';
    $statut = $_POST['statut'] ?? '';
    $motif  = $_POST['motif'] ?? '';

    $cols = [
        'dren'        => ['statut' => 'statut_dren',      'motif' => 'motif_dren'],   
        'fop'         => ['statut' => 'statut_fop',       'motif' => 'motif_fop'],    
        'solde'       => ['statut' => 'statut_solde',     'motif' => 'motif_solde'],  
        'cde'         => ['statut' => 'statut_cde',       'motif' => 'motif_cde'],   
        'men'         => ['statut' => 'statut_men',       'motif' => 'motif_men'], 
        'prefet'      => ['statut' => 'statut_prefet',    'motif' => 'motif_prefet'], 
        'drh'         => ['statut' => 'statut_drh',       'motif' => 'motif_drh'],  
        'mtefop'      => ['statut' => 'statut_mtefop',    'motif' => 'motif_mtefop'],  
        'primature'   => ['statut' => 'statut_primature', 'motif' => 'motif_primature']   
    ];

    if (!isset($cols[$dest])) {
        echo json_encode(['success' => false, 'message' => "Destination invalide"]);
        exit;
    }

    try {
        $pdo->beginTransaction();
        error_log("Update statut - ID: $id | Dest: $dest | Statut: $statut");

        // 1. Mise à jour du statut / motif sur suivi_agents_bordereau
        $sql = "UPDATE suivi_agents_bordereau 
                SET {$cols[$dest]['statut']} = ?, 
                    {$cols[$dest]['motif']} = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$statut, $motif, $id]);

        if ($statut === 'valide' && in_array($dest, ['solde', 'cde', 'prefet', 'drh', 'mtefop', 'primature'])) {
            $stmtInfo = $pdo->prepare("
                SELECT s.im_agent, s.type_bordereau, m.libelle_demande, m.code_mvt 
                FROM suivi_agents_bordereau s
                LEFT JOIN code_mouvement m ON s.type_bordereau = m.type_demande
                WHERE s.id = ?
                LIMIT 1
            ");
            $stmtInfo->execute([$id]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

            if ($info) {
                $im    = $info['im_agent'];
                $typeB = $info['type_bordereau'];

                $typeActe = 'ACTE';
                if ($typeB == 'renouvellement') $typeActe = 'CONTRAT';
                elseif ($typeB == 'avenant') $typeActe = 'AVENANT';
                elseif (preg_match('/avancement|integration|titularisation/', $typeB)) $typeActe = 'ARRETE';
                elseif ($typeB == 'compensatrice' || $typeB == 'installation') $typeActe = 'DECISION';

                $sqlActe = "UPDATE acte_formate 
                            SET acte = ?, libelle_demande = ?, code_mvt = ? 
                            WHERE im = ?";
                $pdo->prepare($sqlActe)->execute([
                    $typeActe,
                    $info['libelle_demande'],
                    $info['code_mvt'],
                    $im
                ]);
            }
        }

        if ($statut === 'valide') {
            $stmtType = $pdo->prepare("SELECT type_bordereau FROM suivi_agents_bordereau WHERE id = ?");
            $stmtType->execute([$id]);
            $typeB = $stmtType->fetchColumn();

            $typesValidationMEN = [
                'compensatrice', 
                'installation', 
                'renouvellement', 
                'avenant', 
                'avancement_classe', 
                'avancement_echelon'
            ];

            // Si le MEN valide l'un de ces types ou si c'est la Préfecture / Primature
            if ($dest === 'prefet' || $dest === 'primature' || ($dest === 'men' && in_array($typeB, $typesValidationMEN))) {
                // 1. Clôture finale du bordereau
                $pdo->prepare("UPDATE suivi_agents_bordereau SET statut_finale = 'valide' WHERE id = ?")
                    ->execute([$id]);

                // 2. Récupération de l'agent et du type de bordereau
                $stmtInfo = $pdo->prepare("SELECT im_agent, type_bordereau FROM suivi_agents_bordereau WHERE id = ?");
                $stmtInfo->execute([$id]);
                $dataAgent = $stmtInfo->fetch(PDO::FETCH_ASSOC);

                if (!empty($dataAgent['im_agent'])) {
                    $imAgent = $dataAgent['im_agent'];
                    $typeBordereau = $dataAgent['type_bordereau'];

                    // Mise à jour de la demande de numéro de dossier
                    $pdo->prepare("UPDATE demandes_numeros_dos SET statut_validation = 'valide' WHERE im = ?")
                        ->execute([$imAgent]);

                    // 3. Nettoyage des alertes et notifications selon le type de bordereau
                    $motsCles = [
                        'admission_retraite' => ['alerte' => '%ADMISSION_RETRAITE%', 'notif' => '%admission%'],
                        'compensatrice'      => ['alerte' => '%COMPENSATRICE%',      'notif' => '%compensatrice%'],
                        'installation'       => ['alerte' => '%INSTALLATION%',        'notif' => '%installation%'],
                    ];

                    if (isset($motsCles[$typeBordereau])) {
                        $config = $motsCles[$typeBordereau];

                        // Supprime l'alerte spécifique
                        $stmtAlerte = $pdo->prepare("DELETE FROM alertes_notifications WHERE im_user = ? AND alerte_id LIKE ?");
                        $stmtAlerte->execute([$imAgent, $config['alerte']]);

                        // Supprime les notifications associées
                        $stmtNotif = $pdo->prepare("DELETE FROM notifications WHERE im = ? AND LOWER(message) LIKE ?");
                        $stmtNotif->execute([$imAgent, $config['notif']]);
                    }
                }
            }
        }

        $pdo->commit();

        if ($statut === 'rejete' || ($statut === 'valide' && in_array($dest, ['prefet', 'primature', 'men']))) {
            declencherAlertesInstantanees($pdo);
        }      
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

elseif ($action == 'regulariser_agent') {
    header('Content-Type: application/json');
    $id = $_POST['id'] ?? '';
    $dest = $_POST['destination'] ?? '';

    $cols = [
        'dren'        => ['statut' => 'statut_dren',      'motif' => 'motif_dren'],   
        'fop'         => ['statut' => 'statut_fop',       'motif' => 'motif_fop'],    
        'solde'       => ['statut' => 'statut_solde',     'motif' => 'motif_solde'],  
        'cde'         => ['statut' => 'statut_cde',       'motif' => 'motif_cde'],
        'men'         => ['statut' => 'statut_men',       'motif' => 'motif_men'],    
        'prefet'      => ['statut' => 'statut_prefet',    'motif' => 'motif_prefet'], 
        'drh'         => ['statut' => 'statut_drh',       'motif' => 'motif_drh'],  
        'mtefop'      => ['statut' => 'statut_mtefop',    'motif' => 'motif_mtefop'],  
        'primature'   => ['statut' => 'statut_primature', 'motif' => 'motif_primature']     
    ];

    if (!isset($cols[$dest])) {
        echo json_encode(['success' => false, 'message' => "Destination invalide"]);
        exit;
    }

    try {
        // On remet le statut à NULL (ou vide) et on efface le motif
        $sql = "UPDATE suivi_agents_bordereau SET {$cols[$dest]['statut']} = 'en_attente', {$cols[$dest]['motif']} = NULL WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la régularisation']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fonction utilitaire pour forcer l'encodage en majuscules sans perte de caractères accentués
function mb_upper($str) {
    return mb_convert_case($str, MB_CASE_UPPER, "UTF-8");
}
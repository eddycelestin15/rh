<?php
ob_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';

use PhpOffice\PhpWord\TemplateProcessor;

if (!isset($_SESSION['user_im'])) {
    die("Erreur : Session expirée.");
}

// 1. RÉCUPÉRATION DES PARAMÈTRES
$user_im        = $_SESSION['user_im'];
$num            = $_GET['num'] ?? '';
$sigle          = $_GET['sigle'] ?? '';
$dest           = $_GET['dest'] ?? ''; 
$filter         = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$type_bordereau = isset($_GET['type_bordereau']) ? trim($_GET['type_bordereau']) : '';
$civilite       = isset($_GET['civilite']) ? strtoupper(trim($_GET['civilite'])) : 'MR';

$mappingSuivi = [
    'augure_dren' => 'deja_imprime_dren',
    'augure_fop'  => 'deja_imprime_fop',
    'augure_dsp'  => 'deja_imprime_solde',
    'augure_cf'   => 'deja_imprime_cde',
    'prefecture'  => 'deja_imprime_prefet',  
    'drh'         => 'deja_imprime_drh',
    'mtefop'      => 'deja_imprime_mtefop',
    'primature'   => 'deja_imprime_primature'
];

if ($dest === 'augure_dsp' && $type_bordereau === 'mandatement') {
    $colonneSuivi = 'deja_imprime_mandatement';
    $colSelection = 'solde_et_pensions_mandatement';
} else {
    $colonneSuivi = $mappingSuivi[$dest] ?? null;
    $colSelection = $dest;
}

if (!$colonneSuivi) {
    die("Erreur : Destination invalide.");
}

// RÉCUPÉRATION ET DÉTERMINATION DU NIVEAU ET TYPE D'ÉTABLISSEMENT
$stmt = $pdo->prepare("
    SELECT u.niveau, u.code_lieu_affectation, u.role_specifique, r.nom_region 
    FROM utilisateurs u
    LEFT JOIN ref_districts d ON TRIM(u.code_lieu_affectation) = TRIM(d.nom_district)
    LEFT JOIN ref_regions r ON (d.region_id = r.id OR TRIM(u.code_lieu_affectation) = TRIM(r.nom_region))
    WHERE u.im = ?
");
$stmt->execute([$user_im]);
$user = $stmt->fetch();

$niveau_resp = strtolower(trim($user['niveau'] ?? ''));
$lieu_resp   = trim($user['code_lieu_affectation'] ?? '');
$role_resp   = strtolower(trim($user['role_specifique'] ?? ''));
$nom_region  = (!empty($user['nom_region'])) ? $user['nom_region'] : $lieu_resp;
$br          = '</w:t><w:br/><w:t>';

// Détermination du type d'établissement
$typeEtablissement = '';
if (in_array($role_resp, ['resp_encadre', 'resp_non_encadre', 'resp_solde', 'resp_retraite'])) {
    if ($niveau_resp === 'district') {
        $typeEtablissement = 'CISCO';
    } elseif ($niveau_resp === 'regional') {
        $typeEtablissement = 'DREN';
    } elseif ($niveau_resp === 'central') {
        $typeEtablissement = 'MEN CENTRAL';
    }
} elseif ($role_resp === 'resp_personnel_crfrp' || $niveau_resp === 'crfrp') {
    $typeEtablissement = 'CRFRP';
}

$tableArchive     = null;
$colonneBordereau = null;

if ($niveau_resp === 'district') {
    if ($dest === 'augure_dren' && $typeEtablissement === 'CISCO') {
        $tableArchive     = 'archives_bordereaux_dren';
        $colonneBordereau = 'bordereau_cisco_dren';
    } elseif ($dest === 'augure_dsp' && $typeEtablissement === 'CISCO') {
        $tableArchive     = 'archives_bordereaux_solde';
        $colonneBordereau = 'bordereau_cisco_solde'; 
    } elseif ($dest === 'augure_fop' && $typeEtablissement === 'CISCO') {
        $tableArchive     = 'archives_bordereaux_fop';
        $colonneBordereau = 'bordereau_cisco_fop';
    } elseif ($dest === 'drh' && $typeEtablissement === 'CISCO') {
        $tableArchive     = 'archives_bordereaux_drh';
        $colonneBordereau = 'bordereau_cisco_drh';
    }
} elseif ($niveau_resp === 'crfrp' && $typeEtablissement === 'CRFRP') {
    if ($dest === 'augure_dren') {
        $tableArchive     = 'archives_bordereaux_dren';
        $colonneBordereau = 'bordereau_crfrp_dren';
    } elseif ($dest === 'augure_dsp') {
        $tableArchive     = 'archives_bordereaux_solde';
        $colonneBordereau = 'bordereau_crfrp_solde';
    } elseif ($dest === 'augure_cf') {
        $tableArchive     = 'archives_bordereaux_cde';
        $colonneBordereau = 'bordereau_crfrp_cde';
    } elseif ($dest === 'augure_fop') {
        $tableArchive     = 'archives_bordereaux_fop';
        $colonneBordereau = 'bordereau_crfrp_fop';
    } elseif ($dest === 'prefecture') {
        $tableArchive     = 'archives_bordereaux_prefet';
        $colonneBordereau = 'bordereau_crfrp_prefet';
    } elseif ($dest === 'drh') {
        $tableArchive     = 'archives_bordereaux_drh';
        $colonneBordereau = 'bordereau_crfrp_drh';
    }
} elseif ($niveau_resp === 'regional' && $typeEtablissement === 'DREN') {
    if ($dest === 'augure_dsp') {
        $tableArchive     = 'archives_bordereaux_solde';
        $colonneBordereau = 'bordereau_dren_solde';
    } elseif ($dest === 'augure_cf') {
        $tableArchive     = 'archives_bordereaux_cde';
        $colonneBordereau = 'bordereau_dren_cde';
    } elseif ($dest === 'augure_fop') {
        $tableArchive     = 'archives_bordereaux_fop';
        $colonneBordereau = 'bordereau_dren_fop';
    } elseif ($dest === 'prefecture') {
        $tableArchive     = 'archives_bordereaux_prefet';
        $colonneBordereau = 'bordereau_dren_prefet';
    } elseif ($dest === 'drh') {
        $tableArchive     = 'archives_bordereaux_drh';
        $colonneBordereau = 'bordereau_dren_drh';
    }
} elseif ($niveau_resp === 'central' && $typeEtablissement === 'MEN CENTRAL') {
    if ($dest === 'augure_dsp') {
        $tableArchive     = 'archives_bordereaux_solde';
        $colonneBordereau = 'bordereau_drh_solde';
    } elseif ($dest === 'augure_cf') {
        $tableArchive     = 'archives_bordereaux_cde';
        $colonneBordereau = 'bordereau_drh_cde';
    } elseif ($dest === 'augure_fop') {
        $tableArchive     = 'archives_bordereaux_fop';
        $colonneBordereau = 'bordereau_drh_fop';
    } elseif ($dest === 'mtefop') {
        $tableArchive     = 'archives_bordereaux_mtefop';
        $colonneBordereau = 'bordereau_drh_mtefop';
    } elseif ($dest === 'primature') {
        $tableArchive     = 'archives_bordereaux_primature';
        $colonneBordereau = 'bordereau_drh_primature';
    }
}

if (!$tableArchive) {
    die("Erreur : Aucune règle de bordereau définie pour ce niveau ($niveau_resp), ce type d'établissement ($typeEtablissement) et cette destination ($dest).");
}

$chef_lieu_region = $lieu_resp; 
if ($niveau_resp === "regional") {
    $stmtChef = $pdo->prepare("SELECT chef_lieu_region FROM ref_regions WHERE nom_region = ?");
    $stmtChef->execute([$lieu_resp]);
    $resultChef = $stmtChef->fetch();
    if ($resultChef) {
        $chef_lieu_region = $resultChef['chef_lieu_region'];
    }
}

// 3. LOGIQUE ADMINISTRATIVE (EN-TÊTE WORD)
$val_type_direction = "DIRECTION REGIONALE"; 
$val_sgrh           = "SERVICES ADMINISTRATIFS";
$val_expeditaire    = "L'ADMINISTRATION";
$val_lieu_envoi     = "Madagascar";
$val_region         = $nom_region;

$lieu_crfrp_text  = $lieu_resp; 
$nom_region_crfrp = '';        

if ($role_resp === 'resp_personnel_crfrp') {
    $stmtCrfrp = $pdo->prepare("
        SELECT c.nom_crfrp, c.lieu_crfrp, r.nom_region 
        FROM ref_crfrp c
        JOIN ref_districts d ON c.district_id = d.id
        JOIN ref_regions r ON d.region_id = r.id
        WHERE c.nom_crfrp = ?
    ");
    $stmtCrfrp->execute([$lieu_resp]);
    $crfrpData = $stmtCrfrp->fetch();    
    if ($crfrpData) {
        $lieu_crfrp_text  = preg_replace('/^CRFRP\s+/i', '', $crfrpData['nom_crfrp']); 
        $nom_region_crfrp = $crfrpData['nom_region'];
        $lieu_crfrp        = $crfrpData['lieu_crfrp'];  
    }
}

if ($niveau_resp === "regional") {
    $val_type_direction = "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" . $br . $lieu_resp;
    $val_sgrh           = "SERVICE DE LA GESTION DES RESSOURCES HUMAINES";
    $val_expeditaire    = "LE DIRECTEUR REGIONAL DE L'EDUCATION NATIONALE " . $lieu_resp;
    $val_lieu_envoi     = ucfirst($chef_lieu_region);
    $val_region         = $lieu_resp;
} else if ($niveau_resp === "crfrp" && $role_resp === 'resp_personnel_crfrp') {
    $lieu_majuscule     = strtoupper($lieu_crfrp_text);
    $val_type_direction = "DIRECTION GENERALE DE L’INSTITUT NATIONAL" . $br . "DE FORMATION PEDAGOGIQUE";
    $val_sgrh           = "CENTRE REGIONAL DE FORMATION" . $br . "ET DE RECHERCHE PEDAGOGIQUE " . $lieu_majuscule;
    $val_expeditaire    = "LE CHEF DE CENTRE REGIONAL DE FORMATION ET DE LA RECHERCHE PEDAGOGIQUE " . $lieu_majuscule;
    $val_lieu_envoi     = ucfirst(mb_strtolower($lieu_crfrp ?? $lieu_resp, 'UTF-8'));
    $val_region         = $nom_region_crfrp; 
} else if ($niveau_resp === "district") {
    $val_type_direction = "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" . $br . $nom_region;
    $val_sgrh           = "CIRCONSCRIPTION SCOLAIRE " . $lieu_resp;
    $val_expeditaire    = "LE CHEF DE LA CIRCONSCRIPTION SCOLAIRE " . $lieu_resp;
    $val_lieu_envoi     = ucfirst($lieu_resp);
    $val_region         = $nom_region;
} elseif ($niveau_resp === 'central') {
    $val_type_direction = "DIRECTION DES RESSOURCES HUMAINES";
    $val_sgrh           = "SERVICE DE LA GESTION DES RESSOURCES HUMAINES";
    $val_expeditaire    = "LE DIRECTEUR DES RESSOURCES HUMAINES";
    $val_lieu_envoi     = "Antananarivo";
    $val_region         = "ANTANANARIVO";
}

$region_pour_destinataire = ($role_resp === 'resp_personnel_crfrp') ? $nom_region_crfrp : $nom_region;
$val_destinataire = "";
switch ($dest) {
    case 'augure_dsp':
        $val_destinataire = "LE CHEF DE SERVICE REGIONAL DES SOLDES ET DES PENSIONS " . $region_pour_destinataire;
        break;
    case 'augure_cf':
        $titre = ($civilite === 'MME') ? "MADAME LE DELEGUE" : "MONSIEUR LE DELEGUE";
        $val_destinataire = $titre . " DE CONTROLE FINANCIER " . $region_pour_destinataire;
        break;
    case 'prefecture':
        $titre = ($civilite === 'MME') ? "MADAME LE PREFET" : "MONSIEUR LE PREFET";
        $val_destinataire = $titre . " DE " . $region_pour_destinataire;
        break;
    case 'augure_fop':
        $titre = ($civilite === 'MME') ? "MADAME LE DIRECTEUR REGIONAL" : "MONSIEUR LE DIRECTEUR REGIONAL";
        $val_destinataire = $titre . " DE LA FONCTION PUBLIQUE " . $region_pour_destinataire;
        break;
    case 'augure_dren':
        $titre = ($civilite === 'MME') ? "MADAME LE DIRECTEUR REGIONAL" : "MONSIEUR LE DIRECTEUR REGIONAL";
        $val_destinataire = $titre . " DE L'EDUCATION NATIONALE " . $region_pour_destinataire;
        break;
    case 'drh':
        $titre = ($civilite === 'MME') ? "MADAME LE DIRECTEUR" : "MONSIEUR LE DIRECTEUR";
        $val_destinataire = $titre . " DES RESSOURCES HUMAINES";
        break;
    case 'mtefop':
        $titre = ($civilite === 'MME') ? "MADAME LE MINISTRE" : "MONSIEUR LE MINISTRE";
        $val_destinataire = $titre . " DE LA FONCTION PUBLIQUE";
        break;
    case 'primature':
        $titre = ($civilite === 'MME') ? "MADAME LE PREMIER MINISTRE" : "MONSIEUR LE PREMIER MINISTRE";
        $val_destinataire = $titre . " CHEF DU GOUVERNEMENT";
        break;
}

// Bloc $observation mis à jour
$observation = "";
if ($dest === 'primature' && in_array($filter, ['integration', 'titularisation', 'admission_retraite'])) {
    $observation = "POUR ENREGISTREMENT";
} elseif ($dest === 'augure_dren' || $dest === 'drh' || $dest === 'mtefop') {
    $observation = "POUR COMPETENCE";
} elseif ($type_bordereau === 'creation_projet') {
    if (in_array($filter, ['titularisation', 'avancement_classe']) && $dest === 'augure_fop') {
        $observation = "POUR VALIDATION SUR AUGURE ET RATIFICATION";
    } elseif (in_array($filter, ['avancement_echelon', 'integration']) && $dest === 'augure_fop') {
        $observation = "POUR VALIDATION SUR AUGURE";
    } elseif (in_array($filter, ['admission_retraite', 'installation']) && $dest === 'augure_fop') {
        $observation = "POUR VISA";
    } elseif (in_array($filter, ['titularisation', 'integration', 'admission_retraite', 'compensatrice', 'installation']) && in_array($dest, ['dgcf', 'augure_cf'])) {
        $observation = "POUR VISA";
    } elseif (in_array($filter, ['renouvellement', 'avenant', 'compensatrice']) && in_array($dest, ['augure_dsp', 'augure_cf'])) {
        $observation = "POUR VISA";
    } elseif (in_array($filter, ['renouvellement', 'avenant', 'avancement_classe', 'avancement_echelon', 'compensatrice']) && $dest === 'prefecture') {
        $observation = "POUR COMPETENCE";
    } else {
        $observation = in_array($dest, ['prefecture', 'augure_fop']) ? "POUR COMPETENCE" : "POUR VISA";
    }
} elseif ($type_bordereau === 'conge' && $filter === 'conge_annuel' && $dest === 'augure_fop') {
    $observation = "POUR COMPETENCE";
} elseif ($type_bordereau === 'mandatement' && $dest === 'augure_dsp') {
    $observation = "POUR MANDATEMENT";
} else {
    if ($type_bordereau === 'mandatement') {
        $observation = "POUR MANDATEMENT";
    } else {
        $observation = in_array($dest, ['prefecture', 'augure_fop']) ? "POUR COMPETENCE" : "POUR VISA";
    }
}

// 1. Mapping de référence
$mapTypeDosBDD = [
    'admission_retraite' => 'Admission_retraite',
    'compensatrice'      => 'Compensatrice',
    'installation'       => 'Installation',
    'avancement_classe'  => 'Avancement_classe',
    'avancement_echelon' => 'Avancement_echelon',
    'integration'        => 'Intégration',
    'titularisation'     => 'Titularisation',
    'conge_annuel'       => 'Conge_annuel'
];

if ($type_bordereau === 'mandatement' && $dest === 'augure_dsp') {
    if ($filter === 'avenant_avec_contrat') {
        $sql = "SELECT af.im, ec.nom, ec.prenoms 
                FROM acte_formate_av_cont af 
                JOIN personnel_etat_civil ec ON af.im = ec.im 
                JOIN personnel_poste_actuel p ON af.im = p.im
                WHERE af.solde_et_pensions_mandatement = 1 
                  AND (af.deja_imprime_mandatement = 0 OR af.deja_imprime_mandatement IS NULL)
                  AND (LOWER(af.statut) = 'termine' OR LOWER(af.statut) = 'valide' OR af.statut IS NULL)";

        if ($niveau_resp === 'regional') {
            $sql .= " AND p.nom_region = " . $pdo->quote($lieu_resp) . 
                    " AND (p.type_etablissement IS NULL OR p.type_etablissement <> 'CRFRP')";
        } elseif ($niveau_resp === 'district') {
            $sql .= " AND p.nom_district = " . $pdo->quote($lieu_resp) . 
                    " AND (p.type_etablissement IS NULL OR p.type_etablissement <> 'CRFRP')";
        } elseif ($niveau_resp === 'crfrp') {
            $sql .= " AND p.nom_etablissement = " . $pdo->quote($lieu_resp);
        }
        $sql .= " GROUP BY af.im";
    } else {
        // Cas standard : acte_formate
        $sql = "SELECT af.im, ec.nom, ec.prenoms 
                FROM acte_formate af 
                JOIN personnel_etat_civil ec ON af.im = ec.im 
                JOIN personnel_poste_actuel p ON af.im = p.im
                WHERE af.solde_et_pensions_mandatement = 1 
                  AND af.deja_imprime_mandatement = 0 
                  AND af.statut = 'termine'";

        if ($niveau_resp === 'regional') {
            $sql .= " AND p.nom_region = " . $pdo->quote($lieu_resp) . 
                    " AND (p.type_etablissement IS NULL OR p.type_etablissement <> 'CRFRP')";
        } elseif ($niveau_resp === 'district') {
            $sql .= " AND p.nom_district = " . $pdo->quote($lieu_resp) . 
                    " AND (p.type_etablissement IS NULL OR p.type_etablissement <> 'CRFRP')";
        } elseif ($niveau_resp === 'crfrp') {
            $sql .= " AND p.nom_etablissement = " . $pdo->quote($lieu_resp);
        }
        $sql .= " GROUP BY af.im";
    }
} else {
    $condTypeDos = "";
    if (!empty($filter)) {
        if ($filter === 'renouvellement') {
            $condTypeDos = " AND (
                d.type_dos IN ('Renouvellement_contrat', 'Contrat1', 'Contrat2', 'Renouvellement', 'RNC1', 'RNC2')
                OR d.type_dos LIKE 'Contrat%'
                OR d.type_dos LIKE 'RNC%'
            ) ";
        } elseif ($filter === 'avenant') {
            $condTypeDos = " AND (
                d.type_dos = 'Avenant'
                OR d.type_dos LIKE 'Avenant%'
            ) ";
        } elseif ($filter === 'integration') {
            $condTypeDos = " AND (d.type_dos = 'Intégration' OR d.type_dos = 'Integration' OR d.type_dos = 'integration') ";
        } elseif ($filter === 'admission_retraite') {
            $condTypeDos = " AND (d.type_dos = 'Admission_retraite' OR d.type_dos = 'admission_retraite' OR d.type_dos = 'Retraite') ";
        } elseif ($filter === 'compensatrice') {
            $condTypeDos = " AND (d.type_dos = 'Compensatrice' OR d.type_dos = 'compensatrice') ";
        } elseif ($filter === 'installation') {
            $condTypeDos = " AND (d.type_dos = 'Installation' OR d.type_dos = 'installation') ";
        } elseif ($filter === 'avancement_classe') {
            $condTypeDos = " AND (d.type_dos = 'Avancement_classe' OR d.type_dos = 'avancement_classe') ";
        } elseif ($filter === 'avancement_echelon') {
            $condTypeDos = " AND (d.type_dos = 'Avancement_echelon' OR d.type_dos = 'avancement_echelon') ";
        } elseif ($filter === 'titularisation') {
            $condTypeDos = " AND (d.type_dos = 'Titularisation' OR d.type_dos = 'titularisation') ";
        } elseif ($filter === 'conge_annuel') {
            $condTypeDos = " AND (d.type_dos = 'Conge_annuel' OR d.type_dos = 'Congé_annuel' OR d.type_dos = 'conge_annuel') ";
        } else {
            // Option de secours générale
            $typeDosValeurBDD = $mapTypeDosBDD[$filter] ?? $filter;
            $valTypeDos = mysqli_real_escape_string($conn, $typeDosValeurBDD);
            $condTypeDos = " AND d.type_dos = '$valTypeDos' ";
        }
    }

    $sql = "SELECT d.im, ec.nom, ec.prenoms 
            FROM demandes_numeros_dos d 
            JOIN personnel_etat_civil ec ON d.im = ec.im 
            JOIN personnel_poste_actuel p ON d.im = p.im
            WHERE d.$colSelection = 1 
            AND d.$colonneSuivi = 0 
            AND (d.statut = 'ATTRIBUE' OR d.statut = 'EN_ATTENTE')
            $condTypeDos";
            
    if ($niveau_resp === 'regional') {
        $sql .= " AND p.nom_region = " . $pdo->quote($lieu_resp);
    } else if ($niveau_resp === 'district') {
        $sql .= " AND p.nom_district = " . $pdo->quote($lieu_resp);
    }
    $sql .= " GROUP BY d.im";
}

$res = mysqli_query($conn, $sql);
$agents = mysqli_fetch_all($res, MYSQLI_ASSOC);
$nbAgents = count($agents);

if ($nbAgents === 0) {
    die("Erreur : Aucun agent sélectionné (Vérifiez que vous avez cliqué sur 'Ajouter' et que l'agent appartient à votre zone : $lieu_resp)");
}

$titrePj = "";
if ($type_bordereau === 'creation_projet') {
    $liaisonPj = ($nbAgents > 1) ? "aux noms de :" : "au nom de :";
    
    switch (trim($filter)) {
        case 'renouvellement':
            $titrePj = "Dossier portant demande de renouvellement de contrat " . $liaisonPj;
            break;
        case 'avenant':
            $titrePj = "Dossier portant demande d'avenant " . $liaisonPj;
            break;
        case 'avancement_classe':
            $titrePj = "Dossier portant demande d'avancement de classe " . $liaisonPj;
            break;
        case 'avancement_echelon':
            $titrePj = "Dossier portant demande d'avancement d'echelon " . $liaisonPj;
            break;
        case 'integration':
            $titrePj = "Dossier portant demande d'intégration " . $liaisonPj;
            break;
        case 'titularisation':
            $titrePj = "Dossier portant demande de titularisation " . $liaisonPj;
            break;
        case 'admission_retraite':
            if ($dest === 'augure_fop') {
                $titrePj = "Dossier de validation de releve de service " . $liaisonPj;
            } else {
                $titrePj = "Dossier portant demande d'arrêté d'admission à la retraite " . $liaisonPj;
            }
            break;
        case 'installation':
            if ($dest === 'augure_fop') {
                $titrePj = "Dossier de validation de releve de service " . $liaisonPj;
            } else {
                $titrePj = "Dossier portant demande de décision d'installation " . $liaisonPj;
            }
            break;
        case 'compensatrice':
            $titrePj = "Dossier portant demande de décision d'une indemnité de compensatrice " . $liaisonPj;
            break;
        default:
            if (!empty($filter)) {
                $label = ucfirst(str_replace('_', ' ', $filter));
                $titrePj = "Dossier portant demande de " . $label . " " . $liaisonPj;
            } else {
                $titrePj = "Dossier de documents " . $liaisonPj;
            }
            break;
    }
} else if ($type_bordereau === 'mandatement') {
    $liaisonPj = ($nbAgents > 1) ? "aux noms de :" : "au nom de :";
    
    switch (trim($filter)) {
        case 'renouvellement':
            $titrePj = "Dossier portant demande mandatament de renouvellement de contrat " . $liaisonPj;
            break;
        case 'avenant':
            $titrePj = "Dossier portant demande mandatament d'avenant " . $liaisonPj;
            break;
        case 'avenant_avec_contrat':
            $titrePj = "Dossier portant demande de rappel différentiel moins perçu " . $liaisonPj;
            break;
        case 'avancement_classe':
            $titrePj = "Dossier portant demande mandatament d'avancement de classe " . $liaisonPj;
            break;
        case 'avancement_echelon':
            $titrePj = "Dossier portant demande mandatament d'avancement d'echelon " . $liaisonPj;
            break;
        case 'integration':
            $titrePj = "Dossier portant demande mandatament d'intégration " . $liaisonPj;
            break;
        case 'titularisation':
            $titrePj = "Dossier portant demande mandatement de titularisation " . $liaisonPj;
            break;
        case 'compensatrice':
            $titrePj = "Demande de mandatement d’indemnité compensatrice de congé non pris " . $liaisonPj;
            break;
        case 'installation':
            $titrePj = "Demande de mandatement d’indemnité d’installation " . $liaisonPj;
            break;
        default:
            if (!empty($filter)) {
                $label = ucfirst(str_replace('_', ' ', $filter));
                $titrePj = "Dossier portant demande de " . $label . " " . $liaisonPj;
            } else {
                $titrePj = "Dossier de documents " . $liaisonPj;
            }
            break;
    }
} else {
    switch (trim($filter)) {
    case 'conge_annuel':
            $titrePj = "Dossier portant demande de décision de congé annuel " . $liaisonPj;
            break;
    }
}

$colDesignation = $titrePj . "\n";
$colNombre      = "\n";
$liste_im_array = []; 

if ($nbAgents === 1) {
    $colDesignation .= "- " . $agents[0]['nom'] . " " . $agents[0]['prenoms'] . ", IM: " . $agents[0]['im'];
    $colNombre .= "01";
    $liste_im_array[] = $agents[0]['im'];
} else {
    foreach ($agents as $idx => $a) {
        $num_agent = $idx + 1;
        $colDesignation .= $num_agent . ". " . $a['nom'] . " " . $a['prenoms'] . ", IM: " . $a['im'];
        $colNombre .= "01";
        
        if ($idx < $nbAgents - 1) {
            $colDesignation .= "\n";
            $colNombre .= "\n";
        }
        $liste_im_array[] = $a['im'];
    }
}

// 6. GÉNÉRATION ET ARCHIVAGE
try {
    $template = new TemplateProcessor(APP_ROOT . '/pieces/Bordereau_d_envoi/bordereau_envoi.docx');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmtLieu = $pdo->prepare("SELECT code_lieu_affectation FROM utilisateurs WHERE im = ?");
    $stmtLieu->execute([$user_im]);
    $code_lieu = $stmtLieu->fetchColumn();

    if ($code_lieu && !empty($sigle)) {
        $sqlSigle = "INSERT INTO sigle_bordereau (libelle_sigle, lieu_direction_service, type_etablissement) 
                    VALUES (:sigle, :lieu, :type_etablissement) 
                    ON DUPLICATE KEY UPDATE libelle_sigle = VALUES(libelle_sigle), type_etablissement = VALUES(type_etablissement)";
        $stmtS = $pdo->prepare($sqlSigle);
        $stmtS->execute([
            'sigle'              => $sigle, 
            'lieu'               => $code_lieu,
            'type_etablissement' => $typeEtablissement
        ]);
    }

    $liste_agents_str = implode(', ', $liste_im_array);
    $anneeActuelle    = date('Y'); 
    $num_complet      = $num . " / " . $anneeActuelle . " - " . $sigle;
    $expediteur_code  = $user['code_lieu_affectation'] ?? 'Inconnu';
    $dateActuelle     = date('Y-m-d H:i:s');

    $sql_archive = "INSERT INTO {$tableArchive} (
                        annee_bordereau, nombre_agents, liste_agents, numero_bordereau, 
                        sigle_bordereau, numero_complet, expediteur, 
                        destination, type_bordereau, type_demande, date_envoi_bordereau, type_etablissement
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql_archive);
    $stmt->execute([
        $anneeActuelle, $nbAgents, $liste_agents_str, (int)$num, 
        $sigle, $num_complet, $expediteur_code, $dest, $type_bordereau, $filter, 
        $dateActuelle, $typeEtablissement
    ]);

    $id_bordereau_genere = $pdo->lastInsertId();

    // B. Mise à jour de la table suivi_agents_bordereau
    if ($colonneBordereau) {
        $sqlSuivi = "INSERT INTO suivi_agents_bordereau (
                        id_bordereau, 
                        im_agent, 
                        type_bordereau, 
                        {$colonneBordereau}
                    ) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        id_bordereau = VALUES(id_bordereau),
                        {$colonneBordereau} = VALUES({$colonneBordereau})";
        
        $insertSuivi = $pdo->prepare($sqlSuivi);

        foreach ($liste_im_array as $im_agent) {
            $insertSuivi->execute([
                $id_bordereau_genere, 
                $im_agent, 
                $filter, 
                $num_complet
            ]);
        }
    }

    // C. Mise à jour des drapeaux d'impression
    if ($colonneSuivi) {
        if ($type_bordereau === 'mandatement' && $dest === 'augure_dsp') {

            $placeholders = implode(',', array_fill(0, count($liste_im_array), '?'));
            if ($filter === 'avenant_avec_contrat') {
                // 1. Mise à jour du numéro de bordereau dans acte_formate_av_cont
                $paramsActe = array_merge([$num_complet], $liste_im_array);
                $stmtActeBord = $pdo->prepare("
                    UPDATE acte_formate_av_cont 
                    SET bordereaux_mandatement = ? 
                    WHERE im IN ($placeholders)
                ");
                $stmtActeBord->execute($paramsActe);

                // 2. Passé le drapeau déjà imprimé à 1 dans acte_formate_av_cont
                $stmtFlag = $pdo->prepare("
                    UPDATE acte_formate_av_cont 
                    SET deja_imprime_mandatement = 1 
                    WHERE solde_et_pensions_mandatement = 1 
                    AND deja_imprime_mandatement = 0
                    AND im IN ($placeholders)
                ");
                $stmtFlag->execute($liste_im_array);

            } 
            else {
                $paramsActe = array_merge([$num_complet], $liste_im_array);
                $stmtActeBord = $pdo->prepare("
                    UPDATE acte_formate 
                    SET bordereaux_mandatement = ? 
                    WHERE im IN ($placeholders)
                ");
                $stmtActeBord->execute($paramsActe);

                // Préparation de la sélection avec récupération de la catégorie actuelle
                $stmtGetAgentData = $pdo->prepare("
                    SELECT af.type_demande, af.new_corps, af.new_grade, af.new_code_corps, 
                        af.new_code_grade, af.new_indice, af.new_date_d_effet,
                        psa.categorie_actuel
                    FROM acte_formate af
                    LEFT JOIN personnel_situation_actuelle psa ON af.im = psa.im
                    WHERE af.im = ? AND (af.statut = 'en_attente' OR af.statut = 'termine')
                    ORDER BY af.id DESC LIMIT 1
                ");

                // Requêtes UPDATE adaptées selon les cas
                
                // 1. Integration - Catégories IV, V, VI, VIII
                $sqlUpdateIntegrationCatHaute = $pdo->prepare("
                    UPDATE acte_formate 
                    SET statut_agent     = 'Fonctionnaire',
                        code_corps_actuel = :new_code_corps,
                        statut            = 'termine'
                    WHERE im = :im AND (statut = 'en_attente' OR statut = 'termine')
                ");

                // 2. Integration - Catégories II, III
                $sqlUpdateIntegrationCatBasse = $pdo->prepare("
                    UPDATE acte_formate 
                    SET statut_agent       = 'Fonctionnaire',
                        grade_actuel        = :new_grade,
                        code_corps_actuel   = :new_code_corps,
                        code_grade_actuel   = :new_code_grade,
                        date_d_effet_actuel = :new_date_d_effet,
                        indice_actuel       = :new_indice,
                        statut              = 'termine'
                    WHERE im = :im AND (statut = 'en_attente' OR statut = 'termine')
                ");

                // 3. Autres types de demande (titularisation, avenant, renouvellement, avancement_classe, avancement_echelon)
                $sqlUpdateAutresDemandes = $pdo->prepare("
                    UPDATE acte_formate 
                    SET grade_actuel        = :new_grade,
                        code_corps_actuel   = :new_code_corps,
                        code_grade_actuel   = :new_code_grade,
                        date_d_effet_actuel = :new_date_d_effet,
                        indice_actuel       = :new_indice,
                        statut              = 'termine'
                    WHERE im = :im AND (statut = 'en_attente' OR statut = 'termine')
                ");

                foreach ($liste_im_array as $im_agent) {
                    $stmtGetAgentData->execute([$im_agent]);
                    $agentData = $stmtGetAgentData->fetch(PDO::FETCH_ASSOC);

                    if ($agentData) {
                        $type_demande = $agentData['type_demande'] ?? '';
                        $categorie    = trim($agentData['categorie_actuel'] ?? '');

                        if ($type_demande === 'integration') {
                            if (in_array($categorie, ['IV', 'V', 'VI', 'VIII'])) {
                                // Cas Intégration IV, V, VI, VIII
                                $sqlUpdateIntegrationCatHaute->execute([
                                    ':new_code_corps' => $agentData['new_code_corps'],
                                    ':im'             => $im_agent
                                ]);
                            } elseif (in_array($categorie, ['II', 'III'])) {
                                // Cas Intégration II, III
                                $sqlUpdateIntegrationCatBasse->execute([
                                    ':new_grade'        => $agentData['new_grade'],
                                    ':new_code_corps'   => $agentData['new_code_corps'],
                                    ':new_code_grade'   => $agentData['new_code_grade'],
                                    ':new_date_d_effet' => $agentData['new_date_d_effet'],
                                    ':new_indice'       => $agentData['new_indice'],
                                    ':im'               => $im_agent
                                ]);
                            }
                        } elseif (in_array($type_demande, ['titularisation', 'avenant', 'renouvellement', 'avancement_classe', 'avancement_echelon'])) {
                            // Cas Titularisation, Avenant, Renouvellement, Avancement classe/échelon
                            $sqlUpdateAutresDemandes->execute([
                                ':new_grade'        => $agentData['new_grade'],
                                ':new_code_corps'   => $agentData['new_code_corps'],
                                ':new_code_grade'   => $agentData['new_code_grade'],
                                ':new_date_d_effet' => $agentData['new_date_d_effet'],
                                ':new_indice'       => $agentData['new_indice'],
                                ':im'               => $im_agent
                            ]);
                        }
                    }
                }

                $stmtFlag = $pdo->prepare("
                    UPDATE acte_formate 
                    SET deja_imprime_mandatement = 1 
                    WHERE solde_et_pensions_mandatement = 1 
                    AND deja_imprime_mandatement = 0
                    AND im IN ($placeholders)
                ");
                $stmtFlag->execute($liste_im_array);
            }
            // Préparation des requêtes
            $stmtDelSuivi = $pdo->prepare("DELETE FROM suivi_agents_bordereau WHERE im_agent = ?");
            $stmtDelDos   = $pdo->prepare("DELETE FROM demandes_numeros_dos WHERE im = ? AND statut_validation = 'valide'");
            $stmtDelNotif = $pdo->prepare("
                DELETE FROM notifications 
                WHERE im = ? 
                AND alerte_id NOT IN (SELECT alerte_id FROM v_moteur_alertes WHERE alerte_id IS NOT NULL)
            ");
            $stmtDelAlertesNotif = $pdo->prepare("
                DELETE FROM alertes_notifications 
                WHERE im_user = ? 
                AND alerte_id NOT IN (SELECT alerte_id FROM v_moteur_alertes WHERE alerte_id IS NOT NULL)
            ");

            foreach ($liste_im_array as $im_agent) {
                $stmtDelSuivi->execute([$im_agent]);
                $stmtDelDos->execute([$im_agent]);
                $stmtDelNotif->execute([$im_agent]);
                $stmtDelAlertesNotif->execute([$im_agent]);
            }

        } else {
            if ($niveau_resp === 'regional') {
                $lieu_resp_esc = mysqli_real_escape_string($conn, $lieu_resp);
                $sql_update = "UPDATE demandes_numeros_dos d
                            JOIN personnel_poste_actuel p ON d.im = p.im
                            SET d.$colonneSuivi = 1
                            WHERE d.$colSelection = 1 
                                AND p.nom_region = '$lieu_resp_esc' 
                                AND (d.statut = 'ATTRIBUE' OR d.statut = 'EN_ATTENTE')
                                AND d.$colonneSuivi = 0";
            } else {
                $sql_update = "UPDATE demandes_numeros_dos 
                            SET $colonneSuivi = 1 
                            WHERE $colSelection = 1 
                                AND (statut = 'ATTRIBUE' OR statut = 'EN_ATTENTE') 
                                AND $colonneSuivi = 0";
            }
            mysqli_query($conn, $sql_update);
        }
    }

    // 7. GÉNÉRATION DU DOCUMENT WORD
    $template->setValue('type_direction', $val_type_direction);
    $template->setValue('sgrh_cisco_crfrp', $val_sgrh);
    $template->setValue('expeditaire', $val_expeditaire);
    $template->setValue('destinataire', $val_destinataire);
    $template->setValue('lieu_d_envoi', $val_lieu_envoi);
    $template->setValue('region', $val_region);
    $template->setValue('num_bord', $num_complet);
    $template->setValue('designation_pieces', str_replace("\n", $br, $colDesignation));
    $template->setValue('nb_pieces', str_replace("\n", $br, $colNombre));
    $template->setValue('observations', $observation);
    $template->setValue('total', str_pad($nbAgents, 2, '0', STR_PAD_LEFT));

    if (ob_get_length()) ob_end_clean();
    $mappingNoms = [
        'augure_dsp'  => 'Solde_et_pensions',
        'augure_fop'  => 'FOP',
        'augure_cf'   => 'CDE',
        'prefecture'  => 'Prefecture',
        'drh'         => 'DRH',
        'mtefop'      => 'FOP',
        'primature'   => 'Primature'
    ];
    $destCourt   = $mappingNoms[$dest] ?? $dest;
    $filterNomFichier = $filter;

    if ($filter === 'avenant_avec_contrat') {
        $filterNomFichier = 'rappel_differentiel_moins_perçu';
    }

    $nom_fichier = "BO_" . $destCourt . "_" . $filterNomFichier . "_" . $num . ".docx";

    header('Content-Description: File Transfer');
    vider_tampon_sortie();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $nom_fichier . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
?>
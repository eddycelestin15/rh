<?php
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
use PhpOffice\PhpWord\TemplateProcessor;

// Helper pour calculer la différence entre deux objets DateTime
function calculerIntervalleDates(?DateTime $dateDebut, ?DateTime $dateFin): array {
    if (!$dateDebut || !$dateFin || $dateDebut > $dateFin) {
        return ['ans' => 0, 'mois' => 0, 'jours' => 0];
    }
    $diff = $dateDebut->diff($dateFin);
    return [
        'ans'   => $diff->y,
        'mois'  => $diff->m,
        'jours' => $diff->d
    ];
}

function dateEnFrancais(?string $dateStr = null): string {
    $date = $dateStr ? new DateTime($dateStr) : new DateTime();
    
    $moisFR = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];
    
    $jour = $date->format('d');
    $mois = $moisFR[(int)$date->format('n')];
    $annee = $date->format('Y');
    
    return "{$jour} {$mois} {$annee}";
}

// 1. Récupération de l'Immatriculation (IM)
$im = $_GET['im'] ?? null;
$numero = $_GET['numero'] ?? '';
$sigle  = $_GET['sigle'] ?? '';
$annee  = date('Y');

if (!$im) {
    die("Erreur : Numéro d'immatriculation (IM) manquant.");
}

try {
    // 2. Requête état civil & situation actuelle
    $sqlAgent = "
        SELECT 
            ec.im,
            ec.nom,
            ec.prenoms,
            ec.date_naiss,
            ec.lieu_naiss,
            sa.corps_actuel,
            sa.grade_actuel,
            sa.date_entree_admin,
            sa.imput_budg
        FROM personnel_etat_civil ec
        LEFT JOIN personnel_situation_actuelle sa ON ec.im = sa.im
        WHERE ec.im = :im
        LIMIT 1
    ";
    
    $stmtAgent = $pdo->prepare($sqlAgent);
    $stmtAgent->execute([':im' => $im]);
    $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);

    if (!$agent) {
        die("Erreur : Aucun agent trouvé avec l'IM " . htmlspecialchars($im));
    }

    // Calcul de la date de retraite (60 ans à partir de date_naiss)
    $dateRetraite = '';
    if (!empty($agent['date_naiss'])) {
        $dtNaiss = new DateTime($agent['date_naiss']);
        $dtNaiss->modify('+60 years');
        $dateRetraite = $dtNaiss->format('d/m/Y');
    }

    // 3. Requête de la liste des avancements successifs
    $sqlAvancements = "
        SELECT 
            av_type_acte,
            av_type_avancement,
            av_grade,
            av_acte_no,
            av_acte_date,
            av_date_effet,
            lieu_de_service
        FROM personnel_avancements
        WHERE im = :im
        ORDER BY av_date_effet ASC
    ";
    
    $stmtAv = $pdo->prepare($sqlAvancements);
    $stmtAv->execute([':im' => $im]);
    $avancements = $stmtAv->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------------------------
    // 3.1 Calcul des durées de service (Auxiliaire, Encadré, Totalité)
    // -------------------------------------------------------------------
    $dateDuJour = new DateTime();

    $ans_aux   = 0; $mois_aux   = 0; $jour_aux   = 0;
    $ans_fonc  = 0; $mois_fonc  = 0; $jour_fonc  = 0;
    $ans_effec = 0; $mois_effec = 0; $jour_effec = 0;

    if (!empty($avancements)) {
        $datesAuxiliaires = [];
        $datesArrete = [];

        foreach ($avancements as $av) {
            $type = mb_strtoupper($av['av_type_acte'] ?? '', 'UTF-8');
            if (!empty($av['av_date_effet'])) {
                if (mb_strpos($type, 'CONTRAT') !== false || mb_strpos($type, 'AVENANT') !== false) {
                    $datesAuxiliaires[] = new DateTime($av['av_date_effet']);
                }
                if (mb_strpos($type, 'ARRÊTÉ') !== false || mb_strpos($type, 'ARRETE') !== false) {
                    $datesArrete[] = new DateTime($av['av_date_effet']);
                }
            }
        }

        // A. Service Auxiliaire
        if (!empty($datesAuxiliaires) && !empty($datesArrete)) {
            $minDateAux = min($datesAuxiliaires);
            $minDateArrete = min($datesArrete);
            $dateFinAux = (clone $minDateArrete)->modify('-1 day');
            $dateCalcul = (clone $dateFinAux)->modify('+1 day');
            $diffAux  = calculerIntervalleDates($minDateAux, $dateCalcul);
            $ans_aux  = $diffAux['ans'];
            $mois_aux = $diffAux['mois'];
            $jour_aux = $diffAux['jours'];
        }

        // B. Service Encadré
        if (!empty($datesArrete)) {
            $minDateArrete = min($datesArrete);
            $diffFonc  = calculerIntervalleDates($minDateArrete, $dateDuJour);
            $ans_fonc  = $diffFonc['ans'];
            $mois_fonc = $diffFonc['mois'];
            $jour_fonc = $diffFonc['jours'];
        }

        // C. Totalité des services effectués
        $allDatesEffet = [];
        foreach ($avancements as $av) {
            if (!empty($av['av_date_effet'])) {
                $allDatesEffet[] = new DateTime($av['av_date_effet']);
            }
        }

        if (!empty($allDatesEffet)) {
            $minDateGlobale = min($allDatesEffet);
            $diffEffec  = calculerIntervalleDates($minDateGlobale, $dateDuJour);
            $ans_effec  = $diffEffec['ans'];
            $mois_effec = $diffEffec['mois'];
            $jour_effec = $diffEffec['jours'];
        }
    }

    // Récupération des informations de l'arrêté d'admission à la retraite
    $numArreteRetraite  = '';
    $dateArreteRetraite = '';

    $sqlRetraite = "
        SELECT num_arrete_retraite, date_arrete_retraite 
        FROM arrete_admission_retraite 
        WHERE im = :im 
        ORDER BY id DESC 
        LIMIT 1
    ";
    $stmtRetraite = $pdo->prepare($sqlRetraite);
    $stmtRetraite->execute([':im' => $im]);
    $retraiteData = $stmtRetraite->fetch(PDO::FETCH_ASSOC);

    if ($retraiteData) {
        $numArreteRetraite = $retraiteData['num_arrete_retraite'] ?? '';
        if (!empty($retraiteData['date_arrete_retraite'])) {
            $dateArreteRetraite = date('d/m/Y', strtotime($retraiteData['date_arrete_retraite']));
        }
    }

    // Chargement du Modèle Word Template
    $typeDemande = strtoupper(trim($_GET['type_demande'] ?? ''));

    // Vérification souple : gère aussi bien "INSTALLATION" que "IM_INSTALLATION" ou "123_INSTALLATION"
    if ($typeDemande === 'INSTALLATION' || str_contains($typeDemande, 'INSTALLATION')) {
        $templatePath = __DIR__ . '/../../pieces/Installation/releve_de_service.docx';
    } else {
        $templatePath = __DIR__ . '/../../pieces/AdmissionRetraite/releve_de_service.docx';
    }

    if (!file_exists($templatePath)) {
        die("Erreur : Le fichier template 'releve_de_service.docx' est introuvable à l'emplacement : " . $templatePath);
    }

    $template = new TemplateProcessor($templatePath);

    $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
    $stmtPosteActuel->execute([$im]);
    $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

    $type_etablissement = $PosteActuel['type_etablissement'] ?? '';
    $type_direction     = $PosteActuel['type_direction'] ?? '';
    $nom_direction      = $PosteActuel['nom_direction'] ?? '';
    $nom_region         = $PosteActuel['nom_region'] ?? ''; 
    $nom_district       = $PosteActuel['nom_district'] ?? ''; 
    $nom_zap            = $PosteActuel['nom_zap'] ?? '';
    $nom_etablissement  = $PosteActuel['nom_etablissement'] ?? '';

    $lieuService = "";
    if ($type_etablissement === 'MEN CENTRAL') {
        $lieuService = !empty($nom_direction) ? $nom_direction : '-';
    } elseif ($type_etablissement === 'DREN') {
        $lieuService = "BUREAU DREN " . $nom_region;
    } elseif ($type_etablissement === 'CISCO') {
        $lieuService = "BUREAU CISCO " . $nom_district;
    } elseif ($type_etablissement === 'CRFRP') {
        $lieuService = !empty($nom_etablissement) ? $nom_etablissement : '-';
    } elseif (in_array($type_etablissement, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
        $lieuService = "DREN " . $nom_region . " / CISCO " . $nom_district . " / ZAP " . $nom_zap . " / " . $nom_etablissement;
    } else {
        $lieuService = !empty($nom_etablissement) ? $nom_etablissement : '-';
    }

    $nom_crfrp_propre = $PosteActuel['nom_etablissement'] ?? '';
    if (stripos($nom_crfrp_propre, 'CRFRP') !== false) {
        $nom_crfrp_propre = trim(preg_replace('/^CRFRP\s+/i', '', $nom_crfrp_propre));
    }
    $nom_crfrp = $nom_crfrp_propre;
    
    $chef_lieu_region = '';
    $chef_lieu_district = '';
    if (!empty($nom_region)) {
        $stmtReg = $pdo->prepare("SELECT chef_lieu_region FROM ref_regions WHERE nom_region = :nom_region LIMIT 1");
        $stmtReg->execute(['nom_region' => $nom_region]);
        $region_data = $stmtReg->fetch(PDO::FETCH_ASSOC);
        if ($region_data && !empty($region_data['chef_lieu_region'])) {
            $chef_lieu_region = $region_data['chef_lieu_region'];
        }
    }
    
    if (!empty($nom_district)) {
        $stmtDist = $pdo->prepare("SELECT chef_lieu_district FROM ref_districts WHERE nom_district = :nom_district LIMIT 1");
        $stmtDist->execute(['nom_district' => $nom_district]);
        $district_data = $stmtDist->fetch(PDO::FETCH_ASSOC);
        if ($district_data && !empty($district_data['chef_lieu_district'])) {
            $chef_lieu_district = $district_data['chef_lieu_district'];
        }
    }

    $br = "\n";
    if ($type_etablissement == "MEN CENTRAL") {
        $template->setValue('type_direction', "DIRECTION DES RESSOURCES HUMAINES");
        $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION ADMINISTRATIVE DU PERSONNEL");
        $template->setValue('lieu_signature', 'Antananarivo');
    } else if ($type_etablissement == "DREN") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" . $br . $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
        $template->setValue('lieu_signature', $chef_lieu_region); 
    } else if ($type_etablissement == "CISCO") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" . $br . $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " . $nom_district);
        $template->setValue('lieu_signature', $chef_lieu_district); 
    } else if ($type_etablissement == "CRFRP") {
        $template->setValue('type_direction', "DIRECTION GENERALE DE L’INSTITUT NATIONAL" . $br . "DE FORMATION PEDAGOGIQUE");
        $template->setValue('sgrh_cisco_crfrp', "CENTRE REGIONAL DE FORMATION" . $br . "ET DE RECHERCHE PEDAGOGIQUE " . $nom_crfrp);
        $template->setValue('lieu_signature', $chef_lieu_district); 
    } else if (in_array($type_etablissement, ['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'])) {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" . $br . $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " . $nom_district);
        $template->setValue('lieu_signature', $chef_lieu_district); 
    }

    // 5. Injection des variables simples de l'état civil / situation
    $template->setValue('num_releve', "N°{$annee}/{$numero} - {$sigle}");
    $nomComplet = strtoupper($agent['nom']) . ' ' . $agent['prenoms'];    
    $template->setValue('im', $agent['im'] ?? '');
    $template->setValue('nom_prenoms', $nomComplet);
    $template->setValue('date_naiss', !empty($agent['date_naiss']) ? date('d/m/Y', strtotime($agent['date_naiss'])) : '');
    $template->setValue('date_retraite', $dateRetraite);
    $template->setValue('lieu_naiss', $agent['lieu_naiss'] ?? '');
    $template->setValue('corps', $agent['corps_actuel'] ?? '');
    $template->setValue('grade', $agent['grade_actuel'] ?? '');
    $template->setValue('imputation', $agent['imput_budg'] ?? '');
    $template->setValue('lieuService', $lieuService);
    $template->setValue('date_prise2service', !empty($agent['date_entree_admin']) ? date('d/m/Y', strtotime($agent['date_entree_admin'])) : '');
    $template->setValue('date_du_jour', dateEnFrancais());
    
    // Injection des durées calculées
    $template->setValue('ans_aux', $ans_aux);
    $template->setValue('mois_aux', $mois_aux);
    $template->setValue('jour_aux', $jour_aux);

    $template->setValue('ans_fonc', $ans_fonc);
    $template->setValue('mois_fonc', $mois_fonc);
    $template->setValue('jour_fonc', $jour_fonc);

    $template->setValue('ans_effec', $ans_effec);
    $template->setValue('mois_effec', $mois_effec);
    $template->setValue('jour_effec', $jour_effec);

    // 6. Injection & Clonage des lignes du Tableau
    $totalRows = count($avancements);

    if ($totalRows > 0) {
        $template->cloneRow('av_grade', $totalRows);

        foreach ($avancements as $index => $row) {
            $i = $index + 1; 
            $dateActe = !empty($row['av_acte_date']) ? date('d/m/Y', strtotime($row['av_acte_date'])) : '';
            $acteNoDate = trim(($row['av_acte_no'] ?? '') . ' du ' . $dateActe, ' du ');
            $dateEffet = !empty($row['av_date_effet']) ? date('d/m/Y', strtotime($row['av_date_effet'])) : '';
            
            // Condition pour remplacer la valeur de av_grade si c'est une Intégration ou Titularisation
            $typeAvancement = trim($row['av_type_avancement'] ?? '');
            if (in_array($typeAvancement, ['Intégration', 'Titularisation'])) {
                $valeurGrade = $typeAvancement;
            } else {
                $valeurGrade = $row['av_grade'] ?? '-';
            }

            $template->setValue("av_grade#{$i}", $valeurGrade);
            $template->setValue("acte_no_date#{$i}", $acteNoDate ?: '-');
            $template->setValue("av_date_effet#{$i}", $dateEffet ?: '-');
            $template->setValue("lieu_de_service#{$i}", $row['lieu_de_service'] ?? '-');
        }
    } else {
        $template->cloneRow('av_grade', 1);
        $template->setValue('av_grade#1', 'Aucun avancement');
        $template->setValue('acte_no_date#1', '-');
        $template->setValue('av_date_effet#1', '-');
        $template->setValue('lieu_de_service#1', '-');
    }
    $template->setValue('num_admission', $numArreteRetraite);
    $template->setValue('date_admission', $dateArreteRetraite);

    $filename = "Releve_de_Service_" . $im . ".docx";
    if (ob_get_length()) ob_clean();
    vider_tampon_sortie();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur lors de la génération du document : " . $e->getMessage());
}
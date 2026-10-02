<?php
// Tampon de sortie : évite qu'un avertissement PHP ne corrompe le .docx
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
use PhpOffice\PhpWord\TemplateProcessor;

$im = $_GET['im'] ?? '';
$type_demande = $_GET['type'] ?? '';

if (!$im) die("IM manquant.");

try {
    // 1. Récupération des informations depuis personnel_etat_civil
    $stmt = $pdo->prepare("SELECT ec.*, psa.*
        FROM personnel_etat_civil ec 
        LEFT JOIN personnel_situation_actuelle psa ON ec.im = psa.im 
        WHERE ec.im = ?");
    $stmt->execute([$im]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agent) die("Agent introuvable.");

    // Récupération des données selon la table appropriée
    if (in_array($type_demande, ['compensatrice', 'installation'])) {
        $stmtGetActe = $pdo->prepare("SELECT * FROM acte_formate_retraite WHERE im = ? AND type_demande = ? ORDER BY id DESC LIMIT 1");
        $stmtGetActe->execute([$im, $type_demande]);
    } elseif ($type_demande === 'avenant_avec_contrat') {
        $stmtGetActe = $pdo->prepare("SELECT * FROM acte_formate_av_cont WHERE im = ? ORDER BY id DESC LIMIT 1");
        $stmtGetActe->execute([$im]);
    } else {
        $stmtGetActe = $pdo->prepare("SELECT * FROM acte_formate WHERE im = ? AND (statut = 'en_attente' OR statut = 'termine') ORDER BY id DESC LIMIT 1");
        $stmtGetActe->execute([$im]);
    }
    $acteData = $stmtGetActe->fetch(PDO::FETCH_ASSOC);

    $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
    $stmtPosteActuel->execute([$im]);
    $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

    // ==========================================
    // RÉCUPÉRATION NUM_ACTE & DATE_ACTE
    // ==========================================
    $num_acte_valeur = $acteData['num_acte'] ?? $agent['num_acte'] ?? '';
    $date_acte_valeur = $acteData['date_acte'] ?? $agent['date_acte'] ?? '';

    // ==========================================
    // LOGIQUE DE GÉNÉRATION DE DOCUMENT WORD
    // ==========================================
    $nbr_af = "02";
    $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement.docx';

    if ($type_demande === 'renouvellement') {
        $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement_renouvellement_contrat.docx';
        if (!empty($agent['new_date_d_effet'])) {
            $jour = (int)date('d', strtotime($agent['new_date_d_effet']));
            if ($jour <= 10) {
                $nbr_af = "04";
            }
        }
    } elseif ($type_demande === 'avenant') {
        $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement_avenant.docx';
    } elseif ($type_demande === 'avenant_avec_contrat') {
        $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement_avenant_rappel_differentiel.docx';
    } elseif ($type_demande === 'avancement_classe') {
        $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement_avancement_classe.docx';
    } elseif ($type_demande === "avancement_echelon") {
        $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement_avancement_echelon.docx';
    } elseif ($type_demande === "integration") {
        $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement_integration.docx';
        if ($agent) {
            $indice_actuel = $agent['indice_actuel'] ?? null;
            $new_indice    = $agent['new_indice'] ?? null;
            if ($indice_actuel !== null && $new_indice !== null && $indice_actuel != $new_indice) {
                $nbr_af = '04';
            }
        }
    } elseif ($type_demande === "titularisation") {
        $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement_titularisation.docx';
    } elseif ($type_demande === "compensatrice") {
        $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement_compensatrice.docx';
    } elseif ($type_demande === "installation") {
        $templatePath = APP_ROOT . '/pieces/Mandatement/Mandatement_installation.docx';
    }
    
    if (!file_exists($templatePath)) {
        erreur_gabarit_absent($templatePath);
    }

    $template = new TemplateProcessor($templatePath);

    $br = '</w:t><w:br/><w:t>';
    $sexe = $agent['sexe'] ?? 'Masculin';
    $type_direction = $PosteActuel['type_direction'] ?? '';
    $nom_direction = $PosteActuel['nom_direction'] ?? '';
    $type_fonction = $PosteActuel['type_fonction'] ?? '';
    $type_etablissement = $PosteActuel['type_etablissement'] ?? '';
    $nom_region = $PosteActuel['nom_region'] ?? '';
    $nom_district = $PosteActuel['nom_district'] ?? '';
    $nom_etablissement = $PosteActuel['nom_etablissement'] ?? '';
    $nom_zap = $PosteActuel['nom_zap'] ?? '';

    if ($sexe == "Masculin"){
        $template->setValue('nee_le', "Né le");
        $template->setValue('interessee', "L'intéressé");
    } else if ($sexe == "Féminin"){
        $template->setValue('nee_le', "Née le");
        $template->setValue('interessee', "L'intéressée");
    }

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

    if ($type_etablissement =="MEN CENTRAL") {
        $template->setValue('type_direction', "DIRECTION DES RESSOURCES HUMAINES");
        $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION ADMINISTRATIVE DU PERSONNEL");
        $template->setValue('chef_signataire', "Chef de Service de la Gestion Administrative du Personnel, de la Direction des Ressources Humaines");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au");
        $template->setValue('en_service', $nom_direction);
        $template->setValue('lieu_signature', 'Antananarivo');
        $template->setValue('adresser_a', 'LE CHEF DE SERVICE REGIONAL DE LA SOLDE ET DES PENSIONS');
        $template->setValue('chef_lieu_region', '');
        $template->setValue('titre_signataire', 'Le Directeur');
    } else if ($type_etablissement =="DREN") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
        $template->setValue('chef_signataire', "Chef de Service de la Gestion des Ressources Humaines, de la Direction Régionale de l’Education Nationale de ");
        $template->setValue('nom_region', ucfirst(mb_strtolower($PosteActuel['nom_region'] ?? '')));
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au");
        $template->setValue('en_service', "DREN ".$nom_region);
        $template->setValue('lieu_signature', $chef_lieu_region); 
        $template->setValue('adresser_a', 'LE CHEF DE SERVICE REGIONAL DE LA SOLDE ET DES PENSIONS');
        $template->setValue('chef_lieu_region', $nom_region);
    } else if ($type_etablissement =="CISCO") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $chef_lieu_district);
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', "CISCO ".$nom_district);
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('adresser_a', 'LE CHEF DE SERVICE REGIONAL DE LA SOLDE ET DES PENSIONS');
        $template->setValue('chef_lieu_region', $nom_region);
    } else if ($type_etablissement =="CRFRP") {
        $template->setValue('type_direction', "DIRECTION GENERALE DE L’INSTITUT NATIONAL" .$br. "DE FORMATION PEDAGOGIQUE");
        $template->setValue('sgrh_cisco_crfrp', "CENTRE REGIONAL DE FORMATION" .$br. "ET DE RECHERCHE PEDAGOGIQUE ".$nom_crfrp);
        $template->setValue('chef_signataire', "Chef de Centre  Régional de Formation et de Recherche Pédagogique de ".ucfirst(mb_strtolower($nom_crfrp, 'UTF-8')));
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', $nom_etablissement);
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('adresser_a', 'LE CHEF DE SERVICE REGIONAL DE LA SOLDE ET DES PENSIONS');
        $template->setValue('chef_lieu_region', $nom_region);
    } else if ($type_etablissement == "LYCEE" || $type_etablissement == "COLLEGE") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $chef_lieu_district);
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
        $template->setValue('lieu_signature', $chef_lieu_district);
        $template->setValue('adresser_a', 'LE CHEF DE SERVICE REGIONAL DE LA SOLDE ET DES PENSIONS');
        $template->setValue('chef_lieu_region', $nom_region);
    } else if ($type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $chef_lieu_district);
        $template->setValue('au_a_la', "à l'");
        $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('adresser_a', 'LE CHEF DE SERVICE REGIONAL DE LA SOLDE ET DES PENSIONS');
        $template->setValue('chef_lieu_region', $nom_region);
    }

    $genre_input = $_GET['genre'] ?? 'Mr';
    $genre_maj = ($genre_input === 'Mme') ? 'MADAME' : 'MONSIEUR';
    $genre_min = ($genre_input === 'Mme') ? 'Madame' : 'Monsieur';
    $template->setValue('genre_min', $genre_min);
    $template->setValue('genre_maj', $genre_maj);
    $template->setValue('nom', htmlspecialchars(($agent['nom'] ?? '') . ' ' . ($agent['prenoms'] ?? '')));
    $template->setValue('im', $agent['im']);
    $template->setValue('corps', $agent['corps_actuel'] ?? '');
    $template->setValue('grade', $agent['grade_actuel'] ?? '');
    $template->setValue('indice', $agent['indice_actuel'] ?? '');
    $template->setValue('imputation', $agent['imput_budg'] ?? '');
    
    $template->setValue('num_acte', $num_acte_valeur);
    $template->setValue('date_acte', !empty($date_acte_valeur) ? date('d/m/Y', strtotime($date_acte_valeur)) : '');
    
    $template->setValue('date_naiss', !empty($agent['date_naiss']) ? date('d/m/Y', strtotime($agent['date_naiss'])) : '');
    $template->setValue('lieu_naiss', $agent['lieu_naiss'] ?? '');
    $template->setValue('cin', $agent['cin'] ?? '');
    $template->setValue('date_cin', !empty($agent['date_cin']) ? date('d/m/Y', strtotime($agent['date_cin'])) : '');
    $template->setValue('date_entree_admin', !empty($agent['date_entree_admin']) ? date('d/m/Y', strtotime($agent['date_entree_admin'])) : '');
    $template->setValue('lieu_cin', $agent['lieu_cin'] ?? '');
    $template->setValue('nbr_af', $nbr_af);

    // ==========================================
    // TRAITEMENT BDD : MAJ STATUT & SITUATION
    // ==========================================
    if ($acteData) {
        $pdo->beginTransaction();

        $type_demande_actuel = $acteData['type_demande'] ?? $type_demande;

        if (in_array($type_demande_actuel, ['compensatrice', 'installation'])) {
            // Pour compensatrice et installation : mise à jour simple du statut uniquement
            $stmtUpdateStatut = $pdo->prepare("UPDATE acte_formate_retraite 
                SET statut = 'valide'
                WHERE id = ?");
            $stmtUpdateStatut->execute([$acteData['id']]);

        } else {
            // Pour tous les autres types de demande
            $categorie_actuelle = trim($agent['categorie_actuel'] ?? '');

            if ($type_demande_actuel === 'integration') {
                $type_avancement = 'Intégration';
            } elseif ($type_demande_actuel === 'titularisation') {
                $type_avancement = 'Titularisation';
            } else {
                $type_avancement = (strpos($acteData['new_code_grade'] ?? '', '1E') !== false) ? 'Classe' : 'Echelon';
            }
            
            $type_acte = 'Acte';
            if (($acteData['acte'] ?? '') == 'CONTRAT') {
                $type_acte = 'Contrat';
            } elseif (($acteData['acte'] ?? '') == 'AVENANT') {
                $type_acte = 'Avenant';
            } elseif (($acteData['acte'] ?? '') == 'ARRETE') {
                $type_acte = 'Arrêté';
            }

            $stmtGrade = $pdo->prepare("SELECT libelle_grade FROM ref_grades_types WHERE code_grade = ? LIMIT 1");
            $stmtGrade->execute([$acteData['new_code_grade'] ?? '']);
            $gradeInfo = $stmtGrade->fetch(PDO::FETCH_ASSOC);
            $libelle_grade = $gradeInfo ? $gradeInfo['libelle_grade'] : ($acteData['new_grade'] ?? $agent['grade_actuel'] ?? '');

            $nouveau_corps = !empty($acteData['new_corps']) ? $acteData['new_corps'] : ($agent['corps_actuel'] ?? '');
            $statut_agent = $agent['statut_actuel'] ?? '';

            $indiceAjuste = (int)($acteData['new_indice'] ?? 0);
            if (in_array($categorie_actuelle, ['II', 'III']) && in_array($type_demande_actuel, ['renouvellement', 'avenant'])) {
                $indiceAjuste -= 15;
            }

            if ($type_demande_actuel === 'integration') {            
                $sqlSituation = "UPDATE personnel_situation_actuelle SET 
                                    statut_actuel = 'Fonctionnaire',
                                    type_avancement_actuel = :type_avancement_actuel,
                                    type_acte_actuel = :type_acte_actuel,
                                    num_acte_actuel = :num_acte_actuel,
                                    date_acte_actuel = :date_acte_actuel,
                                    date_d_effet_actuel = :date_d_effet_actuel,
                                    code_corps_actuel = :code_corps_actuel,
                                    corps_actuel = :corps_actuel,
                                    grade_actuel = :grade_actuel,
                                    indice_actuel = :indice_actuel
                                WHERE im = :im";

                $pdo->prepare($sqlSituation)->execute([
                    ':type_avancement_actuel' => $type_avancement,
                    ':type_acte_actuel'       => $type_acte,
                    ':num_acte_actuel'        => $num_acte_valeur,
                    ':date_acte_actuel'       => $date_acte_valeur,
                    ':date_d_effet_actuel'    => $acteData['new_date_d_effet'] ?? null,
                    ':code_corps_actuel'      => $acteData['new_code_corps'] ?? null,
                    ':corps_actuel'           => $nouveau_corps,
                    ':grade_actuel'           => $libelle_grade,
                    ':indice_actuel'          => $indiceAjuste,
                    ':im'                     => $im
                ]);
            } else {            
                $sqlSituation = "UPDATE personnel_situation_actuelle SET 
                                    statut_actuel = :statut_actuel,
                                    type_avancement_actuel = :type_avancement_actuel,
                                    type_acte_actuel = :type_acte_actuel,
                                    num_acte_actuel = :num_acte_actuel,
                                    date_acte_actuel = :date_acte_actuel,
                                    date_d_effet_actuel = :date_d_effet_actuel,
                                    code_corps_actuel = :code_corps_actuel,
                                    corps_actuel = :corps_actuel,
                                    grade_actuel = :grade_actuel,
                                    indice_actuel = :indice_actuel
                                WHERE im = :im";

                $pdo->prepare($sqlSituation)->execute([
                    ':statut_actuel'          => $statut_agent,
                    ':type_avancement_actuel' => $type_avancement,
                    ':type_acte_actuel'       => $type_acte,
                    ':num_acte_actuel'        => $num_acte_valeur,
                    ':date_acte_actuel'       => $date_acte_valeur,
                    ':date_d_effet_actuel'    => $acteData['new_date_d_effet'] ?? null,
                    ':code_corps_actuel'      => $acteData['new_code_corps'] ?? null,
                    ':corps_actuel'           => $nouveau_corps,
                    ':grade_actuel'           => $libelle_grade,
                    ':indice_actuel'          => $indiceAjuste,
                    ':im'                     => $im
                ]);
            }

            // Insertion dans personnel_avancements
            $stmtCheckAv = $pdo->prepare("SELECT av_grade FROM personnel_avancements WHERE im = ? ORDER BY id DESC LIMIT 1");
            $stmtCheckAv->execute([$im]);
            $dernierAvancement = $stmtCheckAv->fetch(PDO::FETCH_ASSOC);
            $gradeExistant = $dernierAvancement ? $dernierAvancement['av_grade'] : '';

            $estIntegrationOuTitularisation = in_array($type_demande_actuel, ['integration', 'titularisation']);

            if (trim($gradeExistant) !== trim($libelle_grade) || $estIntegrationOuTitularisation) {
                $indiceAvancement = (int)($acteData['new_indice'] ?? 0);
                if (in_array($categorie_actuelle, ['II', 'III']) && in_array($type_demande_actuel, ['renouvellement', 'avenant'])) {
                    $indiceAvancement -= 15;
                }

                $dureeAvancement = null;
                $gradeUpper = mb_strtoupper($libelle_grade, 'UTF-8');
                $estGradeIndetermine = (
                    mb_strpos($gradeUpper, '2°CLASSE/2°ECHELON') !== false ||
                    mb_strpos($gradeUpper, 'ECHELLE III/3°ECHELON') !== false ||
                    mb_strpos($gradeUpper, 'ECHELLE IV/3°ECHELON') !== false
                );

                if ($statut_agent === 'Contractuel EFA' && $estGradeIndetermine) {
                    $dureeAvancement = 'indeterminee';
                } elseif (in_array($categorie_actuelle, ['II', 'III']) && $statut_agent === 'Contractuel EFA') {
                    $dureeAvancement = '2 ans';
                } else {
                    if (mb_stripos($libelle_grade, 'STAGIAIRE') !== false) {
                        $dureeAvancement = '1 an';
                    } elseif (mb_stripos($libelle_grade, '1°ECHELON') !== false || mb_stripos($libelle_grade, '2°ECHELON') !== false) {
                        $dureeAvancement = '2 ans';
                    } elseif (mb_stripos($libelle_grade, '3°ECHELON') !== false) {
                        $dureeAvancement = '3 ans';
                    }
                }

                $sqlAvancement = "INSERT INTO personnel_avancements 
                                    (im, duree, av_type_acte, av_type_avancement, av_corps, av_grade, av_date_effet, av_indice, av_acte_no, av_acte_date) 
                                VALUES 
                                    (:im, :duree, :av_type_acte, :av_type_avancement, :av_corps, :av_grade, :av_date_effet, :av_indice, :av_acte_no, :av_acte_date)";
                
                $pdo->prepare($sqlAvancement)->execute([
                    ':im'                 => $im, 
                    ':duree'              => $dureeAvancement,
                    ':av_type_acte'       => $type_acte, 
                    ':av_type_avancement' => $type_avancement,
                    ':av_corps'           => $nouveau_corps, 
                    ':av_grade'           => $libelle_grade, 
                    ':av_date_effet'      => $acteData['new_date_d_effet'] ?? null,
                    ':av_indice'          => $indiceAvancement, 
                    ':av_acte_no'         => $num_acte_valeur, 
                    ':av_acte_date'       => $date_acte_valeur
                ]);
            }

            // Insertion dans bin_agent
            $type_acte_bin = 'Arrêté';
            $statut_bin = 'Fonctionnaire';

            if ($statut_agent === 'Contractuel EFA') {
                if (strpos(trim($acteData['new_code_corps'] ?? ''), 'U') === 0) {
                    $type_acte_bin = 'Avenant';
                } else {
                    $type_acte_bin = 'Contrat';
                }
                $statut_bin = 'Contractuel EFA';
            } elseif ($type_demande_actuel === 'integration') {
                $type_acte_bin = 'Arrêté';
                $statut_bin = 'Fonctionnaire';
            }

            $sqlBinAgent = "INSERT INTO bin_agent 
                                (im_bin, corps_bin, grade_bin, type_acte_bin, numero_acte_bin, date_acte_bin, statut) 
                            VALUES 
                                (:im_bin, :corps_bin, :grade_bin, :type_acte_bin, :numero_acte_bin, :date_acte_bin, :statut)
                            ON DUPLICATE KEY UPDATE
                                corps_bin = VALUES(corps_bin),
                                grade_bin = VALUES(grade_bin),
                                type_acte_bin = VALUES(type_acte_bin),
                                date_acte_bin = VALUES(date_acte_bin),
                                statut = VALUES(statut)";

            $pdo->prepare($sqlBinAgent)->execute([
                ':im_bin'          => $im,
                ':corps_bin'       => $nouveau_corps,
                ':grade_bin'       => $libelle_grade,
                ':type_acte_bin'   => $type_acte_bin,
                ':numero_acte_bin' => $num_acte_valeur,
                ':date_acte_bin'   => $date_acte_valeur,
                ':statut'          => $statut_bin
            ]);

            // Mise à jour de la table d'origine
            $tableCible = ($acteData['type_demande'] === 'avenant_avec_contrat') ? 'acte_formate_av_cont' : 'acte_formate';
            $stmtUpdateStatut = $pdo->prepare("UPDATE {$tableCible} 
                SET statut = 'termine',
                    solde_et_pensions_mandatement = 0,
                    deja_imprime_mandatement = 0,
                    bordereaux_mandatement = NULL,
                    ref_mandatement = NULL,
                    date_reference_bordereau = NULL
                WHERE id = ?");
            $stmtUpdateStatut->execute([$acteData['id']]);
        }

        $pdo->commit();
    }

    // Téléchargement du fichier Word
    if (ob_get_length()) ob_clean();
    $filename = "Piece_Mandatement_" . $im . ".docx";
    vider_tampon_sortie();
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erreur : " . $e->getMessage());
}
<?php
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
use PhpOffice\PhpWord\TemplateProcessor;

$im = $_GET['im'] ?? null;
if (!$im) die("IM manquant.");

$nom_membre     = $_GET['nom_membre'] ?? '';
$im_membre      = $_GET['im_membre'] ?? '';
$nom_rapporteur = $_GET['nom_rapporteur'] ?? '';
$im_rapporteur  = $_GET['im_rapporteur'] ?? '';

try {
    // 1. Récupération Situation Actuelle
    $stmt = $pdo->prepare("SELECT ec.*, sa.* FROM personnel_etat_civil ec 
                           JOIN personnel_situation_actuelle sa ON ec.im = sa.im 
                           WHERE ec.im = ?");
    $stmt->execute([$im]);
    $agent = $stmt->fetch();

    $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
    $stmtPosteActuel->execute([$im]);
    $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

    // 2. Récupération du corps et du nom_region
    $sqlAgent = "SELECT 
                    p.nom_region, 
                    s.corps_actuel,
                    c.id AS corps_id
                FROM personnel_situation_actuelle s
                INNER JOIN personnel_poste_actuel p ON s.im = p.im
                LEFT JOIN ref_corps c ON TRIM(LOWER(s.corps_actuel)) = TRIM(LOWER(c.libelle_corps))
                WHERE s.im = :im";

    $stmtAgent = $pdo->prepare($sqlAgent);
    $stmtAgent->execute([':im' => $im]);
    $agentInfo = $stmtAgent->fetch(PDO::FETCH_ASSOC);

    $nom_region = $agentInfo['nom_region'] ?? '';
    $corps_id   = $agentInfo['corps_id'] ?? null;

    if (!$agent) die("Agent introuvable.");

    // 3. Récupération des retards
    $stmtAlert = $pdo->prepare("SELECT * FROM v_moteur_alertes 
                                WHERE im = ? 
                                AND alerte_id LIKE 'HIDDEN_%_AVANCEMENT_%' 
                                ORDER BY date_reception_technique ASC");
    $stmtAlert->execute([$im]);
    $etapes_retard = $stmtAlert->fetchAll(PDO::FETCH_ASSOC);

    $nb_projets = count($etapes_retard);
    if ($nb_projets === 0) die("Aucun retard trouvé.");

    $etape_classe_decision = null;
    $grades_cibles = ['1°CLASSE/1°ECHELON', 'PRINCIPAL/1°ECHELON', 'CLASSE EXCEPTIONNELLE/1°ECHELON'];

    foreach ($etapes_retard as $etape) {
        $titre_pur = str_replace(["Demande d’avancement", "(CLASSE et ECHELON)", "(ECHELON)"], "", $etape['titre']);
        $titre_comparaison = str_replace(' ', '', trim($titre_pur));

        if (in_array($titre_comparaison, $grades_cibles)) {
            $etape_classe_decision = $etape;
            break; 
        }
    }

    // 4. Détermination du préfixe du fichier
    $code_corps_prefix = substr($agent['code_corps_actuel'] ?? '', 0, 1);
    $is_fonctionnaire = ($agent['statut_actuel'] === 'Fonctionnaire' && $code_corps_prefix !== 'U');

    // 5. Sélection du template word
    $mode = $_GET['mode'] ?? 'complet';
    $suffixe = ($etape_classe_decision !== null) ? "_avec_classe.docx" : "_sans_classe.docx";
    $type_avancement_mode = ($etape_classe_decision !== null) ? "avec_classe" : "_sans_classe";

    $categorie_actuel = $agent['categorie_actuel'] ?? '';
    $grade_actuel     = $agent['grade_actuel'] ?? '';
    $condition_avenant_sans_avenant = (
        $code_corps_prefix === 'U' &&
        in_array($categorie_actuel, ['II', 'III']) &&
        mb_strpos($grade_actuel, '3°ECHELON') !== false
    );

    if ($condition_avenant_sans_avenant) {
        $nom_fichier_defaut = "Avenant_sans_avenant.docx";
    } else {
        $nom_fichier_defaut = ($is_fonctionnaire ? "Avancement" : "Avenant") . $suffixe;
    }

    // Analyse du type_titre depuis demandes_numeros_dos pour Avancement_classe
    $is_classe_avec_autre_echelon = false;
    if ($etape_classe_decision !== null) {
        $stmtTypeTitre = $pdo->prepare("SELECT type_titre FROM demandes_numeros_dos 
                                        WHERE im = ? AND type_dos = 'Avancement_classe' 
                                        AND statut = 'ATTRIBUE' LIMIT 1");
        $stmtTypeTitre->execute([$im]);
        $rowDos = $stmtTypeTitre->fetch(PDO::FETCH_ASSOC);

        if ($rowDos && !empty($rowDos['type_titre'])) {
            $type_titre_brut = $rowDos['type_titre'];
            // Séparation des éléments du type_titre
            $elements_titre = array_map('trim', explode(',', $type_titre_brut));

            $a_echelon_1 = false;
            $a_autres_echelons = false;

            foreach ($elements_titre as $el) {
                if (mb_strpos($el, '1°ECHELON') !== false) {
                    $a_echelon_1 = true;
                } else {
                    $a_autres_echelons = true;
                }
            }

            if ($a_echelon_1 && $a_autres_echelons) {
                $is_classe_avec_autre_echelon = true;
            }
        }
    }

    switch ($mode) {
        case 'btn':
            $templatePath = APP_ROOT . '/pieces/rectoVerso/BIN.docx';
            $prefix_file  = "BIN_";
            break;

        case 'pavenant':
            $templatePath = APP_ROOT . '/pieces/Projet/projet_avenant.docx';
            $prefix_file  = "Projet_Avenant_";
            break;

        case 'parrete':
            if ($etape_classe_decision !== null) {
                if ($is_classe_avec_autre_echelon) {
                    $nom_fichier_arrete = 'projet_arrete_classe_avec_autre_echelon.docx';
                } else {
                    $nom_fichier_arrete = 'projet_arrete_classe_sans_autre_echelon.docx';
                }
            } else {
                $nom_fichier_arrete = 'projet_arrete_echelon.docx';
            }
            
            $templatePath = APP_ROOT . '/pieces/Projet/' . $nom_fichier_arrete;
            $prefix_file  = "Projet_Arrete_";
            break;

        case 'pdecision':
            $templatePath = APP_ROOT . '/pieces/Projet/projet_decision.docx';
            $prefix_file  = "Projet_Decision_";
            break;

        case 'pvcap':
            $templatePath = APP_ROOT . '/pieces/Projet/pv_cap.docx';
            $prefix_file  = "PV_CAP_";
            break;

        default:
            $templatePath = APP_ROOT . '/pieces/Avancement_et_avenant/' . $nom_fichier_defaut;
            $prefix_file  = ($is_fonctionnaire ? "Avancement_" : "Avenant_");
            break;
    }

    if (!file_exists($templatePath)) {
        erreur_gabarit_absent($templatePath);
    }
    $template = new TemplateProcessor($templatePath);
    
    $texte_arrete_classe = '';
    $texte_arrete_echelon = '';
    $texte_decision = '';

    // 6. Récupération de la Décision et l'arrêté
    if ($corps_id && $nom_region) {
        if ($type_avancement_mode === 'avec_classe') {
            $sqlArrete = "SELECT texte FROM configuration_arrete 
                        WHERE region = :region 
                            AND corps_id = :corps_id 
                            AND type_avancement = 'classe' 
                        LIMIT 1";
            $stmtArrete = $pdo->prepare($sqlArrete);
            $stmtArrete->execute([':region' => $nom_region, ':corps_id' => $corps_id]);
            $texte_arrete_classe = $stmtArrete->fetchColumn() ?: '';

            $sqlDecision = "SELECT texte FROM configuration_decision 
                            WHERE region = :region 
                            AND corps_id = :corps_id 
                            LIMIT 1";
            $stmtDecision = $pdo->prepare($sqlDecision);
            $stmtDecision->execute([':region' => $nom_region, ':corps_id' => $corps_id]);
            $texte_decision = $stmtDecision->fetchColumn() ?: '';

        } else { 
            $sqlArrete = "SELECT texte FROM configuration_arrete 
                        WHERE region = :region 
                            AND corps_id = :corps_id 
                            AND type_avancement = 'echelon' 
                        LIMIT 1";
            $stmtArrete = $pdo->prepare($sqlArrete);
            $stmtArrete->execute([':region' => $nom_region, ':corps_id' => $corps_id]);
            $texte_arrete_echelon = $stmtArrete->fetchColumn() ?: '';
        }
    }
    $template->setValue('texte_arrete_classe', $texte_arrete_classe);
    $template->setValue('texte_arrete_echelon', $texte_arrete_echelon);
    $template->setValue('texte_decision', $texte_decision);

    // Variables de mise en forme et localisation
    $br = '</w:t><w:br/><w:t>';
    $br_word = '</w:t><w:br/><w:t>';
    $logoPath = APP_ROOT . '/Logo/Embleme.png';
    $sexe = $agent['sexe'];
    $lieu_signature = ucfirst(mb_strtolower($PosteActuel['nom_district'] ?? ''));
    $type_direction = $PosteActuel['type_direction'];
    $type_fonction = $PosteActuel['type_fonction'] ?? '';
    $nom_direction = $PosteActuel['nom_direction'];
    $type_etablissement = $PosteActuel['type_etablissement'] ?? '';
    $nom_region = $PosteActuel['nom_region'] ?? '';
    $nom_district = $PosteActuel['nom_district'] ?? '';
    $nom_etablissement = $PosteActuel['nom_etablissement'] ?? '';
    $nom_zap = $PosteActuel['nom_zap'] ?? '';

    // Détermination de la fonction et préfixe
    $label_fonction = "";
    $label_au_a_la = "au "; 

    if ($type_fonction == "Personnel administratif") {
        $label_fonction = "PERSONNEL ADMINISTRATIF";
    } else if ($type_fonction == "Personnel enseignant") {
        if ($sexe == "Masculin") {
            $template->setValue('nee_le', "Né le");
            $template->setValue('interessee', "L'intéressé");
            $label_fonction = ($type_etablissement == "PRESCOLAIRE") ? "EDUCATEUR" : "ENSEIGNANT";
            $label_au_a_la = (in_array($type_etablissement, ["PRESCOLAIRE", "PRIMAIRE"])) ? "à l'" : "au ";
        } else {
            $template->setValue('nee_le', "Née le");
            $template->setValue('interessee', "L'intéressée");
            $label_fonction = ($type_etablissement == "PRESCOLAIRE") ? "EDUCATRICE" : "ENSEIGNANTE";
            $label_au_a_la = (in_array($type_etablissement, ["PRESCOLAIRE", "PRIMAIRE"])) ? "à l'" : "au ";
        }
    }
    if ($type_etablissement == "CRFRP") { $label_fonction = "FORMATEUR"; $label_au_a_la = "au "; }

    if ($type_fonction == "Personnel administratif" && $sexe == "Masculin"){
        $template->setValue('nee_le', "Né le");
        $template->setValue('interessee', "L'intéressé");
    } else if ($type_fonction == "Personnel administratif" && $sexe == "Féminin"){
        $template->setValue('nee_le', "Née le");
        $template->setValue('interessee', "L'intéressée");
    }

    $ville = 'Mananjary'; 
    if (!empty($nom_region)) {
        $stmt = $pdo->prepare("SELECT chef_lieu_region FROM ref_regions WHERE nom_region = :nom_region LIMIT 1");
        $stmt->execute(['nom_region' => $nom_region]);
        $region_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($region_data && !empty($region_data['chef_lieu_region'])) {
            $ville = $region_data['chef_lieu_region'];
        }
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

    $premiere_lettre = mb_substr(ltrim($chef_lieu_region), 0, 1, 'UTF-8');
    $est_voyelle = preg_match('/^[AEIOUYÀÁÂÃÄÅÆÈÉÊËÌÍÎÏÒÓÔÕÖØÙÚÛÜ]/ui', $premiere_lettre);

    $prefecture_val = $est_voyelle ? "PREFECTURE D'" . mb_strtoupper($chef_lieu_region, 'UTF-8') : "PREFECTURE DE " . mb_strtoupper($chef_lieu_region, 'UTF-8');
    $adresser_a_val = $est_voyelle ? "LE PREFET D'" : "LE PREFET DE ";
    $chef_lieu_region_val = mb_strtoupper($chef_lieu_region, 'UTF-8');

    if ($type_etablissement =="MEN CENTRAL") {
        $template->setValue('type_direction', "DIRECTION DES RESSOURCES HUMAINES");
        $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION ADMINISTRATIVE DU PERSONNEL");
        $template->setValue('chef_signataire', "Chef de Service de la Gestion Administrative du Personnel, de la Direction des Ressources Humaines");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au");
        $template->setValue('en_service', $nom_direction);
        $template->setValue('lieu_signature', 'Antananarivo');
        $template->setValue('prefecture', '');
        $template->setValue('adresser_a', 'LE DIRECTEUR DES RESSOURCES HUMAINES');
        $template->setValue('chef_lieu_region', '');
        $template->setValue('lieu_destinataire', '= ANTANANARIVO =');
        $template->setValue('titre_signataire', 'Le Directeur');
        $template->setValue('lieu_signataire', 'Antananarivo');
        $template->setValue('region', 'ANTANANARIVO');
    } else if ($type_etablissement =="DREN") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
        $template->setValue('chef_signataire', "Chef de Service de la Gestion des Ressources Humaines, de la Direction Régionale de l’Education Nationale de ");
        $template->setValue('nom_region', ucfirst(mb_strtolower($PosteActuel['nom_region'] ?? '')));
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au");
        $template->setValue('en_service', "BUREAU DREN ".$nom_region);
        $template->setValue('lieu_destinataire', '');
        $template->setValue('titre_signataire', 'Le Préfet');
        $template->setValue('lieu_signature', $chef_lieu_region); 
        $template->setValue('prefecture', $prefecture_val);
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('lieu_signataire', $chef_lieu_region);
        $template->setValue('region', $nom_region);
    } else if ($type_etablissement =="CISCO") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $chef_lieu_district);
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', "BUREAU CISCO ".$nom_district);
        $template->setValue('lieu_destinataire', '');
        $template->setValue('titre_signataire', 'Le Préfet');
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('prefecture', $prefecture_val);
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('lieu_signataire', $chef_lieu_region);
        $template->setValue('region', $nom_region);
    } else if ($type_etablissement =="CRFRP") {
        $template->setValue('type_direction', "DIRECTION GENERALE DE L’INSTITUT NATIONAL" .$br. "DE FORMATION PEDAGOGIQUE");
        $template->setValue('sgrh_cisco_crfrp', "CENTRE REGIONAL DE FORMATION" .$br. "ET DE RECHERCHE PEDAGOGIQUE ".$nom_crfrp);
        $template->setValue('chef_signataire', "Chef de Centre  Régional de Formation et de Recherche Pédagogique de ".ucfirst(mb_strtolower($nom_crfrp, 'UTF-8')));
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', '');
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', $nom_etablissement);
        $template->setValue('lieu_destinataire', '');
        $template->setValue('titre_signataire', 'Le Préfet');
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('prefecture', $prefecture_val);
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('lieu_signataire', $chef_lieu_region);
        $template->setValue('region', $nom_region);
    } else if ($type_etablissement == "LYCEE" || $type_etablissement == "COLLEGE") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $chef_lieu_district);
        $template->setValue('au_a_la', "au ");
        $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
        $template->setValue('lieu_destinataire', '');
        $template->setValue('titre_signataire', 'Le Préfet');
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('prefecture', $prefecture_val);
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('lieu_signataire', $chef_lieu_region);
        $template->setValue('region', $nom_region);
    } else if ($type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" .$br. $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " .$nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $chef_lieu_district);
        $template->setValue('au_a_la', "à l'");
        $template->setValue('en_service', $nom_etablissement." - ZAP ".$nom_zap);
        $template->setValue('lieu_destinataire', '');
        $template->setValue('titre_signataire', 'Le Préfet');
        $template->setValue('lieu_signature', $chef_lieu_district); 
        $template->setValue('prefecture', $prefecture_val);
        $template->setValue('adresser_a', $adresser_a_val);
        $template->setValue('chef_lieu_region', $chef_lieu_region_val);
        $template->setValue('lieu_signataire', $chef_lieu_region);
        $template->setValue('region', $nom_region);
    }

    $genre_input = $_GET['genre'] ?? 'Mr';
    $genre_maj = ($genre_input === 'Mme') ? 'MADAME' : 'MONSIEUR';
    $genre_min = ($genre_input === 'Mme') ? 'Madame' : 'Monsieur';
    $template->setValue('genre_min', $genre_min);
    $template->setValue('genre_maj', $genre_maj);

    $date_du_jour = (new DateTime())->format('d/m/Y');
    $template->setValue('date_du_jour', $date_du_jour);
    $template->setValue('nom', htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
    $template->setValue('im', $agent['im']);
    $template->setValue('corps_actuel', $agent['corps_actuel']);
    $template->setValue('grade_actuel', $agent['grade_actuel']);
    $template->setValue('indice_actuel', $agent['indice_actuel']);
    $template->setValue('categorie', $agent['categorie_actuel'] ?? '');
    $template->setValue('chapitre', $agent['chap_budg'] ?? '');
    $template->setValue('imputation', $agent['imput_budg']);
    $template->setValue('fonction', $label_fonction);
    $template->setValue('au_a_la', $label_au_a_la);
    $template->setValue('en_service', $en_service ?? '');
    $template->setValue('date_naiss', date('d/m/Y', strtotime($agent['date_naiss'])));
    $template->setValue('lieu_naiss', $agent['lieu_naiss']);
    $template->setValue('cin', $agent['cin']);
    $template->setValue('date_cin', date('d/m/Y', strtotime($agent['date_cin'])));
    $template->setValue('date_entree_admin', date('d/m/Y', strtotime($agent['date_entree_admin'])));
    $template->setValue('lieu_cin', $agent['lieu_cin']);
    $template->setValue('name_region', $nom_region);
    $annee = date('Y');
    $formatter = new NumberFormatter("fr", NumberFormatter::SPELLOUT);
    $annee_lettres = $formatter->format($annee);
    $template->setValue('annee_chiffre', date('Y'));
    $template->setValue('annee_lettre', $annee_lettres);
    $template->setValue('nom_region', $nom_region);

    $template->setValue('nom_membre', $nom_membre);
    $template->setValue('im_membre', $im_membre);
    $template->setValue('nom_rapporteur', $nom_rapporteur);
    $template->setValue('im_rapporteur', $im_rapporteur);

    // 7. Traitement spécifique selon le statut
    if ($is_fonctionnaire) {
        // A. DECISION (Haut de page)
        if ($etape_classe_decision) {
            $grade_dec = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)"], "", $etape_classe_decision['titre']));
            $template->setValue('dec_grade', htmlspecialchars($grade_dec));
            $template->setValue('dec_date', date('d/m/Y', strtotime($etape_classe_decision['date_reception_technique'])));
        }

        // B. Récupération du numéro de dossier
        $types_possibles = ['Avancement', 'Avancement_echelon', 'Avancement_classe'];
        $in = str_repeat('?,', count($types_possibles) - 1) . '?';
        $stmt = $pdo->prepare("SELECT numero_dos FROM demandes_numeros_dos WHERE im = ? AND type_dos IN ($in) AND statut = 'ATTRIBUE' LIMIT 1");
        $params = array_merge([$im], $types_possibles);
        $stmt->execute($params);

        $result = $stmt->fetch();
        $numero_dos = $result ? $result['numero_dos'] : '';
        $template->setValue('numero_dos', $numero_dos);

        // C. Remplissage des tableaux selon le type de fichier arrêté sélectionné
        if ($mode === 'parrete' && $is_classe_avec_autre_echelon) {
            // Fichier: projet_arrete_classe_avec_autre_echelon.docx
            // Séparation en deux tableaux
            $liste_grades_1 = [];
            $liste_dates_1  = [];

            $liste_grades_2 = [];
            $liste_dates_2  = [];

            foreach ($etapes_retard as $etape) {
                $g_nom  = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)", "(ECHELON)"], "", $etape['titre']));
                $g_date = date('d/m/Y', strtotime($etape['date_reception_technique']));

                $g_comparaison = str_replace(' ', '', $g_nom);
                if (in_array($g_comparaison, $grades_cibles)) {
                    // Premier tableau (1° ECHELON seulement)
                    $liste_grades_1[] = htmlspecialchars($g_nom);
                    $liste_dates_1[]  = $g_date;
                } else {
                    // Deuxième tableau (Le reste des échelons/grades)
                    $liste_grades_2[] = htmlspecialchars($g_nom);
                    $liste_dates_2[]  = $g_date;
                }
            }

            // Mappage Premier Tableau
            $template->setValue('grade_liste', implode($br_word, $liste_grades_1));
            $template->setValue('date_liste', implode($br_word, $liste_dates_1));

            // Mappage Deuxième Tableau
            $template->setValue('grade_liste_2', implode($br_word, $liste_grades_2));
            $template->setValue('date_liste_2', implode($br_word, $liste_dates_2));

        } else {
            // Traitement standard (Un seul tableau / projet_arrete_classe_sans_autre_echelon.docx ou autre)
            $liste_grades = [];
            $liste_dates  = [];

            foreach ($etapes_retard as $etape) {
                $g_nom  = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)", "(ECHELON)"], "", $etape['titre']));
                $g_date = date('d/m/Y', strtotime($etape['date_reception_technique']));

                $liste_grades[] = htmlspecialchars($g_nom);
                $liste_dates[]  = $g_date;
            }

            $template->setValue('grade_liste', implode($br_word, $liste_grades));
            $template->setValue('date_liste', implode($br_word, $liste_dates));
        }

        // Gestion logo
        if (file_exists($logoPath)) {
            $template->setImageValue('embleme', ['path' => $logoPath, 'width' => 100, 'height' => 50, 'ratio' => true]);
        }
    } else {
        // CAS CONTRACTUEL
        if ($etape_classe_decision) {
            $grade_dec = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)"], "", $etape_classe_decision['titre']));
            $template->setValue('dec_grade', htmlspecialchars($grade_dec));
            $template->setValue('dec_date', date('d/m/Y', strtotime($etape_classe_decision['date_reception_technique'])));
        }
        $liste_grades = "";
        $liste_dates = "";

        foreach ($etapes_retard as $index => $etape) {
            $g_nom = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)", "(ECHELON)"], "", $etape['titre']));
            $g_date = date('d/m/Y', strtotime($etape['date_reception_technique']));
            
            if ($index > 0) {
                $liste_grades .= $br_word;
                $liste_dates .= $br_word;
            }
            $liste_grades .= htmlspecialchars($g_nom);
            $liste_dates .= $g_date;
        }

        $template->setValue('grade_liste', $liste_grades);
        $template->setValue('date_liste', $liste_dates);

        $template->cloneBlock('projet_block', $nb_projets, true, true);
        $current_grade = $agent['grade_actuel'];
        $current_indice = $agent['indice_actuel'];

        foreach ($etapes_retard as $index => $etape) {
            $n = $index + 1;
            $type_dos_genere = "avenant" . $n;

            if (file_exists($logoPath)) {
                $template->setImageValue('embleme_p#' . $n, ['path' => $logoPath, 'width' => 100, 'height' => 50, 'ratio' => true]);
            }

            $new_grade = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)", "(ECHELON)"], "", $etape['titre']));
            $stI = $pdo->prepare("SELECT rgi.indice FROM ref_grille_indiciaire rgi 
                                JOIN ref_grades_types rg ON rgi.grade_type_id = rg.id 
                                JOIN ref_corps rc ON rgi.corps_id = rc.id 
                                WHERE rc.libelle_corps = ? AND rg.libelle_grade = ?");
            $stI->execute([$agent['corps_actuel'], $new_grade]);
            $new_indice = $stI->fetchColumn() ?: '....';

            $id_alerte_brut = $etape['alerte_id'];

            $sqlDos = "SELECT numero_dos 
                    FROM demandes_numeros_dos 
                    WHERE im = ? 
                    AND (alerte_id = ? OR alerte_id = ?) 
                    AND statut IN ('VALIDE', 'ATTRIBUE') 
                    LIMIT 1";

            $stmtDos = $pdo->prepare($sqlDos);

            $id_sans_hidden = str_replace('HIDDEN_', '', $id_alerte_brut);
            $stmtDos->execute([$im, $id_alerte_brut, $id_sans_hidden]);

            $num_dos = $stmtDos->fetchColumn();

            if (!$num_dos) {
                $stmtFallback = $pdo->prepare("SELECT numero_dos FROM demandes_numeros_dos 
                                            WHERE im = ? AND type_dos = ? 
                                            ORDER BY id DESC LIMIT 1");
                $stmtFallback->execute([$im, $type_dos_genere]);
                $num_dos = $stmtFallback->fetchColumn();
            }

            $valeur_finale = ($num_dos) ? $num_dos : "";
            $template->setValue('numero_dos_p#' . $n, $valeur_finale);
            $template->setValue('old_grade_p#' . $n, $current_grade);
            $template->setValue('old_indice_p#' . $n, $current_indice);
            $template->setValue('new_grade_p#' . $n, $new_grade);
            $template->setValue('new_indice_p#' . $n, $new_indice);
            $template->setValue('new_date_p#' . $n, date('d/m/Y', strtotime($etape['date_reception_technique'])));
            $template->setValue('nom_p#' . $n, htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
            $template->setValue('im_p#' . $n, $agent['im']);

            $nom_direction = $PosteActuel['nom_direction'] ?? ''; 

            if ($type_etablissement == "MEN CENTRAL") {
                $template->setValue('en_service_p#' . $n, $nom_direction);
                $template->setValue('lieu_signataire_p#' . $n, 'Antananarivo');
            } else if ($type_etablissement == "DREN") {
                $template->setValue('en_service_p#' . $n, "BUREAU DREN " . $nom_region);
                $template->setValue('lieu_signataire_p#' . $n, $chef_lieu_region);
            } else if ($type_etablissement == "CISCO") {
                $template->setValue('en_service_p#' . $n, "BUREAU CISCO " . $nom_district);
                $template->setValue('lieu_signataire_p#' . $n, $chef_lieu_region);
            } else if ($type_etablissement == "CRFRP") {
                $template->setValue('en_service_p#' . $n, $nom_etablissement);
                $template->setValue('lieu_signataire_p#' . $n, $chef_lieu_region);
            } else if ($type_etablissement == "LYCEE" || $type_etablissement == "COLLEGE") {
                $template->setValue('en_service_p#' . $n, $nom_etablissement . " - ZAP " . $nom_zap);
                $template->setValue('lieu_signataire_p#' . $n, $chef_lieu_region);
            } else if ($type_etablissement == "PRIMAIRE" || $type_etablissement == "PRESCOLAIRE") {
                $template->setValue('en_service_p#' . $n, $nom_etablissement . " - ZAP " . $nom_zap);                
                $template->setValue('lieu_signataire_p#' . $n, $chef_lieu_region);
            }

            $template->setValue('prefecture_p#' . $n, $prefecture_val);
            $label_fonction = "";
            $label_au_a_la = "au "; 

            $stmtAv = $pdo->prepare("SELECT av_acte_no, av_acte_date 
                         FROM personnel_avancements 
                         WHERE im = ? AND LOWER(TRIM(duree)) = 'indeterminee' 
                         ORDER BY id DESC 
                         LIMIT 1");
            $stmtAv->execute([$im]);
            $contrat3 = $stmtAv->fetch(PDO::FETCH_ASSOC);
            $num_3emeContrat = $contrat3['av_acte_no'] ?? '';
            $date_brute = $contrat3['av_acte_date'] ?? null;
            $date_a_afficher = ($date_brute) ? date('d/m/Y', strtotime($date_brute)) : '';

            $template->setValue('old_acte_p#' . $n, $num_3emeContrat);
            $template->setValue('old_date_acte_p#' . $n, $date_a_afficher);
            
            if ($type_fonction == "Personnel enseignant" && $type_etablissement == "CRFRP") {
                $label_au_a_la = "au ";
                $label_fonction = "FORMATEUR"; 
            } else if ($type_fonction == "Personnel administratif") {
                $label_fonction = "PERSONNEL ADMINISTRATIF";
                $label_au_a_la = "au ";
            } else if ($type_fonction == "Personnel enseignant") {
                if ($sexe == "Masculin") {
                    if ($type_etablissement == "PRESCOLAIRE") {
                        $label_fonction = "EDUCATEUR";
                        $label_au_a_la = "à l'";
                    } else if ($type_etablissement == "PRIMAIRE") {
                        $label_fonction = "ENSEIGNANT";
                        $label_au_a_la = "à l'";
                    } else {
                        $label_fonction = "ENSEIGNANT";
                        $label_au_a_la = "au ";
                    }
                } else {
                    if ($type_etablissement == "PRESCOLAIRE") {
                        $label_fonction = "EDUCATRICE";
                        $label_au_a_la = "à l'";
                    } else if ($type_etablissement == "PRIMAIRE") {
                        $label_fonction = "ENSEIGNANTE";
                        $label_au_a_la = "à l'";
                    } else {
                        $label_fonction = "ENSEIGNANTE";
                        $label_au_a_la = "au ";
                    }
                }
            }

            $template->setValue('fonction_p#' . $n, $label_fonction);
            $template->setValue('au_a_la_p#' . $n, $label_au_a_la);

            $cat = $agent['categorie_actuel'] ?? '';
            $texte_assim = (in_array($cat, ['IV', 'V', 'VI', 'VIII'])) ? "CONT. DE LA CAT. " . $cat.", " : "AUXILIAIRE AUX ";
            $template->setValue('assimilation_p#' . $n, $texte_assim);
            $template->setValue('imput_p#' . $n, $agent['imput_budg']);
            $template->setValue('corps_p#' . $n, $agent['corps_actuel']);

            $current_grade = $new_grade;
            $current_indice = $new_indice;
        }
    }

    $outputName = $prefix_file . $im . ".docx";

    if (ob_get_length()) ob_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $outputName . '"');
    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
use PhpOffice\PhpWord\TemplateProcessor;

// Récupération et validation des paramètres
$im = $_GET['im'] ?? null;
if (!$im) {
    die("IM (Matricule) manquant.");
}

$type_projet = $_GET['type_projet'] ?? ($_GET['type'] ?? 'avancement');
$mode = $_GET['mode'] ?? 'projet'; // 'avenant' ou 'projet' / 'complet'

try {
    // ==========================================
    // 1. RÉCUPÉRATION DES DONNÉES DE BASE (AGENT)
    // ==========================================
    $stmt = $pdo->prepare("SELECT ec.*, sa.* FROM personnel_etat_civil ec 
                           JOIN personnel_situation_actuelle sa ON ec.im = sa.im 
                           WHERE ec.im = ?");
    $stmt->execute([$im]);
    $agent = $stmt->fetch();

    if (!$agent) {
        die("Agent introuvable ou situation non renseignée.");
    }

    $stmtPosteActuel = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
    $stmtPosteActuel->execute([$im]);
    $PosteActuel = $stmtPosteActuel->fetch(PDO::FETCH_ASSOC);

    // ==========================================
    // 2. LOGIQUE ET VARIABLES COMMUNES
    // ==========================================
    $br = '</w:t><w:br/><w:t>';
    $logoPath = APP_ROOT . '/Logo/Embleme.png';
    $logoMenPath = APP_ROOT . '/Logo/Logo_men.png';
    
    $sexe = $agent['sexe'] ?? '';
    $situation_familiale = $agent['situation_familiale'] ?? '';
    $nom_region = $PosteActuel['nom_region'] ?? '';
    $nom_district = $PosteActuel['nom_district'] ?? '';
    $nom_etablissement = $PosteActuel['nom_etablissement'] ?? '';
    $nom_zap = $PosteActuel['nom_zap'] ?? '';
    $type_fonction = $PosteActuel['type_fonction'] ?? '';
    $type_etablissement = $PosteActuel['type_etablissement'] ?? '';
    $type_direction = $PosteActuel['type_direction'] ?? '';

    // Détermination genre
    $genre_input = $_GET['genre'] ?? 'Mr';
    $genre_maj = ($genre_input === 'Mme') ? 'MADAME' : 'MONSIEUR';
    $genre_min = ($genre_input === 'Mme') ? 'Madame' : 'Monsieur';

    // Civilités contractuelles
    $mr_mme_mlle = "Mr ";
    $sit_familiale = "CELIBATAIRE";
    if ($sexe == "Masculin") {
        $mr_mme_mlle = "Mr ";
        $sit_familiale = ($situation_familiale == "Marié(e)") ? "MARIE" : "CELIBATAIRE";
    } else if ($sexe == "Féminin") {
        if ($situation_familiale == "Marié(e)") {
            $mr_mme_mlle = "Mme ";
            $sit_familiale = "MARIEE";
        } else {
            $mr_mme_mlle = "Mlle ";
            $sit_familiale = "CELIBATAIRE";
        }
    }

    // Nettoyage et formatage géographique
    $nom_district_clean = trim($nom_district ?? '');
    $nom_district_clean = preg_replace('/\s+[\dIVX]+\s*$/i', '', $nom_district_clean);
    $nom_lower = mb_strtolower($nom_district_clean, 'UTF-8');        
    $nom_lower = preg_replace_callback('/\s+(i{1,3}|iv|v?ii?)\b/i', function($matches) {
        return ' ' . strtoupper($matches[1]);
    }, $nom_lower);
    $lieu_signature = ucfirst($nom_lower);

    $ville = 'Mananjary'; 
    if (!empty($nom_region)) {
        $stmt_reg = $pdo->prepare("SELECT chef_lieu_region FROM ref_regions WHERE nom_region = :nom_region LIMIT 1");
        $stmt_reg->execute(['nom_region' => $nom_region]);
        $region_data = $stmt_reg->fetch(PDO::FETCH_ASSOC);
        if ($region_data && !empty($region_data['chef_lieu_region'])) {
            $ville = $region_data['chef_lieu_region'];
        }
    }

    $nom_crfrp_propre = $PosteActuel['nom_etablissement'] ?? '';
    if (stripos($nom_crfrp_propre, 'CRFRP') !== false) {
        $nom_crfrp_propre = trim(preg_replace('/^CRFRP\s+/i', '', $nom_crfrp_propre));
    }
    $nom_crfrp = $nom_crfrp_propre;

    $code_corps_officiel = $agent['code_corps_actuel'] ?? '';
    $code_corps_prefix = strtoupper(substr($code_corps_officiel, 0, 1));

    // ==========================================
    // 3. SÉLECTION DU TEMPLATE ET ROUTAGE DES LOGIQUES
    // ==========================================
    $templateFile = '';
    $prefix_file = '';

    if (strtoupper($type_projet) === 'RNC1') {
        if ($mode === 'avenant' && $code_corps_prefix === 'J') {
            $templateFile = APP_ROOT . '/pieces/Contrat_avenant/contrat_avec_avenant_RNC1.docx';
            $prefix_file = "Contrat_RNC1_";
        } else {
            $templateFile = APP_ROOT . '/pieces/rectoVerso/projet_contrat_RNC1.docx';
            $prefix_file = "Projet_Contrat_RNC1_";
        }
    } 
    elseif (strtoupper($type_projet) === 'RNC2') {
        if ($mode === 'avenant' && $code_corps_prefix === 'J') {
            $templateFile = APP_ROOT . '/pieces/Contrat_avenant/contrat_avec_avenant_RNC2.docx';
            $prefix_file = "Contrat_RNC2_";
        } else {
            $templateFile = APP_ROOT . '/pieces/rectoVerso/projet_contrat_RNC2.docx';
            $prefix_file = "Projet_Contrat_RNC2_";
        }
    } 
    elseif (strtoupper($type_projet) === 'AVANCEMENT') {
        $is_fonctionnaire = ($agent['statut_actuel'] === 'Fonctionnaire' && $code_corps_prefix !== 'U');
        
        $stmtAlert = $pdo->prepare("SELECT titre FROM v_moteur_alertes WHERE im = ? AND alerte_id = ?");
        $stmtAlert->execute([$im, $_GET['alerte'] ?? '']);
        $alerte_titre = $stmtAlert->fetchColumn() ?: '';
        
        $implique_classe = (stripos($alerte_titre, 'CLASSE') !== false);
        $suffixe = $implique_classe ? "_avec_classe.docx" : "_sans_classe.docx";
        
        $templateFile = APP_ROOT . '/pieces/Avancement_et_avenant/' . ($is_fonctionnaire ? "Avancement" : "Avenant") . $suffixe;
        $prefix_file = "Projet_" . ($is_fonctionnaire ? "Avancement_" : "Avenant_");
    } 
    elseif (strtoupper($type_projet) === 'TITULARISATION') {
        $templateFile = APP_ROOT . '/pieces/Titularisation/Titularisation.docx';
        $prefix_file = "Titularisation_";
    }

    if (empty($templateFile) || !file_exists($templateFile)) {
        die("Erreur : Fichier template introuvable ou non défini pour ce type de projet.");
    }

    $template = new TemplateProcessor($templateFile);

    // ==========================================
    // 4. ALIMENTATION DES LOGIQUES SPÉCIFIQUES
    // ==========================================
    
    // CAS : AVANCEMENT / AVENANT CASCADE
    if (strtoupper($type_projet) === 'AVANCEMENT') {
        $stmtAlerts = $pdo->prepare("SELECT * FROM v_moteur_alertes 
                                     WHERE im = ? 
                                     AND alerte_id LIKE 'HIDDEN_%_AVANCEMENT_%' 
                                     ORDER BY date_reception_technique ASC");
        $stmtAlerts->execute([$im]);
        $etapes_retard = $stmtAlerts->fetchAll(PDO::FETCH_ASSOC);
        $nb_projets = count($etapes_retard);

        if ($nb_projets === 0) {
            die("Aucun retard ou avancement trouvé.");
        }

        // Détection d'un passage à la classe supérieure
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

        $liste_grades = ""; $liste_dates = "";
        foreach ($etapes_retard as $index => $etape) {
            $g_nom = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)", "(ECHELON)"], "", $etape['titre']));
            $g_date = date('d/m/Y', strtotime($etape['date_reception_technique']));
            if ($index > 0) {
                $liste_grades .= $br;
                $liste_dates .= $br;
            }
            $liste_grades .= htmlspecialchars($g_nom);
            $liste_dates .= $g_date;
        }

        if ($is_fonctionnaire) {
            if ($etape_classe_decision) {
                $grade_dec = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)"], "", $etape_classe_decision['titre']));
                $template->setValue('dec_grade', htmlspecialchars($grade_dec));
                $template->setValue('dec_date', date('d/m/Y', strtotime($etape_classe_decision['date_reception_technique'])));
            }

            $types_possibles = ['Avancement', 'Avancement_echelon', 'Avancement_classe'];
            $in = str_repeat('?,', count($types_possibles) - 1) . '?';
            $stmtDos = $pdo->prepare("SELECT numero_dos FROM demandes_numeros_dos WHERE im = ? AND type_dos IN ($in) AND statut = 'ATTRIBUE' LIMIT 1");
            $params = array_merge([$im], $types_possibles);
            $stmtDos->execute($params);
            $result = $stmtDos->fetch();
            $numero_dos = $result ? $result['numero_dos'] : 'NON ATTRIBUÉ';
            
            $template->setValue('numero_dos', $numero_dos);
            $template->setValue('grade_liste', $liste_grades);
            $template->setValue('date_liste', $liste_dates);
        } else {
            // Logique de cascade / clone de pages pour avenant contractuel
            if ($etape_classe_decision) {
                $grade_dec = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)"], "", $etape_classe_decision['titre']));
                $template->setValue('dec_grade', htmlspecialchars($grade_dec));
                $template->setValue('dec_date', date('d/m/Y', strtotime($etape_classe_decision['date_reception_technique'])));
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

                // Récupération de l'indice cible
                $new_grade = trim(str_replace(["Demande d’avancement", "(CLASSE et ECHELON)", "(ECHELON)"], "", $etape['titre']));
                $stI = $pdo->prepare("SELECT rgi.indice FROM ref_grille_indiciaire rgi 
                                    JOIN ref_grades_types rg ON rgi.grade_type_id = rg.id 
                                    JOIN ref_corps rc ON rgi.corps_id = rc.id 
                                    WHERE rc.libelle_corps = ? AND rg.libelle_grade = ?");
                $stI->execute([$agent['corps_actuel'], $new_grade]);
                $new_indice = $stI->fetchColumn() ?: '....';

                // Gestion dossier
                $id_alerte_brut = $etape['alerte_id'];
                $sqlDos = "SELECT numero_dos FROM demandes_numeros_dos 
                           WHERE im = ? AND (alerte_id = ? OR alerte_id = ?) AND statut IN ('VALIDE', 'ATTRIBUE') LIMIT 1";
                $stmtDos = $pdo->prepare($sqlDos);
                $id_sans_hidden = str_replace('HIDDEN_', '', $id_alerte_brut);
                $stmtDos->execute([$im, $id_alerte_brut, $id_sans_hidden]);
                $num_dos = $stmtDos->fetchColumn();

                if (!$num_dos) {
                    $stmtFallback = $pdo->prepare("SELECT numero_dos FROM demandes_numeros_dos 
                                                WHERE im = ? AND type_dos = ? ORDER BY id DESC LIMIT 1");
                    $stmtFallback->execute([$im, $type_dos_genere]);
                    $num_dos = $stmtFallback->fetchColumn();
                }

                $template->setValue('numero_dos_p#' . $n, $num_dos ?: "");
                $template->setValue('old_grade_p#' . $n, $current_grade);
                $template->setValue('old_indice_p#' . $n, $current_indice);
                $template->setValue('new_grade_p#' . $n, $new_grade);
                $template->setValue('new_indice_p#' . $n, $new_indice);
                $template->setValue('new_date_p#' . $n, date('d/m/Y', strtotime($etape['date_reception_technique'])));
                $template->setValue('nom_p#' . $n, htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
                $template->setValue('im_p#' . $n, $agent['im']);

                // Affection par page
                if ($type_direction == "DREN" && $type_fonction =="Personnel administratif" && $type_etablissement =="DREN") {
                    $template->setValue('en_service_p#' . $n, "DREN ".$nom_region);
                } else if ($type_direction == "INFP" && ($type_fonction =="Personnel administratif" || $type_fonction =="Personnel enseignant") && $type_etablissement =="CRFRP") {
                    $template->setValue('en_service_p#' . $n, $nom_etablissement);
                } else if ($type_direction == "DREN" && $type_fonction =="Personnel administratif" && $type_etablissement =="CISCO") {
                    $template->setValue('en_service_p#' . $n, "CISCO ".$nom_district);
                } else if (in_array($type_etablissement, ["LYCEE", "COLLEGE", "PRIMAIRE", "PRESCOLAIRE"])) {
                    $template->setValue('en_service_p#' . $n, $nom_etablissement." - ZAP ".$nom_zap);
                }

                $current_grade = $new_grade;
                $current_indice = $new_indice;
            }
        }
    } 

    // CAS : PROCESSUS RNC1 / RNC2
    elseif (in_array(strtoupper($type_projet), ['RNC1', 'RNC2'])) {
        $stmtDiplome = $pdo->prepare("SELECT libelle FROM personnel_diplomes WHERE im = ? AND type_diplome = 'acad' ORDER BY annee DESC LIMIT 1");
        $stmtDiplome->execute([$im]);
        $diplome_plus_recent = $stmtDiplome->fetchColumn() ?: ''; 
        $template->setValue('diplome_acad', htmlspecialchars($diplome_plus_recent));

        $projet = $_SESSION['dernier_projet_calcule'][0] ?? null;
        $cat = $agent['categorie_actuel'] ?? '';

        if ($projet) {
            if (strtoupper($type_projet) === 'RNC1') {
                $template->setValue('corps_c1', $projet['avant']['corps']);
                $template->setValue('grade_c1', $projet['avant']['grade']);
                $template->setValue('indice_c1', $projet['avant']['indice']);
                $template->setValue('date_effet_c1', date('d/m/Y', strtotime($projet['avant']['date'])));
                $template->setValue('date_fin_c1', date('d/m/Y', strtotime($projet['avant']['date_fin'])));
                $template->setValue('duree_c1', $projet['avant']['duree_label']); 

                $propose = $projet['apres'][0] ?? null;
                if ($propose) {
                    if (isset($propose['date_effet_avenant'])) {
                        $template->setValue('date_effet_av1', date('d/m/Y', strtotime($propose['date_effet_avenant'])));
                        $template->setValue('corps_av1', $propose['corps']);
                        $template->setValue('grade_av1', $propose['grade']);
                        $template->setValue('indice_av1', $propose['indice']);
                    }            
                    $template->setValue('corps_c2', $propose['corps']);
                    $template->setValue('grade_c2', $propose['grade']);
                    $template->setValue('indice_c2', $propose['indice']);
                    $template->setValue('date_effet_c2', date('d/m/Y', strtotime($propose['date'])));
                    $template->setValue('duree_c2', $propose['duree']);
                }
            } else { // RNC2
                $debut_c1 = $agent['date_entree_admin'];
                $default_grade = (in_array($cat, ['II', 'III'])) ? (($cat === 'II') ? "ECHELLE III/1°ECHELON" : "ECHELLE IV/1°ECHELON") : "STAGIAIRE";
                
                $stG = $pdo->prepare("SELECT rg_prev.libelle_grade FROM ref_grades_types rg_curr JOIN ref_grades_types rg_prev ON rg_prev.id = rg_curr.id - 1 WHERE rg_curr.libelle_grade = ?");
                $stG->execute([$agent['grade_actuel']]);
                $grade_c1 = $stG->fetchColumn() ?: $default_grade; 

                $stI = $pdo->prepare("SELECT rgi.indice FROM ref_grille_indiciaire rgi JOIN ref_corps rc ON rgi.corps_id = rc.id JOIN ref_grades_types rg ON rgi.grade_type_id = rg.id WHERE rc.libelle_corps = ? AND rg.libelle_grade = ?");
                $stI->execute([$agent['corps_actuel'], $grade_c1]);
                $indice_c1 = $stI->fetchColumn();

                $template->setValue('date_debut_c1', date('d/m/Y', strtotime($debut_c1)));
                $template->setValue('grade_c1', $grade_c1);
                $template->setValue('indice_c1', $indice_c1);
                $template->setValue('date_effet_c1', date('d/m/Y', strtotime($debut_c1)));

                $debut_c2 = date('Y-m-d', strtotime($debut_c1 . " + 2 years"));
                $fin_c2 = date('Y-m-d', strtotime($debut_c2 . " + 2 years - 1 day"));
                $template->setValue('date_debut_c2', date('d/m/Y', strtotime($debut_c2)));
                $template->setValue('date_fin_c2', date('d/m/Y', strtotime($fin_c2)));
                $template->setValue('corps_c2', $projet['avant']['corps']);
                $template->setValue('grade_c2', $projet['avant']['grade']);
                $template->setValue('indice_c2', $projet['avant']['indice']);
                $template->setValue('date_effet_c2', date('d/m/Y', strtotime($projet['avant']['date'])));
                $template->setValue('date_effet_av1', date('d/m/Y', strtotime(date('Y-m-d', strtotime($projet['avant']['date'] . " - 1 year")))));

                $propose = $projet['apres'][0] ?? null;
                if ($propose) {
                    $template->setValue('corps_c3', $propose['corps']);
                    $template->setValue('grade_c3', $propose['grade']);
                    $template->setValue('indice_c3', $propose['indice']);
                    $template->setValue('date_effet_c3', date('d/m/Y', strtotime($propose['date'])));
                    $template->setValue('date_effet_av2', date('d/m/Y', strtotime($propose['date_effet_avenant'])));
                    $template->setValue('duree_c3', $propose['duree']);
                }
            }
        }

        // Configuration numéros dossiers contractuels
        $prefix_alerte = strtoupper($type_projet);
        $demandes = [
            'cx' => ['alerte' => $im . '_' . $prefix_alerte, 'type' => ($prefix_alerte === 'RNC1' ? 'contrat1' : 'contrat2')],
            'ax' => ['alerte' => $im . '_' . $prefix_alerte, 'type' => ($prefix_alerte === 'RNC1' ? 'avenant1' : 'avenant2')]
        ];
        foreach ($demandes as $key => $critere) {
            $stmtDos = $pdo->prepare("SELECT numero_dos FROM demandes_numeros_dos WHERE im = ? AND alerte_id = ? AND type_dos = ? AND statut = 'ATTRIBUE' LIMIT 1");
            $stmtDos->execute([$im, $critere['alerte'], $critere['type']]);
            $num_dos = $stmtDos->fetchColumn() ?: "";
            $template->setValue('numero_dos_' . ($key === 'cx' ? 'ct' : 'av') . (substr($prefix_alerte, -1)), $num_dos);
        }
    }

    // CAS : TITULARISATION
    elseif (strtoupper($type_projet) === 'TITULARISATION') {
        $stmtDos = $pdo->prepare("SELECT numero_dos FROM demandes_numeros_dos WHERE im = ? AND type_dos = 'Titularisation' AND statut = 'ATTRIBUE' LIMIT 1");
        $stmtDos->execute([$im]);
        $numero_dos = $stmtDos->fetchColumn() ?: 'NON ATTRIBUÉ';
        $template->setValue('numero_dos', $numero_dos);
    }

    // ==========================================
    // 5. REMPLISSAGES DES VARIABLES MUTUELLES ET INJECTIONS
    // ==========================================
    injectEtablissementVariables($template, $type_fonction, $type_etablissement, $nom_region, $nom_district, $nom_crfrp, $nom_etablissement, $nom_zap, $lieu_signature, $ville, $br);
    injectGenreFonctionVariables($template, $sexe, $type_fonction, $type_etablissement);

    if (file_exists($logoPath)) {
        $template->setImageValue('embleme', ['path' => $logoPath, 'width' => 100, 'height' => 50, 'ratio' => true]);
    }
    if (file_exists($logoMenPath)) {
        $template->setImageValue('logo_men', ['path' => $logoMenPath, 'width' => 100, 'height' => 50, 'ratio' => true]);
    }

    // Remplissages des variables de base de l'agent
    $template->setValue('mr_mme_mlle', $mr_mme_mlle);
    $template->setValue('sit_familiale', $sit_familiale);
    $template->setValue('genre_min', $genre_min);
    $template->setValue('genre_maj', $genre_maj);
    $template->setValue('date_du_jour', (new DateTime())->format('d/m/Y'));
    $template->setValue('nom', htmlspecialchars($agent['nom'] . ' ' . $agent['prenoms']));
    $template->setValue('nom_contrat', htmlspecialchars(mb_strtoupper($agent['nom'] ?? '', 'UTF-8')));
    $template->setValue('prenoms_contrat', htmlspecialchars(mb_strtoupper($agent['prenoms'] ?? '', 'UTF-8')));
    $template->setValue('im', $agent['im']);
    $template->setValue('corps_actuel', $agent['corps_actuel']);
    $template->setValue('grade_actuel', $agent['grade_actuel']);
    $template->setValue('indice_actuel', $agent['indice_actuel']);
    $template->setValue('categorie', $agent['categorie_actuel'] ?? '');
    $template->setValue('chapitre', $agent['chap_budg'] ?? '');
    $template->setValue('imputation', $agent['imput_budg']);
    $template->setValue('date_naiss', date('d/m/Y', strtotime($agent['date_naiss'])));
    $template->setValue('lieu_naiss', $agent['lieu_naiss']);
    $template->setValue('cin', $agent['cin']);
    $template->setValue('date_cin', date('d/m/Y', strtotime($agent['date_cin'])));
    $template->setValue('lieu_cin', $agent['lieu_cin']);
    $template->setValue('adresse', $agent['adresse']);
    $template->setValue('date_entree_admin', date('d/m/Y', strtotime($agent['date_entree_admin'])));
    $template->setValue('num_acte_actuel', $agent['num_acte_actuel']);
    $template->setValue('date_acte_actuel', date('d/m/Y', strtotime($agent['date_acte_actuel'])));

    // ==========================================
    // 6. ENVOI DU FICHIER EN TÉLÉCHARGEMENT
    // ==========================================
    $outputName = $prefix_file . $im . ".docx";

    if (ob_get_length()) ob_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $outputName . '"');
    header('Cache-Control: max-age=0');
    $template->saveAs('php://output');
    exit;

} catch (Exception $e) {
    die("Erreur lors de la génération du document Word : " . $e->getMessage());
}

// ==========================================
// FONCTIONS PARTAGÉES (HELPERS)
// ==========================================

function injectEtablissementVariables($template, $type_fonction, $type_etablissement, $nom_region, $nom_district, $nom_crfrp, $nom_etablissement, $nom_zap, $lieu_signature, $ville, $br) {
    if ($type_fonction == "Personnel administratif" && $type_etablissement == "DREN") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" . $br . $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "SERVICE DE LA GESTION DES RESSOURCES HUMAINES");
        $template->setValue('chef_signataire', "Chef de Service de la Gestion des Ressources Humaines, de la Direction Régionale de l’Education Nationale de ");
        $template->setValue('nom_region', ucfirst(mb_strtolower($nom_region ?? '')));
        $template->setValue('nom_district', '');
        $template->setValue('en_service', "DREN " . $nom_region);
        $template->setValue('lieu_signature', ucfirst(mb_strtolower($ville, 'UTF-8')));
    } else if ($type_fonction == "Personnel administratif" && $type_etablissement == "CISCO") {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" . $br . $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " . $nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $lieu_signature);
        $template->setValue('en_service', "CISCO " . $nom_district);
        $template->setValue('lieu_signature', $lieu_signature);
    } else if (($type_fonction == "Personnel administratif" || $type_fonction == "Personnel enseignant") && $type_etablissement == "CRFRP") {
        $template->setValue('type_direction', "DIRECTION GENERALE DE L’INSTITUT NATIONAL" . $br . "DE FORMATION PEDAGOGIQUE");
        $template->setValue('sgrh_cisco_crfrp', "CENTRE REGIONAL DE FORMATION" . $br . "ET DE RECHERCHE PEDAGOGIQUE " . $nom_crfrp);
        $template->setValue('chef_signataire', "Chef de Centre Régional de Formation et de Recherche Pédagogique de " . ucfirst(mb_strtolower($nom_crfrp, 'UTF-8')));
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', '');
        $template->setValue('en_service', $nom_etablissement);
        $template->setValue('lieu_signature', $lieu_signature);
    } else if (($type_fonction == "Personnel administratif" || $type_fonction == "Personnel enseignant") && in_array($type_etablissement, ["LYCEE", "COLLEGE"])) {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" . $br . $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " . $nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $lieu_signature);
        $template->setValue('en_service', $nom_etablissement . " - ZAP " . $nom_zap);
        $template->setValue('lieu_signature', $lieu_signature);
    } else if (($type_fonction == "Personnel administratif" || $type_fonction == "Personnel enseignant") && in_array($type_etablissement, ["PRIMAIRE", "PRESCOLAIRE"])) {
        $template->setValue('type_direction', "DIRECTION REGIONALE DE L'EDUCATION NATIONALE" . $br . $nom_region);
        $template->setValue('sgrh_cisco_crfrp', "CIRCONSCRIPTION SCOLAIRE " . $nom_district);
        $template->setValue('chef_signataire', "Chef de la Circonscription Scolaire ");
        $template->setValue('nom_region', '');
        $template->setValue('nom_district', $lieu_signature);
        $template->setValue('en_service', $nom_etablissement . " - ZAP " . $nom_zap);
        $template->setValue('lieu_signature', $lieu_signature);
    }
}

function injectGenreFonctionVariables($template, $sexe, $type_fonction, $type_etablissement) {
    $fonction_label = "";
    $nee_le = "Né le";
    $interessee = "L'intéressé";

    if ($type_fonction == "Personnel administratif") {
        $fonction_label = "PERSONNEL ADMINISTRATIF";
    } else if ($type_fonction == "Personnel enseignant" && $type_etablissement == "CRFRP") {
        $fonction_label = "FORMATEUR";
    }

    if ($sexe == "Masculin") {
        $nee_le = "Né le";
        $interessee = "L'intéressé";
        if ($type_fonction == "Personnel enseignant") {
            if (in_array($type_etablissement, ["LYCEE", "COLLEGE", "PRIMAIRE"])) {
                $fonction_label = "ENSEIGNANT";
            } else if ($type_etablissement == "PRESCOLAIRE") {
                $fonction_label = "EDUCATEUR";
            }
        }
    } else if ($sexe == "Féminin") {
        $nee_le = "Née le";
        $interessee = "L'intéressée";
        if ($type_fonction == "Personnel enseignant") {
            if (in_array($type_etablissement, ["LYCEE", "COLLEGE", "PRIMAIRE"])) {
                $fonction_label = "ENSEIGNANTE";
            } else if ($type_etablissement == "PRESCOLAIRE") {
                $fonction_label = "EDUCATRICE";
            }
        }
    }

    $template->setValue('nee_le', $nee_le);
    $template->setValue('interessee', $interessee);
    $template->setValue('fonction', $fonction_label);
}
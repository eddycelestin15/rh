<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/config.php'; 

$action = $_POST['action'] ?? '';

try {

    function determinerDuree(string $grade): string {
        $gradeUpper = strtoupper(trim($grade));

        // 1. Si le grade est STAGIAIRE
        if ($gradeUpper === 'STAGIAIRE') {
            return '1 an';
        }

        // 2. Si le grade contient l'un des 3 échelons spécifiques
        $motsCles = ['2°CLASSE/3°ECHELON', '1°CLASSE/3°ECHELON', 'PRINCIPAL/3°ECHELON'];
        foreach ($motsCles as $mot) {
            if (str_contains($gradeUpper, $mot)) {
                return '3 ans';
            }
        }

        // 3. Le reste
        return '2 ans';
    }

    switch ($action) {
        
        // -------------------------------------------------------------------
        // 1. Charger la liste des corps (ref_corps)
        // -------------------------------------------------------------------
        case 'charger_corps':
            $stmt = $pdo->query("SELECT id, libelle_corps FROM ref_corps ORDER BY libelle_corps ASC");
            $corpsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($corpsList);
            break;

        // -------------------------------------------------------------------
        // 2. Charger les grades filtrés selon le corps et le type d'acte
        // -------------------------------------------------------------------
        case 'charger_grades':
            $corps_id  = filter_input(INPUT_POST, 'corps_id', FILTER_VALIDATE_INT);
            $type_acte = trim($_POST['type_acte'] ?? '');

            if (!$corps_id || empty($type_acte)) {
                echo json_encode([]);
                exit;
            }

            // Récupération du libellé du corps pour appliquer la logique métier
            $stmtCorps = $pdo->prepare("SELECT libelle_corps FROM ref_corps WHERE id = ?");
            $stmtCorps->execute([$corps_id]);
            $corpsRow = $stmtCorps->fetch(PDO::FETCH_ASSOC);

            if (!$corpsRow) {
                echo json_encode([]);
                exit;
            }

            $libelle = strtoupper($corpsRow['libelle_corps']);

            // Requête de base sur ref_grades_types via la grille indiciaire du corps
            $sql = "SELECT DISTINCT g.id, g.libelle_grade 
                    FROM ref_grades_types g
                    INNER JOIN ref_grille_indiciaire gi ON g.id = gi.grade_type_id
                    WHERE gi.corps_id = :corps_id";

            $params = [':corps_id' => $corps_id];

            // Application des filtres selon le type d'acte
            if ($type_acte === 'Contrat' || $type_acte === 'Avenant') {
                if (strpos($libelle, 'INSTITUTRICES "C"') !== false || strpos($libelle, 'OPERATEURS') !== false) {
                    $sql .= " AND g.libelle_grade LIKE '%ECHELLE III%'";
                } elseif (strpos($libelle, 'INSTITUTRICES "B"') !== false || strpos($libelle, 'ENCADREURS') !== false) {
                    $sql .= " AND g.libelle_grade LIKE '%ECHELLE IV%'";
                }
            } elseif ($type_acte === 'Arrêté') {
                if (strpos($libelle, 'INSTITUTRICES "C"') !== false || strpos($libelle, 'OPERATEURS') !== false) {
                    $sql .= " AND g.libelle_grade NOT LIKE '%ECHELLE III%'";
                } elseif (strpos($libelle, 'INSTITUTRICES "B"') !== false || strpos($libelle, 'ENCADREURS') !== false) {
                    $sql .= " AND g.libelle_grade NOT LIKE '%ECHELLE IV%'";
                }
            }

            $sql .= " ORDER BY g.id ASC";

            $stmtGrades = $pdo->prepare($sql);
            $stmtGrades->execute($params);
            $gradesList = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($gradesList);
            break;

        // -------------------------------------------------------------------
        // 3. Calculer l'indice correspondant au corps et au grade sélectionné
        // -------------------------------------------------------------------
        case 'calculer_indice':
            $corps_id      = filter_input(INPUT_POST, 'corps_id', FILTER_VALIDATE_INT);
            $grade_type_id = filter_input(INPUT_POST, 'grade_type_id', FILTER_VALIDATE_INT);

            if (!$corps_id || !$grade_type_id) {
                echo json_encode(['indice' => '']);
                exit;
            }

            $stmtIndice = $pdo->prepare("SELECT indice FROM ref_grille_indiciaire WHERE corps_id = ? AND grade_type_id = ? LIMIT 1");
            $stmtIndice->execute([$corps_id, $grade_type_id]);
            $row = $stmtIndice->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['indice' => $row['indice'] ?? '']);
            break;

        // -------------------------------------------------------------------
        // 4. Enregistrement d'un nouvel avancement
        // -------------------------------------------------------------------
        case 'ajouter':
            if (empty($_POST['im']) || empty($_POST['av_type_acte']) || empty($_POST['av_type_avancement']) || empty($_POST['av_acte_no']) || empty($_POST['av_acte_date']) || empty($_POST['av_date_effet']) || empty($_POST['av_indice']) || empty($_POST['av_corps']) || empty($_POST['av_grade'])) {
                echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
                exit;
            }

            // Normalisation des matricules pour éviter les problèmes d'espaces
            $imPoster = trim($_POST['im']);
            $imSession = trim($_SESSION['user_im'] ?? '');
            $userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'agent';

            // Vérification des droits : Soit c'est son propre dossier, soit c'est un rôle d'administration/gestionnaire
            $isSelf = ($imPoster === $imSession);
            $isAdminOrRH = in_array(strtolower($userRole), ['admin', 'resp_retraite', 'resp_personnel_crfrp']);

            if (!$isSelf && !$isAdminOrRH) {
                echo json_encode(['status' => 'error', 'message' => 'Action non autorisée.']);
                exit;
            }

            // Calcul automatique de la durée selon le grade
            $av_grade = trim($_POST['av_grade']);
            $duree    = determinerDuree($av_grade);

            try {
                $sql = "INSERT INTO personnel_avancements 
                        (im, duree, av_type_acte, av_type_avancement, av_corps, av_grade, av_date_effet, av_indice, av_acte_no, av_acte_date) 
                        VALUES 
                        (:im, :duree, :av_type_acte, :av_type_avancement, :av_corps, :av_grade, :av_date_effet, :av_indice, :av_acte_no, :av_acte_date)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':im'                 => $imPoster,
                    ':duree'              => $duree,
                    ':av_type_acte'       => $_POST['av_type_acte'],
                    ':av_type_avancement' => $_POST['av_type_avancement'],
                    ':av_corps'           => $_POST['av_corps'],
                    ':av_grade'           => $av_grade,
                    ':av_date_effet'      => $_POST['av_date_effet'],
                    ':av_indice'          => $_POST['av_indice'],
                    ':av_acte_no'         => $_POST['av_acte_no'],
                    ':av_acte_date'       => $_POST['av_acte_date']
                ]);

                echo json_encode(['status' => 'success', 'message' => 'Avancement enregistré avec succès !']);
                exit;
            } catch (\PDOException $e) {
                echo json_encode(['status' => 'error', 'message' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()]);
                exit;
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non reconnue.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
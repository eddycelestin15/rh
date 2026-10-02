<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/config.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'charger_corps') {
        $stmt = $pdo->query("SELECT id, libelle_corps FROM ref_corps ORDER BY libelle_corps ASC");
        $corps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($corps);
        exit;
    }

    // --- NOUVEAUTÉ 1 : CHARGEMENT DYNAMIQUE DES GRADES ---
    if ($action === 'charger_grades') {
        $corps_id = intval($_POST['corps_id'] ?? 0);
        $type_acte = $_POST['type_acte'] ?? '';

        // Récupérer le libellé du corps pour appliquer la logique métier demandée
        $stmt = $pdo->prepare("SELECT libelle_corps, modele_id FROM ref_corps WHERE id = ?");
        $stmt->execute([$corps_id]);
        $corps = $stmt->fetch();

        if (!$corps) {
            echo json_encode([]);
            exit;
        }

        $libelle = $corps['libelle_corps'];
        $modele_id = $corps['modele_id'];

        // Initialisation de la requête SQL de base filtrée par le modèle lié au Corps
        $sql = "SELECT id, libelle_grade FROM ref_grades_types WHERE modele_id = :modele_id";
        $conditions = [];

        // Logique conditionnelle demandée
        if ($type_acte === 'Contrat' || $type_acte === 'Avenant') {
            if (strpos($libelle, 'INSTITUTRICES "C"') !== false || strpos($libelle, 'OPERATEURS') !== false) {
                $sql .= " AND libelle_grade LIKE '%ECHELLE III%'";
            } elseif (strpos($libelle, 'INSTITUTRICES "B"') !== false || strpos($libelle, 'ENCADREURS') !== false) {
                $sql .= " AND libelle_grade LIKE '%ECHELLE IV%'";
            }
        } elseif ($type_acte === 'Arrêté') {
            if (strpos($libelle, 'INSTITUTRICES "C"') !== false || strpos($libelle, 'OPERATEURS') !== false) {
                $sql .= " AND libelle_grade NOT LIKE '%ECHELLE III%'";
            } elseif (strpos($libelle, 'INSTITUTRICES "B"') !== false || strpos($libelle, 'ENCADREURS') !== false) {
                $sql .= " AND libelle_grade NOT LIKE '%ECHELLE IV%'";
            }
        }

        $sql .= " ORDER BY id ASC";
        $stmtGrades = $pdo->prepare($sql);
        $stmtGrades->execute([':modele_id' => $modele_id]);
        echo json_encode($stmtGrades->fetchAll(\PDO::FETCH_ASSOC));
        exit;
    }

    // --- NOUVEAUTÉ 2 : CALCUL AUTOMATIQUE DE L'INDICE ---
    if ($action === 'calculer_indice') {
        $corps_id = intval($_POST['corps_id'] ?? 0);
        $grade_type_id = intval($_POST['grade_type_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT indice FROM ref_grille_indiciaire WHERE corps_id = ? AND grade_type_id = ?");
        $stmt->execute([$corps_id, $grade_type_id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        echo json_encode(['indice' => $result ? $result['indice'] : '']);
        exit;
    }

    // --- ENREGISTREMENT ---
    if ($action === 'ajouter') {
        if (empty($_POST['im']) || empty($_POST['av_type_acte']) || empty($_POST['av_type_avancement']) || empty($_POST['av_acte_no']) || empty($_POST['av_acte_date']) || empty($_POST['av_date_effet']) || empty($_POST['av_indice']) || empty($_POST['av_corps']) || empty($_POST['av_grade'])) {
            echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
            exit;
        }

        if ($_POST['im'] !== $_SESSION['user_im']) {
            echo json_encode(['status' => 'error', 'message' => 'Action non autorisée.']);
            exit;
        }

        try {
            $sql = "INSERT INTO personnel_avancements (im, av_type_acte, av_type_avancement, av_corps, av_grade, av_date_effet, av_indice, av_acte_no, av_acte_date) 
                    VALUES (:im, :av_type_acte, :av_type_avancement, :av_corps, :av_grade, :av_date_effet, :av_indice, :av_acte_no, :av_acte_date)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':im'                 => $_POST['im'],
                ':av_type_acte'       => $_POST['av_type_acte'],
                ':av_type_avancement' => $_POST['av_type_avancement'],
                ':av_corps'           => $_POST['av_corps'],
                ':av_grade'           => $_POST['av_grade'],
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
    }

    // --- MODIFICATION ---
    if ($action === 'modifier') {
        if (empty($_POST['id']) || empty($_POST['av_type_acte']) || empty($_POST['av_type_avancement']) || empty($_POST['av_acte_no']) || empty($_POST['av_acte_date']) || empty($_POST['av_date_effet']) || empty($_POST['av_indice']) || empty($_POST['av_corps']) || empty($_POST['av_grade'])) {
            echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
            exit;
        }

        try {
            $sql = "UPDATE personnel_avancements 
                    SET av_type_acte = :av_type_acte, av_type_avancement = :av_type_avancement, av_corps = :av_corps, av_grade = :av_grade, av_date_effet = :av_date_effet, av_indice = :av_indice, av_acte_no = :av_acte_no, av_acte_date = :av_acte_date 
                    WHERE id = :id AND im = :im";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id'                 => $_POST['id'],
                ':im'                 => $_SESSION['user_im'],
                ':av_type_acte'       => $_POST['av_type_acte'],
                ':av_type_avancement' => $_POST['av_type_avancement'],
                ':av_corps'           => $_POST['av_corps'],
                ':av_grade'           => $_POST['av_grade'],
                ':av_date_effet'      => $_POST['av_date_effet'],
                ':av_indice'          => $_POST['av_indice'],
                ':av_acte_no'         => $_POST['av_acte_no'],
                ':av_acte_date'       => $_POST['av_acte_date']
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Avancement mis à jour avec succès !']);
            exit;
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Erreur de modification : ' . $e->getMessage()]);
            exit;
        }
    }

    // --- SUPPRESSION ---
    if ($action === 'supprimer') {
        if (empty($_POST['id'])) {
            echo json_encode(['status' => 'error', 'message' => 'ID manquant.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM personnel_avancements WHERE id = ? AND im = ?");
            $stmt->execute([$_POST['id'], $_SESSION['user_im']]);
            
            echo json_encode(['status' => 'success', 'message' => 'Avancement supprimé avec succès !']);
            exit;
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Erreur de suppression : ' . $e->getMessage()]);
            exit;
        }
    }

    // --- RÉCUPÉRATION D'UN AVANCEMENT SPÉCIFIQUE (POUR MODAL MODIFIER) ---
    if ($action === 'recuperer_un') {
        $id = intval($_POST['id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT * FROM personnel_avancements WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        echo json_encode($row ?: null);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée.']);
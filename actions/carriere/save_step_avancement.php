<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

$im = $_SESSION['user_im'] ?? $_POST['av_im'] ?? null;
$id = $_POST['id'] ?? null; // ID de l'acte pour la modification

try {
    if (!$im) throw new Exception("Matricule manquant.");

    // Si on a un ID, on fait un UPDATE, sinon un INSERT
    if (!empty($id)) {
        $sql = "UPDATE personnel_avancements SET 
                av_type_acte = :type_acte, 
                av_type_avancement = :type_av, 
                av_corps = :corps, 
                av_grade = :grade, 
                av_date_effet = :date_effet, 
                av_indice = :indice, 
                av_acte_no = :acte_no, 
                av_acte_date = :acte_date, 
                av_visa_no = :visa_no, 
                av_visa_date = :visa_date, 
                av_ctrl_no = :ctrl_no, 
                av_ctrl_date = :ctrl_date
                WHERE id = :id AND im = :im";
    } else {
        $sql = "INSERT INTO personnel_avancements (
                im, av_type_acte, av_type_avancement, av_corps, av_grade, 
                av_date_effet, av_indice, av_acte_no, av_acte_date, 
                av_visa_no, av_visa_date, av_ctrl_no, av_ctrl_date
            ) VALUES (
                :im, :type_acte, :type_av, :corps, :grade, 
                :date_effet, :indice, :acte_no, :acte_date, 
                :visa_no, :visa_date, :ctrl_no, :ctrl_date
            )";
    }

    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':type_acte'     => $_POST['av_type_acte'] ?? null,
        ':type_av'       => $_POST['av_type_avancement'] ?? null,
        ':corps'         => $_POST['av_corps'] ?? null,
        ':grade'         => $_POST['av_grade'] ?? null,
        ':date_effet'    => !empty($_POST['av_date_effet']) ? $_POST['av_date_effet'] : null,
        ':indice'        => $_POST['av_indice'] ?? null,
        ':acte_no'       => $_POST['av_acte_no'] ?? null,
        ':acte_date'     => !empty($_POST['av_acte_date']) ? $_POST['av_acte_date'] : null,
        ':visa_no'       => $_POST['av_visa_no'] ?? null,
        ':visa_date'     => !empty($_POST['av_visa_date']) ? $_POST['av_visa_date'] : null,
        ':ctrl_no'       => $_POST['av_ctrl_no'] ?? null,
        ':ctrl_date'     => !empty($_POST['av_ctrl_date']) ? $_POST['av_ctrl_date'] : null,
        ':im'            => $im
    ];

    if (!empty($id)) {
        $params[':id'] = $id;
    }

    $stmt->execute($params);

    echo json_encode(['status' => 'success', 'message' => 'Opération réussie']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
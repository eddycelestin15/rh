<?php
// update_avancement.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

try {
    if (empty($_POST['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID de l\'enregistrement manquant.']);
        exit;
    }

    $sql = "UPDATE personnel_avancements SET 
                av_type_acte = :type_acte,
                av_type_avancement = :type_avancement,
                av_acte_no = :acte_no,
                av_acte_date = :acte_date,
                av_corps = :corps,
                av_grade = :grade,
                av_date_effet = :date_effet,
                av_indice = :indice,
                av_visa_no = :visa_no,
                av_visa_date = :visa_date,
                av_ctrl_no = :ctrl_no,
                av_ctrl_date = :ctrl_date
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':type_acte'        => $_POST['av_type_acte'],
        ':type_avancement'  => $_POST['av_type_avancement'],
        ':acte_no'          => $_POST['av_acte_no'],
        ':acte_date'        => !empty($_POST['av_acte_date']) ? $_POST['av_acte_date'] : null,
        ':corps'            => $_POST['av_corps'], // Nouveau libellé du corps
        ':grade'            => $_POST['av_grade'], // Nouveau libellé du grade
        ':date_effet'       => $_POST['av_date_effet'],
        ':indice'           => $_POST['av_indice'], // Nouvel indice auto-mis à jour
        ':visa_no'          => $_POST['av_visa_no'],
        ':visa_date'        => !empty($_POST['av_visa_date']) ? $_POST['av_visa_date'] : null,
        ':ctrl_no'          => $_POST['av_ctrl_no'],
        ':ctrl_date'        => !empty($_POST['av_ctrl_date']) ? $_POST['av_ctrl_date'] : null,
        ':id'               => $_POST['id']
    ]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()]);
}
?>
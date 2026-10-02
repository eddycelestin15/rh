<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

$userIm = $_SESSION['user_im'] ?? $_SESSION['im'] ?? '';

try {
    $sql = "SELECT 
                c.id,
                c.sender_im AS msg_sender_im,
                c.message,
                c.message_type,
                c.created_at,
                e.nom AS sender_nom, 
                e.prenoms AS sender_prenoms,
                p.lieu_de_service,
                p.type_etablissement,
                p.nom_etablissement,
                p.nom_fonction,
                p.nom_direction,
                p.nom_service,
                p.nom_division,
                d.sigle AS sigle_direction,
                sd.sigle AS sigle_service_dirmen,
                s.sigle AS sigle_service,
                dv.sigle AS sigle_division,
                u.role_specifique,
                u.niveau,
                u.photo
            FROM chat_messages c
            LEFT JOIN personnel_etat_civil e ON c.sender_im = e.im
            LEFT JOIN personnel_poste_actuel p ON e.im = p.im
            LEFT JOIN utilisateurs u ON c.sender_im = u.im
            LEFT JOIN ref_directions d ON TRIM(p.nom_direction) = TRIM(d.nom_direction)
            LEFT JOIN ref_services_dirmen sd ON TRIM(p.nom_service) = TRIM(sd.nom_service)
            LEFT JOIN ref_services s ON TRIM(p.nom_service) = TRIM(s.nom)
            LEFT JOIN ref_divisions dv ON TRIM(p.nom_division) = TRIM(dv.nom)
            ORDER BY c.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedMessages = [];
    foreach ($messages as $msg) {
        $senderIm = $msg['msg_sender_im'] ?? '';
        $dateRaw = $msg['created_at'] ?? 'now';

        // 1. Définir le nom et le chemin d'accès relatif/absolu
        $photoRelative = 'images/' . $senderIm . '.jpg';
        $defaultAvatar = 'images/default.png';

        // 2. Vérifier si le fichier existe réellement sur le serveur
        $photoPath = (!empty($senderIm) && file_exists(APP_ROOT . '/' . $photoRelative))
            ? $photoRelative
            : $defaultAvatar;

        $formattedMessages[] = [
            'id'                   => $msg['id'],
            'message'              => $msg['message'] ?? '',
            'sender_im'            => $senderIm,
            'sender_nom'           => $msg['sender_nom'] ?? '',
            'sender_prenoms'       => $msg['sender_prenoms'] ?? '',
            'lieu_de_service'      => $msg['lieu_de_service'] ?? '',
            'type_etablissement'   => $msg['type_etablissement'] ?? '',
            'nom_etablissement'    => $msg['nom_etablissement'] ?? '',
            'nom_fonction'         => $msg['nom_fonction'] ?? '',
            'nom_direction'        => $msg['nom_direction'] ?? '',
            'nom_service'          => $msg['nom_service'] ?? '',
            'nom_division'         => $msg['nom_division'] ?? '',
            'sigle_direction'      => $msg['sigle_direction'] ?? '',
            'sigle_service_dirmen' => $msg['sigle_service_dirmen'] ?? '',
            'sigle_service'        => $msg['sigle_service'] ?? '',
            'sigle_division'       => $msg['sigle_division'] ?? '',
            'role_specifique'      => $msg['role_specifique'] ?? 'agent',
            'niveau'               => $msg['niveau'] ?? '',
            'photo'                => $photoPath, 
            'is_me'                => ((string)$senderIm === (string)$userIm),
            'created_at'           => date('d/m/Y H:i', strtotime($dateRaw))
        ];
    }

    echo json_encode(['success' => true, 'messages' => $formattedMessages]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'messages' => []]);
}
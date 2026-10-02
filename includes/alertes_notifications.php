<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

// --- CONFIGURATION EXTERNE ---
define('ULTRAMSG_INSTANCE', 'instance187797');
define('ULTRAMSG_TOKEN', 'ejxxlgd4rb19xbxs');

/**
 * Envoie un message WhatsApp via UltraMsg
 */
function sendWhatsApp($phone, $nom, $titre, $msg) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (empty($phone)) return false;

    $isRejet = (stripos($titre, 'rejet') !== false || stripos($titre, 'rejete') !== false);
    $prefixe = $isRejet ? "⚠️ *ALERTE REJET* : *" : "🔔 *SUIVI DOSSIER* : *";

    $params = array(
        'token' => ULTRAMSG_TOKEN,
        'to'    => $phone,
        'body'  => $prefixe . $titre . "*\n\nBonjour *$nom*,\n\n$msg\n\n_Veuillez vous connecter sur le portail RH pour plus d'informations._"
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.ultramsg.com/" . ULTRAMSG_INSTANCE . "/messages/chat",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTPHEADER => array("content-type: application/x-www-form-urlencoded"),
    ));
    
    $res = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("Erreur cURL WhatsApp : " . $err);
        return false;
    }
    return $res;
}

/**
 * Envoie un Email structuré via PHPMailer
 */
function sendEmail($dest, $nom, $sujet, $message) {
    if (empty($dest)) return false;
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'onlineworkbriand@gmail.com';
        $mail->Password   = 'qvqsjdfhocaszbzb';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('onlineworkbriand@gmail.com', 'Service des Ressources Humaines');
        $mail->addAddress($dest);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        $isRejet = (stripos($sujet, 'rejet') !== false || stripos($sujet, 'rejete') !== false);
        $mail->Subject = $isRejet 
            ? "⚠️ REJET DE DOSSIER : " . $sujet 
            : "🔔 RH : " . $sujet;

        $couleur = $isRejet ? '#dc2626' : '#0284c7';
        $icone   = $isRejet ? '⚠️' : '🔔';

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
                <div style='background: {$couleur}; color: white; padding: 16px 24px;'>
                    <h2 style='margin: 0; font-size: 18px;'>{$icone} Service des Ressources Humaines</h2>
                </div>
                <div style='padding: 24px; color: #334155; line-height: 1.6;'>
                    <h3 style='margin-top: 0; color: #1e293b;'>Bonjour {$nom},</h3>
                    <p style='font-size: 15px; text-align: justify;'>".nl2br(htmlspecialchars($message))."</p>
                    <p style='margin-top: 24px; font-size: 13px; color: #64748b;'>
                        <i>Ceci est une notification automatique du système RH.<br>
                        Veuillez vous connecter sur votre espace RH pour plus de détails.</i>
                    </p>
                </div>
                <div style='background: #f8fafc; padding: 12px 24px; font-size: 12px; color: #94a3b8; text-align: center;'>
                    Portail RH – Notification automatique
                </div>
            </div>
        ";

        $mail->AltBody = "Bonjour {$nom},\n\n{$message}\n\nCeci est une notification automatique du système RH.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur PHPMailer : " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envoie un code de validation à 6 chiffres pour la création de compte
 */
function sendVerificationCode($email, $phone, $nom, $code) {
    $sujet = "Code de validation de votre compte RH";
    $message = "Votre code de confirmation pour valider la création de votre compte est : {$code}.\n\nCe code est obligatoire pour finaliser votre inscription.";
    
    if (!empty($phone)) {
        sendWhatsApp($phone, $nom, "VALIDATION COMPTE", "Votre code de validation de création de compte RH est : *$code*.");
    }
    if (!empty($email)) {
        sendEmail($email, $nom, $sujet, $message);
    }
}

/**
 * Envoie un code de réinitialisation de mot de passe à 6 chiffres
 */
function sendResetCode($email, $phone, $nom, $code) {
    $sujet = "Réinitialisation de votre mot de passe RH";
    $message = "Vous avez demandé la réinitialisation de votre mot de passe.\n\nVotre code de réinitialisation est : {$code}.\n\nCe code expire dans 15 minutes.";
    
    if (!empty($phone)) {
        sendWhatsApp($phone, $nom, "REINITIALISATION MOT DE PASSE", "Votre code de réinitialisation de mot de passe RH est : *$code*.");
    }
    if (!empty($email)) {
        sendEmail($email, $nom, $sujet, $message);
    }
}

function declencherAlertesInstantanees($pdo) {
    try {
        $sql = "SELECT v.*, u.email, u.whatsapp, u.nom as user_nom, u.prenoms 
                FROM v_moteur_alertes v
                LEFT JOIN utilisateurs u ON TRIM(v.im) = TRIM(u.im)
                WHERE v.alerte_id NOT LIKE 'HIDDEN_%'";
        
        $stmt = $pdo->query($sql);
        $alertes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($alertes as $row) {
            $prenom   = !empty($row['prenoms']) ? $row['prenoms'] : ($row['user_nom'] ?? 'Agent');
            $titre    = $row['titre'] ?? 'Notification RH';
            $msg      = $row['message'] ?? '';
            $alerteId = $row['alerte_id'];
            $im       = $row['im'];

            $typeKey = strtoupper(trim($row['type_key'] ?? ''));
            $typePourEnum = 'contrat';

            // Mapping des types d'alertes
            if ($typeKey === 'INTG') {
                $typePourEnum = 'integration';
            } elseif ($typeKey === 'TITU') {
                $typePourEnum = 'titularisation';
            } elseif (in_array($typeKey, ['ADMISSION_RETRAITE'])) {
                $typePourEnum = 'admission_retraite';
            } elseif (in_array($typeKey, ['COMPENSATRICE'])) {
                $typePourEnum = 'compensatrice';
            } elseif (in_array($typeKey, ['INSTALLATION'])) {
                $typePourEnum = 'installation';
            } elseif (in_array($typeKey, ['DOS_STEP', 'DOS_REJET'])) {
                $typePourEnum = 'dos';
            } elseif ($typeKey === 'BORDEREAU') {
                $typePourEnum = 'bordereau'; 
            } elseif ($typeKey === 'CONTRAT') {
                $typePourEnum = 'contrat';
            } elseif (in_array($typeKey, ['ECHELON', 'ECHELON_DETAIL'])) {
                $typePourEnum = 'echelon';
            } elseif ($typeKey === 'CLASSE') {
                $typePourEnum = 'classe';
            }

            $checkNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE alerte_id = ?");
            $checkNotif->execute([$alerteId]);

            if ($checkNotif->fetchColumn() == 0) {
                $stmtIns = $pdo->prepare("INSERT INTO notifications 
                    (alerte_id, im, type_alerte, message, est_lu, created_at) 
                    VALUES (?, ?, ?, ?, 0, NOW())");
                
                $stmtIns->execute([
                    $alerteId,
                    $im,
                    $typePourEnum, 
                    $msg
                ]);

                if (!empty($row['whatsapp'])) {
                    sendWhatsApp($row['whatsapp'], $prenom, $titre, $msg);
                }
                if (!empty($row['email'])) {
                    sendEmail($row['email'], $prenom, $titre, $msg);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur moteur d'alerte : " . $e->getMessage());
    }
}
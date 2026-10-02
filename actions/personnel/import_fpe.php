<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'json');
require_once __DIR__ . '/../../includes/check_session.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_excel'])) {
    $file = $_FILES['file_excel']['tmp_name'];
    $handle = fopen($file, "r");
    $stats = ['total' => 0];

    try {
        $pdo->beginTransaction();
        $i = 0;
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            if ($i == 0) { $i++; continue; }

            // Conversion Date Naissance (M = colonne 12)
            $dn_brute = trim($data[12]);
            $dn_mysql = null;
            if(!empty($dn_brute)) {
                $parts = explode('/', $dn_brute);
                if(count($parts) == 3) $dn_mysql = $parts[2].'-'.$parts[1].'-'.$parts[0];
            }

            // Détection automatique (Etab = J/9, Secteur = O/14)
            $etab = mb_strtoupper(trim($data[9]));
            $sect = mb_strtoupper(trim($data[14]));

            $b_d = (strpos($etab, 'DREN') !== false) ? 1 : 0;
            $b_c = (strpos($etab, 'CISCO') !== false) ? 1 : 0;
            $b_z = (strpos($etab, 'ZAP') !== false) ? 1 : 0;
            $crf = (strpos($etab, 'CRFRP') !== false) ? 1 : 0;

            // Logique Enseignant (Si secteur contient EPP, CEG, LYCEE ou ENSEIGNEMENT)
            $is_enseignant = (preg_match('/EPP|CEG|LYCEE|ENS|PRESCO|PRIMAIRE|COLLEGE/', $etab . $sect)) ? 1 : 0;
            // Hors FPE (Exemple: si le statut contient "SUBVENTIONNE" ou "FRAM")
            $is_hors_fpe = (strpos($sect, 'HORS') !== false || strpos(mb_strtoupper($data[13]), 'FRAM') !== false) ? 1 : 0;

            $sql = "INSERT INTO personnel_fpe (
                code_dren, nom_dren, code_cisco, nom_cisco, code_commune, nom_commune, 
                code_zap, nom_zap, code_etab, nom_etablissement, im, nom_et_prenoms, 
                date_naissance, statut, secteur, bureau_d, bureau_c, bureau_z, crfrp, primaire, hors_fpe
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE 
                nom_et_prenoms = VALUES(nom_et_prenoms),
                date_naissance = VALUES(date_naissance),
                nom_etablissement = VALUES(nom_etablissement),
                statut = VALUES(statut),
                primaire = VALUES(primaire),
                hors_fpe = VALUES(hors_fpe)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data[0], $data[1], $data[2], $data[3], $data[4], $data[5],
                $data[6], $data[7], $data[8], $data[9], trim($data[10]), trim($data[11]),
                $dn_mysql, $data[13], $data[14], $b_d, $b_c, $b_z, $crf, $is_enseignant, $is_hors_fpe
            ]);
            $stats['total']++;
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
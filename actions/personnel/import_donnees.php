<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

// Activer le rapport d'erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Utilisation de PhpSpreadsheet pour lire le fichier Excel XLSX
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';
$status = '';

if (isset($_POST['import_submit'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0 && !empty($_POST['type_import'])) {
        
        $fileTmpPath = $_FILES['excel_file']['tmp_name'];
        $fileName = $_FILES['excel_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $typeImport = $_POST['type_import'];

        if ($fileExtension === 'xlsx') {
            try {
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $spreadsheet = IOFactory::load($fileTmpPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                $inserted = 0;

                // On commence à la ligne 1 pour ignorer l'en-tête (ligne 0)
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];

                    // --- CAS 1 : IMPORTATION DES DISTRICTS ---
                    if ($typeImport === 'districts') {
                        // Structure attendue : 0: id, 1: code_district, 2: nom_district
                        if (!isset($row[1]) || empty(trim($row[1]))) continue;

                        $code_dist = substr(trim($row[1]), 0, 10);
                        $nom_dist  = !empty($row[2]) ? substr(trim($row[2]), 0, 100) : 'INCONNU';

                        $sql = "INSERT INTO ref_districts (code_district, nom_district) 
                                VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE nom_district = VALUES(nom_district)";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$code_dist, $nom_dist]);
                        $inserted++;
                    }

                    // --- CAS 2 : IMPORTATION DES ZAPs ---
                    elseif ($typeImport === 'zaps') {
                        // Structure attendue : 0: id, 1: district_id, 2: code_zap, 3: nom_zap
                        if (!isset($row[2]) || empty(trim($row[2]))) continue;

                        $district_id = !empty($row[1]) ? (int)$row[1] : null;
                        $code_zap    = substr(trim($row[2]), 0, 5);
                        $nom_zap     = !empty($row[3]) ? substr(trim($row[3]), 0, 100) : 'INCONNU';

                        $sql = "INSERT INTO ref_zaps (district_id, code_zap, nom_zap) 
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                    district_id = VALUES(district_id),
                                    nom_zap = VALUES(nom_zap)";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$district_id, $code_zap, $nom_zap]);
                        $inserted++;
                    }

                    // --- CAS 3 : IMPORTATION DES ÉTABLISSEMENTS ---
                    elseif ($typeImport === 'etablissements') {
                        // Structure attendue standardisée
                        if (!isset($row[5]) || empty(trim($row[5]))) continue;

                        $code_etab      = substr(trim($row[5]), 0, 9);
                        $zap_id         = !empty($row[0]) ? (int)$row[0] : null;
                        $district_id    = !empty($row[1]) ? (int)$row[1] : null;
                        $code_commune   = !empty($row[2]) ? (int)$row[2] : null;
                        $nom_commune    = !empty($row[3]) ? substr(trim($row[3]), 0, 255) : null;
                        $cat_commune    = !empty($row[4]) ? substr(trim($row[4]), 0, 20) : null;
                        $nom_etab       = !empty($row[6]) ? substr(trim($row[6]), 0, 255) : 'INCONNU';
                        
                        $is_eec         = (!empty($row[7])  && (int)$row[7]  === 1) ? 1 : 0;
                        $is_prescolaire = (!empty($row[8])  && (int)$row[8]  === 1) ? 1 : 0;
                        $is_epp         = (!empty($row[9])  && (int)$row[9]  === 1) ? 1 : 0;
                        $is_ceg         = (!empty($row[10]) && (int)$row[10] === 1) ? 1 : 0;
                        $is_lycee       = (!empty($row[11]) && (int)$row[11] === 1) ? 1 : 0;
                        $is_crfrp       = (!empty($row[12]) && (int)$row[12] === 1) ? 1 : 0;

                        $sql = "INSERT INTO ref_etablissements (
                                    zap_id, district_id, code_commune, nom_commune, cat_commune, 
                                    code_etab, nom_etab, is_eec, is_prescolaire, is_epp, is_ceg, is_lycee, is_crfrp
                                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE 
                                    zap_id = VALUES(zap_id),
                                    district_id = VALUES(district_id),
                                    code_commune = VALUES(code_commune),
                                    nom_commune = VALUES(nom_commune),
                                    cat_commune = VALUES(cat_commune),
                                    nom_etab = VALUES(nom_etab),
                                    is_eec = VALUES(is_eec),
                                    is_prescolaire = VALUES(is_prescolaire),
                                    is_epp = VALUES(is_epp),
                                    is_ceg = VALUES(is_ceg),
                                    is_lycee = VALUES(is_lycee),
                                    is_crfrp = VALUES(is_crfrp)";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $zap_id, $district_id, $code_commune, $nom_commune, $cat_commune, 
                            $code_etab, $nom_etab, $is_eec, $is_prescolaire, $is_epp, $is_ceg, $is_lycee, $is_crfrp
                        ]);
                        $inserted++;
                    }
                }

                $status = 'success';
                $labelType = ($typeImport === 'districts') ? 'districts' : (($typeImport === 'zaps') ? 'ZAPs' : 'établissements');
                $message = "Importation réussie ! <strong>$inserted</strong> $labelType ont été synchronisés depuis le fichier Excel.";
            } catch (Exception $e) {
                $status = 'error';
                $message = "Erreur fatale lors du traitement SQL : " . $e->getMessage();
            }
        } else {
            $status = 'error';
            $message = "Format invalide. Veuillez sélectionner exclusivement un fichier au format <strong>.xlsx</strong>.";
        }
    } else {
        $status = 'error';
        $message = "Formulaire incomplet ou erreur lors du transfert du fichier.";
    }
}
?>

<div class="p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Plateforme d'Importation des Références</h1>
            <p class="text-xs text-slate-500 mt-1">Sélectionnez la catégorie de données et importez le fichier Excel (.xlsx)</p>
        </div>
        <div class="p-3 bg-sky-50 rounded-xl text-sky-600">
            <i class="fas fa-database text-xl"></i>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-xl flex items-start gap-3 <?php echo $status === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
            <div class="text-sm font-medium flex-1"><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" id="importForm" class="space-y-6">
        
        <div class="bg-white p-4 rounded-xl border border-slate-200">
            <label for="type_import" class="block text-sm font-semibold text-slate-700 mb-2">1. Type de données à importer :</label>
            <div class="relative">
                <select name="type_import" id="type_import" class="block w-full rounded-xl border-slate-300 bg-slate-50/50 p-3 text-sm text-slate-800 focus:border-sky-500 focus:bg-white focus:ring-sky-500 transition-all appearance-none cursor-pointer" required>
                    <option value="" disabled selected>-- Choisissez la liste correspondante --</option>
                    <option value="districts">📁 Références Districts (ref_districts)</option>
                    <option value="zaps">📁 Références ZAPs (ref_zaps)</option>
                    <option value="etablissements">📁 Références Établissements (ref_etablissements)</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                    <i class="fas fa-chevron-down text-xs"></i>
                </div>
            </div>
        </div>

        <div id="dropzone" class="border-2 border-dashed border-slate-300 rounded-2xl bg-white p-8 text-center cursor-pointer hover:border-sky-500 hover:bg-sky-50/10 transition-all group relative">
            <input type="file" name="excel_file" id="fileInput" accept=".xlsx" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required>
            <div class="space-y-3">
                <div class="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto group-hover:bg-sky-100 text-slate-400 group-hover:text-sky-600 transition-colors">
                    <i class="fas fa-cloud-upload-alt text-2xl"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-700">2. Sélectionnez ou déposez votre fichier Excel (.xlsx)</p>
                    <p class="text-xs text-slate-400 mt-1">Le fichier doit correspondre scrupuleusement au type choisi ci-dessus</p>
                </div>
                <div id="fileTemplateBadge" class="hidden inline-flex items-center gap-1.5 px-3 py-1 bg-sky-100 text-sky-700 rounded-full text-xs font-semibold mx-auto">
                    <span id="fileNameDisplay">Fichier sélectionné</span>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" name="import_submit" id="submitBtn" class="bg-gradient-to-r from-sky-500 to-sky-600 text-white font-semibold text-sm px-6 py-3 rounded-xl shadow-md hover:from-sky-600 hover:to-sky-700 transition-all flex items-center gap-2">
                <i class="fas fa-upload"></i> Lancer la synchronisation
            </button>
        </div>
    </form>
</div>

<script>
    const fileInput = document.getElementById('fileInput');
    const dropzone = document.getElementById('dropzone');
    const fileBadge = document.getElementById('fileTemplateBadge');
    const fileNameDisplay = document.getElementById('fileNameDisplay');

    fileInput.addEventListener('change', function() {
        if(fileInput.files.length > 0) {
            fileNameDisplay.textContent = fileInput.files[0].name;
            fileBadge.classList.remove('hidden');
            dropzone.classList.add('border-sky-500', 'bg-sky-50/10');
        }
    });
</script>
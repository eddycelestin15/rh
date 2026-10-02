<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$im_connecte = $_SESSION['user_im']; // On récupère l'IM de l'agent 367058

// On cherche EXCLUSIVEMENT la photo de cet agent
$stmt = $pdo->prepare("SELECT photo FROM utilisateurs WHERE im = ?");
$stmt->execute([$im_connecte]);
$user = $stmt->fetch();

// On définit le chemin final
// utilisateurs.photo contient un chemin relatif a la racine de l'application :
// on teste son existence sur disque, on l'affiche tel quel comme URL.
$photoRelative = ltrim((string)($user['photo'] ?? ''), '/');
$photo_path = ($photoRelative !== '' && file_exists(APP_ROOT . '/' . $photoRelative))
    ? $photoRelative
    : 'images/default.png';
?>

<!-- HTML -->


<div class="max-w-md mx-auto bg-white rounded-[2rem] shadow-xl overflow-hidden mt-10 p-8">
    <div class="text-center">        
        <!-- Zone d'affichage de la photo -->
        <div class="relative inline-block">
            <img id="previewPhoto" src="<?php echo $photo_path . '?t=' . time(); ?>" 
     class="w-48 h-48 rounded-full object-cover border-4 border-sky-100 shadow-lg">
        </div>

        <div class="mt-8 space-y-4">
            <!-- Bouton Parcourir -->
            <label class="flex items-center justify-center gap-2 w-full p-4 bg-slate-50 text-slate-600 rounded-xl cursor-pointer hover:bg-slate-100 border-2 border-dashed border-slate-200 transition-all">
                <i class="fas fa-search"></i>
                <span id="fileName">Parcourir une photo...</span>
                <input type="file" id="fileInput" class="hidden" accept="image/*" onchange="updateFileName(this)">
            </label>

            <!-- Bouton Enregistrer -->
            <button onclick="uploadPhoto()" class="w-full py-4 bg-sky-500 text-white rounded-xl font-bold shadow-lg shadow-sky-200 hover:bg-sky-600 transition-all flex items-center justify-center gap-2">
                <i class="fas fa-save"></i> Enregistrer la photo
            </button>
        </div>
    </div>
</div>

<script>
window.updateFileName = function (input) {
    if (input.files && input.files[0]) {
        document.getElementById('fileName').textContent = input.files[0].name;
        
        // Prévisualisation immédiate
        const reader = new FileReader();
        reader.onload = (e) => document.getElementById('previewPhoto').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
};

window.uploadPhoto = function () {
    const fileInput = document.getElementById('fileInput');
    if (fileInput.files.length === 0) {
        Swal.fire('Attention', 'Veuillez choisir une photo avant d\'enregistrer', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('photo', fileInput.files[0]);

    Swal.fire({ title: 'Chargement...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

    fetch('actions/personnel/upload_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const newTimestamp = new Date().getTime();
            const newSrc = data.path + '?t=' + newTimestamp;

            // Mettre à jour l'image de prévisualisation
            document.getElementById('previewPhoto').src = newSrc;

            // Mettre à jour l'image dans le header
            const headerImg = document.getElementById('headerPhoto');
            if (headerImg) headerImg.src = newSrc;

            Swal.fire({
                icon: 'success',
                title: 'Photo enregistrée !',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Erreur', data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Erreur', 'Impossible de contacter le serveur', 'error');
    });
};
</script>
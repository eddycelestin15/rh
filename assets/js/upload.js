/**
 * Upload de photo de profil globale
 */
window.executerUploadGlobale = function() {
    const fileInput = document.getElementById('inputPhotoGlobale');
    if (!fileInput || fileInput.files.length === 0) return;

    const file = fileInput.files[0];

    const typesAutorises = ['image/jpeg', 'image/png', 'image/webp'];
    if (!typesAutorises.includes(file.type)) {
        Swal.fire({ icon: 'warning', title: 'Format invalide', text: 'Veuillez choisir une image au format JPG, PNG ou WEBP.' });
        fileInput.value = "";
        return;
    }

    if (file.size > 5 * 1024 * 1024) { // Max 5 Mo
        Swal.fire({ icon: 'warning', title: 'Fichier trop lourd', text: 'La taille de la photo ne doit pas dépasser 5 Mo.' });
        fileInput.value = "";
        return;
    }

    const formData = new FormData();
    formData.append('photo', file);
    
    const imgHeader = document.getElementById('headerPhoto');
    if (imgHeader) imgHeader.style.opacity = '0.5';

    fetch('actions/personnel/upload_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error(`Erreur serveur (${response.status})`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (imgHeader) {
                imgHeader.src = data.path + '?t=' + new Date().getTime();
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Photo mise à jour',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Erreur', text: data.message || 'Une erreur est survenue.' });
        }
    })
    .catch(err => {
        console.error("Erreur Upload:", err);
        Swal.fire({ icon: 'error', title: 'Erreur', text: err.message || "Erreur de connexion au serveur." });
    })
    .finally(() => {
        if (imgHeader) imgHeader.style.opacity = '1';
        fileInput.value = ""; 
    });
};
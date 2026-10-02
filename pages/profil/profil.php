<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$im = $_SESSION['user_im'];

// Récupération des données actuelles
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE im = ?");
$stmt->execute([$im]);
$user = $stmt->fetch();

$photo_path = !empty($user['photo']) ? $user['photo'] : 'https://ui-avatars.com/api/?name='.urlencode($user['nom']).'&background=0ea5e9&color=fff';
?>

<!-- Conteneur principal avec gestion stricte de la hauteur -->
<div class="max-w-5xl mx-auto h-[calc(100vh-140px)] flex flex-col gap-4 animate-fadeIn overflow-hidden">
    <form id="formUpdateProfil" class="flex-1 bg-white p-8 rounded-[0.5rem] shadow-sm border border-slate-100 flex flex-col justify-between overflow-hidden">
        
        <div class="grid grid-cols-2 gap-x-12 gap-y-6">
            <!-- Section GAUCHE : Coordonnées -->
            <div class="space-y-5">
                <h3 class="text-sm font-black text-sky-600 uppercase tracking-[0.2em] flex items-center gap-3 mb-6">
                    <span class="p-2 bg-sky-50 rounded-lg"><i class="fas fa-address-book"></i></span> 
                    Coordonnées
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-2 block tracking-wider">Numéro de Téléphone</label>
                        <div class="relative flex items-center">
                            <!-- Badge +261 ajouté -->
                            <div class="absolute left-4 flex items-center gap-2">
                                <span class="text-sky-500 font-black text-sm">+261</span>
                                <div class="w-[1px] h-4 bg-slate-200"></div>
                            </div>
                            <input type="text" name="telephone" value="<?php echo $user['telephone']; ?>" 
                                   placeholder="3x xx xxx xx"
                                   class="w-full pl-20 pr-5 py-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-sky-500 outline-none transition-all font-bold text-base text-slate-700 shadow-inner">
                        </div>
                    </div>

                    <div>
                        <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-2 block tracking-wider">Adresse Email Professionnelle</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-5 top-1/2 -translate-y-1/2 text-sky-400 text-base"></i>
                            <input type="email" name="email" value="<?php echo $user['email']; ?>" 
                                   class="w-full pl-12 pr-5 py-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-sky-500 outline-none transition-all font-bold text-base text-slate-700 shadow-inner">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section DROITE : Sécurité -->
            <div class="space-y-5">
                <h3 class="text-sm font-black text-rose-500 uppercase tracking-[0.2em] flex items-center gap-3 mb-6">
                    <span class="p-2 bg-rose-50 rounded-lg"><i class="fas fa-shield-alt"></i></span> 
                    Sécurité
                </h3>

                <div class="space-y-4">
                    <div class="relative">
                        <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-2 block tracking-wider">Nouveau Mot de Passe</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-5 top-1/2 -translate-y-1/2 text-rose-300 text-base"></i>
                            <input type="password" id="new_password" name="new_password" placeholder="••••••••••••" 
                                   class="w-full pl-12 p-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-rose-400 outline-none pr-14 transition-all text-base font-bold shadow-inner">
                            <button type="button" onclick="toggleVisibility('new_password', 'eye1')" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500 transition-colors">
                                <i id="eye1" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-2 block tracking-wider">Confirmer le changement</label>
                        <div class="relative">
                            <i class="fas fa-check-double absolute left-5 top-1/2 -translate-y-1/2 text-rose-300 text-base"></i>
                            <input type="password" id="confirm_password" placeholder="••••••••••••" 
                                   class="w-full pl-12 p-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-rose-400 outline-none pr-14 transition-all text-base font-bold shadow-inner">
                            <button type="button" onclick="toggleVisibility('confirm_password', 'eye2')" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500 transition-colors">
                                <i id="eye2" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pied de formulaire -->
        <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3 text-slate-400">
                <i class="fas fa-info-circle text-lg text-sky-500"></i>
                <p class="text-[11px] italic font-bold uppercase tracking-tight">
                    Laissez les mots de passe vides pour ne rien modifier
                </p>
            </div>
            <button type="submit" class="bg-slate-900 text-white px-10 py-4 rounded-2xl font-black text-sm uppercase tracking-[0.15em] hover:bg-sky-600 transition-all shadow-xl hover:shadow-sky-200 active:scale-95 flex items-center gap-3">
                Mettre à jour le profil <i class="fas fa-save text-base"></i>
            </button>
        </div>
    </form>
</div>

<script>
// Prévisualisation de l'image de profil
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview_img').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleVisibility(id, eyeId) {
    const input = document.getElementById(id);
    const eye = document.getElementById(eyeId);
    input.type = input.type === 'password' ? 'text' : 'password';
    eye.classList.toggle('fa-eye');
    eye.classList.toggle('fa-eye-slash');
}

document.getElementById('formUpdateProfil').addEventListener('submit', async function(e) {
    e.preventDefault();
    const pass = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;

    if(pass !== "" && pass !== confirm) {
        Swal.fire({ icon: 'warning', title: 'Attention', text: 'Les deux mots de passe doivent être identiques.', confirmButtonColor: '#f43f5e' });
        return;
    }

    const formData = new FormData(this);
    // On ajoute explicitement le fichier s'il a été sélectionné
    const fileInput = document.getElementById('photo_upload');
    if(fileInput.files[0]) {
        formData.append('photo', fileInput.files[0]);
    }

    try {
        const res = await fetch('actions/compte/update_profil_action.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.success) {
            Swal.fire({ icon: 'success', title: 'Profil mis à jour', text: 'Vos modifications ont été enregistrées.', timer: 2000, showConfirmButton: false });
            setTimeout(() => { loadPage('pages/profil/profil.php', 'Mon Profil'); }, 2000);
        } else {
            Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
        }
    } catch (err) {
        console.error("Erreur :", err);
    }
});
</script>
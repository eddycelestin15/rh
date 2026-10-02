<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';

$im = $_SESSION['user_im'];

// Récupération complète des coordonnées
$stmt = $pdo->prepare("SELECT nom, prenoms, photo, telephone, whatsapp, email FROM utilisateurs WHERE im = ?");
$stmt->execute([$im]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Nettoyage des numéros pour le pré-remplissage
$telephone_clean = preg_replace('/^\+261/', '', $user['telephone'] ?? '');
$whatsapp_clean  = preg_replace('/^\+261/', '', $user['whatsapp'] ?? '');
$email_clean     = $user['email'] ?? '';
?>

<div class="max-w-5xl mx-auto my-6 animate-fadeIn">
    <form id="formUpdateProfile" class="bg-white p-8 md:p-10 rounded-[1.5rem] shadow-sm border border-slate-100 flex flex-col gap-8">
        
        <!-- En-tête -->
        <div class="text-center space-y-2">
            <h3 class="text-sm font-black text-sky-600 uppercase tracking-[0.2em] flex justify-center items-center gap-3">
                <span class="p-2 bg-sky-50 rounded-lg"><i class="fas fa-user-cog"></i></span> 
                Mon Profil & Sécurité
            </h3>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Mettez à jour vos coordonnées et votre mot de passe</p>
        </div>

        <!-- GRILLE SUR 2 COLONNES -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 relative">

            <!-- COLONNE 1 : COORDONNÉES DE CONTACT -->
            <div class="space-y-5">
                <h4 class="text-xs font-black text-slate-500 uppercase tracking-wider border-b border-slate-100 pb-3 flex items-center gap-2">
                    <i class="fas fa-address-card text-sky-500 text-sm"></i> Coordonnées de contact
                </h4>

                <!-- Numéro Téléphone -->
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-1.5 block tracking-wider">Numéro de téléphone</label>
                    <div class="relative flex items-center">
                        <span class="absolute left-4 font-black text-slate-500 text-sm select-none z-10 flex items-center gap-2">
                            <i class="fas fa-phone text-slate-300"></i> +261
                        </span>
                        <input type="tel" id="telephone" name="telephone" placeholder="340000000" maxlength="9" 
                               value="<?= htmlspecialchars($telephone_clean) ?>" required
                               class="w-full pl-24 pr-4 py-3.5 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-sky-400 outline-none transition-all text-base font-bold shadow-inner text-slate-700">
                    </div>
                </div>

                <!-- Numéro WhatsApp -->
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-1.5 block tracking-wider">Numéro WhatsApp</label>
                    <div class="relative flex items-center">
                        <span class="absolute left-4 font-black text-emerald-600 text-sm select-none z-10 flex items-center gap-2">
                            <i class="fab fa-whatsapp text-emerald-500 text-base"></i> +261
                        </span>
                        <input type="tel" id="whatsapp" name="whatsapp" placeholder="340000000" maxlength="9" 
                               value="<?= htmlspecialchars($whatsapp_clean) ?>"
                               class="w-full pl-24 pr-4 py-3.5 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-emerald-400 outline-none transition-all text-base font-bold shadow-inner text-slate-700">
                    </div>
                </div>

                <!-- Adresse Email -->
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-1.5 block tracking-wider">Adresse Email</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-base"></i>
                        <input type="email" id="email" name="email" placeholder="exemple@domaine.com" 
                               value="<?= htmlspecialchars($email_clean) ?>" required
                               class="w-full pl-12 pr-4 py-3.5 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-sky-400 outline-none transition-all text-base font-bold shadow-inner text-slate-700">
                    </div>
                </div>
            </div>

            <!-- SÉPARATEUR VERTICAL -->
            <div class="hidden md:block absolute top-0 bottom-0 left-1/2 -translate-x-1/2 w-px bg-slate-100"></div>

            <!-- COLONNE 2 : CHANGEMENT DE MOT DE PASSE (FACULTATIF) -->
            <div class="space-y-5">
                <h4 class="text-xs font-black text-slate-500 uppercase tracking-wider border-b border-slate-100 pb-3 flex items-center gap-2">
                    <i class="fas fa-lock text-rose-500 text-sm"></i> Changement de mot de passe <span class="text-[10px] text-slate-400 font-bold lowercase">(facultatif)</span>
                </h4>

                <!-- Ancien Mot de Passe -->
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-1.5 block tracking-wider">Mot de passe actuel</label>
                    <div class="relative">
                        <i class="fas fa-key absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-base"></i>
                        <input type="password" id="old_password" name="old_password" placeholder="••••••••••••"
                               class="w-full pl-12 pr-14 py-3.5 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-sky-400 outline-none transition-all text-base font-bold shadow-inner text-slate-700">
                        <button type="button" onclick="toggleVisibility('old_password', 'eye0')" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-sky-500 transition-colors">
                            <i id="eye0" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Nouveau Mot de Passe -->
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-1.5 block tracking-wider">Nouveau mot de passe</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-5 top-1/2 -translate-y-1/2 text-rose-300 text-base"></i>
                        <input type="password" id="new_password" name="new_password" placeholder="••••••••••••"
                               class="w-full pl-12 pr-14 py-3.5 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-rose-400 outline-none transition-all text-base font-bold shadow-inner text-slate-700">
                        <button type="button" onclick="toggleVisibility('new_password', 'eye1')" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500 transition-colors">
                            <i id="eye1" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirmation -->
                <div>
                    <label class="text-[11px] font-black text-slate-400 uppercase ml-2 mb-1.5 block tracking-wider">Confirmer le nouveau mot de passe</label>
                    <div class="relative">
                        <i class="fas fa-check-double absolute left-5 top-1/2 -translate-y-1/2 text-rose-300 text-base"></i>
                        <input type="password" id="confirm_password" placeholder="••••••••••••"
                               class="w-full pl-12 pr-14 py-3.5 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-rose-400 outline-none transition-all text-base font-bold shadow-inner text-slate-700">
                        <button type="button" onclick="toggleVisibility('confirm_password', 'eye2')" class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500 transition-colors">
                            <i id="eye2" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <hr class="border-slate-100 my-2">

        <!-- Bouton de validation -->
        <div>
            <button type="submit" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-sm uppercase tracking-[0.15em] hover:bg-emerald-600 transition-all shadow-xl hover:shadow-emerald-100 active:scale-[0.98] flex items-center justify-center gap-3">
                <i class="fas fa-check-circle text-base"></i> Enregistrer les modifications
            </button>
        </div>
    </form>
</div>

<script>
window.toggleVisibility = function (id, eyeId) {
    const input = document.getElementById(id);
    const eye = document.getElementById(eyeId);
    input.type = input.type === 'password' ? 'text' : 'password';
    eye.classList.toggle('fa-eye');
    eye.classList.toggle('fa-eye-slash');
};

// Restriction de la saisie aux chiffres uniquement pour les téléphones
['telephone', 'whatsapp'].forEach(id => {
    const el = document.getElementById(id);
    if(el) {
        el.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
});

document.getElementById('formUpdateProfile').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const oldPass = document.getElementById('old_password').value.trim();
    const newPass = document.getElementById('new_password').value.trim();
    const confirm = document.getElementById('confirm_password').value.trim();

    // Vérification FACULTATIVE du mot de passe : uniquement si l'un des champs est rempli
    if (oldPass !== "" || newPass !== "" || confirm !== "") {
        if (!oldPass) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'Attention', 
                text: 'Veuillez renseigner votre mot de passe actuel pour valider le changement.', 
                confirmButtonColor: '#10b981'
            });
            return;
        }

        if (newPass === "") {
            Swal.fire({ 
                icon: 'warning', 
                title: 'Attention', 
                text: 'Veuillez saisir le nouveau mot de passe.', 
                confirmButtonColor: '#10b981'
            });
            return;
        }

        if (newPass !== confirm) {
            Swal.fire({ 
                icon: 'warning', 
                title: 'Attention', 
                text: 'La confirmation ne correspond pas au nouveau mot de passe.', 
                confirmButtonColor: '#10b981'
            });
            return;
        }

        if (newPass.length < 6) {
            Swal.fire({ 
                icon: 'info', 
                title: 'Mot de passe trop court', 
                text: 'Pour plus de sécurité, utilisez au moins 6 caractères.', 
                confirmButtonColor: '#10b981'
            });
            return;
        }
    }

    const formData = new FormData(this);

    try {
        // Correction de l'URL du fichier : update_profil_action.php (sans "e" à profil)
        const res = await fetch('actions/compte/update_profil_action.php', { method: 'POST', body: formData });
        
        // Sécurité en cas de réponse serveur non-JSON (404/500)
        const responseText = await res.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error("Réponse du serveur non-JSON :", responseText);
            throw new Error("Le serveur a renvoyé une réponse invalide (vérifiez le chemin du fichier update_profil_action.php).");
        }
        
        if (data.success) {
            Swal.fire({ 
                icon: 'success', 
                title: 'Profil mis à jour', 
                text: 'Vos informations ont été enregistrées avec succès.', 
                timer: 2000, 
                showConfirmButton: false 
            });

            // Rechargement/redirection
            setTimeout(() => { 
                if (typeof loadPage === 'function') {
                    let targetPage = 'pages/dashboards/dashboard_agent.php';
                    let title = 'Tableau de bord';

                    if (data.user_type === 'admin') {
                        targetPage = 'pages/dashboards/dashboard_admin.php';
                        title = 'Administration';
                    } else if (data.user_type === 'responsable') {
                        targetPage = 'pages/dashboards/dashboard_responsable.php';
                        title = 'Responsable';
                    }
                    loadPage(targetPage, title); 
                } else {
                    location.reload();
                }
            }, 2000);
        } else {
            Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
        }
    } catch (err) {
        console.error("Erreur technique :", err);
        Swal.fire({ icon: 'error', title: 'Erreur technique', text: err.message || 'Impossible de joindre le serveur.' });
    }
});
</script>
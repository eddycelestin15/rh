<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Agent</title>
    
    <!-- Favicon Personnalisé -->
    <link rel="icon" type="image/png" href="assets/images/grh.png">

    <!-- Styles Hybrides (CDN avec secours local) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" onerror="this.onerror=null;this.href='assets/vendor/fontawesome/css/all.min.css';">
    <script>
        if (!window.tailwind) {
            document.write('<script src="assets/js/tailwind.min.js"><\/script>');
        }
    </script>

    <style>
        body { overflow: hidden; height: 100vh; }
        .bg-main {
            background: linear-gradient(rgba(15, 23, 42, 0.82), rgba(15, 23, 42, 0.82)), 
                        url('assets/images/fond_authentification.png') no-repeat center center;
            background-size: cover;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(16px);
        }
        @keyframes fadeInRight { 
            from { opacity: 0; transform: translateX(40px); } 
            to { opacity: 1; transform: translateX(0); } 
        }
        .animate-form { animation: fadeInRight 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
    </style>
</head>
<body class="bg-main font-sans antialiased">

<div class="flex h-full w-full">
    <!-- Panneau Gauche : Emblème & Titre Institutionnel -->
    <div class="hidden lg:flex w-1/2 flex-col justify-center px-16 text-white relative z-10">
        <div class="mb-6 p-3 bg-white/10 backdrop-blur-md w-max rounded-3xl border border-white/20 shadow-2xl">
            <img src="assets/images/Embleme.png" alt="Emblème MEN" class="w-24 drop-shadow-2xl">
        </div>
        <h1 class="text-6xl font-black leading-tight uppercase tracking-tighter drop-shadow-md">
            Gestion <br><span class="text-sky-400">Ressources</span> <br>Humaines
        </h1>
        <div class="w-24 h-2 bg-gradient-to-r from-sky-400 to-blue-600 my-8 rounded-full shadow-lg"></div>
        <p class="text-2xl font-medium italic text-sky-100 opacity-90 tracking-wide">
            Portail d'inscription de votre espace carrière.
        </p>
    </div>

    <!-- Panneau Droit : Formulaire d'Inscription -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 bg-slate-950/30 backdrop-blur-sm">
        <div class="glass-card w-full max-w-2xl p-8 md:p-10 rounded-3xl shadow-2xl animate-form border border-white/60 relative">
            
            <!-- En-tête -->
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-gradient-to-tr from-sky-50 to-white rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-xl border border-sky-100 p-2 transform hover:rotate-3 transition-transform">
                    <img src="assets/images/grh.png" alt="Logo GRH" class="w-full h-full object-contain">
                </div>
                <h2 class="text-3xl font-black text-sky-700 tracking-tighter uppercase italic">Créer un compte Agent</h2>
                <p class="text-slate-500 text-xs font-bold tracking-[0.2em] uppercase mt-1">Remplissez vos informations</p>
            </div>

            <!-- Formulaire -->
            <form action="auth/process_inscription.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-3">
                
                <div class="space-y-1">
                    <label class="text-xs font-black text-slate-600 uppercase ml-2 tracking-wider">Nom</label>
                    <input type="text" name="nom" required placeholder="Ex: RAKOTO" class="w-full px-4 py-3 bg-slate-50/80 border-2 border-slate-200 rounded-2xl focus:bg-white focus:border-sky-600 outline-none transition-all font-bold text-base text-slate-800 uppercase shadow-inner">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-black text-slate-600 uppercase ml-2 tracking-wider">Prénoms</label>
                    <input type="text" name="prenoms" required placeholder="Ex: Jean Paul" class="w-full px-4 py-3 bg-slate-50/80 border-2 border-slate-200 rounded-2xl focus:bg-white focus:border-sky-600 outline-none transition-all font-bold text-base text-slate-800 shadow-inner">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-black text-slate-600 uppercase ml-2 tracking-wider">Numéro IM</label>
                    <div class="relative group">
                        <i class="fas fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-sky-600 text-lg transition-colors"></i>
                        <input type="text" name="im" required placeholder="Ex: 367000" class="w-full pl-12 pr-4 py-3 bg-slate-50/80 border-2 border-slate-200 rounded-2xl focus:bg-white focus:border-sky-600 outline-none transition-all font-bold text-base text-sky-700 shadow-inner">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-black text-slate-600 uppercase ml-2 tracking-wider">Téléphone</label>
                    <div class="relative flex items-center group">
                        <span class="absolute left-4 text-sky-700 font-black text-base">+261</span>
                        <input type="tel" name="telephone" required placeholder="3x xx xxx xx" maxlength="9" class="w-full pl-16 pr-4 py-3 bg-slate-50/80 border-2 border-slate-200 rounded-2xl focus:bg-white focus:border-sky-600 outline-none transition-all font-bold text-base text-slate-800 shadow-inner">
                    </div>
                </div>

                <div class="md:col-span-2 space-y-1">
                    <label class="text-xs font-black text-slate-600 uppercase ml-2 tracking-wider">Adresse Email <span class="lowercase italic font-normal text-slate-400">(facultatif)</span></label>
                    <input type="email" name="email" placeholder="exemple@mail.com" class="w-full px-4 py-3 bg-slate-50/80 border-2 border-slate-200 rounded-2xl focus:bg-white focus:border-sky-600 outline-none transition-all font-bold text-base text-slate-800 shadow-inner">
                </div>

                <div class="space-y-1 relative group">
                    <label class="text-xs font-black text-slate-600 uppercase ml-2 tracking-wider">Mot de passe</label>
                    <div class="relative">
                        <input type="password" id="mdp" name="mdp" required placeholder="••••••••" class="w-full px-4 pr-12 py-3 bg-slate-50/80 border-2 border-slate-200 rounded-2xl focus:bg-white focus:border-sky-600 outline-none transition-all font-bold text-base text-slate-800 shadow-inner">
                        <button type="button" onclick="togglePassword('mdp', 'eye1')" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-sky-700 transition-colors">
                            <i id="eye1" class="fas fa-eye text-base"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-1 relative group">
                    <label class="text-xs font-black text-slate-600 uppercase ml-2 tracking-wider">Confirmer mot de passe</label>
                    <div class="relative">
                        <input type="password" id="mdp_confirm" name="mdp_confirm" required placeholder="••••••••" class="w-full px-4 pr-12 py-3 bg-slate-50/80 border-2 border-slate-200 rounded-2xl focus:bg-white focus:border-sky-600 outline-none transition-all font-bold text-base text-slate-800 shadow-inner">
                        <button type="button" onclick="togglePassword('mdp_confirm', 'eye2')" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-sky-700 transition-colors">
                            <i id="eye2" class="fas fa-eye text-base"></i>
                        </button>
                    </div>
                </div>

                <div class="md:col-span-2 pt-2">
                    <button type="submit" class="w-full bg-slate-900 hover:bg-sky-700 text-white py-3.5 rounded-2xl font-black text-lg uppercase tracking-wider shadow-xl shadow-slate-900/20 hover:shadow-sky-600/30 transition-all transform active:scale-[0.98] flex items-center justify-center gap-2">
                        <span>S'enregistrer maintenant</span>
                        <i class="fas fa-paper-plane text-base"></i>
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center border-t border-slate-100 pt-4">
                <a href="auth/login_agent.php" class="text-sky-700 hover:text-sky-900 font-black text-xs uppercase tracking-wider hover:underline transition">
                    Déjà inscrit ? Se connecter ici
                </a>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 avec fallback local -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" onerror="this.onerror=null;this.src='assets/js/sweetalert2.all.min.js';"></script>
<link rel="stylesheet" href="assets/css/tailwind.css">
<script>
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // Capture des messages de notification d'URL
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has('success') && urlParams.get('success') === 'registered') {
        Swal.fire({
            icon: 'success',
            title: 'Compte créé avec succès !',
            text: 'Redirection vers la page de connexion...',
            timer: 2500,
            timerProgressBar: true,
            showConfirmButton: false
        }).then(() => {
            window.location.href = 'auth/login_agent.php';
        });
    }

    if (urlParams.has('error')) {
        let errorMessage = 'Une erreur est survenue lors de l\'inscription.';
        if (urlParams.get('error') === 'password_mismatch') {
            errorMessage = 'Les mots de passe ne correspondent pas.';
        } else if (urlParams.get('error') === 'invalid_phone') {
            errorMessage = 'Le numéro de téléphone doit comporter 9 chiffres.';
        } else if (urlParams.get('error') === 'im_exists') {
            errorMessage = 'Cet IM est déjà inscrit sur la plateforme.';
        }

        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: errorMessage
        });
    }
</script>
</body>
</html>
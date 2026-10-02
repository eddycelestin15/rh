<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Agent — Gestion de Carrière Agent | MEN</title>
    
    <!-- Favicon Personnalisé -->
    <link rel="icon" type="image/png" href="assets/images/grh.png">

    <!-- Font & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" onerror="this.onerror=null;this.href='assets/vendor/fontawesome/css/all.min.css';">
    <script>
        if (!window.tailwind) {
            document.write('<script src="assets/js/tailwind.min.js"><\/script>');
        }
    </script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #E6E4DD;
        }

        /* Ajustement du fond du panneau droit avec transparence et effet verre dépoli */
        .right-panel-bg {
            background: linear-gradient(180deg, rgba(250, 248, 242, 0.82) 0%, rgba(245, 241, 230, 0.88) 100%);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .btn-yellow {
            background-color: #F8D053;
            color: #2D271E;
        }

        .btn-yellow:hover {
            background-color: #EBC03F;
        }

        .input-pill {
            background-color: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(239, 236, 230, 0.9);
            border-radius: 9999px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            backdrop-filter: blur(4px);
        }

        .input-pill:focus-within {
            border-color: #E0C767;
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(248, 208, 83, 0.25);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 lg:p-8 antialiased">

    <!-- Container Principal Flottant avec l'image globale en fond sous-jacent -->
    <div class="w-full max-w-6xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col lg:flex-row min-h-[660px] border border-stone-200/50 relative bg-stone-900">
        
        <!-- Image de fond globale étalée sur TOUT le conteneur principal -->
        <img src="assets/images/fond.jpg" 
             alt="Fond MEN Madagascar" 
             class="absolute inset-0 w-full h-full object-cover object-left opacity-95">

        <!-- CÔTÉ GAUCHE : VISUEL & MÉTIERS DE CARRIÈRE RH -->
        <div class="w-full lg:w-1/2 relative min-h-[360px] lg:min-h-full overflow-hidden p-8 flex flex-col justify-between order-2 lg:order-1 z-10">

            <!-- Calque dégradé plus léger pour préserver les couleurs de l'arrière-plan -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent -z-10"></div>

            <!-- Logo MEN Décoratif -->
            <div class="relative z-10 flex justify-start">
                <div class="w-12 h-12 rounded-full bg-white/90 backdrop-blur-md flex items-center justify-center text-stone-700 shadow-lg p-2 hover:bg-white transition">
                    <img src="assets/images/Logo_men.png" alt="Logo MEN" class="w-full h-full object-contain">
                </div>
            </div>

            <!-- Bas de page Gauche -->
            <div class="relative z-10 pt-3 border-t border-white/10 flex items-center justify-center lg:justify-start">
                <div class="inline-flex items-center gap-2 text-amber-300 text-xs font-bold tracking-wide uppercase">
                    <i class="fas fa-user-plus text-amber-400"></i>
                    <span>Création de compte et suivi de dossier agent</span>
                </div>
            </div>
        </div>

        <!-- CÔTÉ DROIT : FORMULAIRE D'INSCRIPTION (Translucide avec effet Frosted Glass) -->
        <div class="w-full lg:w-1/2 right-panel-bg p-8 sm:p-10 lg:p-12 flex flex-col justify-between relative order-1 lg:order-2 z-10 border-l border-white/30">
            
            <!-- Contenu central du formulaire -->
            <div class="my-auto py-4 max-w-lg w-full mx-auto text-center">
                
                <!-- Logo GRH Centré -->
                <div class="w-20 h-20 bg-white/90 backdrop-blur-md rounded-3xl flex items-center justify-center mx-auto mb-3 shadow-md border border-stone-200/60 p-3 transform hover:scale-105 transition-transform duration-300">
                    <img src="assets/images/grh.png" alt="Logo GRH" class="w-full h-full object-contain">
                </div>

                <!-- En-tête Titre -->
                <div class="mb-5">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-stone-800 tracking-tight mb-1">
                        Inscription Agent
                    </h1>
                    <p class="text-stone-600 text-xs font-medium">
                        Remplissez vos informations pour créer votre espace personnel
                    </p>
                </div>

                <!-- Formulaire d'Inscription -->
                <form action="auth/register_process.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3 text-left">
                    
                    <!-- Champ IM -->
                    <div class="md:col-span-2 space-y-1">
                        <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Numéro Matricule (IM)</label>
                        <div class="input-pill flex items-center px-5 py-3 transition-all">
                            <input type="text" name="im" required placeholder="Ex: 415263"
                                   class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold placeholder:text-stone-400 placeholder:font-normal">
                        </div>
                    </div>

                    <!-- Champ Nom -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Nom</label>
                        <div class="input-pill flex items-center px-5 py-3 transition-all">
                            <input type="text" name="nom" required placeholder="NOM"
                                   class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold uppercase placeholder:text-stone-400 placeholder:font-normal placeholder:normal-case">
                        </div>
                    </div>

                    <!-- Champ Prénoms -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Prénoms</label>
                        <div class="input-pill flex items-center px-5 py-3 transition-all">
                            <input type="text" name="prenoms" required placeholder="Prénoms"
                                   class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold placeholder:text-stone-400 placeholder:font-normal">
                        </div>
                    </div>

                    <!-- Champ Téléphone -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Téléphone</label>
                        <div class="input-pill flex items-center px-4 py-3 transition-all gap-1.5">
                            <span class="text-stone-700 font-bold text-xs">+261</span>
                            <input type="text" name="telephone" required placeholder="3x xx xxx xx" maxlength="9"
                                   class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold placeholder:text-stone-400 placeholder:font-normal">
                        </div>
                    </div>

                    <!-- Champ Email -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Email</label>
                        <div class="input-pill flex items-center px-5 py-3 transition-all">
                            <input type="email" name="email" required placeholder="votre@gmail.mg"
                                   class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold placeholder:text-stone-400 placeholder:font-normal">
                        </div>
                    </div>

                    <!-- Champ Mot de passe -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Mot de passe</label>
                        <div class="input-pill flex items-center px-5 py-3 transition-all relative">
                            <input type="password" id="mdp" name="mdp" required placeholder="••••••••"
                                   class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold placeholder:text-stone-400">
                            <button type="button" onclick="togglePass('mdp', 'eyeIcon1')" class="text-stone-500 hover:text-stone-700 pl-2">
                                <i id="eyeIcon1" class="fas fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Champ Confirmation -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Confirmation</label>
                        <div class="input-pill flex items-center px-5 py-3 transition-all relative">
                            <input type="password" id="mdp_confirm" name="mdp_confirm" required placeholder="••••••••"
                                   class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold placeholder:text-stone-400">
                            <button type="button" onclick="togglePass('mdp_confirm', 'eyeIcon2')" class="text-stone-500 hover:text-stone-700 pl-2">
                                <i id="eyeIcon2" class="fas fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Bouton Valider l'inscription -->
                    <div class="md:col-span-2 pt-2">
                        <button type="submit" class="w-full btn-yellow py-3.5 rounded-full font-bold text-sm tracking-wide shadow-md hover:shadow-lg transition-all active:scale-[0.99] flex items-center justify-center gap-2">
                            <span>Valider l'inscription</span>
                            <i class="fas fa-arrow-right text-xs"></i>
                        </button>
                    </div>

                    <!-- Lien de retour vers la connexion -->
                    <div class="md:col-span-2 pt-1 text-center">
                        <a href="auth/login_agent.php" class="flex items-center justify-center gap-2 py-3 px-4 rounded-full border border-stone-300/80 bg-white/80 backdrop-blur-md hover:bg-white text-stone-800 font-semibold text-xs transition shadow-sm w-full">
                            <i class="fas fa-arrow-left text-stone-500 text-xs"></i>
                            Retour à la connexion
                        </a>
                    </div>
                </form>
            </div>

            <div class="pt-1"></div>
        </div>

    </div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" onerror="this.onerror=null;this.src='assets/js/sweetalert2.all.min.js';"></script>
<link rel="stylesheet" href="assets/css/tailwind.css">
<script>
    function togglePass(inputId, iconId) {
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

    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has('success') && urlParams.get('success') === 'registered_pending') {
        const im = urlParams.get('im') || '';
        Swal.fire({
            icon: 'info',
            title: 'Code de validation envoyé !',
            text: 'Un code à 6 chiffres vous a été envoyé par Email et WhatsApp. Veuillez le saisir pour activer votre compte.',
            confirmButtonColor: '#F8D053',
            confirmButtonText: 'Saisir le code'
        }).then(() => {
            window.location.href = 'auth/verify_code.php?im=' + encodeURIComponent(im);
        });
    }

    if (urlParams.has('error')) {
        let errorMessage = 'Une erreur est survenue lors de l\'inscription.';
        const errorType = urlParams.get('error');
        if (errorType === 'password_mismatch') {
            errorMessage = 'Les mots de passe ne correspondent pas.';
        } else if (errorType === 'invalid_phone') {
            errorMessage = 'Le numéro de téléphone doit comporter exactement 9 chiffres.';
        } else if (errorType === 'im_exists') {
            errorMessage = 'Cet IM est déjà associé à un compte.';
        }

        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: errorMessage,
            confirmButtonColor: '#2D271E',
            confirmButtonText: 'Réessayer'
        });
    }
</script>
</body>
</html>
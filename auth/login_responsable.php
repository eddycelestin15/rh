<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Responsable — Gestion de Carrière Agent | MEN</title>
    
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

        .btn-indigo {
            background-color: #4F46E5;
            color: #FFFFFF;
        }

        .btn-indigo:hover {
            background-color: #4338CA;
        }

        .input-pill {
            background-color: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(239, 236, 230, 0.9);
            border-radius: 9999px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            backdrop-filter: blur(4px);
        }

        .input-pill:focus-within {
            border-color: #818CF8;
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
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

        <!-- CÔTÉ GAUCHE : VISUEL & VALIDATION DES DOSSIERS RH -->
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
                <div class="inline-flex items-center gap-2 text-indigo-300 text-xs font-bold tracking-wide uppercase">
                    <i class="fas fa-shield-alt text-indigo-400"></i>
                    <span>Espace de Validation et Suivi des Dossiers RH</span>
                </div>
            </div>
        </div>

        <!-- CÔTÉ DROIT : FORMULAIRE D'AUTHENTIFICATION (Translucide avec effet Frosted Glass) -->
        <div class="w-full lg:w-1/2 right-panel-bg p-8 sm:p-10 lg:p-12 flex flex-col justify-between relative order-1 lg:order-2 z-10 border-l border-white/30">
            
            <!-- Contenu central du formulaire -->
            <div class="my-auto py-6 max-w-md w-full mx-auto text-center">
                
                <!-- Logo GRH Centré -->
                <div class="w-24 h-24 bg-white/90 backdrop-blur-md rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-md border border-stone-200/60 p-3 transform hover:scale-105 transition-transform duration-300">
                    <img src="assets/images/grh.png" alt="Logo GRH" class="w-full h-full object-contain">
                </div>

                <!-- En-tête Titre (Même style exact que login_agent) -->
                <div class="mb-6">
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-stone-800 tracking-tight mb-2">
                        Espace Responsable
                    </h1>
                    <p class="text-stone-600 text-xs font-medium">
                        Direction et validation des actes administratifs et avancements
                    </p>
                </div>

                <!-- Formulaire de Connexion -->
                <form action="auth/auth_process.php" method="POST" class="space-y-4 text-left">
                    <input type="hidden" name="type_connexion" value="responsable">

                    <!-- Champ IM -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Numéro Matricule (IM)</label>
                        <div class="input-pill flex items-center px-5 py-3.5 transition-all">
                            <input type="text" name="im" required placeholder="Ex: 367060"
                                   class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold placeholder:text-stone-400 placeholder:font-normal">
                        </div>
                    </div>

                    <!-- Champ Mot de passe -->
                    <div class="space-y-1">
                        <div class="flex justify-between items-center px-4">
                            <label class="text-[11px] font-bold text-stone-600 tracking-wide uppercase">Mot de passe</label>
                            <a href="auth/forgot_password.php?type=responsable" class="text-xs font-bold text-indigo-700 hover:text-indigo-800 transition">Mot de passe Oublié ?</a>
                        </div>
                        <div class="input-pill flex items-center px-5 py-3.5 transition-all relative">
                            <input type="password" id="mdp_resp" name="mdp" required placeholder="••••••••"
                                   class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold placeholder:text-stone-400">
                            <button type="button" onclick="togglePass('mdp_resp', 'eyeResp')" class="text-stone-500 hover:text-stone-700 pl-2">
                                <i id="eyeResp" class="fas fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Bouton Connexion -->
                    <button type="submit" class="w-full btn-indigo py-4 rounded-full font-bold text-sm tracking-wide shadow-md hover:shadow-lg transition-all active:scale-[0.99] mt-3 flex items-center justify-center gap-2">
                        <span>Accéder au portail responsable</span>
                        <i class="fas fa-shield-alt text-xs"></i>
                    </button>

                    <!-- Création / Retour Agent -->
                    <div class="pt-1">
                        <a href="auth/login_agent.php" class="flex items-center justify-center gap-2 py-3 px-4 rounded-full border border-stone-300/80 bg-white/80 backdrop-blur-md hover:bg-white text-stone-800 font-semibold text-xs transition shadow-sm w-full">
                            <i class="fas fa-arrow-left text-stone-500 text-xs"></i>
                            Retour au portail Agent
                        </a>
                    </div>
                </form>
            </div>

            <div class="pt-2"></div>
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

    if (urlParams.has('success')) {
        let msg = 'Opération réussie.';
        const successType = urlParams.get('success');
        if (successType === 'password_reset') msg = 'Mot de passe réinitialisé avec succès.';
        
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: msg,
            confirmButtonColor: '#4F46E5',
            confirmButtonText: 'D\'accord'
        });
    }

    if (urlParams.has('error')) {
        let title = 'Erreur d\'accès';
        let msg = 'Une erreur est survenue lors de la connexion.';
        const errorType = urlParams.get('error');

        if (errorType === 'empty') {
            title = 'Champs requis';
            msg = 'Veuillez remplir le matricule et le mot de passe.';
        } else if (errorType === 'password' || errorType === 'not_found' || errorType === 'invalid_credentials') {
            title = 'Identifiants incorrects';
            msg = 'Numéro matricule (IM) ou mot de passe incorrect.';
        } else if (errorType === 'access_denied') {
            title = 'Accès refusé';
            msg = 'Ce compte ne dispose pas des droits d\'accès à l\'Espace Responsable.';
        }
        
        Swal.fire({
            icon: 'error',
            title: title,
            text: msg,
            confirmButtonColor: '#4338CA',
            confirmButtonText: 'Réessayer'
        });
    }
</script>
</body>
</html>
<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - MEN Madagascar</title>
    
    <!-- Favicon Personnalisé (Remplace le logo WampServer) -->
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
            background: linear-gradient(rgba(15, 23, 42, 0.90), rgba(15, 23, 42, 0.92)), 
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
    <!-- GAUCHE : TEXTE MINISTERE -->
    <div class="hidden lg:flex w-1/2 flex-col justify-center px-16 text-white relative z-10">
        <div class="mb-8 p-3 bg-white/10 backdrop-blur-md w-max rounded-3xl border border-white/20 shadow-2xl">
            <img src="assets/images/Embleme.png" alt="Emblème MEN" class="w-24 drop-shadow-2xl">
        </div>
        <h1 class="text-6xl font-black leading-tight uppercase tracking-tighter text-sky-400 drop-shadow-md">
            PANNEAU <br><span class="text-white">ADMINISTRATEUR</span>
        </h1>
        <div class="w-24 h-2 bg-gradient-to-r from-sky-400 to-slate-500 my-8 rounded-full shadow-lg"></div>
        <p class="text-2xl font-medium italic text-sky-100 opacity-90 tracking-wide">
            Gestion sécurisée du système RH.
        </p>
    </div>

    <!-- DROITE : FORMULAIRE -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 bg-slate-950/40 backdrop-blur-sm">
        <div class="glass-card w-full max-w-md p-8 sm:p-10 rounded-3xl shadow-2xl animate-form border border-white/60 relative">
            
            <!-- En-tête avec Logo Personnalisé -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-tr from-slate-100 to-white rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-xl border border-slate-200 p-2 transform hover:scale-105 transition-transform">
                    <img src="assets/images/grh.png" alt="Logo GRH" class="w-full h-full object-contain">
                </div>
                <h2 class="text-3xl font-black text-slate-900 tracking-tighter uppercase italic leading-tight">Espace Admin</h2>
                <p class="text-sky-700 text-xs font-bold tracking-[0.2em] uppercase mt-1">Accès Haute Sécurité</p>
            </div>

            <!-- Formulaire -->
            <form action="auth/auth_process.php" method="POST" class="space-y-5">
                <input type="hidden" name="type_connexion" value="admin">

                <div class="space-y-1.5">
                    <label class="text-xs font-black text-slate-600 uppercase ml-2 tracking-wider">Identifiant (IM)</label>
                    <div class="relative group">
                        <i class="fas fa-fingerprint absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-sky-600 text-lg transition-colors"></i>
                        <input type="text" name="im" required placeholder="Ex: ADMIN"
                               class="w-full pl-12 pr-4 py-3.5 bg-slate-50/80 rounded-2xl border-2 border-slate-200 focus:bg-white focus:border-sky-600 outline-none transition-all font-bold text-lg text-slate-800 shadow-inner">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex justify-between items-center px-2">
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Mot de passe</label>
                        <a href="forgot_password.php" class="text-xs font-bold text-sky-600 hover:text-sky-800 hover:underline uppercase transition">Oublié ?</a>
                    </div>
                    <div class="relative group">
                        <i class="fas fa-shield-alt absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-sky-600 text-lg transition-colors"></i>
                        <input type="password" id="mdp_admin" name="mdp" required placeholder="••••••••"
                               class="w-full pl-12 pr-12 py-3.5 bg-slate-50/80 rounded-2xl border-2 border-slate-200 focus:bg-white focus:border-sky-600 outline-none transition-all font-bold text-lg text-slate-800 shadow-inner">
                        <button type="button" onclick="togglePass('mdp_admin', 'eyeAdmin')" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-sky-700 p-2 transition-colors">
                            <i id="eyeAdmin" class="fas fa-eye text-lg"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-slate-900 hover:bg-sky-700 text-white py-4 rounded-2xl font-black text-lg uppercase tracking-wider shadow-xl shadow-slate-900/30 hover:shadow-sky-700/30 transition-all transform active:scale-[0.98] mt-2 flex items-center justify-center gap-3">
                    <span>Se connecter</span>
                    <i class="fas fa-arrow-right text-sm"></i>
                </button>
            </form>

            <div class="mt-8 text-center pt-5 border-t border-slate-100">
                <a href="auth/login_agent.php" class="text-xs font-bold text-slate-500 hover:text-sky-700 uppercase tracking-wider italic transition flex items-center justify-center gap-2">
                    <i class="fas fa-arrow-left"></i> Retour au Portail Agent
                </a>
            </div>
        </div>
    </div>
</div>

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
</script>
</body>
</html>
<?php 
require_once __DIR__ . '/../includes/bootstrap.php'; 
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/alertes_notifications.php';

$type = (isset($_GET['type']) && $_GET['type'] === 'responsable') ? 'responsable' : 'agent';
$step = 1; 
$im = '';
$message_erreur = '';
$code_envoye = false;
$blocage_contact = false; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $im = strtoupper(trim($_POST['im'] ?? ''));

    if ($action === 'request_code') {
        // --- ÉTAPE 1 : Génération et envoi du code avec limitation à 3 essais ---
        if (!empty($im)) {
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE im = ? AND (type_compte = ? OR (type_compte = '' AND ? = 'agent'))");
            $stmt->execute([$im, $type, $type]);
            $user = $stmt->fetch();

            if ($user) {
                // Vérification du nombre de demandes de réinitialisation
                $tentatives = isset($user['reset_attempts']) ? (int)$user['reset_attempts'] : 0;

                if ($tentatives >= 3) {
                    $blocage_contact = true;
                } else {
                    $nouvelles_tentatives = $tentatives + 1;
                    $reset_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', time() + 900);

                    $update = $pdo->prepare("UPDATE utilisateurs SET reset_code = ?, reset_expires_at = ?, reset_attempts = ? WHERE im = ?");
                    $update->execute([$reset_code, $expires, $nouvelles_tentatives, $im]);

                    sendResetCode($user['email'], $user['telephone'], $user['prenoms'] ?: $user['nom'], $reset_code);
                    
                    $code_envoye = true;
                    $step = 2;
                }
            } else {
                $message_erreur = "Matricule introuvable pour cet espace.";
            }
        } else {
            $message_erreur = "Veuillez entrer votre matricule IM.";
        }

    } elseif ($action === 'reset_password') {
        // --- ÉTAPE 2 : Validation du code et changement de mot de passe ---
        $code = trim($_POST['code'] ?? '');
        $new_mdp = $_POST['new_mdp'] ?? '';
        $confirm_mdp = $_POST['confirm_mdp'] ?? '';

        if ($new_mdp !== $confirm_mdp) {
            $message_erreur = "Les mots de passe ne correspondent pas.";
            $step = 2; 
        } else {
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE im = ? AND reset_code = ?");
            $stmt->execute([$im, $code]);
            $user = $stmt->fetch();

            if ($user) {
                $expiration = strtotime($user['reset_expires_at']);
                if ($expiration && time() <= $expiration) {
                    $hash = password_hash($new_mdp, PASSWORD_DEFAULT);
                    
                    $update = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ?, reset_code = NULL, reset_expires_at = NULL, reset_attempts = 0 WHERE im = ?");
                    $update->execute([$hash, $im]);

                    $redirect = ($type === 'responsable') ? 'auth/login_responsable.php' : 'auth/login_agent.php';
                    rediriger($redirect . "?success=password_reset");
                } else {
                    $message_erreur = "Le code a expiré. Veuillez en demander un nouveau.";
                    $step = 1;
                }
            } else {
                $message_erreur = "Code de réinitialisation incorrect.";
                $step = 2; 
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <?php echo base_tag(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation mot de passe — Gestion de Carrière | MEN</title>
    
    <link rel="icon" type="image/png" href="assets/images/grh.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        if (!window.tailwind) {
            document.write('<script src="assets/js/tailwind.min.js"><\/script>');
        }
    </script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #E6E4DD; }
        .right-panel-bg {
            background: linear-gradient(180deg, rgba(250, 248, 242, 0.82) 0%, rgba(245, 241, 230, 0.88) 100%);
            backdrop-filter: blur(16px);
        }
        .btn-yellow { background-color: #F8D053; color: #2D271E; }
        .btn-yellow:hover { background-color: #EBC03F; }
        .btn-indigo { background-color: #4F46E5; color: #FFFFFF; }
        .btn-indigo:hover { background-color: #4338CA; }
        .input-pill {
            background-color: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(239, 236, 230, 0.9);
            border-radius: 9999px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        }
        .input-pill:focus-within {
            border-color: <?php echo $type === 'responsable' ? '#818CF8' : '#E0C767'; ?>;
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px <?php echo $type === 'responsable' ? 'rgba(79, 70, 229, 0.2)' : 'rgba(248, 208, 83, 0.25)'; ?>;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 lg:p-8 antialiased">

    <div class="w-full max-w-6xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col lg:flex-row min-h-[660px] border border-stone-200/50 relative bg-stone-900">
        <img src="assets/images/fond.jpg" alt="Fond MEN Madagascar" class="absolute inset-0 w-full h-full object-cover object-left opacity-95">

        <!-- Côté Gauche -->
        <div class="w-full lg:w-1/2 relative min-h-[360px] lg:min-h-full overflow-hidden p-8 flex flex-col justify-between order-2 lg:order-1 z-10">
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent -z-10"></div>
            <div class="relative z-10 flex justify-start">
                <div class="w-12 h-12 rounded-full bg-white/90 backdrop-blur-md flex items-center justify-center text-stone-700 shadow-lg p-2">
                    <img src="assets/images/Logo_men.png" alt="Logo MEN" class="w-full h-full object-contain">
                </div>
            </div>
            <div class="relative z-10 pt-3 border-t border-white/10 flex items-center justify-center lg:justify-start">
                <div class="inline-flex items-center gap-2 <?php echo $type === 'responsable' ? 'text-indigo-300' : 'text-amber-300'; ?> text-xs font-bold tracking-wide uppercase">
                    <i class="fas fa-key"></i>
                    <span>Récupération sécurisée du compte <?php echo ucfirst($type); ?></span>
                </div>
            </div>
        </div>

        <!-- Côté Droit : Formulaire -->
        <div class="w-full lg:w-1/2 right-panel-bg p-8 sm:p-10 lg:p-12 flex flex-col justify-between relative order-1 lg:order-2 z-10 border-l border-white/30">
            <div class="my-auto py-6 max-w-md w-full mx-auto text-center">
                
                <div class="w-24 h-24 bg-white/90 backdrop-blur-md rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-md border border-stone-200/60 p-3">
                    <img src="assets/images/grh.png" alt="Logo GRH" class="w-full h-full object-contain">
                </div>

                <div class="mb-6">
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-stone-800 tracking-tight mb-2">
                        Mot de passe oublié ?
                    </h1>
                    <p class="text-stone-600 text-xs font-medium">
                        Espace <?php echo ucfirst($type); ?> — Saisissez votre code à 6 chiffres transmis par Email ou WhatsApp
                    </p>
                </div>

                <?php if ($step === 1): ?>
                    <form action="auth/forgot_password.php?type=<?php echo $type; ?>" method="POST" class="space-y-4 text-left">
                        <input type="hidden" name="action" value="request_code">
                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Numéro Matricule (IM)</label>
                            <div class="input-pill flex items-center px-5 py-3.5 transition-all">
                                <input type="text" name="im" value="<?php echo htmlspecialchars($im); ?>" required placeholder="Ex: 367060"
                                       class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold placeholder:text-stone-400">
                            </div>
                        </div>

                        <button type="submit" class="w-full <?php echo $type === 'responsable' ? 'btn-indigo' : 'btn-yellow'; ?> py-4 rounded-full font-bold text-sm tracking-wide shadow-md transition-all flex items-center justify-center gap-2 mt-3">
                            <span>Envoyer le code de réinitialisation</span>
                            <i class="fas fa-paper-plane text-xs"></i>
                        </button>

                        <div class="pt-1">
                            <a href="<?php echo $type === 'responsable' ? 'auth/login_responsable.php' : 'auth/login_agent.php'; ?>" class="flex items-center justify-center gap-2 py-3 px-4 rounded-full border border-stone-300/80 bg-white/80 hover:bg-white text-stone-800 font-semibold text-xs transition shadow-sm w-full">
                                <i class="fas fa-arrow-left text-stone-500 text-xs"></i>
                                Retour à la connexion
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <form action="auth/forgot_password.php?type=<?php echo $type; ?>" method="POST" class="space-y-4 text-left">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="im" value="<?php echo htmlspecialchars($im); ?>">

                        <div class="p-3.5 bg-amber-50/90 backdrop-blur-md rounded-2xl border border-amber-200/80 text-xs text-amber-900 font-medium text-center shadow-sm">
                            Code de confirmation envoyé pour le matricule : <span class="font-extrabold"><?php echo htmlspecialchars($im); ?></span>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Code reçu (6 chiffres)</label>
                            <div class="input-pill flex items-center px-5 py-3 transition-all">
                                <input type="text" name="code" required maxlength="6" placeholder="123456" autocomplete="off"
                                       class="w-full bg-transparent outline-none text-stone-800 text-center text-lg font-bold tracking-[0.2em]">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Nouveau mot de passe</label>
                            <div class="input-pill flex items-center px-5 py-3 transition-all relative">
                                <input type="password" id="new_mdp" name="new_mdp" required placeholder="••••••••" class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold">
                                <button type="button" onclick="togglePass('new_mdp', 'eyeIcon1')" class="text-stone-500 pl-2"><i id="eyeIcon1" class="fas fa-eye text-xs"></i></button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[11px] font-bold text-stone-600 ml-4 tracking-wide uppercase">Confirmer le mot de passe</label>
                            <div class="input-pill flex items-center px-5 py-3 transition-all relative">
                                <input type="password" id="confirm_mdp" name="confirm_mdp" required placeholder="••••••••" class="w-full bg-transparent outline-none text-stone-800 text-sm font-semibold">
                                <button type="button" onclick="togglePass('confirm_mdp', 'eyeIcon2')" class="text-stone-500 pl-2"><i id="eyeIcon2" class="fas fa-eye text-xs"></i></button>
                            </div>
                        </div>

                        <button type="submit" class="w-full <?php echo $type === 'responsable' ? 'btn-indigo' : 'btn-yellow'; ?> py-4 rounded-full font-bold text-sm tracking-wide shadow-md transition-all flex items-center justify-center gap-2 mt-3">
                            <span>Changer le mot de passe</span>
                            <i class="fas fa-key text-xs"></i>
                        </button>

                        <div class="pt-1">
                            <a href="<?php echo $type === 'responsable' ? 'auth/login_responsable.php' : 'auth/login_agent.php'; ?>" class="flex items-center justify-center gap-2 py-3 px-4 rounded-full border border-stone-300/80 bg-white/80 hover:bg-white text-stone-800 font-semibold text-xs transition shadow-sm w-full">
                                <i class="fas fa-arrow-left text-stone-500 text-xs"></i>
                                Retour à la connexion
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

<?php if ($code_envoye): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Code envoyé !',
        html: 'Un code de réinitialisation a été envoyé par <b>Email</b> et par <b>WhatsApp</b>. Veuillez consulter vos messages.',
        confirmButtonColor: '<?php echo $type === 'responsable' ? '#4338CA' : '#F8D053'; ?>',
        confirmButtonText: 'Saisir le code'
    });
</script>
<?php endif; ?>

<?php if ($blocage_contact): ?>
<script>
    Swal.fire({
        icon: 'warning',
        title: 'Limite atteinte',
        html: 'Vous avez atteint le nombre maximal de 3 demandes de réinitialisation.<br><br>Veuillez <b>contacter le responsable au niveau de votre établissement</b> pour procéder au déblocage de votre compte.',
        confirmButtonColor: '#2D271E',
        confirmButtonText: 'Compris'
    });
</script>
<?php endif; ?>

<?php if (!empty($message_erreur)): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Erreur',
        text: '<?php echo addslashes($message_erreur); ?>',
        confirmButtonColor: '#2D271E',
        confirmButtonText: 'Réessayer'
    });
</script>
<?php endif; ?>
</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_im'])) {
    $mode = defined('AUTH_RESPONSE') ? AUTH_RESPONSE : 'redirect';

    if ($mode === 'json') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'not_authenticated',
            'message' => 'Session expirée. Veuillez vous reconnecter.',
        ]);
        exit();
    }

    if ($mode === 'fragment') {
        http_response_code(401);
        header('Content-Type: text/html; charset=utf-8');
        echo '<div class="p-6 text-center text-slate-500">'
           . 'Session expirée. Veuillez <a href="auth/login_agent.php" class="text-sky-600 underline">vous reconnecter</a>.'
           . '</div>';
        exit();
    }

    require_once __DIR__ . '/bootstrap.php';
    rediriger('auth/login_agent.php');
}

require_once __DIR__ . '/config.php';

$userRole  = $_SESSION['user_role'] ?? 'agent';
$isAgent   = ($userRole === 'agent');
$isAdmin   = ($userRole === 'admin');

if (isset($_GET['switch_view']) && !$isAgent) {
    $requestedView = $_GET['switch_view'];
    if (in_array($requestedView, ['agent', 'responsable'], true)) {
        $_SESSION['view_mode'] = $requestedView;
    }
}

if (!isset($_SESSION['view_mode'])) {
    $_SESSION['view_mode'] = $isAgent ? 'agent' : 'responsable';
}

$activeView = $_SESSION['view_mode'];

if (isset($_SESSION['user_im']) && isset($pdo)) {
    try {
        $stmtUpdate = $pdo->prepare("UPDATE utilisateurs SET derniere_activite = NOW(), est_actif = 1 WHERE im = ?");
        $stmtUpdate->execute([$_SESSION['user_im']]);
    } catch (Exception $e) {
    }
}
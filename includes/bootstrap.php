<?php
/**
 * Amorçage de l'application : chemins de base, sans connexion à la base.
 *
 * À inclure par tout script qui a besoin de résoudre un chemin, y compris les
 * pages de connexion qui n'accèdent pas à la base de données.
 *
 *   APP_ROOT : chemin DISQUE de la racine de l'application (sans / final).
 *              À utiliser pour lire un fichier : APP_ROOT . "/pieces/Titularisation/Titularisation.docx"
 *
 *   BASE_URL : chemin WEB de la racine de l'application (avec / final).
 *              Sert au <base href> des pages, pour que toutes les URL
 *              relatives se résolvent depuis la racine, quel que soit le
 *              sous-dossier du script courant. Fonctionne aussi bien si le
 *              projet est déployé à la racine du domaine que dans un
 *              sous-dossier (ex. /ressources_humaines/).
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!defined('BASE_URL')) {
    $appRoot = str_replace(DIRECTORY_SEPARATOR, '/', APP_ROOT);
    $docRoot = str_replace(DIRECTORY_SEPARATOR, '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $docRoot = rtrim($docRoot, '/');

    $base = '';
    if ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) {
        $base = substr($appRoot, strlen($docRoot));
    }

    define('BASE_URL', rtrim($base, '/') . '/');
}

/**
 * Balise <base> à placer en tête de <head> des pages complètes.
 * Rend toutes les URL relatives du document (assets, liens, fetch, formulaires)
 * relatives à la racine de l'application.
 */
if (!function_exists('base_tag')) {
    function base_tag(): string
    {
        return '<base href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('rediriger')) {
    /**
     * Redirige vers un chemin exprimé depuis la racine de l'application.
     *
     * Indispensable depuis un sous-dossier : un « Location: index.php » émis
     * par auth/auth_process.php serait résolu par le navigateur en
     * /auth/index.php (404). On préfixe donc systématiquement par BASE_URL.
     *
     * @param string $chemin ex. 'index.php', 'auth/login_agent.php', 'agent?error=empty'
     */
    function rediriger(string $chemin): void
    {
        header('Location: ' . BASE_URL . ltrim($chemin, '/'));
        exit;
    }
}

if (!function_exists('afficher_erreur_500')) {
    /**
     * Affiche la page d'erreur 500 à la place d'une page blanche.
     *
     * Apache ne déclenche pas ErrorDocument sur une erreur fatale PHP : il
     * considère que le script a traité la requête. Sans ce gestionnaire,
     * l'utilisateur reçoit une réponse vide.
     *
     * Le format de la réponse est adapté à l'appelant : JSON pour les
     * endpoints d'API, rien du tout si un téléchargement binaire a déjà
     * commencé (y injecter du HTML corromprait le fichier).
     */
    function afficher_erreur_500(): void
    {
        // Un téléchargement est-il déjà en cours ? On ne peut plus rien écrire.
        foreach (headers_list() as $entete) {
            if (stripos($entete, 'Content-Disposition:') === 0
                || stripos($entete, 'Content-Type: application/vnd') === 0
                || stripos($entete, 'Content-Type: application/octet-stream') === 0) {
                return;
            }
        }

        // Rien de ce qui a été produit avant l'erreur ne doit être envoyé.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        // Les appelants JSON attendent du JSON, pas une page HTML.
        $attendJson = (defined('AUTH_RESPONSE') && AUTH_RESPONSE === 'json');
        foreach (headers_list() as $entete) {
            if (stripos($entete, 'Content-Type: application/json') === 0) {
                $attendJson = true;
            }
        }

        if ($attendJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'status'  => 'error',
                'message' => "Une erreur interne est survenue. Réessayez dans quelques instants.",
            ]);
            return;
        }

        $page = APP_ROOT . '/errors/500.php';
        if (is_readable($page)) {
            require $page;
        } else {
            echo "Une erreur interne est survenue.";
        }
    }
}

// Filet de sécurité : transforme une erreur fatale en page d'erreur lisible.
// En développement (APP_DEBUG=1), on laisse PHP afficher le détail.
register_shutdown_function(static function (): void {
    $erreur = error_get_last();
    if ($erreur === null) {
        return;
    }
    $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($erreur['type'], $fatales, true)) {
        return;
    }

    error_log(sprintf('Erreur fatale : %s dans %s:%d',
        $erreur['message'], $erreur['file'], $erreur['line']));

    if (filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN)) {
        return; // le détail est déjà affiché au développeur
    }

    afficher_erreur_500();
});

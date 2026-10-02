<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Configuration centrale de l'application.
 *
 * Expose deux connexions partagées par tout le projet :
 *   - $pdo  : PDO    (majorité des pages et des API)
 *   - $conn : mysqli (tableaux de bord et quelques scripts)
 *
 * Les paramètres sont lus depuis l'environnement quand il est défini
 * (cas de Docker), sinon on retombe sur les valeurs WAMP/XAMPP habituelles.
 *
 * Ce fichier est idempotent : l'inclure plusieurs fois réutilise la même
 * connexion au lieu d'en ouvrir une nouvelle.
 */

if (!function_exists('env_or')) {
    /**
     * Lit une variable d'environnement avec valeur de repli.
     */
    function env_or(string $key, string $default): string
    {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

if (!function_exists('app_db')) {
    /**
     * Ouvre (une seule fois) les connexions et les renvoie.
     *
     * @return array{0: PDO, 1: mysqli}
     */
    function app_db(): array
    {
        static $handles = null;
        if ($handles !== null) {
            return $handles;
        }

        $host = env_or('DB_HOST', 'localhost');
        $port = (int) env_or('DB_PORT', '3306');
        $name = env_or('DB_NAME', 'gestion_personnel');
        $user = env_or('DB_USER', 'root');
        $pass = env_or('DB_PASS', '');

        try {
            // PDO — utf8mb4, erreurs en exceptions, fetch associatif par défaut.
            $pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            // MySQLi — mêmes paramètres, jeu de caractères aligné sur PDO.
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn = new mysqli($host, $user, $pass, $name, $port);
            $conn->set_charset('utf8mb4');
        } catch (Throwable $e) {
            http_response_code(500);
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }

        $handles = [$pdo, $conn];
        return $handles;
    }
}

// Affichage des erreurs : activé hors production uniquement.
$appDebug = env_or('APP_DEBUG', '1') === '1';
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
error_reporting($appDebug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
unset($appDebug);

[$pdo, $conn] = app_db();

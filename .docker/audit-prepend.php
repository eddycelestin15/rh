<?php
/**
 * Capteur d'erreurs pour l'audit (environnement Docker uniquement).
 *
 * Charge via auto_prepend_file. Un gestionnaire installe avec
 * set_error_handler() est appele meme quand le script fait error_reporting(0)
 * ou ini_set('display_errors', 0) : c'est le seul moyen de voir les
 * avertissements que l'application masque.
 *
 * Rien n'est affiche : tout part dans /tmp/audit-errors.log, pour ne pas
 * corrompre les telechargements binaires (.docx).
 */

set_error_handler(function ($niveau, $message, $fichier, $ligne) {
    static $vus = [];

    $noms = [
        E_WARNING           => 'WARNING',
        E_NOTICE            => 'NOTICE',
        E_DEPRECATED        => 'DEPRECATED',
        E_USER_WARNING      => 'USER_WARNING',
        E_USER_NOTICE       => 'USER_NOTICE',
        E_USER_DEPRECATED   => 'USER_DEPRECATED',
        E_RECOVERABLE_ERROR => 'RECOVERABLE',
    ];
    $nom = $noms[$niveau] ?? ('N' . $niveau);

    $fichier = str_replace('/var/www/html/', '', $fichier);
    $cle = $fichier . ':' . $ligne . ':' . $message;
    if (isset($vus[$cle])) {
        return false;
    }
    $vus[$cle] = true;

    file_put_contents(
        '/tmp/audit-errors.log',
        sprintf("%s\t%s\t%s:%d\t%s\n",
            $nom,
            str_replace('/var/www/html/', '', $_SERVER['SCRIPT_FILENAME'] ?? '?'),
            $fichier, $ligne, $message),
        FILE_APPEND
    );

    return false; // laisse le comportement normal se poursuivre
});

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        file_put_contents(
            '/tmp/audit-errors.log',
            sprintf("FATAL\t%s\t%s:%d\t%s\n",
                str_replace('/var/www/html/', '', $_SERVER['SCRIPT_FILENAME'] ?? '?'),
                str_replace('/var/www/html/', '', $e['file']), $e['line'], $e['message']),
            FILE_APPEND
        );
    }
});

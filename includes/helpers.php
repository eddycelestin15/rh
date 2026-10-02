<?php
/**
 * Fonctions utilitaires partagées par les générateurs de documents.
 */

if (!function_exists('nettoyerChaine')) {
    /**
     * Retire les accents puis tout caractère qui n'est ni une lettre ASCII,
     * ni un espace, ni un tiret.
     *
     * Utilisé pour remplir les gabarits .docx case par case (une lettre par
     * champ), où seuls des caractères ASCII sont acceptés.
     */
    function nettoyerChaine($chaine)
    {
        $chaine = (string) $chaine;

        if (class_exists('Normalizer')) {
            $chaine = Normalizer::normalize($chaine, Normalizer::FORM_D);
        }

        $chaine = preg_replace('/\p{M}/u', '', $chaine);
        $chaine = preg_replace('/[^a-zA-Z\s-]/', '', $chaine);

        return $chaine;
    }
}

if (!function_exists('vider_tampon_sortie')) {
    /**
     * Vide tout tampon de sortie avant l'envoi d'un fichier binaire.
     *
     * Un .docx est une archive ZIP : le moindre octet emis avant lui
     * (avertissement PHP, espace parasite avant une balise) se retrouve en tete
     * du fichier et le rend illisible par Word. On jette donc ce qui a pu etre
     * emis, en le tracant dans le journal des erreurs plutot que dans le fichier.
     *
     * A appeler juste avant les header() de telechargement.
     */
    function vider_tampon_sortie(): void
    {
        $parasite = '';
        while (ob_get_level() > 0) {
            $parasite .= (string) ob_get_clean();
        }

        if (trim($parasite) !== '') {
            error_log(sprintf(
                '%s : %d octets de sortie parasite ignores avant le telechargement - %s',
                $_SERVER['SCRIPT_NAME'] ?? 'document',
                strlen($parasite),
                mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($parasite))), 0, 300)
            ));
        }
    }
}

if (!function_exists('champs_manquants')) {
    /**
     * Renvoie les champs absents ou vides parmi ceux attendus.
     *
     * Évite que les endpoints d'écriture poursuivent avec des valeurs nulles :
     * sans ce contrôle, PHP 8 émet un avertissement par champ manquant et la
     * requête insère des lignes vides en base.
     *
     * @param array    $source  $_POST ou $_GET
     * @param string[] $requis  noms des champs obligatoires
     * @return string[]         les champs manquants (vide si tout est présent)
     */
    function champs_manquants(array $source, array $requis): array
    {
        $manquants = [];
        foreach ($requis as $champ) {
            if (!isset($source[$champ]) || trim((string) $source[$champ]) === '') {
                $manquants[] = $champ;
            }
        }
        return $manquants;
    }
}

if (!function_exists('exiger_champs')) {
    /**
     * Vérifie les champs requis et interrompt le script par une réponse JSON
     * d'erreur si l'un d'eux manque.
     *
     * @param array    $source $_POST ou $_GET
     * @param string[] $requis noms des champs obligatoires
     */
    function exiger_champs(array $source, array $requis): void
    {
        $manquants = champs_manquants($source, $requis);
        if (!$manquants) {
            return;
        }

        if (!headers_sent()) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success'   => false,
            'status'    => 'error',
            'message'   => 'Champs obligatoires manquants : ' . implode(', ', $manquants),
            'manquants' => $manquants,
        ]);
        exit;
    }
}

if (!function_exists('erreur_gabarit_absent')) {
    /**
     * Interrompt la génération d'un document quand son gabarit .docx est absent.
     *
     * Le chemin du fichier n'est JAMAIS montré à l'utilisateur : il révélerait
     * l'arborescence du serveur. Il part dans le journal des erreurs, seul
     * endroit où l'administrateur en a besoin.
     */
    function erreur_gabarit_absent(string $chemin): void
    {
        error_log('Gabarit de document introuvable : ' . $chemin);

        if (function_exists('vider_tampon_sortie')) {
            vider_tampon_sortie();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }

        echo "Ce document ne peut pas être généré pour le moment. "
           . "Veuillez signaler le problème à l'administrateur du système.";
        exit;
    }
}

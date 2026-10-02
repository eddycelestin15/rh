<?php
/**
 * Correspondance unique entre une destination logique et les colonnes
 * réelles de la table `demandes_numeros_dos`.
 *
 * Chaque destination possède deux colonnes :
 *   - selection : l'agent est retenu pour le bordereau de cette destination ;
 *   - imprime   : le bordereau correspondant a déjà été imprimé.
 *
 * Ce fichier existe parce que plusieurs scripts déduisaient ces noms de
 * colonnes chacun à leur façon, avec des vocabulaires divergents
 * (« dren » / « fonction_publique » / « solde_et_pensions »…) qui ne
 * correspondaient à aucune colonne réelle et provoquaient des
 * « Unknown column » à l'exécution.
 */

if (!function_exists('destinations_dos')) {
    /**
     * @return array<string, array{selection: string, imprime: string}>
     */
    function destinations_dos(): array
    {
        return [
            'dren'               => ['selection' => 'augure_dren', 'imprime' => 'deja_imprime_dren'],
            'fonction_publique'  => ['selection' => 'augure_fop',  'imprime' => 'deja_imprime_fop'],
            'fop'                => ['selection' => 'augure_fop',  'imprime' => 'deja_imprime_fop'],
            'solde_et_pensions'  => ['selection' => 'augure_dsp',  'imprime' => 'deja_imprime_solde'],
            'controle_financier' => ['selection' => 'augure_cf',   'imprime' => 'deja_imprime_cde'],
            'prefecture'         => ['selection' => 'prefecture',  'imprime' => 'deja_imprime_prefet'],
            'drh'                => ['selection' => 'drh',         'imprime' => 'deja_imprime_drh'],
            'mtefop'             => ['selection' => 'mtefop',      'imprime' => 'deja_imprime_mtefop'],
            'primature'          => ['selection' => 'primature',   'imprime' => 'deja_imprime_primature'],
        ];
    }

    /**
     * Renvoie les colonnes d'une destination, ou null si elle est inconnue.
     *
     * Accepte aussi directement un nom de colonne de sélection
     * (ex. « augure_dren »), certains appelants transmettant déjà celui-ci.
     *
     * @return array{selection: string, imprime: string}|null
     */
    function destination_colonnes(?string $destination): ?array
    {
        $destination = trim((string) $destination);
        $map = destinations_dos();

        if (isset($map[$destination])) {
            return $map[$destination];
        }

        foreach ($map as $cols) {
            if ($cols['selection'] === $destination) {
                return $cols;
            }
        }

        return null;
    }
}

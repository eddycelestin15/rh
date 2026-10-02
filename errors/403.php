<?php
// Accès refusé par le serveur (dossier protégé, listing interdit...).
$code    = 403;
$titre   = "Accès non autorisé";
$message = "Vous n'avez pas les droits nécessaires pour consulter cette ressource. "
         . "Si vous pensez qu'il s'agit d'une erreur, rapprochez-vous de "
         . "l'administrateur du système.";
$teinte  = 'ambre';
$icone   = 'verrou';
require __DIR__ . '/_rendu.php';

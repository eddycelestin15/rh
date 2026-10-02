<?php
// Erreur interne du serveur.
$code    = 500;
$titre   = "Une erreur est survenue";
$message = "Le serveur a rencontré un problème inattendu et n'a pas pu traiter "
         . "votre demande. Réessayez dans quelques instants ; si le problème "
         . "persiste, signalez-le à l'administrateur du système.";
$teinte  = 'rose';
$icone   = 'alerte';
require __DIR__ . '/_rendu.php';

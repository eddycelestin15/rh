<?php
// Adresse inexistante.
$code    = 404;
$titre   = "Page introuvable";
$message = "Cette page n'existe pas ou a été déplacée. Vérifiez l'adresse saisie, "
         . "ou revenez à l'accueil pour reprendre votre navigation.";
$teinte  = 'ciel';
$icone   = 'boussole';
require __DIR__ . '/_rendu.php';

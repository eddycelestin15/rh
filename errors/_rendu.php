<?php
/**
 * Rendu commun des pages d'erreur (403, 404, 500).
 *
 * Appelé par errors/403.php, errors/404.php et errors/500.php via la directive
 * ErrorDocument du .htaccess.
 *
 * Contraintes de conception :
 *  - Aucune dépendance externe (ni Tailwind CDN, ni Font Awesome) : une page
 *    d'erreur doit s'afficher correctement même quand le réseau ou
 *    l'application sont en difficulté. Le CSS est donc intégré et les icônes
 *    sont des SVG en ligne.
 *  - Aucun accès à la base de données : la page 500 doit fonctionner
 *    précisément quand la base est injoignable.
 *  - Les liens sont construits à partir de BASE_URL, car Apache sert cette
 *    page en sous-requête depuis n'importe quelle profondeur d'URL.
 *
 * @var int    $code    code HTTP (403, 404, 500)
 * @var string $titre   titre court affiché à l'utilisateur
 * @var string $message explication en langage courant
 * @var string $teinte  couleur d'accent : 'ambre', 'ciel' ou 'rose'
 * @var string $icone   identifiant du pictogramme
 */

// bootstrap fournit BASE_URL. S'il est indisponible (panne profonde), on
// retombe sur une racine calculée à la volée : la page reste affichable.
$bootstrap = dirname(__DIR__) . '/includes/bootstrap.php';
if (is_readable($bootstrap)) {
    require_once $bootstrap;
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

if (!headers_sent()) {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
}

// Le chemin demandé n'est JAMAIS affiché : il révélerait l'arborescence du
// serveur et renverrait à l'écran une valeur fournie par le visiteur. Il est
// uniquement consigné dans le journal, pour le diagnostic administrateur.
$demande = strtok($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '', '?');
if ($demande !== '') {
    error_log(sprintf('Erreur %d sur %s', $code, $demande));
}

$accents = [
    'ambre' => ['#b45309', '#fef3c7', '#f59e0b'],
    'ciel'  => ['#0369a1', '#e0f2fe', '#0ea5e9'],
    'rose'  => ['#be123c', '#ffe4e6', '#f43f5e'],
];
[$accentFort, $accentDoux, $accentVif] = $accents[$teinte] ?? $accents['ciel'];

$pictos = [
    // Cadenas fermé
    'verrou' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    // Boussole
    'boussole' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5.5-5.5 2 2-5.5z"/>',
    // Panneau d'alerte
    'alerte' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
];
$picto = $pictos[$icone] ?? $pictos['alerte'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>">
<title><?= $code ?> — <?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></title>
<style>
  :root{
    --fort:<?= $accentFort ?>; --doux:<?= $accentDoux ?>; --vif:<?= $accentVif ?>;
    --encre:#0f172a; --gris:#64748b; --bord:#e2e8f0; --papier:#ffffff;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{
    min-height:100vh; display:flex; align-items:center; justify-content:center;
    padding:24px; background:#0f172a; color:var(--encre);
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
    background-image:linear-gradient(rgba(15,23,42,.88),rgba(15,23,42,.94)),
                     url('assets/images/fond_authentification.png');
    background-size:cover; background-position:center;
  }
  .carte{
    position:relative; width:100%; max-width:620px; background:var(--papier);
    border-radius:24px; padding:48px 44px 36px; overflow:hidden;
    box-shadow:0 30px 70px -20px rgba(0,0,0,.6);
    animation:apparait .45s cubic-bezier(.2,.7,.3,1) both;
  }
  @keyframes apparait{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
  @media (prefers-reduced-motion:reduce){.carte{animation:none}}

  /* Code d'erreur en filigrane */
  .filigrane{
    position:absolute; top:-28px; right:14px; font-size:170px; font-weight:800;
    letter-spacing:-.06em; color:var(--doux); line-height:1; user-select:none;
    pointer-events:none;
  }
  .contenu{position:relative}

  .pastille{
    width:64px; height:64px; border-radius:18px; background:var(--doux);
    display:flex; align-items:center; justify-content:center; margin-bottom:22px;
  }
  .pastille svg{width:30px;height:30px;stroke:var(--fort);fill:none;
                stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

  .surtitre{
    font-size:11px; font-weight:800; letter-spacing:.16em; text-transform:uppercase;
    color:var(--vif); margin-bottom:8px;
  }
  h1{font-size:30px; font-weight:800; letter-spacing:-.02em; line-height:1.15; margin-bottom:12px}
  .texte{font-size:15px; line-height:1.65; color:var(--gris); max-width:46ch}

  .actions{display:flex; flex-wrap:wrap; gap:12px; margin-top:30px}
  .bouton{
    display:inline-flex; align-items:center; gap:9px; padding:13px 22px;
    border-radius:12px; font-size:14px; font-weight:700; text-decoration:none;
    border:1px solid transparent; cursor:pointer; transition:transform .12s, filter .12s;
  }
  .bouton:hover{transform:translateY(-1px); filter:brightness(1.06)}
  .bouton:focus-visible{outline:3px solid var(--vif); outline-offset:2px}
  .principal{background:var(--fort); color:#fff}
  .secondaire{background:#fff; color:var(--encre); border-color:var(--bord)}
  .bouton svg{width:16px;height:16px;stroke:currentColor;fill:none;
              stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

  .pied{
    margin-top:30px; padding-top:18px; border-top:1px solid var(--bord);
    display:flex; align-items:center; justify-content:space-between; gap:16px;
    flex-wrap:wrap; font-size:12px; color:var(--gris);
  }
  .marque{display:flex; align-items:center; gap:10px; font-weight:700; color:var(--encre)}
  .marque img{width:26px; height:26px; object-fit:contain}
  .ref{color:var(--gris)}
  @media (max-width:560px){
    .carte{padding:36px 26px 28px; border-radius:18px}
    .filigrane{font-size:118px; top:-16px; right:8px}
    h1{font-size:24px}
    .actions .bouton{flex:1 1 100%; justify-content:center}
  }
</style>
</head>
<body>
  <main class="carte" role="alert">
    <div class="filigrane" aria-hidden="true"><?= $code ?></div>

    <div class="contenu">
      <div class="pastille" aria-hidden="true">
        <svg viewBox="0 0 24 24"><?= $picto ?></svg>
      </div>

      <p class="surtitre">Erreur <?= $code ?></p>
      <h1><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="texte"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

      <div class="actions">
        <a class="bouton principal" href="index.php">
          <svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 21V12h6v9"/></svg>
          Retour à l'accueil
        </a>
        <button class="bouton secondaire" type="button" onclick="history.back()">
          <svg viewBox="0 0 24 24"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
          Page précédente
        </button>
      </div>

      <div class="pied">
        <span class="marque">
          <img src="assets/images/grh.png" alt="">
          Ministère de l'Éducation Nationale
        </span>
        <span class="ref">Système de gestion du personnel</span>
      </div>
    </div>
  </main>
</body>
</html>

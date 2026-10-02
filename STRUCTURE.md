# Organisation du projet

Le projet était initialement composé de **147 fichiers PHP en vrac à la racine**.
Ils sont désormais rangés par rôle puis par domaine métier.

```
.
├── index.php                  Point d'entrée unique de l'application (shell SPA)
├── .htaccess                  URL courtes + blocage des dossiers privés
│
├── includes/            (4)   Socle commun — jamais servi en HTTP (403)
│   ├── bootstrap.php          APP_ROOT / BASE_URL / base_tag()
│   ├── config.php             Connexions $pdo et $conn
│   ├── check_session.php      Garde d'accès (401 JSON, 401 fragment, ou redirection)
│   └── helpers.php            nettoyerChaine(), vider_tampon_sortie()
│
├── auth/               (10)   Connexion, déconnexion, inscription, comptes
│
├── api/                (46)   Endpoints JSON (lecture)
│   ├── agents/          (9)   Fiches agent, recherche, listes, historiques
│   ├── carriere/       (10)   Avancements, avenants, grades, indices, mandatement
│   ├── dossiers/        (7)   Demandes, traitement, références, considérants
│   ├── bordereaux/      (5)   Bordereaux d'envoi et suivi
│   ├── allocation/      (3)   Allocations familiales, enfants
│   ├── notifications/   (5)   Notifications et marquage « lu »
│   ├── messagerie/      (3)   Messages internes
│   └── referentiel/     (4)   Grades, corps, localités, services
│
├── actions/            (26)   Écritures (POST) : enregistrement, mise à jour, suppression
│   ├── personnel/       (9)   Fiches, affectations, photos, imports
│   ├── carriere/        (6)   Avancements, mandatements, certificats
│   ├── dossiers/        (5)   Dossiers, suivis, références
│   ├── conges/          (2)   Congés
│   └── compte/          (4)   Profil, mot de passe, responsables
│
├── pages/              (43)   Pages et fragments HTML
│   ├── dashboards/      (5)   Un tableau de bord par profil
│   ├── personnel/      (11)   État civil, poste, situation, diplômes, avancements…
│   ├── dossiers/       (14)   Dossiers en cours, suivi, traitement, bordereaux…
│   ├── conges/          (3)   Demande, prise et historique de congés
│   ├── profil/          (4)   Profil utilisateur et documents
│   ├── notifications/   (4)   Notifications, archives, messagerie
│   ├── admin/           (1)   Création de responsables
│   └── sections/        (1)   Fragments réutilisables
│
├── documents/          (19)   Génération de documents Word
│   ├── actes/           (7)   Actes formatés, allocations, rappels
│   ├── carriere/        (7)   Avancement, intégration, titularisation, contrats
│   ├── bordereaux/      (1)   Bordereaux d'envoi
│   ├── conges/          (1)   Décisions de congé
│   └── mandatement/     (2)   Pièces de mandatement
│
├── errors/              (4)   Pages d'erreur 403 / 404 / 500
│                             Sans dépendance CDN ni accès base : elles doivent
│                             s'afficher même quand l'application est en panne.
│
├── cron/                (2)   Tâches planifiées — bloqué en HTTP (403)
│   ├── cron_notifications.php
│   └── execution_cron.bat
│
├── database/                  Dump SQL — bloqué en HTTP (403)
├── assets/                    CSS, JS et images de l'interface
├── images/ Logo/ pieces/ uploads/   Médias et gabarits .docx
├── vendor/ PHPMailer/         Dépendances
├── .docker/                   Dockerfile, init base, tests, scripts de migration
└── _archive/                  Code mort conservé — bloqué en HTTP (403)
```

## Deux règles à connaître

### 1. Les chemins **disque** passent par `APP_ROOT`

```php
$template = new TemplateProcessor(APP_ROOT . '/pieces/Mandatement/Acte_formate.docx');
```

`APP_ROOT` est le chemin disque de la racine du projet. Sans lui, un chemin
relatif serait résolu depuis le dossier du script appelant et casserait dès
qu'un fichier change de dossier.

### 2. Les **URL** restent relatives à la racine, grâce à `<base href>`

Chaque page complète place en tête de `<head>` :

```php
<?php echo base_tag(); ?>   <!-- <base href="/"> -->
```

Toutes les URL relatives du document (assets, liens, formulaires, `fetch`,
`$.post`) se résolvent alors depuis la racine de l'application, **quel que soit
le sous-dossier du script courant**. C'est ce qui permet d'écrire partout :

```js
fetch('api/agents/get_data.php?im=' + im)
```

sans se soucier de la profondeur du fichier. Cela fonctionne aussi bien si le
projet est déployé à la racine du domaine que dans un sous-dossier
(ex. `/ressources_humaines/`), `BASE_URL` étant calculé à l'exécution.

> Les fragments chargés en AJAX dans `index.php` héritent du `<base>` de la
> page hôte : leurs URL suivent donc la même règle.

## Contrôle d'accès

Tout script non public commence par :

```php
define('AUTH_RESPONSE', 'json');        // ou 'fragment', ou rien pour une redirection
require_once __DIR__ . '/../../includes/check_session.php';
```

| Mode         | Utilisé par              | Réponse sans session               |
|--------------|--------------------------|------------------------------------|
| `json`       | `api/`, `actions/`       | `401` + `{"error":"not_authenticated"}` |
| `fragment`   | fragments de `pages/`    | `401` + court message HTML         |
| *(défaut)*   | pages complètes          | redirection vers le login          |

`check_session.php` charge `config.php` : il est donc inutile d'inclure les deux.

## Pages d'erreur

`403`, `404` et `500` sont rendues par [errors/_rendu.php](errors/_rendu.php),
avec un titre, une explication en langage courant et deux actions
(*Retour à l'accueil*, *Page précédente*).

Trois règles ont guidé leur conception :

1. **Aucune dépendance externe.** Ni Tailwind CDN ni Font Awesome : le CSS est
   intégré et les icônes sont des SVG en ligne. Une page d'erreur doit
   s'afficher même quand le réseau ou l'application sont en difficulté.
2. **Aucun accès à la base.** La page 500 doit fonctionner précisément quand la
   base est injoignable.
3. **Aucune information technique à l'écran.** Ni chemin, ni nom de fichier, ni
   trace d'exécution : tout part dans le journal des erreurs du serveur, seul
   endroit où l'administrateur en a besoin.

### Deux mécanismes complémentaires

`ErrorDocument` (dans le `.htaccess`) couvre les erreurs produites par Apache :
adresse inexistante, dossier protégé, listing interdit.

Il ne suffit pas : **Apache ne déclenche pas `ErrorDocument` sur une erreur
fatale PHP**, car il considère que le script a traité la requête — le visiteur
reçoit alors une page blanche. Un `register_shutdown_function()` dans
[includes/bootstrap.php](includes/bootstrap.php) prend donc le relais et adapte
sa réponse à l'appelant :

| Appelant | Réponse en cas d'erreur fatale |
|---|---|
| Page HTML | la page 500 |
| Endpoint JSON (`AUTH_RESPONSE = 'json'`) | un JSON d'erreur, pour ne pas casser le JavaScript |
| Téléchargement déjà commencé | rien : y injecter du HTML corromprait le `.docx` |

En développement (`APP_DEBUG=1`), le gestionnaire s'efface et laisse PHP
afficher le détail de l'erreur.

> Les codes `400` et `401` sont volontairement **absents** des `ErrorDocument` :
> ils sont produits par l'application avec un corps JSON destiné au JavaScript.

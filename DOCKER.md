# Environnement Docker — Gestion du Personnel (RH)

> L'organisation des dossiers du projet est decrite dans **STRUCTURE.md**.

Permet de faire tourner et tester le projet **sans WAMP ni XAMPP**.

## Démarrage

```bash
docker compose up -d --build
```

Au premier lancement, la base est créée et le dump `database/gestion_personnel.sql`
est importé automatiquement (comptez 1 à 2 minutes).

| Service     | URL                     | Identifiants           |
|-------------|-------------------------|------------------------|
| Application | http://localhost:8080   | voir ci-dessous        |
| phpMyAdmin  | http://localhost:8081   | `root` / `root`        |
| MySQL       | `localhost:3307`        | `root` / `root`        |

### Comptes de test

En environnement Docker, **tous** les comptes ont le mot de passe `test1234`
(voir `.docker/dev-passwords.sql` — dev uniquement, jamais en production).

| IM       | Rôle                   | Page de connexion            |
|----------|------------------------|------------------------------|
| `ADMIN`  | Administrateur         | http://localhost:8080/administrateur |
| `350350` | Responsable CRFRP      | http://localhost:8080/responsable    |
| `321321` | Responsable encadré    | http://localhost:8080/responsable    |
| `300300` | Responsable solde      | http://localhost:8080/responsable    |
| `360360` | Responsable non encadré| http://localhost:8080/responsable    |
| `504026` | Agent                  | http://localhost:8080/agent          |

## Commandes utiles

```bash
docker compose logs -f web        # journaux Apache/PHP
docker compose logs db            # journaux MySQL (dont l'import initial)
docker compose down               # arrêt (la base est conservée)
docker compose down -v            # arrêt + remise à zéro de la base
docker compose exec web bash      # shell dans le conteneur PHP
```

### Vérifier tout le code

```bash
# Contrôle de syntaxe sur tous les fichiers PHP du projet
docker compose exec web bash -c 'cd /var/www/html && \
  for f in $(find . -name "*.php" -not -path "./vendor/*" \
    -not -path "./PHPMailer/*" -not -path "./_archive/*"); do php -l "$f"; done' \
  | grep -v "No syntax errors"

# Test de fumée : parcourt toutes les pages avec un compte donné
docker compose exec web bash .docker/smoke-test.sh ADMIN test1234 admin
docker compose exec web bash .docker/smoke-test.sh 504026 test1234 agent
```

## Configuration

`config.php` lit ces variables d'environnement, avec repli sur les valeurs
WAMP/XAMPP habituelles. **Le projet continue donc de fonctionner sous WAMP
sans aucune modification.**

| Variable    | Défaut (hors Docker) | Valeur Docker       |
|-------------|----------------------|---------------------|
| `DB_HOST`   | `localhost`          | `db`                |
| `DB_PORT`   | `3306`               | `3306`              |
| `DB_NAME`   | `gestion_personnel`  | `gestion_personnel` |
| `DB_USER`   | `root`               | `root`              |
| `DB_PASS`   | *(vide)*             | `root`              |
| `APP_DEBUG` | `1`                  | `1`                 |

En production, positionnez `APP_DEBUG=0` pour masquer les erreurs PHP.

## À savoir sur l'import de la base

L'export phpMyAdmin `database/gestion_personnel.sql` contient **deux défauts** qui
empêchent la vue `v_moteur_alertes` d'être créée :

1. **Alias de colonnes trop longs.** phpMyAdmin nomme les colonnes calculées
   avec le texte de l'expression ; 46 d'entre eux dépassent la limite de
   64 caractères d'un identifiant MySQL →
   `ERROR 1166: Incorrect column name 'DATE_ADD(pc.date_effet_etape...'`.
2. **Mot-clé `RECURSIVE` manquant.** La CTE `progression_carriere` se
   référence elle-même, mais l'export écrit `WITH` au lieu de
   `WITH RECURSIVE` → `ERROR 1146: Table 'progression_carriere' doesn't exist`.

Comme l'entrypoint MySQL s'arrête à la première erreur, **tout le reste de
l'import était abandonné**. Le script `.docker/init-db.sh` pilote donc
l'import : dump avec `--force`, puis recréation propre de la vue via
`.docker/fix-view-moteur-alertes.sql`.

> Si vous ré-exportez la base depuis phpMyAdmin, le problème réapparaîtra.
> Le script de réparation reste valable tant que la définition de la vue ne
> change pas ; sinon, régénérez-le à partir de la nouvelle définition.

## Audit des erreurs PHP

Le projet contient plusieurs scripts qui masquent leurs propres erreurs
(`error_reporting(0)`, `display_errors = 0`). Pour les voir malgré tout,
`.docker/audit-prepend.php` installe un gestionnaire d'erreurs qui journalise
tout, y compris ce que ces scripts suppriment.

```bash
# 1. Activer le capteur
docker compose exec web bash -c   'echo "auto_prepend_file = /var/www/html/.docker/audit-prepend.php"    > /usr/local/etc/php/conf.d/zzz-audit.ini && apache2ctl graceful'

# 2. Balayer toutes les pages et tous les endpoints, avec 6 profils
docker compose exec web bash .docker/audit-runtime.sh

# 3. Lire le resultat
docker compose exec web sh -c 'cut -f1,3,4 /tmp/audit-errors.log | sort -u'

# 4. Desactiver le capteur
docker compose exec web rm -f /usr/local/etc/php/conf.d/zzz-audit.ini
docker compose restart web
```

Un audit qui se termine sur **0 avertissement** signifie qu'aucune page ni
aucun endpoint n'emet de `Warning`, `Notice`, `Deprecated` ni d'erreur fatale.

### Autres outils

| Script | Role |
|---|---|
| `.docker/smoke-test.sh` | Parcourt les 109 pages/endpoints avec un compte donne (suit les redirections) |
| `.docker/audit_sql.py` | Recense les requetes SQL construites avec des variables |
| `.docker/audit_sql_taint.py` | Isole celles ou la variable vient d'une entree utilisateur |

## Production (Traefik)

`docker-compose.prod.yml` + `.docker/Dockerfile.prod` : le code est embarqué dans l'image,
MySQL reste sur un réseau privé, l'application est exposée uniquement via Traefik
(réseau externe `traefik-network`, certificat `letsencrypt`) sur
`ressource-humaine.flycelest.com` (à changer dans les labels `traefik.http.routers.rh.rule`).

```bash
cp .env.example .env        # puis définir MYSQL_ROOT_PASSWORD
docker compose -f docker-compose.prod.yml up -d --build
```

Au premier démarrage le dump est importé **sans** les mots de passe de test :
les comptes gardent leurs mots de passe d'origine.

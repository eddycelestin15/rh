# Corrections apportées

Journal des bugs corrigés sur l'application RH, avec pour chacun la cause
réelle et la façon dont il a été vérifié.

**Résultat mesuré :** l'audit d'exécution est passé de **3097 avertissements
PHP à 0**, sur 6 profils utilisateurs × la totalité des pages et endpoints.

| Étape | Avertissements captés |
|---|---|
| État initial | 3097 (dont 96 erreurs fatales) |
| Après contrôle des entrées + gardes de session | 744 |
| Après correction des générateurs et des chemins | 6 |
| État final | **0** |

> Ces bugs étaient en grande partie **invisibles** : dix fichiers appellent
> `error_reporting(0)` ou `display_errors = 0`. Il a fallu installer un
> gestionnaire d'erreurs (`.docker/audit-prepend.php`) qui journalise malgré
> cette suppression. Voir *Audit des erreurs PHP* dans [DOCKER.md](DOCKER.md).

---

## 1. Erreurs fatales

### `dren` n'est pas un nom de colonne

`actions/dossiers/mark_as_printed.php` et `api/agents/update_agent_status.php`
utilisaient la destination logique (`dren`, `fonction_publique`,
`solde_et_pensions`, `controle_financier`) directement comme nom de colonne.
**Aucun de ces noms n'existe** dans `demandes_numeros_dos` : les colonnes
réelles sont `augure_dren`, `augure_fop`, `augure_dsp`, `augure_cf`.

Chaque appel échouait sur `Unknown column 'dren' in field list`.

Les deux scripts déduisaient ces noms chacun à leur façon, avec des
vocabulaires divergents. La correspondance est désormais unique et partagée
dans [includes/destinations.php](includes/destinations.php) :

```php
'dren' => ['selection' => 'augure_dren', 'imprime' => 'deja_imprime_dren'],
```

Les requêtes sont passées à PDO préparé au passage.

### Vue `v_moteur_alertes` absente de la base

L'export phpMyAdmin échoue sur cette vue pour **deux raisons** :

1. phpMyAdmin nomme les colonnes calculées avec le texte de l'expression ;
   46 de ces noms dépassent la limite de 64 caractères d'un identifiant MySQL
   → `ERROR 1166: Incorrect column name 'DATE_ADD(pc.date_effet_etape...'`
2. La CTE `progression_carriere` se référence elle-même, mais l'export écrit
   `WITH` au lieu de `WITH RECURSIVE`
   → `ERROR 1146: Table 'progression_carriere' doesn't exist`

L'entrypoint MySQL s'arrêtant à la première erreur, **tout le reste de
l'import était abandonné**. Trois pages majeures tombaient en erreur fatale
(`dossiers_en_cours`, `liste_demandes_dos`, `dashboard_responsable`).

Corrigé par [.docker/init-db.sh](.docker/init-db.sh), qui pilote l'import et
recrée la vue proprement, avec un garde-fou qui fait échouer l'initialisation
si la vue est absente.

---

## 2. Documents Word corrompus

Un `.docx` est une archive ZIP : le moindre octet émis avant l'en-tête de
téléchargement se retrouve en tête du fichier et **Word refuse de l'ouvrir**.

Trois générateurs produisaient des fichiers corrompus, avec 708 octets de HTML
en tête, à cause d'une notice PHP 8.3 émise avant les en-têtes :

| Fichier | Cause |
|---|---|
| `documents/actes/generate_acte_formate.php` | `mb_strtoupper(null)` sur `libelle_demande` |
| `documents/actes/generate_acte_allocation.php` | clé `code_localite` absente |
| `documents/mandatement/generate_reste_mandatement_rappel_solde.php` | `UPDATE` exécuté **après** l'envoi du fichier |

Corrections :

- `vider_tampon_sortie()` ([includes/helpers.php](includes/helpers.php)) jette
  toute sortie parasite et la trace dans le journal au lieu du fichier.
  Appliquée aux **20 points de téléchargement** du projet.
- Les valeurs nulles sont explicitement traitées (`?? ''`).
- Dans `generate_reste_mandatement_rappel_solde.php`, la mise à jour est
  remontée **avant** l'envoi, suivie d'un `exit` — le motif qu'utilisait déjà
  son fichier jumeau.

### `acte_formate` écrasée par des NULL

Ce même fichier exécutait :

```sql
UPDATE acte_formate SET corps_actuel = :new_corps, grade_actuel = :new_grade, ...
```

en lisant `$agent['new_corps']`, `$agent['new_grade']`… alors que **sa requête
ne joint pas `acte_formate`**. Ces clés n'existaient pas dans `$agent` :
chaque génération de document écrasait ces colonnes avec `NULL`.

Ces colonnes appartenant à la table elle-même, elles sont maintenant copiées
directement en SQL :

```sql
UPDATE acte_formate SET corps_actuel = new_corps, grade_actuel = new_grade, ...
```

> **À confirmer :** c'est la seule correction touchant à de la logique métier.

### `$localite_service` jamais définie

Le gabarit de mandatement attendait cette variable, qui **n'était définie nulle
part** : le champ sortait vide. Alimentée depuis l'établissement d'affectation
(`$PosteActuel['nom_etablissement']`).

> **À confirmer :** vérifier que c'est bien la donnée attendue par le gabarit.

---

## 3. Contrôle d'accès

`api/agents/fetch_liste_data.php` renvoyait **la liste complète du personnel —
matricules et noms — à n'importe quel visiteur non connecté**. Une quarantaine
de fichiers n'avaient aucun contrôle de session.

La garde est désormais posée sur **134 fichiers**, avec le format de refus
adapté à chaque type de réponse :

| Mode | Utilisé par | Réponse sans session |
|---|---|---|
| `json` | `api/`, `actions/` | `401` + `{"error":"not_authenticated"}` |
| `fragment` | fragments de `pages/` | `401` + court message HTML |
| *(défaut)* | pages complètes | redirection vers le login |

Les endpoints JSON ne renvoient donc pas une page de login au JavaScript.

Également retirés de la racine web (`403` via `.htaccess`) : `includes/`,
`cron/`, `database/`, `_archive/` — ce dernier contenait un `phpinfo()` et un
destructeur de session accessibles publiquement.

---

## 4. Injections SQL — vérification

**Aucune injection exploitable n'a été trouvée**, contrairement à ce qui était
soupçonné au départ.

Les 95 requêtes construites avec des variables ont été tracées
([.docker/audit_sql_taint.py](.docker/audit_sql_taint.py)) : le code emploie
systématiquement des listes blanches (`in_array`, `array_key_exists`,
`isset($cols[...])`), `intval` et `mysqli_real_escape_string`. Ce qui est
interpolé, ce sont des **noms de colonnes issus de correspondances internes**,
jamais des valeurs transmises par le client.

Seule exception : `save_suivi_ref.php`, qui interpolait quatre champs `$_POST`
sans échappement. Il référençait trois tables inexistantes
(`suivi_depots`, `bordereaux_archives`, `notifications_agents`) et n'était
appelé par personne → **archivé**.

---

## 5. Contrôle des entrées

Dix endpoints lisaient `$_POST` / `$_GET` sans vérification. Champs absents
→ un avertissement PHP par champ, puis **écriture de valeurs `NULL` en base**
(lignes enfants vides, coordonnées écrasées).

Ils renvoient maintenant `400` avec la liste des champs manquants, via
`exiger_champs()` ([includes/helpers.php](includes/helpers.php)) :

```
api/allocation/api_allocation.php            9 champs
actions/personnel/update_affectation.php     6 champs
actions/personnel/save_step_affectation.php  6 champs
actions/carriere/save_certificat_data.php    2 champs
actions/compte/save_responsable.php          4 champs
actions/personnel/update_dcf_data.php        3 champs
api/messagerie/send_message.php              3 champs
api/carriere/get_indice_by_libelle.php       2 champs
actions/conges/save_conge.php                2 champs
api/allocation/get_region_dcf.php            1 champ
```

Les champs réellement facultatifs (`telephone`, `email`, `num_tel`,
`adress_mail`, `localite`) reçoivent une valeur par défaut plutôt qu'un rejet.

### Lecture d'une ligne SQL inexistante

Quatre pages lisaient `$agent['nom']` sur un `fetch()` ayant renvoyé `false` :

- `pages/personnel/allocation_familiale.php`
- `pages/conges/prendre_conge.php`
- `pages/dossiers/envoi_dossiers.php`
- `api/agents/get_historique_agent.php`

Cas réel et fréquent : **les comptes admin et responsable n'ont pas de fiche
dans `personnel_etat_civil`**. Le `fetch()` retombe désormais sur un tableau
vide et les lectures sont protégées.

---

## 6. Bugs introduits par la restructuration, puis corrigés

Le rangement des 147 fichiers PHP a cassé quatre choses, toutes détectées et
corrigées avant livraison.

### Connexion cassée

`auth/auth_process.php` émettait `Location: index.php`. Depuis `/auth/`, le
navigateur résout cela en `/auth/index.php` → **404**. Le parcours de connexion
ne fonctionnait plus dans un vrai navigateur.

Le test de fumée ne le voyait pas : il ne **suivait pas** les redirections.
Il les suit désormais et vérifie l'URL d'arrivée — sans quoi cette classe de
bug reste invisible.

16 redirections corrigées via `rediriger()`
([includes/bootstrap.php](includes/bootstrap.php)), qui préfixe par `BASE_URL`.

### Photos de profil

`utilisateurs.photo` stocke un chemin **relatif**
(`uploads/profils/ADMIN_1772478277.jpg`) qui sert directement d'URL dans les
`<img src>`. La réécriture automatique y avait injecté `APP_ROOT`, donc un
chemin disque (`/var/www/html/...`) affiché comme URL — et les handlers
d'upload s'étaient mis à **stocker ce chemin absolu en base**.

Rétabli sur les 5 fichiers concernés : vérification d'existence avec
`APP_ROOT`, affichage et stockage en chemin relatif.

### Redirection de session vers `/includes/`

`check_session.php` recalculait lui-même la racine de l'application à partir de
`__DIR__`. Correct tant que le fichier était à la racine ; après son
déplacement dans `includes/`, `__DIR__` vaut `/includes` — **toute session
expirée renvoyait vers `/includes/login_agent.php`**, une adresse protégée en
403. La cible était doublement fausse, le login ayant lui aussi été déplacé
dans `auth/`.

Le calcul dupliqué est supprimé : `check_session.php` délègue désormais à
`rediriger()`, qui s'appuie sur `BASE_URL`. La racine n'est donc plus calculée
qu'à un seul endroit, `includes/bootstrap.php`.

### Chemins de logos et d'includes

- `__DIR__ . '/Logo/Embleme.png'` cherchait dans `documents/mandatement/Logo/`
  → passé à `APP_ROOT` (3 fichiers, 5 occurrences).
- `pages/personnel/formulaire.php` incluait `pages/sections/distinction.php`
  en relatif au répertoire courant.

### `AUTH_RESPONSE` redéfinie

Une page incluant une autre page redéfinissait la constante
→ `Constant AUTH_RESPONSE already defined`. Définition rendue idempotente sur
les 43 fichiers concernés.

---

## 7. Fuites d'information dans les messages d'erreur

Dix générateurs affichaient le **chemin disque absolu** du gabarit manquant :

```
Template introuvable : /var/www/html/pieces/Mandatement/Mandatement.docx
```

Cela révèle l'arborescence du serveur à n'importe quel visiteur. Tous ces
messages passent maintenant par `erreur_gabarit_absent()`
([includes/helpers.php](includes/helpers.php)), qui consigne le chemin dans le
journal et n'affiche qu'un texte neutre.

Les pages d'erreur 403/404/500 n'affichent, elles non plus, ni chemin, ni nom
de fichier, ni trace d'exécution.

## 8. Chemins d'assets

### Casse des noms — bloquant sous Linux

Invisible sous Windows (système insensible à la casse), **bloquant en
production** :

| Référencé | Réel |
|---|---|
| `pieces/projet/…` | `pieces/Projet/…` |
| `pieces/Accessoires/releve_service.docx` | `releve_de_service.docx` |

### Fichier manquant

`images/default.png`, utilisé comme avatar de repli par `index.php` et
`pages/profil/profil_photo.php`, n'existait pas. Créé.

---

## 9. Code mort supprimé

**29 fichiers** déplacés dans `_archive/` (conservés, mais bloqués en `403`) :

- 4 variantes obsolètes d'`index.php` et `avancements copy.php`
- `login.php` **cassé** : il postait `password` / `action` alors que
  `auth_process.php` attend `mdp` / `type_connexion`
- `info.php` (`phpinfo()`) et `debug_session.php` (destructeur de session),
  tous deux **exposés publiquement**
- `fonction_dos.php` : copie **obsolète** de `determinerTypesDossiers`
  (2 Ko contre 6 Ko dans `save_dos.php`). Archivée plutôt que fusionnée —
  une fusion aurait fait régresser la logique.
- `api_details.php`, doublon du bloc `details` d'`api_bordereaux.php`
- 8 fragments inutilisés de l'ancien dossier `sections/` et 3 CSS orphelins

### Code dupliqué fusionné

`nettoyerChaine()` était dupliquée **à l'identique dans 5 générateurs** →
[includes/helpers.php](includes/helpers.php). Vérifié : les 5 produisent des
`.docx` strictement identiques en octets après refactorisation.

`config.php` est devenu la source unique de connexion :
`check_session.php` et `save_user.php` ouvraient chacun la leur, avec des jeux
de caractères divergents (`utf8` en PDO contre `utf8mb4` en MySQLi).

---

## 10. Divergences entre fichiers

| Fichier | Problème |
|---|---|
| `api/notifications/get_unread_notifs.php` | Interrogeait `lu` et `date_reception_technique`. Les colonnes sont `est_lu` et `created_at` — seul fichier sur 8 à se tromper. |
| `api/bordereaux/api_bordereaux.php` | Lisait `statut_dren` sur `archives_bordereaux_dren` (c'est `statut_bordereau`) ; affichait `nom` sur une table ne stockant que l'IM. |
| `index.php` | `resp_presonnel_crfrp` — faute de frappe contre 29 occurrences correctes. **Les responsables CRFRP n'obtenaient jamais leurs droits.** |
| `includes/check_session.php` | Redirigeait vers `login_agent.php` en relatif → 404 depuis `api/`. |

---

## Ce qui reste — non corrigeable par du code

### Gabarits `.docx` jamais livrés

Ces fichiers sont référencés par le code mais **absents du dépôt**. Ils
échouent maintenant avec un message explicite au lieu de produire un fichier
corrompu, mais **les fichiers doivent être fournis** :

```
pieces/Mandatement/Mandatement.docx        (gabarit par défaut du mandatement)
pieces/template_fiche.docx                 (export de la fiche agent)
pieces/Projet/projet_arrete_titu.docx      (titularisation, mode « parrete »)
pieces/Projet/projet_decision_titu.docx    (titularisation, mode « pdecision »)
pieces/Projet/pv_cap_titu.docx             (titularisation, mode « pvcap »)
```

C'est la seule erreur restante du test de fumée
(`documents/export_word_logic.php` → HTTP 500). Elle est laissée **visible
volontairement**, pour ne pas masquer un manque réel.

### Fonctionnalités jamais développées

Huit entrées d'interface pointent vers des pages inexistantes :

```
retraite_admission.php      generate_retraite.php     forgot_password.php
creation_compte_dren.php    creation_compte_cisco.php distinction_demande.php
traitement_avenant.php      save_conge_history.php
```

`loadPage()` affiche désormais « Fonctionnalité non disponible » au lieu
d'« Erreur de chargement ». Les entrées de menu n'ont **pas** été retirées et
les pages n'ont **pas** été écrites : c'est une décision produit.

---

## Comment revérifier

```bash
docker compose up -d --build

# Syntaxe de tous les fichiers PHP
docker compose exec web bash -c 'cd /var/www/html && \
  for f in $(find . -name "*.php" -not -path "./vendor/*" \
    -not -path "./PHPMailer/*" -not -path "./_archive/*"); do php -l "$f"; done' \
  | grep -v "No syntax errors"

# Parcours complet, un profil à la fois (suit les redirections)
docker compose exec web bash .docker/smoke-test.sh ADMIN test1234 admin
docker compose exec web bash .docker/smoke-test.sh 504026 test1234 agent

# Audit des avertissements PHP — doit finir sur 0
# (procédure complète dans DOCKER.md)
docker compose exec web bash .docker/audit-runtime.sh
```

#!/bin/bash
# =====================================================================
#  Initialisation de la base de données du conteneur MySQL.
#
#  L'entrypoint MySQL exécute les fichiers de /docker-entrypoint-initdb.d
#  dans l'ordre alphabétique et s'arrête à la première erreur. Or l'export
#  phpMyAdmin (gestion_personnel.sql) échoue systématiquement sur la vue
#  `v_moteur_alertes` (alias de colonne > 64 caractères), ce qui
#  interrompait tout le reste de l'import.
#
#  On pilote donc l'import ici :
#    1. le dump est chargé avec --force (on passe outre l'erreur connue) ;
#    2. la vue est recréée correctement ;
#    3. les mots de passe de développement sont posés.
# =====================================================================
set -euo pipefail

MYSQL=(mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" --default-character-set=utf8mb4 "${MYSQL_DATABASE}")

echo "[init-db] 1/3 — import du dump (les erreurs connues sont ignorées)…"
"${MYSQL[@]}" --force < /sql/gestion_personnel.sql 2> >(grep -v "^ERROR 1166" >&2 || true)

echo "[init-db] 2/3 — recréation de la vue v_moteur_alertes…"
"${MYSQL[@]}" < /sql/fix-view-moteur-alertes.sql

# Monté uniquement par docker-compose.yml (dev) ; jamais en production.
if [ -f /sql/dev-passwords.sql ]; then
    echo "[init-db] 3/3 — mots de passe de développement…"
    "${MYSQL[@]}" < /sql/dev-passwords.sql
else
    echo "[init-db] 3/3 — mots de passe de développement : ignoré (production)."
fi

# Garde-fou : l'init doit échouer si la vue n'a pas été créée.
count=$("${MYSQL[@]}" -N -B -e "SELECT COUNT(*) FROM information_schema.views WHERE table_schema='${MYSQL_DATABASE}' AND table_name='v_moteur_alertes';")
if [ "$count" != "1" ]; then
    echo "[init-db] ÉCHEC : la vue v_moteur_alertes est absente." >&2
    exit 1
fi

echo "[init-db] Terminé : base prête."

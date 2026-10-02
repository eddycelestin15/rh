#!/usr/bin/env bash
# =====================================================================
#  Test de fumée : se connecte avec un compte donné puis appelle chaque
#  page / endpoint du projet en signalant les erreurs PHP rencontrées.
#
#  Usage (dans le conteneur web) :
#     bash .docker/smoke-test.sh [IM] [MOT_DE_PASSE] [type_connexion]
# =====================================================================
set -u

IM="${1:-ADMIN}"
MDP="${2:-test1234}"
TYPE="${3:-admin}"
BASE="http://localhost"
JAR="$(mktemp)"
OUT="$(mktemp)"

cd /var/www/html || exit 1

# --- Connexion ---------------------------------------------------------
# -L : on SUIT la redirection. Sans cela, une redirection cassée (par
# exemple « Location: index.php » émis depuis auth/, qui pointe vers
# /auth/index.php) passerait totalement inaperçue.
final=$(curl -s -L -o /dev/null -w '%{url_effective}|%{http_code}' -c "$JAR" \
        -d "im=$IM&mdp=$MDP&type_connexion=$TYPE" "$BASE/auth/auth_process.php")
url="${final%|*}"; code="${final##*|}"

if [ "$code" != "200" ] || [ "$url" != "$BASE/index.php" ]; then
    echo "ERREUR : connexion $IM -> $url (HTTP $code), attendu $BASE/index.php (200)"
    exit 1
fi
echo "Connecté : $IM ($TYPE)  [redirection suivie jusqu'à index.php]"
echo "--------------------------------------------------------------"

# Endpoints à ne pas appeler : ils modifient l'état ou terminent la session.
SKIP='(auth_process|logout|import_donnees|import_fpe|setup_admin)\.php$'

total=0; ok=0; http_err=0; php_err=0; refus=0

FILES=$(printf '%s\n' index.php; find pages api documents -name '*.php' 2>/dev/null | sort)

for f in $FILES; do
    echo "$f" | grep -qE "$SKIP" && continue
    total=$((total+1))

    status=$(curl -s -b "$JAR" -o "$OUT" -w '%{http_code}' "$BASE/$f")

    errs=$(grep -aoiE '(Fatal error|Parse error|Uncaught [A-Za-z]*(Exception|Error))[^<]{0,140}' "$OUT" | head -1)
    warns=$(grep -aciE 'Warning:|Notice:|Deprecated:' "$OUT")

    if [ -n "$errs" ]; then
        php_err=$((php_err+1))
        printf 'ERREUR  %-52s HTTP %s | %s\n' "$f" "$status" "$errs"
    elif [ "$status" = "400" ]; then
        # 400 = l'endpoint réclame des paramètres obligatoires absents ici :
        # c'est le comportement attendu, pas une panne.
        refus=$((refus+1))
    elif [ "$status" != "200" ] && [ "$status" != "302" ]; then
        http_err=$((http_err+1))
        printf 'HTTP    %-52s HTTP %s\n' "$f" "$status"
    elif [ "$warns" -gt 0 ]; then
        ok=$((ok+1))
        printf 'AVERTIS %-52s HTTP %s | %s avertissement(s)\n' "$f" "$status" "$warns"
    else
        ok=$((ok+1))
    fi
done

echo "--------------------------------------------------------------"
echo "Total : $total | OK : $ok | Paramètres requis (400) : $refus | Erreurs PHP : $php_err | Erreurs HTTP : $http_err"
rm -f "$JAR" "$OUT"
[ "$php_err" -eq 0 ] && [ "$http_err" -eq 0 ] || exit 1

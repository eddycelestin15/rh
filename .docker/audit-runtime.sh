#!/usr/bin/env bash
# =====================================================================
#  Balayage complet : appelle chaque page et chaque endpoint avec des
#  parametres realistes, sous plusieurs profils, et laisse le capteur
#  (.docker/audit-prepend.php) journaliser tous les avertissements PHP
#  dans /tmp/audit-errors.log — meme ceux que les scripts masquent.
# =====================================================================
set -u
cd /var/www/html || exit 1

BASE="http://localhost"
IM="${AUDIT_IM:-367058}"
ALERTE="${AUDIT_ALERTE:-367058_RNC1}"

: > /tmp/audit-errors.log

# --- Un passage par profil -------------------------------------------------
for compte in "ADMIN:admin" "350350:responsable" "321321:responsable" \
              "300300:responsable" "360360:responsable" "504026:agent"; do
    im_c="${compte%%:*}"; type_c="${compte##*:}"
    JAR=$(mktemp)
    curl -s -o /dev/null -c "$JAR" \
         -d "im=$im_c&mdp=test1234&type_connexion=$type_c" \
         "$BASE/auth/auth_process.php"

    # Toutes les pages et endpoints, en GET, avec les parametres usuels
    for f in index.php $(find pages api documents -name '*.php' | sort); do
        case "$f" in
            *auth_process*|*logout*) continue ;;
        esac
        curl -s -b "$JAR" -o /dev/null --max-time 25 \
             "$BASE/$f?im=$IM&alerte_id=$ALERTE&id=1&type=RNC1&annee=2026"
    done

    # Endpoints d'ecriture et d'action : en POST, avec une action plausible
    for f in $(find actions api -name '*.php' | sort); do
        for act in "" "liste" "details" "get_agents_bordereau_complet" \
                   "get_regions" "get_corps" "get_dossiers" "get_bordereaux"; do
            curl -s -b "$JAR" -o /dev/null --max-time 25 \
                 -d "action=$act&im=$IM&alerte_id=$ALERTE&id=1&dest=dren&destination=dren" \
                 "$BASE/$f"
        done
    done

    rm -f "$JAR"
done

echo "Balayage termine."
echo "Avertissements captes : $(wc -l < /tmp/audit-errors.log)"

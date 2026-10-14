#!/bin/sh
# Démarrage du conteneur : migrations, données de démo optionnelles, puis Apache.
set -e
cd /var/www/html

# Les commandes Symfony tournent avec l'utilisateur du serveur web pour ne pas créer de fichiers root dans var/.
console() { runuser -u www-data -- php bin/console "$@"; }

# La base peut mettre quelques secondes à répondre (ex. base serverless qui se réveille).
attempt=1
until console doctrine:migrations:migrate --no-interaction --allow-no-migration; do
    if [ "$attempt" -ge 6 ]; then
        echo "Migrations impossibles après $attempt tentatives : arrêt." >&2
        exit 1
    fi
    attempt=$((attempt + 1))
    sleep 5
done

# DEMO_MODE=1 : remet les données de démonstration à zéro à chaque démarrage (déploiement, ou réveil après
# la mise en veille de l'hébergeur). La commande refuse de purger une base qui contient de vrais comptes ;
# dans ce cas le serveur démarre quand même.
if [ "${DEMO_MODE:-0}" = "1" ]; then
    console app:demo:seed --reset || echo "Remise à zéro de la démo ignorée." >&2
fi

exec apache2-foreground

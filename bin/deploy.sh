#!/usr/bin/env bash
# Déploiement de mosltrans.fr en production.
# Usage : ./bin/deploy.sh
set -euo pipefail

PROJET="/var/www/mosltrans"
HORODATAGE=$(date +%F-%H%M)

cd "$PROJET"

echo "→ Sauvegarde de la base"
mkdir -p var/sauvegardes
cp var/data.db "var/sauvegardes/data-$HORODATAGE.db"

echo "→ Récupération du code"
git pull origin main

echo "→ Dépendances de production"
composer install --no-dev --optimize-autoloader

echo "→ Migrations"
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "→ Compilation des assets"
php bin/console tailwind:build --minify --env=prod
php bin/console asset-map:compile --env=prod

echo "→ Cache"
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "→ Droits"
chown -R www-data:www-data var/
chmod -R 775 var/

echo "Déploiement terminé le $HORODATAGE"

#!/usr/bin/env bash
# Script de déploiement exécuté SUR le VPS (appelé en SSH par deploy.yml).
# Structure attendue : /var/www/mosltrans/{releases,shared,current}
#   shared/.env.local  -> secrets de production (jamais dans Git)
#   shared/var/        -> base SQLite / logs persistants
set -euo pipefail

SHA="${1:?usage: deploy.sh <git-sha>}"
BASE=/var/www/mosltrans
RELEASE="$BASE/releases/$(date +%Y%m%d%H%M%S)-${SHA:0:7}"

git clone --quiet git@github.com:sidr-valley-ai/mosltrans.fr.git "$RELEASE"
git -C "$RELEASE" checkout --quiet "$SHA"

cd "$RELEASE"
ln -s "$BASE/shared/.env.local" .env.local
ln -s "$BASE/shared/var" var

export APP_ENV=prod
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear

# Bascule atomique : repointer "current" vers la nouvelle release
ln -sfn "$RELEASE" "$BASE/current"
sudo systemctl reload php8.4-fpm

# Garder les 5 dernières releases (retour arrière = repointer "current")
ls -1dt "$BASE"/releases/* | tail -n +6 | xargs -r rm -rf

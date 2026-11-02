#!/usr/bin/env bash
#
# Mettre à jour MOK-Business après de nouvelles modifications (sur le serveur).
# À lancer quand une nouvelle version est disponible sur GitHub :
#
#   cd /var/www/mok-business && sudo bash deploy/mettre-a-jour.sh
# ------------------------------------------------------------------------------

set -euo pipefail
DOSSIER="/var/www/mok-business"
cd "${DOSSIER}"

echo ">>> Récupération de la nouvelle version…"
git pull

echo ">>> Mise à jour des dépendances…"
composer install --no-dev --optimize-autoloader

echo ">>> Base de données + caches…"
php artisan migrate --force
php artisan config:cache
php artisan route:cache

chown -R www-data:www-data "${DOSSIER}"
chmod -R 775 "${DOSSIER}/storage" "${DOSSIER}/bootstrap/cache"
systemctl reload php8.2-fpm nginx

echo ">>> Mise à jour terminée ✅  (pense à vider le cache sur ton téléphone si besoin)"

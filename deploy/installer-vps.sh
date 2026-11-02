#!/usr/bin/env bash
#
# Installation automatique de MOK-Business sur un serveur VPS (Ubuntu 22.04 / 24.04).
# À lancer UNE SEULE FOIS, sur un serveur neuf, connecté en root (ou avec sudo).
#
#   1. Modifie les 2 lignes ci-dessous (DOMAINE et EMAIL).
#   2. Copie ce fichier sur le serveur, puis lance :  sudo bash installer-vps.sh
#
# Le script installe PHP 8.2, MySQL, Nginx, Composer, récupère l'application,
# crée la base de données, configure le site et active le HTTPS gratuit.
# ------------------------------------------------------------------------------

set -euo pipefail

# ====== À MODIFIER =============================================================
DOMAINE="votre-domaine.com"          # ← ton nom de domaine (sans https://)
EMAIL="oumarkeita410@gmail.com"      # ← ton email (pour le certificat HTTPS)
REPO="https://github.com/oumarkeita410-hash/boutique-pro.git"
# ==============================================================================

DOSSIER="/var/www/mok-business"
DB_NAME="mok_business"
DB_USER="mok_user"
# Mot de passe de la base généré au hasard (noté à la fin).
DB_PASS="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 24)"

echo ">>> 1/7 — Mise à jour du système…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y && apt-get upgrade -y

echo ">>> 2/7 — Installation de PHP, MySQL, Nginx, Composer…"
apt-get install -y software-properties-common curl unzip git
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml \
  php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd php8.2-intl \
  mysql-server nginx
# Composer
if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
fi

echo ">>> 3/7 — Création de la base de données…"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo ">>> 4/7 — Récupération de l'application…"
if [ -d "${DOSSIER}/.git" ]; then
  git -C "${DOSSIER}" pull
else
  git clone "${REPO}" "${DOSSIER}"
fi
cd "${DOSSIER}"

echo ">>> 5/7 — Configuration de l'application…"
composer install --no-dev --optimize-autoloader
if [ ! -f .env ]; then
  cp .env.example .env
fi
# Renseigne les valeurs de production dans le .env.
sed -i "s|^APP_ENV=.*|APP_ENV=production|"                         .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|"                          .env
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAINE}|"                 .env
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|"                  .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|"                 .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|"                 .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|"                 .env
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
# Droits : le serveur web doit pouvoir écrire dans storage/ et bootstrap/cache.
chown -R www-data:www-data "${DOSSIER}"
chmod -R 775 "${DOSSIER}/storage" "${DOSSIER}/bootstrap/cache"

echo ">>> 6/7 — Configuration du site Nginx…"
cat > /etc/nginx/sites-available/mok <<NGINX
server {
    listen 80;
    server_name ${DOMAINE} www.${DOMAINE};
    root ${DOSSIER}/public;
    index index.php;

    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(self), camera=(), microphone=(), payment=()" always;
    add_header Cross-Origin-Opener-Policy "same-origin" always;

    client_max_body_size 20M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/mok /etc/nginx/sites-enabled/mok
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo ">>> 7/7 — Activation du HTTPS gratuit (Let's Encrypt)…"
apt-get install -y certbot python3-certbot-nginx
certbot --nginx -d "${DOMAINE}" -d "www.${DOMAINE}" \
  --non-interactive --agree-tos -m "${EMAIL}" --redirect || \
  echo "!! Le HTTPS n'a pas pu s'activer. Vérifie que le domaine pointe bien vers ce serveur, puis relance : certbot --nginx -d ${DOMAINE}"

echo ""
echo "==================================================================="
echo " INSTALLATION TERMINÉE 🎉"
echo "   Application   : https://${DOMAINE}/app/"
echo "   Base de données : ${DB_NAME}"
echo "   Utilisateur DB  : ${DB_USER}"
echo "   Mot de passe DB : ${DB_PASS}"
echo "   >>> NOTE CE MOT DE PASSE QUELQUE PART DE SÛR <<<"
echo "==================================================================="

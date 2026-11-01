# Mise en ligne — MOK-Business

Guide pour publier l'application sur internet, en toute sécurité.
Deux cas : **hébergement mutualisé (cPanel/Apache)** — le plus simple — et **serveur VPS (Nginx)**.

> Important pour les deux cas : la **racine web doit pointer sur le dossier `public/`**,
> jamais sur la racine du projet (sinon `.env` et le code seraient exposés).

---

## Préparer (une fois, avant tout)

Sur le serveur, dans le dossier du projet :

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env            # puis remplir les valeurs (voir ci-dessous)
php artisan key:generate
php artisan migrate --force
php artisan config:cache && php artisan route:cache
```

Dans `.env` (serveur), au minimum :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com
# Base de données (créée chez l'hébergeur)
DB_CONNECTION=mysql
DB_DATABASE=...   DB_USERNAME=...   DB_PASSWORD=...
# Paiement CinetPay (quand le compte marchand est prêt)
CINETPAY_APIKEY=...   CINETPAY_SITE_ID=...   CINETPAY_SECRET_KEY=...
CINETPAY_NOTIFY_URL=https://votre-domaine.com/api/webhooks/cinetpay
CINETPAY_RETURN_URL=https://votre-domaine.com/app/
```

---

## Cas A — Hébergement mutualisé (cPanel / Apache) — le plus simple

1. Crée une **base de données MySQL** dans cPanel (note le nom, l'utilisateur, le mot de passe).
2. Envoie les fichiers du projet (via le gestionnaire de fichiers ou FTP).
3. Fais pointer le **domaine (ou sous-domaine)** sur le dossier `public/` du projet
   (dans cPanel : « Domaines » → racine du document = `.../public`).
4. Remplis le `.env` et lance les commandes « Préparer » ci-dessus
   (via le **Terminal** cPanel ou SSH ; sinon demande à l'hébergeur).
5. Active le **HTTPS gratuit** (cPanel → SSL/TLS → AutoSSL ou Let's Encrypt).
6. Les **en-têtes de sécurité sont déjà** dans `public/.htaccess` — rien à configurer.
   Une fois le HTTPS actif, tu peux dé-commenter la ligne `Strict-Transport-Security`.

C'est tout. L'application est en ligne et sécurisée.

---

## Cas B — Serveur VPS avec Nginx

Installe PHP 8.2+ (php-fpm), MySQL, Nginx, Composer, puis le certificat HTTPS
(`sudo certbot --nginx`). Configuration du site (`/etc/nginx/sites-available/mok`) :

```nginx
server {
    listen 80;
    server_name votre-domaine.com;
    # Force le HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name votre-domaine.com;

    # Certificats (posés par certbot)
    ssl_certificate     /etc/letsencrypt/live/votre-domaine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votre-domaine.com/privkey.pem;

    # LA RACINE POINTE SUR public/, jamais sur le dossier du projet
    root /var/www/mok-business/public;
    index index.php;

    # En-têtes de sécurité (sur tout le site, y compris la page /app)
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(self), camera=(), microphone=(), payment=()" always;
    add_header Cross-Origin-Opener-Policy "same-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    # Ne jamais servir les fichiers cachés / sensibles
    location ~ /\.(?!well-known).* { deny all; }
}
```

Puis : `sudo ln -s /etc/nginx/sites-available/mok /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx`.

---

## Après la mise en ligne

- Vérifie le **cadenas HTTPS** dans le navigateur.
- Donne les URLs de webhook à CinetPay (et Orange si compte marchand) :
  `https://votre-domaine.com/api/webhooks/cinetpay`
- **Sauvegarde** la base de données chaque jour.
- Garde le `.env` **secret** (jamais sur GitHub — il est déjà ignoré).

Voir aussi `SECURITE.md` pour la liste des protections.

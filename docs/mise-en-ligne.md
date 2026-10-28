# Mettre MOK-Business en ligne (accessible partout, 24h/24)

Ce guide explique comment héberger l'application pour que vos vendeurs y accèdent
depuis n'importe où, sur téléphone, tablette ou ordinateur.

---

## Ce qu'il vous faut

1. **Un nom de domaine** (ex. `mok-business.com`) — environ 10 $/an.
2. **Un hébergement** qui sait faire tourner **Laravel 12 / PHP 8.2+** avec **MySQL**.
3. **Le HTTPS** (cadenas) — **obligatoire** : sans lui, la localisation et l'installation de
   l'application sur le téléphone ne fonctionnent pas. Tous les hébergeurs ci-dessous le donnent gratuitement.

---

## Choisir un hébergement

### Option A — VPS + Laravel Forge / Ploi (recommandé, fiable 24h/24)
- Un **VPS** (serveur) chez Hetzner, DigitalOcean, Contabo, OVH… (~5–10 $/mois).
- **Forge** (forge.laravel.com) ou **Ploi** (ploi.io) configure tout pour vous : PHP, MySQL,
  HTTPS automatique, le **cron** et la **file d'attente**. On relie le serveur, on clique « Deploy ».
- Avantage : pensé pour Laravel, le cron des rappels et des ventes tourne sans souci.

### Option B — Plateforme tout-en-un (la plus simple)
- **Laravel Cloud** (cloud.laravel.com), **Railway** (railway.app) ou **Render** (render.com).
- On connecte le code (via GitHub), la base MySQL est fournie, le HTTPS est automatique.
- Demande une carte bancaire et une interface en anglais.

### Option C — Hébergement mutualisé cPanel (le moins cher)
- Beaucoup d'hébergeurs africains/français proposent cPanel avec PHP 8.2 + MySQL (~3–5 $/mois).
- Possible, mais le déploiement de Laravel y est plus manuel (voir la section cPanel plus bas).

> **Conseil.** Pour une boutique qui doit marcher 24h/24 dans 33 préfectures, l'**option A** est la plus
> solide. L'option B est la plus rapide à lancer. L'option C est la moins chère si le budget est serré.

---

## Étapes de déploiement (VPS / Forge / Ploi / plateforme)

1. **Mettre le code sur GitHub** (dépôt privé) depuis ce dossier :
   ```bash
   git init && git add . && git commit -m "MOK-Business"
   # puis créer un dépôt privé sur github.com et :
   git remote add origin https://github.com/VOTRE-COMPTE/mok-business.git
   git push -u origin main
   ```
   > Vérifiez que `.env` n'est PAS envoyé (il est déjà ignoré par `.gitignore`).

2. **Créer le site** sur Forge/Ploi/la plateforme, relié à ce dépôt. Choisir PHP 8.2+.

3. **Créer la base MySQL** (l'hébergeur donne le nom, l'utilisateur, le mot de passe).

4. **Configurer le `.env`** du serveur à partir de `.env.production.example` :
   - remplir la base de données, `ADMIN_PASSWORD`, `APP_URL` (votre domaine en https).

5. **Commandes de déploiement** (Forge/Ploi les lancent automatiquement à chaque « Deploy ») :
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate --force      # seulement la 1re fois
   php artisan migrate --force
   php artisan db:seed --force           # seulement la 1re fois (admin + réparateurs + catalogue)
   php artisan optimize                  # cache config/routes/vues (production rapide)
   ```

6. **Activer le HTTPS** (bouton « SSL / Let's Encrypt » — automatique partout).

7. **Programmer le cron** (rappels « heure de travailler », alertes dettes) — une seule ligne :
   ```
   * * * * * cd /chemin/du/site && php artisan schedule:run >> /dev/null 2>&1
   ```
   (Forge/Ploi : bouton « Scheduler », rien à taper.)

8. **File d'attente** (facultatif pour l'instant — l'appli marche sans). Si vous l'activez plus tard :
   `php artisan queue:work` en service permanent (Forge/Ploi : bouton « Queue Worker »).

C'est fini : ouvrez `https://votre-domaine.com` → l'application s'ouvre directement.

---

## Cas particulier — hébergement mutualisé cPanel

1. Envoyer les fichiers du projet dans un dossier **hors** `public_html` (ex. `/home/compte/mok`).
2. Mettre le **contenu de `public/`** dans `public_html`, et dans `public_html/index.php`
   corriger les deux chemins `require` pour pointer vers `/home/compte/mok/vendor/autoload.php`
   et `/home/compte/mok/bootstrap/app.php`.
3. Créer la base MySQL dans cPanel, remplir le `.env`.
4. Lancer les commandes via « Terminal » cPanel (ou SSH) : `composer install --no-dev`,
   `php artisan key:generate --force`, `php artisan migrate --force`, `php artisan db:seed --force`,
   `php artisan optimize`.
5. Activer **AutoSSL** (HTTPS) et ajouter la tâche **Cron** de l'étape 7 ci-dessus.

---

## Après la mise en ligne

- Chaque vendeur ouvre `https://votre-domaine.com` sur son téléphone, se connecte avec son identifiant
  et son mot de passe, puis **« Ajouter à l'écran d'accueil »** pour installer l'application.
- Pour verrouiller l'appareil sur la seule application : voir `docs/verrouiller-les-appareils.md`.
- **Sauvegardes** : activez la sauvegarde automatique de la base MySQL chez l'hébergeur
  (quotidienne). C'est votre filet de sécurité.
- **Mettre à jour l'application** plus tard : `git push`, puis « Deploy » (ou re-lancer les commandes
  de l'étape 5, sans le `db:seed`).

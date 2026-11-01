# Sécurité — MOK-Business

Ce qui est **déjà** protégé dans le code, et ce qu'il faut **régler à la mise en ligne**.

## ✅ Protections déjà dans l'application

- **Mots de passe** chiffrés (hachage), jamais stockés en clair.
- **Connexion limitée** : 6 essais par minute, puis **blocage du compte après 3 tentatives d'intrusion** (accès à une zone interdite).
- **Rôles** : vendeur / conseiller / admin ; chacun ne voit que ce qui le concerne. Le prix d'achat n'est jamais montré au vendeur.
- **Chaque vendeur n'agit que sur ses propres ventes** (encaissement, paiement) — vérifié côté serveur.
- **Factures non modifiables** sans accord de l'administrateur ; toute action sensible est tracée dans le **journal d'activité**.
- **Paiements** : une vente ne passe « payée » que par vérification serveur (CinetPay re-vérifié ; Orange Money : jeton + montant contrôlés). Un faux message ne peut pas marquer une vente payée.
- **En-têtes de sécurité** sur les réponses du serveur (anti-clickjacking, anti-sniffing, politique de référent) + **CSP** sur la page (scripts limités à notre site).
- **Secrets** (clés de paiement, WhatsApp) dans `.env`, jamais dans le code ni sur GitHub.

## ⚠️ À régler lors de la mise en ligne (hébergement)

1. **HTTPS obligatoire** (cadenas). Certificat gratuit via Let's Encrypt.
2. Dans le `.env` du serveur :
   - `APP_ENV=production`
   - `APP_DEBUG=false`  ← important : sinon les erreurs révèlent des détails internes.
   - `APP_KEY=` bien généré (`php artisan key:generate`).
3. **En-têtes aussi sur les fichiers statiques** de la page (le middleware PHP ne les couvre pas). Exemple **nginx** :

   ```nginx
   add_header X-Frame-Options "DENY" always;
   add_header X-Content-Type-Options "nosniff" always;
   add_header Referrer-Policy "strict-origin-when-cross-origin" always;
   add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
   ```

   Exemple **Apache** (`.htaccess`) :

   ```apache
   Header always set X-Frame-Options "DENY"
   Header always set X-Content-Type-Options "nosniff"
   Header always set Referrer-Policy "strict-origin-when-cross-origin"
   Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
   ```

4. **Sauvegardes** régulières de la base de données (au moins chaque jour).
5. **Mises à jour** : `composer update` de temps en temps pour les correctifs de sécurité.
6. Ne jamais mettre le vrai `.env` sur GitHub (il est déjà ignoré).

## Tester rapidement

```bash
php artisan test        # tout doit passer
```

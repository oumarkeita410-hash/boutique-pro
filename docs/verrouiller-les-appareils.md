# Verrouiller les appareils des vendeurs — MOK-Business

But : empêcher un vendeur de quitter l'application pour aller sur d'autres sites ou applications.

> **À savoir d'abord.** L'application elle-même ne peut pas bloquer tout l'appareil (aucune application web ne le peut). Le verrouillage se fait **une fois, par l'administrateur, sur chaque appareil**, avec les réglages du système ci-dessous. C'est gratuit et intégré à Android et à Windows.

---

## 1. Installer l'application en plein écran (à faire sur chaque appareil)

1. Ouvrir le navigateur (Chrome) et aller sur l'adresse de l'application
   (ex. `http://192.168.1.188:8000/app/`, ou votre adresse internet une fois en ligne).
2. Menu du navigateur (⋮) → **« Ajouter à l'écran d'accueil »** / **« Installer l'application »**.
3. L'application s'ouvre alors **sans barre d'adresse** : le vendeur ne peut pas taper un autre site dedans.

---

## 2. Android — Épinglage d'écran (gratuit, intégré)

Garde l'appareil sur **une seule application**. Pour sortir, il faut un code.

1. **Réglages → Sécurité → Épinglage d'écran** (ou « Épinglage d'application ») → **Activer**.
2. Activer aussi **« Demander le code avant de désépingler »**.
3. Ouvrir l'application MOK-Business.
4. Bouton **Aperçu** (carré ou gestes récents) → appuyer sur l'icône de l'appli en haut → **« Épingler »**.
5. Pour sortir (vous, l'administrateur) : maintenir **Retour + Aperçu**, puis saisir le code de l'appareil.

> Le vendeur reste ainsi bloqué dans l'application. Il ne peut pas ouvrir Chrome, WhatsApp, YouTube, etc.

### Pour plusieurs appareils (flotte) — mode kiosque
Pour 10, 20, 50 appareils dans vos préfectures, utiliser un outil de **gestion d'appareils (MDM) / mode kiosque** :
Scalefusion, Esper, Fully Kiosk Browser, ou Android Enterprise. Ils permettent de verrouiller à distance
tous les appareils sur la seule application MOK-Business, depuis un tableau de bord.

---

## 3. Windows (ordinateur) — Accès attribué (kiosque, intégré)

1. Créer un **compte local** dédié à la vente (ex. « Vendeur »), sans droits administrateur.
2. **Paramètres → Comptes → Autres utilisateurs → Configurer un kiosque** (« Accès attribué »).
3. Choisir le compte « Vendeur », puis l'application **Microsoft Edge** en mode kiosque.
4. Indiquer l'adresse de l'application MOK-Business comme page unique.
5. Edge en mode kiosque n'affiche **ni barre d'adresse, ni autres onglets** : impossible d'aller ailleurs.

> Pour sortir du kiosque : **Ctrl + Alt + Suppr** → changer d'utilisateur → revenir sur votre compte administrateur.

---

## 4. Ce que l'application fait déjà de son côté

- Aucune page de l'application n'emmène vers un site extérieur.
- Un vendeur qui tente d'ouvrir une zone réservée à l'administrateur est **signalé**, et son compte est
  **bloqué automatiquement au bout de 3 tentatives** (réactivation par l'administrateur seulement).
- La position et l'appareil sont enregistrés à la connexion et à chaque vente (le vendeur en est informé).

---

## 5. Conseils

- Mettre un **code d'écran** solide sur chaque appareil, connu de vous seul.
- Noter quel appareil est confié à quel vendeur.
- En cas de perte ou de départ d'un vendeur : **verrouiller son compte** dans l'application
  (Comptes → le vendeur → Verrouiller), ce qui le déconnecte immédiatement partout.

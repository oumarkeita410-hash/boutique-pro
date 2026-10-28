/* MOK-Business — application mobile (PWA). Fonctionne avec et sans internet. */
'use strict';

const API = location.origin + '/api';
const $ = (sel, el = document) => el.querySelector(sel);
const app = $('#app');

/* ---------- État local (persistant dans le navigateur) ---------- */
const store = {
  get token() { return localStorage.getItem('mok_token'); },
  set token(v) { v ? localStorage.setItem('mok_token', v) : localStorage.removeItem('mok_token'); },
  get user() { try { return JSON.parse(localStorage.getItem('mok_user') || 'null'); } catch { return null; } },
  set user(v) { v ? localStorage.setItem('mok_user', JSON.stringify(v)) : localStorage.removeItem('mok_user'); },
};

/* ---------- Thème clair / sombre ---------- */
function appliquerTheme(t) { document.documentElement.setAttribute('data-theme', t); localStorage.setItem('mok_theme', t); }
function themeActuel() { return localStorage.getItem('mok_theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); }
function basculerTheme() { appliquerTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); }
appliquerTheme(themeActuel());

/* ---------- Petites aides ---------- */
function toast(msg) {
  const t = $('#toast'); t.textContent = msg; t.hidden = false;
  clearTimeout(toast._t); toast._t = setTimeout(() => { t.hidden = true; }, 3200);
}
function gnf(n) { return (Number(n) || 0).toLocaleString('fr-FR') + ' GNF'; }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
function uuid() {
  if (crypto.randomUUID) return crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); });
}
function position() {
  return new Promise(resolve => {
    if (!navigator.geolocation) return resolve(null);
    navigator.geolocation.getCurrentPosition(
      p => resolve({ latitude: +p.coords.latitude.toFixed(7), longitude: +p.coords.longitude.toFixed(7), precision_m: Math.round(p.coords.accuracy) }),
      () => resolve(null), { timeout: 6000, maximumAge: 60000 });
  });
}

/* ---------- Appels API ---------- */
async function api(methode, chemin, corps) {
  const headers = { 'Accept': 'application/json' };
  if (corps) headers['Content-Type'] = 'application/json';
  if (store.token) headers['Authorization'] = 'Bearer ' + store.token;
  const res = await fetch(API + chemin, { method: methode, headers, body: corps ? JSON.stringify(corps) : undefined });
  const data = res.status === 204 ? null : await res.json().catch(() => ({}));
  if (res.status === 401) { deconnexion(); throw new ApiError('Session terminée.', 401, data); }
  if (!res.ok) throw new ApiError((data && data.message) || 'Erreur.', res.status, data);
  return data;
}
class ApiError extends Error {
  constructor(message, status, data) { super(message); this.status = status; this.data = data; }
  premierDetail() { const e = this.data && this.data.errors; if (e) { const k = Object.keys(e)[0]; return e[k][0]; } return this.message; }
}

/* ================================================================  CONNEXION  */
const ROLES = [
  { v: 'admin', nom: 'Administrateur', emoji: '🛡️' },
  { v: 'vendeur', nom: 'Vendeur', emoji: '🛒' },
  { v: 'conseiller', nom: 'Conseiller', emoji: '🎧' },
];
function ecranConnexion() {
  app.innerHTML = `
    <div class="login-wrap">
      <div class="logo"><div class="mark">MB</div><h1>MOK<span>-Business</span></h1>
        <p>Boutique électronique — Guinée Conakry</p></div>
      <div class="roles">${ROLES.map((r, i) => `<div class="role-chip ${i === 1 ? 'active' : ''}" data-role="${r.v}"><span class="emoji">${r.emoji}</span>${r.nom}</div>`).join('')}</div>
      <form id="form-login" novalidate>
        <div class="field"><label for="identifiant">Identifiant, téléphone ou email</label>
          <input id="identifiant" autocomplete="username" autocapitalize="none" required></div>
        <div class="field"><label for="password">Mot de passe</label>
          <input id="password" type="password" autocomplete="current-password" required></div>
        <div class="error-msg" id="login-error"></div>
        <button class="btn" type="submit" id="login-btn">Se connecter</button>
      </form>
      <div class="btn-row mt"><button class="btn secondary" id="theme-btn" type="button">🌓 Thème clair / sombre</button></div>
      <p class="notice">L'accès à chaque compte nécessite un identifiant et un mot de passe fournis par l'administrateur.<br>
        Votre position et votre appareil sont enregistrés par MOK-Business pendant l'utilisation.</p>
    </div>`;
  $('.roles').addEventListener('click', e => {
    const c = e.target.closest('.role-chip'); if (!c) return;
    $('.roles').querySelectorAll('.role-chip').forEach(x => x.classList.remove('active')); c.classList.add('active');
    $('#identifiant').focus();
  });
  $('#theme-btn').addEventListener('click', basculerTheme);
  $('#form-login').addEventListener('submit', soumettreConnexion);
}
async function soumettreConnexion(e) {
  e.preventDefault();
  const btn = $('#login-btn'), err = $('#login-error'); err.textContent = '';
  const identifiant = $('#identifiant').value.trim(), password = $('#password').value;
  if (!identifiant || !password) { err.textContent = 'Remplissez les deux champs.'; return; }
  btn.disabled = true; btn.textContent = 'Connexion…';
  try {
    const pos = await position();
    const data = await api('POST', '/connexion', { identifiant, password, appareil: navigator.userAgent.slice(0, 80), ...(pos || {}) });
    store.token = data.token; store.user = data.utilisateur;
    if (data.avis) toast(data.avis);
    routerAccueil();
  } catch (ex) {
    err.textContent = navigator.onLine ? (ex.premierDetail ? ex.premierDetail() : 'Connexion impossible.') : 'Pas de connexion internet.';
  } finally { btn.disabled = false; btn.textContent = 'Se connecter'; }
}
function deconnexion() { api('POST', '/deconnexion').catch(() => {}); store.token = null; store.user = null; ecranConnexion(); }

/* ================================================================  CADRE  */
function cadre(titre, contenuHTML, onglets, actifIdx, opts = {}) {
  app.innerHTML = `
    <div id="barre"></div>
    <div class="topbar">
      ${opts.retour ? `<button class="icon-btn" id="btn-retour" title="Retour">←</button>` : `<div class="brand">MOK<span>·</span>B</div>`}
      <div class="spacer"></div>
      <button class="icon-btn" id="btn-theme" title="Thème clair/sombre">🌓</button>
      <button class="icon-btn" id="btn-logout" title="Se déconnecter">⎋</button>
    </div>
    <div class="content"><h2 class="screen-title">${esc(titre)}</h2><div id="vue">${contenuHTML}</div></div>
    ${onglets ? `<nav class="tabbar">${onglets.map((o, i) => `<button data-idx="${i}" class="${i === actifIdx ? 'active' : ''}"><span class="emoji">${o.emoji}</span>${o.nom}</button>`).join('')}</nav>` : ''}`;
  $('#btn-theme').addEventListener('click', basculerTheme);
  $('#btn-logout').addEventListener('click', deconnexion);
  if (opts.retour) $('#btn-retour').addEventListener('click', opts.retour);
  if (onglets) $('.tabbar').addEventListener('click', e => { const b = e.target.closest('button'); if (b) onglets[+b.dataset.idx].aller(); });
  majBarre();
}

/* Bandeau d'état : hors ligne et/ou ventes en attente d'envoi. */
function majBarre() {
  const el = $('#barre'); if (!el) return;
  const n = fileAttente.length;
  if (!navigator.onLine) {
    el.className = 'offline-bar';
    el.textContent = n ? `Hors ligne — ${n} vente(s) gardée(s), envoi automatique au retour d'internet`
      : "Hors ligne — les ventes seront gardées et envoyées au retour d'internet";
  } else if (n) {
    el.className = 'offline-bar';
    el.textContent = `${n} vente(s) en attente d'envoi…`;
  } else {
    el.className = ''; el.textContent = '';
  }
}

/* ================================================================  AIGUILLAGE  */
function routerAccueil() {
  const u = store.user;
  if (!u) return ecranConnexion();
  if (u.role === 'vendeur') return vendeurCatalogue();
  if (u.role === 'admin') return adminTableau();
  if (u.role === 'conseiller') return conseillerClients();
  ecranConnexion();
}

/* ================================================================  VENDEUR  */
const ongletsVendeur = () => [
  { nom: 'Vendre', emoji: '🛒', aller: vendeurCatalogue },
  { nom: 'Panier', emoji: '🧺', aller: vendeurPanier },
  { nom: 'Messages', emoji: '💬', aller: vendeurMessages },
  { nom: 'Mon jour', emoji: '🕗', aller: vendeurHoraire },
];

/* Panier en mémoire du navigateur */
let panier = [];
try { panier = JSON.parse(localStorage.getItem('mok_panier') || '[]'); } catch { panier = []; }
function sauverPanier() { localStorage.setItem('mok_panier', JSON.stringify(panier)); }
function nbPanier() { return panier.reduce((n, l) => n + l.quantite, 0); }
function ajouterAuPanier(p) {
  const l = panier.find(x => x.produit_id === p.id);
  if (l) l.quantite++; else panier.push({ produit_id: p.id, nom: p.nom, quantite: 1 });
  sauverPanier(); toast(p.nom + ' ajouté (' + nbPanier() + ')');
}

async function vendeurCatalogue() {
  cadre('Catalogue', `
    <div class="field"><input id="q" placeholder="Rechercher un article ou modèle…"></div>
    <p class="muted" style="font-size:.85rem;margin-top:-6px">Touchez un article pour l'ajouter au panier.</p>
    <div id="liste"><div class="spinner"></div></div>`, ongletsVendeur(), 0);
  const rendre = async (q = '') => {
    try {
      const data = await api('GET', '/catalogue' + (q ? '?recherche=' + encodeURIComponent(q) : ''));
      const items = data.data || [];
      $('#liste').innerHTML = items.length ? `<div class="card">${items.map(p => `
        <div class="list-item tap" data-id="${p.id}" data-nom="${esc(p.nom)}" role="button" tabindex="0">
          <div class="main"><div class="name">${esc(p.nom)}</div><div class="sub">${esc(p.modele || p.categorie || '')}</div></div>
          <span class="badge cat">+ Ajouter</span>
        </div>`).join('')}</div>` : `<p class="muted center mt">Aucun article trouvé.</p>`;
      $('#liste').querySelectorAll('.tap').forEach(el => el.addEventListener('click', () => {
        ajouterAuPanier({ id: +el.dataset.id, nom: el.dataset.nom });
        majBadgePanier();
      }));
    } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${navigator.onLine ? esc(ex.message) : 'Hors ligne — catalogue indisponible.'}</p>`; }
  };
  let t; $('#q').addEventListener('input', e => { clearTimeout(t); t = setTimeout(() => rendre(e.target.value.trim()), 300); });
  rendre(); majBadgePanier();
}
function majBadgePanier() {
  const b = $('.tabbar button[data-idx="1"]'); if (!b) return;
  const n = nbPanier();
  b.innerHTML = `<span class="emoji">🧺</span>Panier${n ? ' (' + n + ')' : ''}`;
}

function vendeurPanier() {
  cadre('Panier', '', ongletsVendeur(), 1);
  const rendre = () => {
    if (!panier.length) { $('#vue').innerHTML = `<p class="muted center mt">Panier vide. Ajoutez des articles depuis le catalogue.</p>`; return; }
    $('#vue').innerHTML = `
      <div class="card">${panier.map((l, i) => `
        <div class="list-item">
          <div class="main"><div class="name">${esc(l.nom)}</div></div>
          <div class="btn-row" style="width:auto">
            <button class="icon-btn" data-moins="${i}">−</button>
            <span style="min-width:28px;text-align:center;align-self:center">${l.quantite}</span>
            <button class="icon-btn" data-plus="${i}">+</button>
            <button class="icon-btn" data-sup="${i}" style="color:var(--danger)">🗑</button>
          </div>
        </div>`).join('')}</div>
      <div class="card">
        <div class="field"><label>Mode de paiement</label>
          <select id="mode"><option value="especes">Espèces</option><option value="orange_money">Orange Money</option><option value="carte">Carte bancaire</option></select></div>
        <div class="error-msg" id="p-err"></div>
        <button class="btn" id="valider">Valider la vente (${nbPanier()} article(s))</button>
      </div>`;
    $('#vue').querySelectorAll('[data-plus]').forEach(b => b.onclick = () => { panier[+b.dataset.plus].quantite++; sauverPanier(); rendre(); });
    $('#vue').querySelectorAll('[data-moins]').forEach(b => b.onclick = () => { const l = panier[+b.dataset.moins]; if (--l.quantite < 1) panier.splice(+b.dataset.moins, 1); sauverPanier(); rendre(); });
    $('#vue').querySelectorAll('[data-sup]').forEach(b => b.onclick = () => { panier.splice(+b.dataset.sup, 1); sauverPanier(); rendre(); });
    $('#valider').onclick = validerVente;
  };
  rendre();
}
async function validerVente() {
  const btn = $('#valider'), err = $('#p-err'); err.textContent = '';
  if (!panier.length) return;
  btn.disabled = true; btn.textContent = 'Enregistrement…';
  const pos = await position();
  const vente = { uuid_client: uuid(), lignes: panier.map(l => ({ produit_id: l.produit_id, quantite: l.quantite })), mode_paiement: $('#mode').value, ...(pos || {}) };

  // Hors ligne : on garde la vente tout de suite, elle partira seule au retour d'internet.
  if (!navigator.onLine) { return mettreEnAttente(vente); }

  try {
    const res = await api('POST', '/ventes', vente);
    panier = []; sauverPanier();
    if (res.data.mode_paiement === 'especes') {
      toast('Vente ' + res.data.reference + ' — encaissé : ' + gnf(res.data.montant_a_encaisser));
      vendeurCatalogue();
    } else {
      vendeurPaiement(res.data); // Orange Money / carte : instructions + référence
    }
  } catch (ex) {
    // Panne réseau / coupure pendant l'envoi (pas de réponse, ou erreur serveur) :
    // on NE REJETTE PAS la vente, on la garde pour l'envoyer automatiquement plus tard.
    if (!(ex instanceof ApiError) || ex.status >= 500 || ex.status === 429) {
      return mettreEnAttente(vente);
    }
    // Vrai refus (ex. données invalides) : on l'affiche.
    err.textContent = ex.premierDetail();
    btn.disabled = false; btn.textContent = 'Valider la vente';
  }
}

/* Garde la vente dans la file locale (survit à une coupure d'électricité : stockée sur l'appareil). */
function mettreEnAttente(vente) {
  fileAttente.push(vente); sauverFile();
  panier = []; sauverPanier();
  const om = vente.mode_paiement === 'orange_money' ? ' Faites payer au 621952252.' : '';
  toast('Vente gardée. Elle sera enregistrée automatiquement dès le retour d\'internet.' + om);
  vendeurCatalogue();
}

/* Écran paiement Orange Money (compte personnel) : montrer le numéro, saisir la référence */
async function vendeurPaiement(v) {
  cadre('Paiement', `<div id="p"><div class="spinner"></div></div>`, null, null, { retour: vendeurCatalogue });
  try {
    const info = await api('GET', '/ventes/' + v.id + '/instructions-paiement');
    $('#p').innerHTML = `
      <div class="card center">
        <div class="sub muted">Vente ${esc(v.reference)}</div>
        <div class="value" style="font-size:1.6rem">${gnf(info.montant)}</div>
        <p>Faites payer le client sur <b>${esc(info.mode === 'carte' ? 'carte bancaire' : 'Orange Money')}</b>${info.mode === 'orange_money' ? ' au numéro :' : '.'}</p>
        ${info.mode === 'orange_money' ? `<div class="value" style="font-size:1.4rem;color:var(--primary)">${esc(info.numero_orange_money)}</div>` : ''}
      </div>
      <div class="card">
        <div class="field"><label>Référence de la transaction (donnée par le client)</label><input id="ref" placeholder="ex. PP24..."></div>
        <div class="error-msg" id="ref-err"></div>
        <button class="btn" id="ref-btn">Enregistrer la référence</button>
        <button class="btn secondary block" id="skip">Plus tard</button>
        <p class="notice">Le paiement sera confirmé par l'administrateur après vérification sur le compte.</p>
      </div>`;
    $('#ref-btn').onclick = async () => {
      const err = $('#ref-err'); err.textContent = '';
      const reference_paiement = $('#ref').value.trim();
      if (!reference_paiement) { err.textContent = 'Saisissez la référence, ou touchez « Plus tard ».'; return; }
      try { await api('POST', '/ventes/' + v.id + '/reference-paiement', { reference_paiement }); toast('Référence enregistrée.'); vendeurCatalogue(); }
      catch (ex) { err.textContent = ex.premierDetail(); }
    };
    $('#skip').onclick = vendeurCatalogue;
  } catch (ex) { $('#p').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p><button class="btn mt" onclick="location.reload()">OK</button>`; }
}

/* File d'attente hors-ligne */
let fileAttente = [];
try { fileAttente = JSON.parse(localStorage.getItem('mok_file') || '[]'); } catch { fileAttente = []; }
function sauverFile() { localStorage.setItem('mok_file', JSON.stringify(fileAttente)); }
let syncEnCours = false;
async function synchroniser() {
  if (syncEnCours || !navigator.onLine || !fileAttente.length || !store.token) return;
  syncEnCours = true;
  const reste = []; let envoyees = 0, echouees = 0;
  for (const v of fileAttente) {
    try {
      await api('POST', '/ventes', v); // le serveur dédoublonne via uuid_client
      envoyees++;
    } catch (ex) {
      if (!(ex instanceof ApiError) || ex.status >= 500 || ex.status === 429 || !navigator.onLine) {
        reste.push(v); // problème temporaire → on réessaiera
      } else {
        // Refus définitif (ex. stock épuisé entre-temps) : on ne perd pas, on signale.
        const ech = JSON.parse(localStorage.getItem('mok_echecs') || '[]');
        ech.push({ vente: v, raison: ex.premierDetail ? ex.premierDetail() : 'refus', le: new Date().toISOString() });
        localStorage.setItem('mok_echecs', JSON.stringify(ech));
        echouees++;
      }
    }
  }
  fileAttente = reste; sauverFile(); syncEnCours = false;
  if (envoyees) toast(envoyees + ' vente(s) enregistrée(s) automatiquement.');
  if (echouees) toast(echouees + ' vente(s) non enregistrée(s) (ex. stock épuisé). Prévenez l\'administrateur.');
  majBarre();
}

async function vendeurMessages() {
  cadre('Messages', `<div id="fil"><div class="spinner"></div></div>
    <div class="card"><div class="field"><textarea id="msg" rows="3" placeholder="Écrire à l'administrateur (ex. un problème)…"></textarea></div>
      <div class="error-msg" id="m-err"></div><button class="btn" id="envoyer">Envoyer</button>
      <button class="btn secondary block" id="wa">💬 Contacter la boutique sur WhatsApp</button></div>`, ongletsVendeur(), 2);
  $('#wa').onclick = () => whatsAppBoutique('Bonjour, ');
  const charger = async () => {
    try {
      const msgs = await api('GET', '/messages');
      $('#fil').innerHTML = msgs.length ? `<div class="card">${msgs.map(m => `
        <div class="list-item"><div class="main"><div class="sub">${esc(m.auteur)} · ${esc(m.date)}</div>
          <div>${esc(m.contenu)}</div></div></div>`).join('')}</div>` : `<p class="muted center">Aucun message. Écrivez à l'administrateur ci-dessous.</p>`;
    } catch (ex) { $('#fil').innerHTML = `<p class="muted center">${esc(ex.message)}</p>`; }
  };
  $('#envoyer').onclick = async () => {
    const err = $('#m-err'); err.textContent = '';
    const contenu = $('#msg').value.trim();
    if (!contenu) { err.textContent = 'Message vide.'; return; }
    try { await api('POST', '/messages', { contenu }); $('#msg').value = ''; toast('Message envoyé.'); charger(); }
    catch (ex) { err.textContent = ex.premierDetail(); }
  };
  charger();
}

async function vendeurHoraire() {
  cadre('Mon jour de travail', `<div id="h"><div class="spinner"></div></div>`, ongletsVendeur(), 3);
  try {
    const d = await api('GET', '/mon-horaire'); const m = d.maintenant || {};
    const jours = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $('#h').innerHTML = `
      <div class="card center"><div style="font-size:2rem">${m.statut === 'en_service' ? '🟢' : m.statut === 'avant_service' ? '🟡' : '⚪'}</div>
        <h3 style="margin:.3rem 0">${esc(m.message || '')}</h3><p class="muted">Fuseau : ${esc(d.fuseau_horaire || '')}</p></div>
      <div class="card"><h3>Ma semaine</h3>${(d.jours && d.jours.length) ? d.jours.map(j => `<div class="list-item"><div class="main"><div class="name">${jours[j.jour_semaine] || j.jour_semaine}</div></div><span class="badge">${(j.heure_debut || '').slice(0, 5)} – ${(j.heure_fin || '').slice(0, 5)}</span></div>`).join('') : '<p class="muted">Aucun horaire défini.</p>'}</div>`;
  } catch (ex) { $('#h').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* ================================================================  ADMINISTRATEUR  */
const ongletsAdmin = () => [
  { nom: 'Accueil', emoji: '📊', aller: adminTableau },
  { nom: 'Comptes', emoji: '👥', aller: adminComptes },
  { nom: 'Produits', emoji: '📦', aller: adminProduits },
  { nom: 'Réparateurs', emoji: '🔧', aller: adminReparateurs },
  { nom: 'Plus', emoji: '≡', aller: adminPlus },
];

/* « Plus » : accès à Paie, Messages, Alertes */
function adminPlus() {
  cadre('Plus', `
    <button class="btn block" id="m-ventes">📒 Toutes les ventes</button>
    <button class="btn block secondary" id="m-vcredit">🧾 Vente à crédit (client)</button>
    <button class="btn block secondary" id="m-credits">💳 Crédits clients</button>
    <button class="btn block secondary" id="m-conf">✅ Paiements à confirmer</button>
    <button class="btn block secondary" id="m-paie">💵 Paie des vendeurs</button>
    <button class="btn block secondary" id="m-msg">💬 Messages des vendeurs</button>
    <button class="btn block secondary" id="m-alertes">🔔 Alertes et demandes</button>`, ongletsAdmin(), 4);
  $('#m-ventes').onclick = adminVentes;
  $('#m-vcredit').onclick = adminVenteCredit;
  $('#m-credits').onclick = adminCreditsClients;
  $('#m-conf').onclick = adminPaiementsConfirmer;
  $('#m-paie').onclick = adminPaie;
  $('#m-msg').onclick = adminMessages;
  $('#m-alertes').onclick = adminAlertes;
}

async function adminPaiementsConfirmer() {
  cadre('Paiements à confirmer', `<div id="pc"><div class="spinner"></div></div>`, null, null, { retour: adminPlus });
  try {
    const d = await api('GET', '/admin/paiements-a-confirmer');
    const items = d.data || [];
    $('#pc').innerHTML = items.length ? `<p class="muted" style="font-size:.85rem">Vérifiez sur votre compte Orange Money, puis confirmez.</p>
      <div class="card">${items.map(v => `
        <div class="list-item"><div class="main"><div class="name">${esc(v.reference)} — ${gnf(v.montant_total)}</div>
          <div class="sub">${esc(v.mode_paiement)} · ${v.vendeur ? esc(v.vendeur.prenom) + ' ' + esc(v.vendeur.name) : ''}${v.reference_paiement ? ' · réf ' + esc(v.reference_paiement) : ' · (pas de réf)'}</div></div>
          <button class="btn" style="width:auto;padding:8px 12px" data-ok="${v.id}">Confirmer</button>
        </div>`).join('')}</div>` : `<p class="muted center mt">Aucun paiement en attente.</p>`;
    $('#pc').querySelectorAll('[data-ok]').forEach(b => b.onclick = async () => {
      if (!confirm('Confirmer la réception de ce paiement ?')) return;
      try { await api('POST', '/admin/ventes/' + b.dataset.ok + '/confirmer-paiement'); toast('Paiement confirmé.'); adminPaiementsConfirmer(); }
      catch (ex) { toast(ex.premierDetail()); }
    });
  } catch (ex) { $('#pc').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

async function adminTableau() {
  cadre('Tableau de bord', `<div id="tb"><div class="spinner"></div></div>`, ongletsAdmin(), 0);
  try {
    const d = await api('GET', '/admin/tableau-de-bord'); const ca = d.chiffre_affaires || {};
    const jours = (d.ventes_par_jour || []).map(x => ({ label: x.jour, value: Number(x.montant), n: x.nombre }));
    const vendeurs = (d.top_vendeurs_mois || []).map(v => ({ nom: v.vendeur ? `${v.vendeur.prenom} ${v.vendeur.name}` : '—', value: Number(v.montant) }));
    const produits = (d.top_produits_mois || []).map(p => ({ nom: p.produit ? p.produit.nom : '—', value: Number(p.quantite), suffix: ' u.' }));
    const regions = (d.ca_par_region_mois || []).map(r => ({ nom: r.region, value: Number(r.montant) }));

    $('#tb').innerHTML = `
      <div class="hero-card">
        <div class="hero-label">Chiffre d'affaires du mois</div>
        <div class="hero-value">${gnf(ca.mois)}</div>
        <div class="hero-sub">Aujourd'hui : ${gnf(ca.aujourdhui)} · ${d.ventes_aujourdhui ?? 0} vente(s) · Marge du mois : ${gnf(d.marge_mois)}</div>
      </div>

      <div class="card"><h3>Ventes des 30 derniers jours</h3>${chartCourbe(jours)}</div>

      ${blocTop('Meilleurs vendeurs du mois', vendeurs, 'c1')}
      ${blocTop('Produits les plus vendus', produits, 'c2')}
      ${blocTop('Ventes par région / préfecture', regions, 'c3')}

      <div class="card"><h3>À surveiller</h3>
        <div class="stat-row">
          <div class="stat-chip ${d.stock?.en_rupture ? 'alerte' : ''}"><b>${d.stock?.en_rupture ?? 0}</b>En rupture de stock</div>
          <div class="stat-chip"><b>${d.stock?.stock_bas ?? 0}</b>Stock bas</div>
          <div class="stat-chip ${d.reparateurs?.en_alerte ? 'alerte' : ''}"><b>${d.reparateurs?.en_alerte ?? 0}</b>Réparateurs en alerte</div>
          <div class="stat-chip"><b style="font-size:.95rem">${gnf(d.reparateurs?.dette_totale)}</b>Dette réparateurs</div>
          <div class="stat-chip ${d.messages_non_lus ? 'alerte' : ''}"><b>${d.messages_non_lus ?? 0}</b>Messages non lus</div>
          <div class="stat-chip"><b>${d.comptes?.verrouilles ?? 0}</b>Comptes verrouillés</div>
          <div class="stat-chip"><b>${d.paiements_en_attente ?? 0}</b>Paiements à confirmer</div>
          <div class="stat-chip"><b>${d.clients?.total ?? 0}</b>Clients enregistrés</div>
        </div>
      </div>`;
  } catch (ex) { $('#tb').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* Courbe des ventes par jour (une seule série → couleur unique, pas de légende). */
function chartCourbe(data) {
  if (!data.length) return '<p class="muted">Aucune vente sur la période.</p>';
  if (data.length === 1) {
    return `<p class="muted">Une seule journée de ventes : ${gnf(data[0].value)}.</p>`;
  }
  const max = Math.max(1, ...data.map(d => d.value));
  const W = 300, H = 100, padX = 4, padT = 8, padB = 10;
  const n = data.length;
  const px = i => padX + (i * (W - padX * 2)) / (n - 1);
  const py = v => padT + (1 - v / max) * (H - padT - padB);
  const pts = data.map((d, i) => [px(i), py(d.value)]);
  const ligne = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
  const aire = `M${px(0).toFixed(1)},${(H - padB)} ` + pts.map(p => 'L' + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ') + ` L${px(n - 1).toFixed(1)},${(H - padB)} Z`;
  const points = data.map((d, i) => `<circle cx="${px(i).toFixed(1)}" cy="${py(d.value).toFixed(1)}" r="1.8" fill="var(--primary)"><title>${esc((d.label || '').slice(5))} : ${gnf(d.value)} (${d.n} vente(s))</title></circle>`).join('');
  return `<div class="chart-line"><svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Courbe des ventes par jour sur 30 jours">
    <line x1="${padX}" y1="${H - padB}" x2="${W - padX}" y2="${H - padB}" stroke="var(--border)" stroke-width="0.7"/>
    <path d="${aire}" fill="var(--primary)" fill-opacity="0.12"/>
    <path d="${ligne}" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
    ${points}</svg></div>
    <p class="muted" style="font-size:.74rem;text-align:right;margin:4px 0 0">Jour le plus fort : ${gnf(max)}</p>`;
}

/* Barres horizontales (classement par grandeur). `cls` = couleur du bloc (c1/c2/c3). */
function blocTop(titre, items, cls = 'c1') {
  if (!items.length) return '';
  const max = Math.max(1, ...items.map(i => i.value));
  return `<div class="card"><h3>${esc(titre)}</h3>${items.slice(0, 5).map(i => `
    <div class="hbar-row">
      <div class="hbar-name">${esc(i.nom)}</div>
      <div class="hbar-track"><div class="hbar-fill ${cls}" style="width:${Math.max(3, Math.round(i.value / max * 100))}%"></div></div>
      <div class="hbar-val">${i.suffix ? i.value + i.suffix : gnf(i.value)}</div>
    </div>`).join('')}</div>`;
}

/* ---- Comptes : liste + création + modification ---- */
async function adminComptes() {
  cadre('Comptes', `
    <button class="btn block" id="nouveau">➕ Nouveau compte</button>
    <div class="field mt"><input id="q" placeholder="Rechercher (nom, identifiant, téléphone)…"></div>
    <div id="liste"><div class="spinner"></div></div>`, ongletsAdmin(), 1);
  $('#nouveau').onclick = () => adminFormCompte();
  const rendre = async (q = '') => {
    try {
      const d = await api('GET', '/admin/utilisateurs' + (q ? '?recherche=' + encodeURIComponent(q) : ''));
      const items = d.data || [];
      $('#liste').innerHTML = `<div class="card">${items.map(u => `
        <div class="list-item tap" data-id="${u.id}" role="button" tabindex="0">
          <div class="main"><div class="name">${esc(u.prenom)} ${esc(u.nom)}</div>
          <div class="sub">${esc(u.identifiant)} · ${esc(u.role)}${u.region ? ' · ' + esc(u.region) : ''}</div></div>
          <span class="badge" style="color:${u.est_verrouille ? 'var(--danger)' : 'var(--success)'}">${u.est_verrouille ? 'Verrouillé' : 'Actif'}</span>
        </div>`).join('')}</div>`;
      $('#liste').querySelectorAll('.tap').forEach(el => el.onclick = () => adminDetailCompte(+el.dataset.id));
    } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  let t; $('#q').addEventListener('input', e => { clearTimeout(t); t = setTimeout(() => rendre(e.target.value.trim()), 300); });
  rendre();
}

function champ(id, label, val = '', type = 'text') {
  return `<div class="field"><label for="${id}">${label}</label><input id="${id}" type="${type}" value="${esc(val)}"></div>`;
}
function adminFormCompte(u = null) {
  const modif = !!u;
  cadre(modif ? 'Modifier le compte' : 'Nouveau compte', `
    <div class="card">
      ${champ('f-prenom', 'Prénom', u?.prenom)}
      ${champ('f-nom', 'Nom', u?.nom)}
      ${champ('f-identifiant', 'Identifiant', u?.identifiant)}
      ${champ('f-tel', 'Téléphone', u?.telephone)}
      ${champ('f-email', 'Email (facultatif)', u?.email, 'email')}
      <div class="field"><label for="f-role">Rôle</label><select id="f-role">
        ${['vendeur', 'conseiller', 'admin'].map(r => `<option value="${r}" ${u?.role === r ? 'selected' : ''}>${r}</option>`).join('')}</select></div>
      ${champ('f-region', 'Région / préfecture', u?.region)}
      <div class="field"><label for="f-freq">Paie</label><select id="f-freq">
        <option value="">—</option>${['jour', 'semaine', 'mois'].map(f => `<option value="${f}" ${u?.frequence_paie === f ? 'selected' : ''}>${f}</option>`).join('')}</select></div>
      ${champ('f-montant', 'Montant de paie (GNF)', u?.montant_paie || '', 'number')}
      ${modif ? '' : champ('f-pass', 'Mot de passe (vide = généré)', '', 'text')}
      <div class="error-msg" id="f-err"></div>
      <button class="btn" id="f-save">${modif ? 'Enregistrer les modifications' : 'Créer le compte'}</button>
    </div>`, null, null, { retour: modif ? () => adminDetailCompte(u.id) : adminComptes });
  $('#f-save').onclick = () => sauverCompte(u);
}
async function sauverCompte(u) {
  const btn = $('#f-save'), err = $('#f-err'); err.textContent = '';
  const corps = {
    prenom: $('#f-prenom').value.trim(), name: $('#f-nom').value.trim(),
    identifiant: $('#f-identifiant').value.trim(), telephone: $('#f-tel').value.trim(),
    email: $('#f-email').value.trim() || null, role: $('#f-role').value,
    region: $('#f-region').value.trim() || null, frequence_paie: $('#f-freq').value || null,
    montant_paie: parseInt($('#f-montant').value, 10) || 0,
  };
  if (!u) { const p = $('#f-pass').value.trim(); if (p) corps.password = p; }
  btn.disabled = true; btn.textContent = 'Enregistrement…';
  try {
    if (u) { await api('PUT', '/admin/utilisateurs/' + u.id, corps); toast('Compte modifié.'); adminDetailCompte(u.id); }
    else {
      const res = await api('POST', '/admin/utilisateurs', corps);
      ecranMotDePasse('Compte créé', res.data.identifiant, res.mot_de_passe_initial, adminComptes);
    }
  } catch (ex) { err.textContent = ex.premierDetail(); btn.disabled = false; btn.textContent = u ? 'Enregistrer les modifications' : 'Créer le compte'; }
}
function ecranMotDePasse(titre, identifiant, motDePasse, retour) {
  cadre(titre, `
    <div class="card center">
      <p>Notez ces accès et transmettez-les au vendeur. Le mot de passe ne sera plus affiché.</p>
      <div class="field"><label>Identifiant</label><input value="${esc(identifiant)}" readonly></div>
      <div class="field"><label>Mot de passe</label><input value="${esc(motDePasse)}" readonly style="font-weight:700"></div>
      <button class="btn" id="ok">J'ai noté, terminer</button>
    </div>`, null, null, { retour });
  $('#ok').onclick = retour;
}
async function adminDetailCompte(id) {
  cadre('Compte', `<div id="d"><div class="spinner"></div></div>`, null, null, { retour: adminComptes });
  try {
    const u = (await api('GET', '/admin/utilisateurs/' + id)).data;
    $('#d').innerHTML = `
      <div class="card">
        <h3>${esc(u.prenom)} ${esc(u.nom)}</h3>
        <div class="list-item"><div class="main"><div class="sub">Identifiant</div><div class="name">${esc(u.identifiant)}</div></div></div>
        <div class="list-item"><div class="main"><div class="sub">Téléphone</div><div class="name">${esc(u.telephone || '—')}</div></div></div>
        <div class="list-item"><div class="main"><div class="sub">Email</div><div class="name">${esc(u.email || '—')}</div></div></div>
        <div class="list-item"><div class="main"><div class="sub">Rôle · Région</div><div class="name">${esc(u.role)} · ${esc(u.region || '—')}</div></div></div>
        <div class="list-item"><div class="main"><div class="sub">Paie</div><div class="name">${u.frequence_paie ? esc(u.frequence_paie) + ' · ' + gnf(u.montant_paie) : '—'}</div></div></div>
        <div class="list-item"><div class="main"><div class="sub">État</div><div class="name" style="color:${u.est_verrouille ? 'var(--danger)' : 'var(--success)'}">${u.est_verrouille ? 'Verrouillé' : 'Actif'}${u.tentatives_intrusion ? ' · ' + u.tentatives_intrusion + ' tentative(s)' : ''}</div></div></div>
      </div>
      <button class="btn block" id="b-modif">✏️ Modifier</button>
      ${u.role === 'vendeur' ? `<button class="btn block secondary" id="b-horaires">🕗 Définir les horaires</button>` : ''}
      ${u.telephone ? `<button class="btn block secondary" id="b-wa">💬 WhatsApp</button>` : ''}
      ${u.est_verrouille
        ? `<button class="btn block" id="b-activer" style="background:var(--success);color:#fff">🔓 Réactiver le compte</button>`
        : `<button class="btn block danger" id="b-verrou">🔒 Verrouiller le compte</button>`}
      <button class="btn block secondary" id="b-mdp">🔑 Réinitialiser le mot de passe</button>`;
    $('#b-modif').onclick = () => adminFormCompte(u);
    if (u.role === 'vendeur') $('#b-horaires').onclick = () => adminHoraires(u);
    if (u.telephone) $('#b-wa').onclick = () => ouvrirWhatsApp(u.telephone, 'Bonjour ' + u.prenom + ', ');
    const agir = async (url, msg) => { try { await api('POST', url); toast(msg); adminDetailCompte(id); } catch (ex) { toast(ex.premierDetail()); } };
    if (u.est_verrouille) $('#b-activer').onclick = () => agir('/admin/utilisateurs/' + id + '/deverrouiller', 'Compte réactivé.');
    else $('#b-verrou').onclick = () => { if (confirm('Verrouiller ce compte ? Le vendeur sera déconnecté.')) agir('/admin/utilisateurs/' + id + '/verrouiller', 'Compte verrouillé.'); };
    $('#b-mdp').onclick = async () => {
      if (!confirm('Générer un nouveau mot de passe ?')) return;
      try { const r = await api('POST', '/admin/utilisateurs/' + id + '/reinitialiser-mot-de-passe'); ecranMotDePasse('Nouveau mot de passe', r.identifiant, r.nouveau_mot_de_passe, () => adminDetailCompte(id)); }
      catch (ex) { toast(ex.premierDetail()); }
    };
  } catch (ex) { $('#d').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* ---- Produits ---- */
async function adminProduits() {
  cadre('Produits', `
    <button class="btn block" id="nouveau">➕ Nouveau produit</button>
    <div class="field mt"><input id="q" placeholder="Rechercher un produit…"></div>
    <div id="liste"><div class="spinner"></div></div>`, ongletsAdmin(), 2);
  $('#nouveau').onclick = adminFormProduit;
  const rendre = async (q = '') => {
    try {
      const d = await api('GET', '/admin/produits' + (q ? '?recherche=' + encodeURIComponent(q) : ''));
      const items = d.data || [];
      $('#liste').innerHTML = `<div class="card">${items.map(p => `
        <div class="list-item tap" data-id="${p.id}" role="button" tabindex="0">
          <div class="main"><div class="name">${esc(p.nom)}</div><div class="sub">${esc(p.categorie || '')} · ${gnf(p.prix_unitaire)} · stock ${p.stock}${p.actif ? '' : ' · inactif'}</div></div>
          <span class="badge">＋ stock</span>
        </div>`).join('')}</div>`;
      $('#liste').querySelectorAll('.tap').forEach(el => el.onclick = () => adminApprovisionner(+el.dataset.id));
    } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  let t; $('#q').addEventListener('input', e => { clearTimeout(t); t = setTimeout(() => rendre(e.target.value.trim()), 300); });
  rendre();
}
function adminFormProduit() {
  cadre('Nouveau produit', `
    <div class="card">
      ${champ('p-nom', 'Nom')}
      ${champ('p-cat', 'Catégorie')}
      ${champ('p-mod', 'Modèle (facultatif)')}
      ${champ('p-prix', 'Prix de vente (GNF)', '', 'number')}
      ${champ('p-achat', "Prix d'achat (GNF, facultatif)", '', 'number')}
      ${champ('p-seuil', 'Seuil alerte stock', '5', 'number')}
      <div class="field"><label><input type="checkbox" id="p-actif" checked> Actif (visible pour les vendeurs)</label></div>
      <div class="error-msg" id="p-err"></div>
      <button class="btn" id="p-save">Créer le produit</button>
    </div>`, null, null, { retour: adminProduits });
  $('#p-save').onclick = async () => {
    const err = $('#p-err'); err.textContent = '';
    const corps = { nom: $('#p-nom').value.trim(), categorie: $('#p-cat').value.trim() || null, modele: $('#p-mod').value.trim() || null,
      prix_unitaire: parseInt($('#p-prix').value, 10) || 0, prix_achat: parseInt($('#p-achat').value, 10) || 0,
      seuil_alerte: parseInt($('#p-seuil').value, 10) || 5, actif: $('#p-actif').checked };
    if (!corps.nom) { err.textContent = 'Le nom est obligatoire.'; return; }
    try { await api('POST', '/admin/produits', corps); toast('Produit créé.'); adminProduits(); }
    catch (ex) { err.textContent = ex.premierDetail(); }
  };
}
function adminApprovisionner(id) {
  const q = prompt('Quantité à ajouter au stock :');
  if (!q) return;
  const quantite = parseInt(q, 10);
  if (!quantite || quantite < 1) { toast('Quantité invalide.'); return; }
  api('POST', '/admin/produits/' + id + '/approvisionner', { quantite })
    .then(p => { toast('Stock mis à jour : ' + p.stock); adminProduits(); })
    .catch(ex => toast(ex.premierDetail()));
}

/* ---- Paie ---- */
async function adminPaie() {
  cadre('Paie des vendeurs', `
    <div class="card">
      <div class="field"><label>Fréquence</label><select id="freq"><option value="mois">Mensuelle</option><option value="semaine">Hebdomadaire</option><option value="jour">Journalière</option></select></div>
      <div class="field"><label>Mode de paiement</label><select id="mode"><option value="orange_money">Orange Money</option><option value="especes">Espèces</option><option value="carte">Carte</option></select></div>
    </div>
    <div id="liste"><div class="spinner"></div></div>`, null, null, { retour: adminPlus });
  const rendre = async () => {
    const freq = $('#freq').value;
    try {
      const d = await api('GET', '/admin/paie?frequence=' + freq);
      const v = d.vendeurs || [];
      $('#liste').innerHTML = `
        <div class="card"><div class="row-between"><h3>Période</h3><span class="badge">${esc(d.periode_debut)} → ${esc(d.periode_fin)}</span></div>
          <p class="muted">Reste à payer : <b>${gnf(d.reste_a_payer)}</b></p>
          <button class="btn" id="payer-tout">Payer tous les non-payés</button></div>
        <div class="card">${v.length ? v.map(x => `
          <div class="list-item"><div class="main"><div class="name">${esc(x.nom)}</div>
            <div class="sub">${x.region ? esc(x.region) + ' · ' : ''}${gnf(x.montant_paie)} · ventes ${gnf(x.ventes_periode)}</div></div>
            ${x.deja_paye ? '<span class="badge" style="color:var(--success)">Payé</span>' : `<button class="btn" style="width:auto;padding:8px 12px" data-pay="${x.id}">Payer</button>`}
          </div>`).join('') : '<p class="muted">Aucun vendeur avec cette fréquence de paie.</p>'}</div>`;
      $('#liste').querySelectorAll('[data-pay]').forEach(b => b.onclick = () => payer(+b.dataset.pay, freq, $('#mode').value, rendre));
      const pt = $('#payer-tout'); if (pt) pt.onclick = async () => {
        if (!confirm('Payer tous les vendeurs non encore payés pour cette période ?')) return;
        try { const r = await api('POST', '/admin/paie/tout', { frequence: freq, mode_paiement: $('#mode').value }); toast(r.nombre_payes + ' payé(s) · ' + gnf(r.montant_total)); rendre(); }
        catch (ex) { toast(ex.premierDetail()); }
      };
    } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  $('#freq').onchange = rendre; rendre();
}
async function payer(userId, freq, mode, apres) {
  try { await api('POST', '/admin/paie', { user_id: userId, frequence: freq, mode_paiement: mode }); toast('Vendeur payé.'); apres(); }
  catch (ex) { toast(ex.premierDetail()); }
}

/* ---- Alertes : notifications + demandes de modification ---- */
async function adminAlertes() {
  cadre('Alertes', `<div id="a"><div class="spinner"></div></div>`, null, null, { retour: adminPlus });
  try {
    const [notifs, demandes] = await Promise.all([
      api('GET', '/notifications').catch(() => []),
      api('GET', '/admin/modifications-vente').catch(() => ({ data: [] })),
    ]);
    const dmod = (demandes.data || []);
    $('#a').innerHTML = `
      <div class="card"><h3>Demandes de modification de vente (${dmod.length})</h3>
        ${dmod.length ? dmod.map(x => `
          <div class="list-item"><div class="main"><div class="name">Vente ${esc(x.vente?.reference || '')}</div>
            <div class="sub">${esc(x.demandeur?.prenom || '')} ${esc(x.demandeur?.name || '')} — ${esc(JSON.stringify(x.changements))}<br>${esc(x.motif_demande || '')}</div></div>
            <div class="btn-row" style="width:auto">
              <button class="icon-btn" style="color:var(--success)" data-ok="${x.id}">✓</button>
              <button class="icon-btn" style="color:var(--danger)" data-no="${x.id}">✗</button></div>
          </div>`).join('') : '<p class="muted">Aucune demande en attente.</p>'}</div>
      <div class="card"><h3>Notifications</h3>
        ${(notifs.length ? notifs.map(n => `<div class="list-item"><div class="main"><div class="name">${esc(n.message || n.type)}</div>
          <div class="sub">${esc(n.date || '')}</div></div></div>`).join('') : '<p class="muted">Aucune notification.</p>')}</div>`;
    $('#a').querySelectorAll('[data-ok]').forEach(b => b.onclick = async () => { try { await api('POST', '/admin/modifications-vente/' + b.dataset.ok + '/approuver'); toast('Modification approuvée.'); adminAlertes(); } catch (ex) { toast(ex.premierDetail()); } });
    $('#a').querySelectorAll('[data-no]').forEach(b => b.onclick = async () => { const motif = prompt('Motif du refus (facultatif) :') || null; try { await api('POST', '/admin/modifications-vente/' + b.dataset.no + '/refuser', { motif_refus: motif }); toast('Demande refusée.'); adminAlertes(); } catch (ex) { toast(ex.premierDetail()); } });
  } catch (ex) { $('#a').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* ---- Réparateurs (admin : voit tout, accorde les crédits) ---- */
async function adminReparateurs() {
  cadre('Réparateurs', `
    <button class="btn block" id="nouveau">➕ Nouveau réparateur</button>
    <div id="liste"><div class="spinner"></div></div>`, ongletsAdmin(), 3);
  $('#nouveau').onclick = adminFormReparateur;
  try {
    const d = await api('GET', '/admin/reparateurs');
    const seuil = d.seuil || 500000;
    const items = d.reparateurs || [];
    $('#liste').innerHTML = `<p class="muted" style="font-size:.85rem">Alerte si la dette dépasse ${gnf(seuil)}.</p>
      <div class="card">${items.map(r => `
        <div class="list-item tap" data-id="${r.id}" role="button" tabindex="0">
          <div class="main"><div class="name">${esc(r.nom)} ${r.en_alerte ? '🔴' : ''}</div>
            <div class="sub">${r.telephone ? esc(r.telephone) + ' · ' : ''}dette ${gnf(r.dette)}</div></div>
          <span class="badge" style="${r.en_alerte ? 'color:var(--danger)' : ''}">${r.dette > 0 ? gnf(r.dette) : 'à jour'}</span>
        </div>`).join('')}</div>`;
    $('#liste').querySelectorAll('.tap').forEach(el => el.onclick = () => adminReparateurDetail(+el.dataset.id));
  } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}
function adminFormReparateur() {
  cadre('Nouveau réparateur', `
    <div class="card">${champ('r-prenom', 'Prénom')}${champ('r-nom', 'Nom (facultatif)')}
      ${champ('r-tel', 'Téléphone (facultatif)')}${champ('r-email', 'Email (facultatif)', '', 'email')}
      <div class="error-msg" id="r-err"></div><button class="btn" id="r-save">Créer</button></div>`, null, null, { retour: adminReparateurs });
  $('#r-save').onclick = async () => {
    const err = $('#r-err'); err.textContent = '';
    const corps = { prenom: $('#r-prenom').value.trim(), nom: $('#r-nom').value.trim() || null, telephone: $('#r-tel').value.trim() || null, email: $('#r-email').value.trim() || null };
    if (!corps.prenom) { err.textContent = 'Le prénom est obligatoire.'; return; }
    try { await api('POST', '/admin/reparateurs', corps); toast('Réparateur créé.'); adminReparateurs(); }
    catch (ex) { err.textContent = ex.premierDetail(); }
  };
}
async function adminReparateurDetail(id) {
  cadre('Réparateur', `<div id="d"><div class="spinner"></div></div>`, null, null, { retour: adminReparateurs });
  try {
    const r = await api('GET', '/admin/reparateurs/' + id);
    $('#d').innerHTML = `
      <div class="card"><h3>${esc(r.nom)}</h3>
        <p class="muted">${esc(r.telephone || '')} ${r.email ? '· ' + esc(r.email) : ''}</p>
        <div class="value" style="font-size:1.4rem">Dette : ${gnf(r.dette)}</div></div>
      <div class="card"><h3>Prise d'écran</h3>
        ${champ('p-prod', 'Produit (numéro)', '', 'number')}
        ${champ('p-qte', 'Quantité', '1', 'number')}
        ${champ('p-paye', 'Montant payé (GNF, 0 = crédit total)', '0', 'number')}
        <div class="error-msg" id="pr-err"></div>
        <button class="btn" id="pr-btn">Enregistrer la prise</button></div>
      <div class="card"><h3>Remboursement</h3>
        ${champ('rb-montant', 'Montant reçu (GNF)', '', 'number')}
        <div class="field"><label>Mode</label><select id="rb-mode"><option value="especes">Espèces</option><option value="orange_money">Orange Money</option><option value="carte">Carte</option></select></div>
        <div class="error-msg" id="rb-err"></div>
        <button class="btn secondary" id="rb-btn">Enregistrer le remboursement</button></div>
      <div class="card"><h3>Écrans pris</h3>${(r.prises || []).length ? r.prises.map(p => `
        <div class="list-item"><div class="main"><div class="name">${esc(p.article)} ${p.modele ? '(' + esc(p.modele) + ')' : ''}</div>
          <div class="sub">${esc(p.date)} · payé ${gnf(p.montant_paye)} · reste ${gnf(p.reste)}</div></div></div>`).join('') : '<p class="muted">Aucune.</p>'}</div>`;
    $('#pr-btn').onclick = async () => {
      const err = $('#pr-err'); err.textContent = '';
      const corps = { produit_id: parseInt($('#p-prod').value, 10), quantite: parseInt($('#p-qte').value, 10), montant_paye: parseInt($('#p-paye').value, 10) || 0 };
      if (!corps.produit_id || !corps.quantite) { err.textContent = 'Produit et quantité obligatoires.'; return; }
      try { const res = await api('POST', '/reparateurs/' + id + '/prises', corps); toast('Prise : reste ' + gnf(res.reste)); adminReparateurDetail(id); }
      catch (ex) { err.textContent = ex.premierDetail(); }
    };
    $('#rb-btn').onclick = async () => {
      const err = $('#rb-err'); err.textContent = '';
      const montant = parseInt($('#rb-montant').value, 10);
      if (!montant || montant < 1) { err.textContent = 'Montant invalide.'; return; }
      try { const res = await api('POST', '/reparateurs/' + id + '/paiements', { montant, mode_paiement: $('#rb-mode').value }); toast('Reçu. Dette restante : ' + gnf(res.dette_restante)); adminReparateurDetail(id); }
      catch (ex) { err.textContent = ex.premierDetail(); }
    };
  } catch (ex) { $('#d').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* ---- Messagerie admin : un fil par vendeur ---- */
async function adminMessages() {
  cadre('Messages des vendeurs', `<div id="b"><div class="spinner"></div></div>`, null, null, { retour: adminPlus });
  try {
    const d = await api('GET', '/admin/messages');
    const items = d.data || [];
    $('#b').innerHTML = items.length ? `<div class="card">${items.map(x => `
      <div class="list-item tap" data-id="${x.user_id}" role="button" tabindex="0">
        <div class="main"><div class="name">${esc(x.utilisateur?.prenom || '')} ${esc(x.utilisateur?.name || '')}</div>
          <div class="sub">${esc(x.utilisateur?.identifiant || '')}${x.utilisateur?.region ? ' · ' + esc(x.utilisateur.region) : ''}</div></div>
        ${x.non_lus > 0 ? `<span class="badge" style="color:var(--primary-ink);background:var(--primary)">${x.non_lus}</span>` : ''}
      </div>`).join('')}</div>` : `<p class="muted center mt">Aucun message reçu.</p>`;
    $('#b').querySelectorAll('.tap').forEach(el => el.onclick = () => adminFilMessage(+el.dataset.id));
  } catch (ex) { $('#b').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}
async function adminFilMessage(userId) {
  cadre('Conversation', `<div id="fil"><div class="spinner"></div></div>
    <div class="card"><div class="field"><textarea id="rep" rows="3" placeholder="Répondre…"></textarea></div>
      <div class="error-msg" id="r-err"></div><button class="btn" id="rep-btn">Répondre</button></div>`, null, null, { retour: adminMessages });
  const charger = async () => {
    try {
      const msgs = await api('GET', '/admin/messages/' + userId);
      $('#fil').innerHTML = `<div class="card">${msgs.map(m => `
        <div class="list-item"><div class="main"><div class="sub">${esc(m.auteur)} (${esc(m.role)}) · ${esc(m.date)}</div>
          <div>${esc(m.contenu)}</div></div></div>`).join('')}</div>`;
    } catch (ex) { $('#fil').innerHTML = `<p class="muted center">${esc(ex.message)}</p>`; }
  };
  $('#rep-btn').onclick = async () => {
    const err = $('#r-err'); err.textContent = '';
    const contenu = $('#rep').value.trim();
    if (!contenu) { err.textContent = 'Message vide.'; return; }
    try { await api('POST', '/admin/messages/' + userId, { contenu }); $('#rep').value = ''; charger(); }
    catch (ex) { err.textContent = ex.premierDetail(); }
  };
  charger();
}

/* ---- Vente à crédit (admin seulement) ---- */
let creditClient = null;      // { id, nom }
let creditPanier = [];        // [{produit_id, nom, quantite}]
function adminVenteCredit() {
  creditPanier = [];
  cadre('Vente à crédit', `
    <div class="card"><h3>1. Client</h3>
      <div id="client-zone">
        <div class="field"><input id="cq" placeholder="Rechercher un client (nom, téléphone)…"></div>
        <div id="client-res"></div>
      </div></div>
    <div class="card"><h3>2. Articles</h3>
      <div class="field"><input id="pq" placeholder="Rechercher un article…"></div>
      <div id="prod-res"></div></div>
    <div class="card"><h3>3. Panier</h3><div id="credit-panier"><p class="muted">Vide.</p></div>
      <div class="error-msg" id="vc-err"></div>
      <button class="btn" id="vc-valider">Valider la vente à crédit</button></div>`, null, null, { retour: adminPlus });

  let tc; $('#cq').addEventListener('input', e => { clearTimeout(tc); tc = setTimeout(() => chercherClient(e.target.value.trim()), 300); });
  let tp; $('#pq').addEventListener('input', e => { clearTimeout(tp); tp = setTimeout(() => chercherProduitCredit(e.target.value.trim()), 300); });
  $('#vc-valider').onclick = validerVenteCredit;
  rendreClientChoisi(); rendreCreditPanier();
}
function rendreClientChoisi() {
  if (!creditClient) return;
  $('#client-zone').innerHTML = `<div class="list-item"><div class="main"><div class="name">${esc(creditClient.nom)}</div></div>
    <button class="btn secondary" style="width:auto;padding:6px 10px" id="chg">Changer</button></div>`;
  $('#chg').onclick = adminVenteCredit;
}
async function chercherClient(q) {
  if (!q) { $('#client-res').innerHTML = ''; return; }
  try {
    const d = await api('GET', '/clients?recherche=' + encodeURIComponent(q));
    const items = d.data || [];
    $('#client-res').innerHTML = items.length ? items.map(c => `<div class="list-item tap" data-id="${c.id}" data-nom="${esc(c.prenom + ' ' + c.nom)}" role="button" tabindex="0"><div class="main"><div class="name">${esc(c.prenom)} ${esc(c.nom)}</div><div class="sub">${esc(c.telephone)} · ${esc(c.ville)}</div></div><span class="badge cat">Choisir</span></div>`).join('') : '<p class="muted">Aucun client. Le conseiller doit l\'enregistrer d\'abord.</p>';
    $('#client-res').querySelectorAll('.tap').forEach(el => el.onclick = () => { creditClient = { id: +el.dataset.id, nom: el.dataset.nom }; rendreClientChoisi(); $('#client-res').innerHTML = ''; });
  } catch (ex) { $('#client-res').innerHTML = `<p class="muted">${esc(ex.message)}</p>`; }
}
async function chercherProduitCredit(q) {
  if (!q) { $('#prod-res').innerHTML = ''; return; }
  try {
    const d = await api('GET', '/catalogue?recherche=' + encodeURIComponent(q));
    const items = d.data || [];
    $('#prod-res').innerHTML = items.length ? items.map(p => `<div class="list-item tap" data-id="${p.id}" data-nom="${esc(p.nom)}" role="button" tabindex="0"><div class="main"><div class="name">${esc(p.nom)}</div><div class="sub">${esc(p.modele || p.categorie || '')}</div></div><span class="badge cat">+ Ajouter</span></div>`).join('') : '<p class="muted">Aucun article.</p>';
    $('#prod-res').querySelectorAll('.tap').forEach(el => el.onclick = () => {
      const id = +el.dataset.id, l = creditPanier.find(x => x.produit_id === id);
      if (l) l.quantite++; else creditPanier.push({ produit_id: id, nom: el.dataset.nom, quantite: 1 });
      rendreCreditPanier(); toast(el.dataset.nom + ' ajouté');
    });
  } catch (ex) { $('#prod-res').innerHTML = `<p class="muted">${esc(ex.message)}</p>`; }
}
function rendreCreditPanier() {
  const el = $('#credit-panier'); if (!el) return;
  if (!creditPanier.length) { el.innerHTML = '<p class="muted">Vide.</p>'; return; }
  el.innerHTML = creditPanier.map((l, i) => `<div class="list-item"><div class="main"><div class="name">${esc(l.nom)}</div></div>
    <div class="btn-row" style="width:auto"><button class="icon-btn" data-m="${i}">−</button><span style="min-width:28px;text-align:center;align-self:center">${l.quantite}</span><button class="icon-btn" data-p="${i}">+</button><button class="icon-btn" data-s="${i}" style="color:var(--danger)">🗑</button></div></div>`).join('');
  el.querySelectorAll('[data-p]').forEach(b => b.onclick = () => { creditPanier[+b.dataset.p].quantite++; rendreCreditPanier(); });
  el.querySelectorAll('[data-m]').forEach(b => b.onclick = () => { const l = creditPanier[+b.dataset.m]; if (--l.quantite < 1) creditPanier.splice(+b.dataset.m, 1); rendreCreditPanier(); });
  el.querySelectorAll('[data-s]').forEach(b => b.onclick = () => { creditPanier.splice(+b.dataset.s, 1); rendreCreditPanier(); });
}
async function validerVenteCredit() {
  const err = $('#vc-err'); err.textContent = '';
  if (!creditClient) { err.textContent = 'Choisissez d\'abord un client.'; return; }
  if (!creditPanier.length) { err.textContent = 'Ajoutez au moins un article.'; return; }
  const btn = $('#vc-valider'); btn.disabled = true; btn.textContent = 'Enregistrement…';
  try {
    const res = await api('POST', '/ventes', { uuid_client: uuid(), client_id: creditClient.id, mode_paiement: 'credit', lignes: creditPanier.map(l => ({ produit_id: l.produit_id, quantite: l.quantite })) });
    toast('Vente à crédit ' + res.data.reference + ' — dû : ' + gnf(res.data.montant_a_encaisser));
    adminCreditsClients();
  } catch (ex) { err.textContent = ex.premierDetail(); btn.disabled = false; btn.textContent = 'Valider la vente à crédit'; }
}

/* ---- Crédits clients : liste + remboursement ---- */
async function adminCreditsClients() {
  cadre('Crédits clients', `<div id="cc"><div class="spinner"></div></div>`, null, null, { retour: adminPlus });
  try {
    const items = await api('GET', '/admin/credits');
    $('#cc').innerHTML = items.length ? `<div class="card">${items.map(c => `
      <div class="list-item tap" data-id="${c.client_id}" role="button" tabindex="0">
        <div class="main"><div class="name">${esc(c.nom)}</div><div class="sub">${esc(c.telephone || '')}${c.ville ? ' · ' + esc(c.ville) : ''}</div></div>
        <span class="badge" style="color:var(--danger)">${gnf(c.dette)}</span></div>`).join('')}</div>`
      : `<p class="muted center mt">Aucun client à crédit.</p>`;
    $('#cc').querySelectorAll('.tap').forEach(el => el.onclick = () => adminCreditDetail(+el.dataset.id));
  } catch (ex) { $('#cc').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}
async function adminCreditDetail(clientId) {
  cadre('Crédit du client', `<div id="d"><div class="spinner"></div></div>`, null, null, { retour: adminCreditsClients });
  try {
    const d = await api('GET', '/admin/credits/' + clientId);
    $('#d').innerHTML = `
      <div class="card"><h3>${esc(d.client.nom)}</h3><p class="muted">${esc(d.client.telephone || '')}</p>
        <div class="value" style="font-size:1.4rem">Dette : ${gnf(d.dette)}</div></div>
      ${d.client.telephone ? `<button class="btn block secondary" id="wa-cli">💬 Rappeler sur WhatsApp</button>` : ''}
      <div class="card"><h3>Remboursement</h3>
        ${champ('rb-montant', 'Montant reçu (GNF)', '', 'number')}
        <div class="field"><label>Mode</label><select id="rb-mode"><option value="especes">Espèces</option><option value="orange_money">Orange Money</option><option value="carte">Carte</option></select></div>
        <div class="error-msg" id="rb-err"></div>
        <button class="btn" id="rb-btn">Enregistrer le remboursement</button></div>
      <div class="card"><h3>Ventes à crédit</h3>${d.ventes.map(v => `
        <div class="list-item"><div class="main"><div class="name">${esc(v.reference)} — ${gnf(v.montant_total)}</div>
          <div class="sub">${esc(v.date)} · payé ${gnf(v.montant_paye)} · reste ${gnf(v.reste)} · ${esc(v.statut)}</div></div></div>`).join('')}</div>`;
    if (d.client.telephone) $('#wa-cli').onclick = () => ouvrirWhatsApp(d.client.telephone, 'Bonjour, votre dette chez MOK-Business est de ' + gnf(d.dette) + '. Merci de passer régler.');
    $('#rb-btn').onclick = async () => {
      const err = $('#rb-err'); err.textContent = '';
      const montant = parseInt($('#rb-montant').value, 10);
      if (!montant || montant < 1) { err.textContent = 'Montant invalide.'; return; }
      try { const r = await api('POST', '/admin/credits/' + clientId + '/remboursement', { montant, mode_paiement: $('#rb-mode').value }); toast('Reçu. Dette restante : ' + gnf(r.dette_restante)); adminCreditDetail(clientId); }
      catch (ex) { err.textContent = ex.premierDetail(); }
    };
  } catch (ex) { $('#d').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* ---- WhatsApp (bouton, marche avec le compte personnel) ---- */
function ouvrirWhatsApp(numero, texte = '') {
  const n = String(numero || '').replace(/\D+/g, '');
  if (!n) { toast('Numéro WhatsApp indisponible.'); return; }
  window.open('https://wa.me/' + n + (texte ? '?text=' + encodeURIComponent(texte) : ''), '_blank');
}
async function whatsAppBoutique(texte = '') {
  try { const d = await api('GET', '/whatsapp/contact'); ouvrirWhatsApp(d.numero, texte); }
  catch { ouvrirWhatsApp('224621952252', texte); }
}

/* ---- Admin : toutes les ventes ---- */
async function adminVentes() {
  cadre('Toutes les ventes', `
    <div class="card">
      <div class="field"><label>Période</label><select id="periode"><option value="jour">Aujourd'hui</option><option value="semaine">Cette semaine</option><option value="mois">Ce mois</option><option value="">Toutes</option></select></div>
      <div class="field"><label>Statut</label><select id="statut"><option value="">Tous</option><option value="payee">Payées</option><option value="a_credit">À crédit</option><option value="en_attente_paiement">En attente</option></select></div>
    </div>
    <div id="liste"><div class="spinner"></div></div>`, null, null, { retour: adminPlus });
  const rendre = async () => {
    const p = $('#periode').value, s = $('#statut').value;
    try {
      const d = await api('GET', '/admin/ventes?periode=' + p + (s ? '&statut=' + s : ''));
      const items = d.data || [];
      $('#liste').innerHTML = items.length ? `<div class="card">${items.map(v => `
        <div class="list-item"><div class="main">
          <div class="name">${esc(v.reference)} — ${gnf(v.montant_total)}</div>
          <div class="sub">${esc(v.date)} · ${esc(v.vendeur || '—')} · ${esc(v.mode_paiement)} · ${esc(v.statut)}${v.client ? ' · ' + esc(v.client) : ''}${v.reste > 0 ? ' · reste ' + gnf(v.reste) : ''}</div>
          <div class="sub">${esc(v.articles.join(', '))}</div></div>
          ${v.latitude ? `<button class="icon-btn" title="Voir sur la carte" data-lat="${v.latitude}" data-lng="${v.longitude}">📍</button>` : ''}
        </div>`).join('')}</div>` : `<p class="muted center mt">Aucune vente.</p>`;
      $('#liste').querySelectorAll('[data-lat]').forEach(b => b.onclick = () => window.open('https://www.google.com/maps?q=' + b.dataset.lat + ',' + b.dataset.lng, '_blank'));
    } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  $('#periode').onchange = rendre; $('#statut').onchange = rendre; rendre();
}

/* ---- Admin : horaires d'un vendeur ---- */
const JOURS = [[1, 'Lundi'], [2, 'Mardi'], [3, 'Mercredi'], [4, 'Jeudi'], [5, 'Vendredi'], [6, 'Samedi'], [7, 'Dimanche']];
async function adminHoraires(user) {
  cadre('Horaires — ' + user.prenom, `<div id="h"><div class="spinner"></div></div>`, null, null, { retour: () => adminDetailCompte(user.id) });
  try {
    const d = await api('GET', '/admin/utilisateurs/' + user.id + '/horaires');
    const parJour = {}; (d.jours || []).forEach(j => parJour[j.jour_semaine] = j);
    $('#h').innerHTML = `
      <div class="card">
        <div class="field"><label>Fuseau horaire</label><input id="tz" value="${esc(d.fuseau_horaire || 'Africa/Conakry')}"></div>
        <p class="muted" style="font-size:.85rem">Cochez les jours travaillés et indiquez les heures. Un jour décoché = repos.</p>
        ${JOURS.map(([n, nom]) => {
          const j = parJour[n];
          return `<div class="list-item">
            <label style="flex:1"><input type="checkbox" class="jr" data-j="${n}" ${j ? 'checked' : ''}> ${nom}</label>
            <input type="time" class="hd" data-j="${n}" value="${j ? (j.heure_debut || '').slice(0, 5) : '08:00'}" style="width:auto">
            <input type="time" class="hf" data-j="${n}" value="${j ? (j.heure_fin || '').slice(0, 5) : '18:00'}" style="width:auto">
          </div>`;
        }).join('')}
        <div class="error-msg" id="h-err"></div>
        <button class="btn" id="h-save">Enregistrer les horaires</button>
      </div>
      <p class="notice">Le rappel « il est l'heure de travailler » est envoyé automatiquement avant le début du service (à l'heure du vendeur).</p>`;
    $('#h-save').onclick = async () => {
      const err = $('#h-err'); err.textContent = '';
      const jours = [];
      document.querySelectorAll('.jr').forEach(cb => {
        if (cb.checked) {
          const n = cb.dataset.j;
          jours.push({ jour_semaine: +n, heure_debut: document.querySelector('.hd[data-j="' + n + '"]').value, heure_fin: document.querySelector('.hf[data-j="' + n + '"]').value });
        }
      });
      try { await api('PUT', '/admin/utilisateurs/' + user.id + '/horaires', { fuseau_horaire: $('#tz').value.trim(), jours }); toast('Horaires enregistrés.'); adminDetailCompte(user.id); }
      catch (ex) { err.textContent = ex.premierDetail(); }
    };
  } catch (ex) { $('#h').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* ================================================================  CONSEILLER  */
function conseillerClients() {
  cadre('Enregistrer un client', `
    <div class="card">
      ${champ('c-prenom', 'Prénom')}${champ('c-nom', 'Nom')}
      ${champ('c-tel', 'Téléphone')}${champ('c-email', 'Email (facultatif)', '', 'email')}
      ${champ('c-pays', 'Pays', 'Guinée')}${champ('c-ville', 'Ville')}
      <div class="error-msg" id="c-err"></div>
      <button class="btn" id="c-btn">Enregistrer le client</button>
    </div>`);
  $('#c-btn').onclick = async () => {
    const err = $('#c-err'); err.textContent = '';
    const corps = { prenom: $('#c-prenom').value.trim(), nom: $('#c-nom').value.trim(), telephone: $('#c-tel').value.trim(),
      email: $('#c-email').value.trim() || null, pays: $('#c-pays').value.trim(), ville: $('#c-ville').value.trim() };
    if (!corps.prenom || !corps.nom || !corps.telephone || !corps.pays || !corps.ville) { err.textContent = 'Tous les champs sauf email sont obligatoires.'; return; }
    try { await api('POST', '/clients', corps); toast('Client enregistré.'); ['c-prenom', 'c-nom', 'c-tel', 'c-email', 'c-ville'].forEach(id => $('#' + id).value = ''); }
    catch (ex) { err.textContent = ex.premierDetail(); }
  };
}

/* ================================================================  DÉMARRAGE  */
// Reprise automatique : au retour d'internet, quand l'appli revient au premier plan,
// et régulièrement (toutes les 20 s) tant qu'il reste des ventes en attente.
window.addEventListener('online', () => { if (store.user) toast('De retour en ligne.'); synchroniser(); majBarre(); });
window.addEventListener('offline', majBarre);
document.addEventListener('visibilitychange', () => { if (!document.hidden) synchroniser(); });
window.addEventListener('focus', synchroniser);
setInterval(() => { if (navigator.onLine && fileAttente.length) synchroniser(); }, 20000);

if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
store.token && store.user ? routerAccueil() : ecranConnexion();
if (navigator.onLine) synchroniser();

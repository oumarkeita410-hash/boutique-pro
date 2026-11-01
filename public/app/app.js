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
  // Dernier compte utilisé sur CET appareil : pour une reconnexion très rapide.
  get lastId() { return localStorage.getItem('mok_last_id') || ''; },
  set lastId(v) { v ? localStorage.setItem('mok_last_id', v) : localStorage.removeItem('mok_last_id'); },
  get lastRole() { return localStorage.getItem('mok_last_role') || ''; },
  set lastRole(v) { v ? localStorage.setItem('mok_last_role', v) : localStorage.removeItem('mok_last_role'); },
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

/* ---------- Icônes (SVG professionnelles, pas d'emoji) ----------
   Trait fin, couleur = couleur du texte (s'adapte au thème). ic('nom'). */
const ICONES = {
  back: '<path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/>',
  forward: '<path d="M5 12h14"/><path d="M12 5l7 7-7 7"/>',
  close: '<path d="M18 6 6 18"/><path d="M6 6l12 12"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  checkCircle: '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
  plus: '<path d="M12 5v14"/><path d="M5 12h14"/>',
  minus: '<path d="M5 12h14"/>',
  trash: '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
  theme: '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
  menu: '<path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/>',
  message: '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.8L3 21l1.9-5.2A8.4 8.4 0 0 1 12 3a8.5 8.5 0 0 1 9 8.5Z"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  file: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/>',
  receipt: '<path d="M5 3v18l2-1 2 1 2-1 2 1 2-1 2 1V3l-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 8h8"/><path d="M8 12h8"/>',
  coins: '<circle cx="9" cy="9" r="6"/><path d="M14.7 5.3a6 6 0 1 1 0 13.4"/>',
  banknote: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 10v4"/><path d="M18 10v4"/>',
  wallet: '<path d="M3 7a2 2 0 0 1 2-2h12v4"/><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9H6"/><path d="M16 13h.01"/>',
  card: '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
  bell: '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
  cart: '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L23 7H6"/>',
  headset: '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="2" y="14" width="4" height="7" rx="2"/><rect x="18" y="14" width="4" height="7" rx="2"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
  box: '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>',
  wrench: '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4l-6 6a2 2 0 1 0 3 3l6-6a4 4 0 0 0 5.4-5.4l-2.5 2.5-2-2 2.5-2.5Z"/>',
  list: '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 9h6"/><path d="M9 13h6"/><path d="M9 17h4"/>',
  journal: '<path d="M4 4a2 2 0 0 1 2-2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M8 8h5"/><path d="M8 12h8"/><path d="M8 16h8"/>',
  dashboard: '<rect x="3" y="3" width="8" height="9" rx="1"/><rect x="13" y="3" width="8" height="5" rx="1"/><rect x="13" y="11" width="8" height="10" rx="1"/><rect x="3" y="15" width="8" height="6" rx="1"/>',
  trendUp: '<path d="M3 17l6-6 4 4 7-7"/><path d="M17 7h4v4"/>',
  trendDown: '<path d="M3 7l6 6 4-4 7 7"/><path d="M17 17h4v-4"/>',
  edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
  lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
  unlock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>',
  key: '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9"/><path d="M17 5l3 3"/><path d="M15 7l2 2"/>',
  download: '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M5 21h14"/>',
  pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
  tag: '<path d="M20 13l-7 7-9-9V4h7Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
  bank: '<path d="M12 3l9 5H3Z"/><path d="M5 10v8"/><path d="M9 10v8"/><path d="M15 10v8"/><path d="M19 10v8"/><path d="M3 21h18"/>',
  printer: '<path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/>',
  alert: '<path d="M12 3 2 20h20Z"/><path d="M12 9v5"/><path d="M12 17h.01"/>',
  in: '<circle cx="12" cy="12" r="9"/><path d="M12 8v7"/><path d="M8.5 11.5 12 15l3.5-3.5"/>',
  out: '<circle cx="12" cy="12" r="9"/><path d="M12 16V9"/><path d="M8.5 12.5 12 9l3.5 3.5"/>',
  undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v5"/>',
  dot: '<circle cx="12" cy="12" r="6"/>',
};
function ic(nom, taille = 18) {
  const p = ICONES[nom];
  if (!p) return '';
  return `<svg class="ic" viewBox="0 0 24 24" width="${taille}" height="${taille}" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${p}</svg>`;
}

/* Couleur propre à chaque fonction (style « vraies applications »). */
const TEINTES = {
  dashboard: '#2563eb', users: '#7c3aed', box: '#0891b2', wrench: '#ea580c',
  list: '#0d9488', receipt: '#059669', wallet: '#d97706', file: '#64748b',
  user: '#db2777', coins: '#f59e0b', card: '#4f46e5', banknote: '#16a34a',
  checkCircle: '#16a34a', message: '#0ea5e9', bell: '#dc2626', journal: '#475569',
  cart: '#2563eb', headset: '#7c3aed', shield: '#1d4ed8', clock: '#0891b2',
};
/* Icône « pleine » : dessin blanc dans une pastille arrondie de couleur. */
function icTile(nom, taille = 30) {
  const p = ICONES[nom];
  if (!p) return '';
  const couleur = TEINTES[nom] || '#2563eb';
  const g = Math.round(taille * 0.6);
  return `<span class="ic-tile" style="--tc:${couleur};width:${taille}px;height:${taille}px"><svg viewBox="0 0 24 24" width="${g}" height="${g}" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${p}</svg></span>`;
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
  { v: 'admin', nom: 'Administrateur', icone: 'shield' },
  { v: 'vendeur', nom: 'Vendeur', icone: 'cart' },
  { v: 'conseiller', nom: 'Conseiller', icone: 'headset' },
];
function ecranConnexion() {
  // Type pré-sélectionné = le dernier utilisé sur cet appareil (sinon « vendeur »).
  const roleDefaut = store.lastRole || 'vendeur';
  app.innerHTML = `
    <div class="login-wrap">
      <div class="logo"><div class="mark">MB</div><h1>MOK<span>-Business</span></h1>
        <p>Boutique électronique — Guinée Conakry</p></div>
      <p class="roles-titre">Choisissez votre type de compte</p>
      <div class="roles">${ROLES.map(r => `<div class="role-chip ${r.v === roleDefaut ? 'active' : ''}" data-role="${r.v}"><span class="emoji">${icTile(r.icone, 44)}</span>${r.nom}</div>`).join('')}</div>
      <div id="comptes-liste"></div>
      <form id="form-login" novalidate>
        <div class="field"><label for="identifiant">Identifiant, téléphone ou email</label>
          <input id="identifiant" autocomplete="username" autocapitalize="none" required></div>
        <div class="field"><label for="password">Mot de passe</label>
          <input id="password" type="password" autocomplete="current-password" required></div>
        <div class="error-msg" id="login-error"></div>
        <button class="btn" type="submit" id="login-btn">Se connecter</button>
      </form>
      <div class="btn-row mt"><button class="btn secondary" id="theme-btn" type="button">${ic('theme')} Thème clair / sombre</button></div>
      <p class="notice">L'accès à chaque compte nécessite un identifiant et un mot de passe fournis par l'administrateur.<br>
        Votre position et votre appareil sont enregistrés par MOK-Business pendant l'utilisation.</p>
    </div>`;
  $('.roles').addEventListener('click', e => {
    const c = e.target.closest('.role-chip'); if (!c) return;
    $('.roles').querySelectorAll('.role-chip').forEach(x => x.classList.remove('active')); c.classList.add('active');
    chargerComptesConnexion(c.dataset.role);
  });
  $('#theme-btn').addEventListener('click', basculerTheme);
  $('#form-login').addEventListener('submit', soumettreConnexion);
  // Reconnexion rapide : on pré-remplit le dernier identifiant et on vise le mot de passe.
  if (store.lastId) { $('#identifiant').value = store.lastId; setTimeout(() => $('#password').focus(), 100); }
  chargerComptesConnexion(roleDefaut); // liste des comptes du type sélectionné
}

/* Affiche les comptes d'un type sous l'écran de connexion. Toucher un compte remplit l'identifiant. */
async function chargerComptesConnexion(role) {
  const el = $('#comptes-liste'); if (!el) return;
  el.innerHTML = `<div class="spinner" style="margin:12px auto"></div>`;
  try {
    const comptes = await api('GET', '/comptes-connexion?role=' + role);
    if (!comptes.length) {
      el.innerHTML = `<p class="muted" style="font-size:.8rem;text-align:center;margin:8px 0 14px">Aucun compte ${esc(role)} pour l'instant.</p>`;
      return;
    }
    el.innerHTML = `<div class="card" style="margin-bottom:14px">
      <div class="sub muted" style="font-size:.78rem;margin-bottom:4px">Touchez votre compte, puis entrez votre mot de passe :</div>
      ${comptes.map(c => `<div class="list-item tap" data-id="${esc(c.identifiant)}" role="button" tabindex="0"><div class="main"><div class="name">${esc(c.nom)}</div><div class="sub">${esc(c.identifiant)}</div></div><span class="badge cat">Choisir</span></div>`).join('')}</div>`;
    el.querySelectorAll('.tap').forEach(x => x.onclick = () => { $('#identifiant').value = x.dataset.id; $('#password').focus(); });
  } catch (ex) { el.innerHTML = ''; }
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
    // On retient le compte pour une reconnexion rapide la prochaine fois.
    store.lastId = identifiant;
    store.lastRole = (data.utilisateur && data.utilisateur.role) || store.lastRole;
    if (data.avis) toast(data.avis);
    routerAccueil();
  } catch (ex) {
    err.textContent = navigator.onLine ? (ex.premierDetail ? ex.premierDetail() : 'Connexion impossible.') : 'Pas de connexion internet.';
  } finally { btn.disabled = false; btn.textContent = 'Se connecter'; }
}
function deconnexion() { api('POST', '/deconnexion').catch(() => {}); store.token = null; store.user = null; ecranConnexion(); }

/* ================================================================  CADRE  */
function cadre(titre, contenuHTML, onglets, actifIdx, opts = {}) {
  document.body.classList.remove('admin-mode'); // écrans vendeur/conseiller/connexion : pas de menu latéral
  app.innerHTML = `
    <div id="barre"></div>
    <div class="topbar">
      ${opts.retour ? `<button class="icon-btn" id="btn-retour" title="Retour">${ic('back')}</button>` : `<div class="brand">MOK<span>·</span>B</div>`}
      <div class="spacer"></div>
      <button class="icon-btn" id="btn-theme" title="Thème clair/sombre">${ic('theme')}</button>
      <button class="icon-btn" id="btn-logout" title="Se déconnecter">${ic('logout')}</button>
    </div>
    <div class="content"><h2 class="screen-title">${esc(titre)}</h2><div id="vue">${contenuHTML}</div></div>
    ${onglets ? `<nav class="tabbar">${onglets.map((o, i) => `<button data-idx="${i}" class="${i === actifIdx ? 'active' : ''}"><span class="emoji">${icTile(o.icone, 30)}</span>${o.nom}</button>`).join('')}</nav>` : ''}`;
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
  { nom: 'Vendre', icone: 'cart', aller: vendeurCatalogue },
  { nom: 'Panier', icone: 'cart', aller: vendeurPanier },
  { nom: 'Messages', icone: 'message', aller: vendeurMessages },
  { nom: 'Mon jour', icone: 'clock', aller: vendeurHoraire },
  { nom: 'Devis', icone: 'file', aller: vendeurDevis },
  { nom: 'Ventes', icone: 'coins', aller: vendeurMesVentes },
];
async function vendeurMesVentes() {
  cadre('Mes ventes', `<div id="mv"><div class="spinner"></div></div>`, ongletsVendeur(), 5);
  const rendre = async () => {
    try {
      const d = await api('GET', '/mes-ventes');
      const items = d.ventes?.data || [];
      $('#mv').innerHTML = `
        <div class="card">
          <div class="info-row"><span class="lbl">Argent payé par les clients</span><span class="val" style="color:var(--success)">${gnf(d.total_paye)}</span></div>
          <div class="info-row"><span class="lbl">Reste à payer (crédits)</span><span class="val" style="color:var(--danger)">${gnf(d.total_reste)}</span></div>
        </div>
        <div class="card"><h3>Mes ventes</h3>${items.length ? items.map(v => `
          <div class="vente-bloc" style="padding:10px 0;border-bottom:1px solid var(--border)">
            <div class="row-between"><div><b>${esc(v.reference)}</b> — ${gnf(v.montant_total)}
              <div class="muted" style="font-size:.82rem">${esc(v.date)}${v.client ? ' · ' + esc(v.client) : ''} · payé ${gnf(v.montant_paye)}${v.reste > 0 ? ' · <b style="color:var(--danger)">reste ' + gnf(v.reste) + '</b>' : ' ' + ic('check', 14)}</div></div>
              ${v.reste > 0 ? `<button class="btn" style="width:auto;padding:6px 10px" data-enc="${v.id}" data-reste="${v.reste}">${ic('banknote')} Encaisser</button>` : ''}
            </div>
            ${(v.paiements || []).length ? `<div class="muted" style="font-size:.78rem;margin-top:4px">Versements : ${v.paiements.map(p => gnf(p.montant) + ' (' + (p.date || '').slice(0, 10) + ')').join(' · ')}</div>` : ''}
          </div>`).join('') : '<p class="muted">Aucune vente.</p>'}</div>`;
      $('#mv').querySelectorAll('[data-enc]').forEach(b => b.onclick = async () => {
        const reste = +b.dataset.reste;
        const saisie = prompt('Reste à payer : ' + gnf(reste) + '\nMontant que le client paie maintenant (GNF) :', reste);
        if (saisie === null) return;
        const montant = parseInt(String(saisie).replace(/\D/g, ''), 10);
        if (!montant || montant < 1) { toast('Montant invalide.'); return; }
        try {
          const r = await api('POST', '/mes-ventes/' + b.dataset.enc + '/encaisser', { montant });
          toast(r.reste > 0 ? 'Paiement noté. Reste ' + gnf(r.reste) : 'Payé entièrement');
          rendre();
        } catch (ex) { toast(ex.premierDetail ? ex.premierDetail() : ex.message); }
      });
    } catch (ex) { $('#mv').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  rendre();
}
async function vendeurDevis() {
  cadre('Devis', `<button class="btn block" id="nouveau">${ic('plus')} Nouveau devis</button><div id="liste"><div class="spinner"></div></div>`, ongletsVendeur(), 4);
  $('#nouveau').onclick = () => formDevis(vendeurDevis);
  try {
    const d = await api('GET', '/devis');
    const items = d.data || [];
    $('#liste').innerHTML = items.length ? `<div class="card">${items.map(x => `
      <div class="list-item tap" data-id="${x.id}" role="button" tabindex="0"><div class="main"><div class="name">${esc(x.reference)} — ${gnf(x.montant_total)}</div><div class="sub">${esc(x.client)} · ${esc(x.date)} · ${esc(x.statut)}</div></div><span class="badge cat">Voir</span></div>`).join('')}</div>` : '<p class="muted center mt">Aucun devis.</p>';
    $('#liste').querySelectorAll('.tap').forEach(el => el.onclick = () => voirDevis(+el.dataset.id, vendeurDevis));
  } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* Panier en mémoire du navigateur */
let panier = [];
try { panier = JSON.parse(localStorage.getItem('mok_panier') || '[]'); } catch { panier = []; }
function sauverPanier() { localStorage.setItem('mok_panier', JSON.stringify(panier)); }
function nbPanier() { return panier.reduce((n, l) => n + l.quantite, 0); }
function ajouterAuPanier(p) {
  const l = panier.find(x => x.produit_id === p.id);
  if (l) l.quantite++; else panier.push({ produit_id: p.id, nom: p.nom, prix: p.prix || 0, quantite: 1 });
  sauverPanier(); toast(p.nom + ' ajouté (' + nbPanier() + ')');
}
function totalPanier() { return panier.reduce((s, l) => s + (l.prix || 0) * l.quantite, 0); }
let venteClient = null;      // client enregistré choisi (facultatif)
let venteClientNom = '';     // nom tapé à la main (facultatif)
let venteClientTel = '';     // téléphone tapé à la main (facultatif)

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
        <div class="list-item tap" data-id="${p.id}" data-nom="${esc(p.nom)}" data-prix="${p.prix_unitaire || 0}" role="button" tabindex="0">
          <div class="main"><div class="name">${esc(p.nom)}</div>
            <div class="sub">${esc(p.modele || p.categorie || '')} · <b>${gnf(p.prix_unitaire)}</b> · stock ${p.stock}${p.stock <= 0 ? ' ' + ic('alert', 14) : ''}</div></div>
          <span class="badge cat">+ Ajouter</span>
        </div>`).join('')}</div>` : `<p class="muted center mt">Aucun article trouvé.</p>`;
      $('#liste').querySelectorAll('.tap').forEach(el => el.addEventListener('click', () => {
        ajouterAuPanier({ id: +el.dataset.id, nom: el.dataset.nom, prix: +el.dataset.prix });
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
  b.innerHTML = `<span class="emoji">${icTile('cart', 30)}</span>Panier${n ? ' (' + n + ')' : ''}`;
}

function vendeurPanier() {
  cadre('Panier', '', ongletsVendeur(), 1);
  const rendre = () => {
    if (!panier.length) { $('#vue').innerHTML = `<p class="muted center mt">Panier vide. Ajoutez des articles depuis le catalogue.</p>`; return; }
    $('#vue').innerHTML = `
      <div class="card">${panier.map((l, i) => `
        <div class="list-item">
          <div class="main"><div class="name">${esc(l.nom)}</div>
            <div class="sub" data-sub="${i}">${gnf(l.prix)} × ${l.quantite} = <b>${gnf((l.prix || 0) * l.quantite)}</b></div></div>
          <div class="btn-row" style="width:auto">
            <button class="icon-btn" data-moins="${i}">${ic('minus')}</button>
            <input class="qte-input" data-i="${i}" type="number" inputmode="numeric" min="1" value="${l.quantite}">
            <button class="icon-btn" data-plus="${i}">${ic('plus')}</button>
            <button class="icon-btn" data-sup="${i}" style="color:var(--danger)">${ic('trash')}</button>
          </div>
        </div>`).join('')}
        <div class="info-row" style="padding-top:12px"><span class="lbl">Total à encaisser</span><span class="val" id="pan-total">${gnf(totalPanier())}</span></div></div>
      <div class="card"><h3>Client (facultatif — pour la facture)</h3>
        ${venteClient ? `<div class="list-item"><div class="main"><div class="name">${esc(venteClient.nom)}</div><div class="sub">${esc(venteClient.tel || '')} · client enregistré</div></div><button class="icon-btn" id="vcl-clear" style="color:var(--danger)">${ic('close')}</button></div>`
          : `<div class="field"><label>Nom complet du client</label><input id="vcl-nom" value="${esc(venteClientNom)}" placeholder="Prénom et nom"></div>
             <div class="field"><label>Numéro de téléphone</label><input id="vcl-tel" value="${esc(venteClientTel)}" inputmode="tel" placeholder="+224…"></div>
             <div class="field"><label>…ou choisir un client déjà enregistré</label><input id="vcl-search" placeholder="Rechercher par nom ou téléphone"></div>
             <div id="vcl-res"></div>`}
      </div>
      <div class="card">
        <div class="field"><label>Mode de paiement</label>
          <select id="mode"><option value="especes">Espèces</option><option value="orange_money">Orange Money</option><option value="carte">Carte bancaire</option></select></div>
        <div class="field" id="paye-wrap"><label>Argent payé maintenant par le client (GNF)</label>
          <input id="montant-paye" type="number" inputmode="numeric" value="${totalPanier()}"></div>
        <div id="reste-info" style="font-size:.85rem;margin:-6px 0 10px"></div>
        <div class="error-msg" id="p-err"></div>
        <button class="btn" id="valider">Valider la vente · <span id="pan-total-btn">${gnf(totalPanier())}</span></button>
      </div>`;
    $('#vue').querySelectorAll('[data-plus]').forEach(b => b.onclick = () => { panier[+b.dataset.plus].quantite++; sauverPanier(); rendre(); });
    $('#vue').querySelectorAll('[data-moins]').forEach(b => b.onclick = () => { const l = panier[+b.dataset.moins]; if (--l.quantite < 1) panier.splice(+b.dataset.moins, 1); sauverPanier(); rendre(); });
    $('#vue').querySelectorAll('[data-sup]').forEach(b => b.onclick = () => { panier.splice(+b.dataset.sup, 1); sauverPanier(); rendre(); });
    // Quantité tapée à la main (sans tout recharger, pour garder le curseur dans le champ).
    const majTotaux = () => { const t = gnf(totalPanier()); $('#pan-total').textContent = t; $('#pan-total-btn').textContent = t; };
    $('#vue').querySelectorAll('.qte-input').forEach(inp => {
      inp.oninput = () => {
        const i = +inp.dataset.i, v = parseInt(inp.value, 10);
        if (!isNaN(v) && v >= 1) {
          panier[i].quantite = v; sauverPanier();
          $(`[data-sub="${i}"]`).innerHTML = `${gnf(panier[i].prix)} × ${v} = <b>${gnf((panier[i].prix || 0) * v)}</b>`;
          majTotaux();
        }
      };
      inp.onchange = () => { const i = +inp.dataset.i; if (!parseInt(inp.value, 10) || parseInt(inp.value, 10) < 1) { inp.value = panier[i].quantite; } };
    });
    $('#valider').onclick = validerVente;
    // Montant payé / reste à payer (espèces seulement).
    const majReste = () => {
      const info = $('#reste-info'); if (!info) return;
      if ($('#mode').value !== 'especes') { $('#paye-wrap').style.display = 'none'; info.textContent = ''; return; }
      $('#paye-wrap').style.display = '';
      const total = totalPanier(), paye = parseInt($('#montant-paye').value, 10);
      if (isNaN(paye) || paye >= total) info.innerHTML = ic('check') + ' Payé entièrement';
      else info.innerHTML = 'Reste à payer : <b style="color:var(--danger)">' + gnf(total - Math.max(0, paye)) + '</b>';
    };
    $('#mode').onchange = majReste;
    $('#montant-paye').oninput = majReste;
    majReste();
    if (venteClient) { $('#vcl-clear').onclick = () => { venteClient = null; rendre(); }; }
    else {
      $('#vcl-nom').oninput = e => { venteClientNom = e.target.value; };
      $('#vcl-tel').oninput = e => { venteClientTel = e.target.value; };
      let tc; $('#vcl-search').addEventListener('input', async e => {
        clearTimeout(tc); const q = e.target.value.trim();
        tc = setTimeout(async () => {
          if (!q) { $('#vcl-res').innerHTML = ''; return; }
          try {
            const d = await api('GET', '/clients?recherche=' + encodeURIComponent(q));
            const items = (d.data || []).slice(0, 6);
            $('#vcl-res').innerHTML = items.length ? `<div class="card" style="margin:6px 0">${items.map(c => `<div class="list-item tap" data-id="${c.id}" data-nom="${esc(c.prenom + ' ' + c.nom)}" data-tel="${esc(c.telephone || '')}" role="button"><div class="main"><div class="name">${esc(c.prenom)} ${esc(c.nom)}</div><div class="sub">${esc(c.telephone || '')}</div></div><span class="badge cat">Choisir</span></div>`).join('')}</div>` : '<p class="muted" style="font-size:.85rem">Aucun client enregistré. Tapez le nom et le téléphone ci-dessus.</p>';
            $('#vcl-res').querySelectorAll('.tap').forEach(x => x.onclick = () => { venteClient = { id: +x.dataset.id, nom: x.dataset.nom, tel: x.dataset.tel }; rendre(); });
          } catch (ex) { $('#vcl-res').innerHTML = `<p class="muted">${esc(ex.message)}</p>`; }
        }, 300);
      });
    }
  };
  rendre();
}
async function validerVente() {
  const btn = $('#valider'), err = $('#p-err'); err.textContent = '';
  if (!panier.length) return;
  btn.disabled = true; btn.textContent = 'Enregistrement…';
  const pos = await position();
  const vente = {
    uuid_client: uuid(),
    lignes: panier.map(l => ({ produit_id: l.produit_id, quantite: l.quantite })),
    mode_paiement: $('#mode').value,
    client_id: venteClient?.id || null,
    client_nom: venteClient ? null : (venteClientNom.trim() || null),
    client_tel: venteClient ? null : (venteClientTel.trim() || null),
    montant_paye: $('#mode').value === 'especes' ? (parseInt($('#montant-paye').value, 10) || 0) : null,
    ...(pos || {}),
  };

  // Hors ligne : on garde la vente tout de suite, elle partira seule au retour d'internet.
  if (!navigator.onLine) { return mettreEnAttente(vente); }

  try {
    const res = await api('POST', '/ventes', vente);
    panier = []; sauverPanier(); venteClient = null; venteClientNom = ''; venteClientTel = '';
    if (res.data.mode_paiement === 'especes') {
      venteConfirmee(res.data);
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

/* Confirmation après une vente en espèces : montant encaissé + bouton Facture. */
function venteConfirmee(v) {
  cadre('Vente enregistrée', `
    <div class="card center">
      <div style="color:var(--success)">${ic('checkCircle', 44)}</div>
      <h3 style="margin:.3rem 0">${esc(v.reference)}</h3>
      <div class="value" style="font-size:1.6rem">${gnf(v.montant_a_encaisser)}</div>
      <div class="info-row"><span class="lbl">Payé par le client</span><span class="val" style="color:var(--success)">${gnf(v.montant_paye)}</span></div>
      ${v.reste > 0 ? `<div class="info-row"><span class="lbl">Reste à payer</span><span class="val" style="color:var(--danger)">${gnf(v.reste)}</span></div>` : '<p class="muted">Payé entièrement</p>'}
      <button class="btn block" id="vf-fact">${ic('receipt')} Voir / imprimer le reçu</button>
      <button class="btn secondary block" id="vf-wa">${ic('message')} Envoyer le reçu au client</button>
      <button class="btn secondary block" id="vf-new">Nouvelle vente</button>
      <p class="muted" style="font-size:.78rem;margin-top:6px">Cette vente est déjà enregistrée : l'administrateur la voit dans « Toutes les ventes ».</p>
    </div>`, ongletsVendeur(), 0);
  $('#vf-fact').onclick = () => voirFacture(v.id, () => venteConfirmee(v));
  $('#vf-wa').onclick = () => envoyerRecuWhatsApp(v.id);
  $('#vf-new').onclick = vendeurCatalogue;
}

/* Récupère la facture d'une vente et ouvre WhatsApp avec le reçu (au client si connu). */
async function envoyerRecuWhatsApp(venteId) {
  try {
    const d = await api('GET', '/ventes/' + venteId + '/facture');
    envoyerFactureWhatsApp(d);
  } catch (ex) { toast(ex.premierDetail ? ex.premierDetail() : ex.message); }
}

/* Garde la vente dans la file locale (survit à une coupure d'électricité : stockée sur l'appareil). */
function mettreEnAttente(vente) {
  fileAttente.push(vente); sauverFile();
  panier = []; sauverPanier(); venteClient = null; venteClientNom = ''; venteClientTel = '';
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
        <h3>${ic('card')} Payer en ligne (Orange Money, MTN, carte)</h3>
        <p class="muted" style="font-size:.82rem">Génère un lien de paiement sécurisé. Le client paie, et la vente passe en « payée » automatiquement.</p>
        <div class="field"><label>Téléphone du client (facultatif)</label><input id="enl-tel" inputmode="tel" placeholder="ex. 62xxxxxxx"></div>
        <div class="error-msg" id="enl-err"></div>
        <button class="btn block" id="enl-btn">${ic('card')} Créer le lien de paiement</button>
        <div id="enl-apres"></div>
      </div>
      <div class="card">
        <div class="field"><label>Référence de la transaction (donnée par le client)</label><input id="ref" placeholder="ex. PP24..."></div>
        <div class="error-msg" id="ref-err"></div>
        <button class="btn" id="ref-btn">Enregistrer la référence</button>
        <button class="btn secondary block" id="fact">${ic('receipt')} Facture</button>
        <button class="btn secondary block" id="skip">Plus tard</button>
        <p class="notice">Le paiement sera confirmé par l'administrateur après vérification sur le compte.</p>
      </div>`;
    // Paiement en ligne (CinetPay) : créer le lien, puis vérifier.
    $('#enl-btn').onclick = async () => {
      const err = $('#enl-err'); err.textContent = '';
      const btn = $('#enl-btn'); btn.disabled = true; btn.textContent = 'Création…';
      try {
        const r = await api('POST', '/ventes/' + v.id + '/payer-en-ligne', { telephone: $('#enl-tel').value.trim() || null });
        window.open(r.url_paiement, '_blank');
        $('#enl-apres').innerHTML = `
          <a class="btn secondary block" href="${esc(r.url_paiement)}" target="_blank" rel="noopener">${ic('forward')} Rouvrir le lien de paiement</a>
          <button class="btn block" id="enl-verif">${ic('check')} Le client a payé — vérifier</button>
          <p class="muted" style="font-size:.78rem">Réf : ${esc(r.reference)}</p>`;
        btn.style.display = 'none';
        $('#enl-verif').onclick = async () => {
          const vb = $('#enl-verif'); vb.disabled = true; vb.textContent = 'Vérification…';
          try {
            const res = await api('POST', '/ventes/' + v.id + '/verifier-paiement', { reference: r.reference });
            if (res.paye) { toast('Paiement confirmé.'); venteConfirmee({ ...v, montant_a_encaisser: info.montant, montant_paye: info.montant, reste: 0 }); }
            else { vb.disabled = false; vb.textContent = 'Pas encore payé — vérifier à nouveau'; toast('Paiement pas encore reçu.'); }
          } catch (ex) { vb.disabled = false; vb.textContent = 'Réessayer'; toast(ex.premierDetail()); }
        };
      } catch (ex) { err.textContent = ex.premierDetail(); btn.disabled = false; btn.textContent = 'Créer le lien de paiement'; }
    };
    $('#fact').onclick = () => voirFacture(v.id, () => vendeurPaiement(v));
    $('#ref-btn').onclick = async () => {
      const err = $('#ref-err'); err.textContent = '';
      const reference_paiement = $('#ref').value.trim();
      if (!reference_paiement) { err.textContent = 'Saisissez la référence, ou touchez « Plus tard ».'; return; }
      try { await api('POST', '/ventes/' + v.id + '/reference-paiement', { reference_paiement }); toast('Référence enregistrée.'); vendeurCatalogue(); }
      catch (ex) { err.textContent = ex.premierDetail(); }
    };
    $('#skip').onclick = vendeurCatalogue;
  } catch (ex) { $('#p').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p><button class="btn mt" id="p-reload">OK</button>`; const rb = $('#p-reload'); if (rb) rb.onclick = () => location.reload(); }
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
      <button class="btn secondary block" id="wa">${ic('message')} Contacter la boutique sur WhatsApp</button></div>`, ongletsVendeur(), 2);
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
      <div class="card center"><div style="font-size:2rem">${m.statut === 'en_service' ? '<span style="color:var(--success)">'+ic('dot',30)+'</span>' : m.statut === 'avant_service' ? '<span style="color:#d97706">'+ic('dot',30)+'</span>' : '<span style="color:#9ca3af">'+ic('dot',30)+'</span>'}</div>
        <h3 style="margin:.3rem 0">${esc(m.message || '')}</h3><p class="muted">Fuseau : ${esc(d.fuseau_horaire || '')}</p></div>
      <div class="card"><h3>Ma semaine</h3>${(d.jours && d.jours.length) ? d.jours.map(j => `<div class="list-item"><div class="main"><div class="name">${jours[j.jour_semaine] || j.jour_semaine}</div></div><span class="badge">${(j.heure_debut || '').slice(0, 5)} – ${(j.heure_fin || '').slice(0, 5)}</span></div>`).join('') : '<p class="muted">Aucun horaire défini.</p>'}</div>`;
  } catch (ex) { $('#h').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* ================================================================  ADMINISTRATEUR
   Présentation type « tableau de bord » : menu latéral à gauche (replié en ☰ sur
   téléphone) + barre du haut + contenu en cartes. */

function menuAdmin() {
  return [
    ['tableau', 'dashboard', 'Tableau de bord', adminTableau],
    ['comptes', 'users', 'Comptes vendeurs', adminComptes],
    ['produits', 'box', 'Produits', adminProduits],
    ['reparateurs', 'wrench', 'Réparateurs', adminReparateurs],
    ['ventes', 'list', 'Ventes', adminVentes],
    ['rapport', 'receipt', 'Rapport du jour', adminRapportJour],
    ['caisse', 'wallet', 'Caisse', adminCaisse],
    ['devis', 'file', 'Devis', adminDevis],
    ['clients', 'user', 'Clients', adminClients],
    ['restes', 'coins', 'Restes à payer', adminRestes],
    ['credits', 'card', 'Crédits clients', adminCreditsClients],
    ['paie', 'banknote', 'Paie', adminPaie],
    ['confirm', 'checkCircle', 'Paiements', adminPaiementsConfirmer],
    ['messages', 'message', 'Messages', adminMessages],
    ['alertes', 'bell', 'Alertes', adminAlertes],
    ['journal', 'journal', 'Journal', adminJournal],
  ];
}

/* Journal : libellés lisibles des actions + mise en forme des détails. */
const ACTIONS_LBL = {
  connexion: 'Connexion', compte_cree: 'Compte créé', compte_modifie: 'Compte modifié',
  compte_verrouille: 'Compte verrouillé', compte_deverrouille: 'Compte réactivé',
  mot_de_passe_reinitialise: 'Mot de passe réinitialisé', client_cree: 'Client créé', client_modifie: 'Client modifié',
  produit_cree: 'Produit créé', produit_modifie: 'Produit modifié', reparateur_cree: 'Réparateur créé', reparateur_modifie: 'Réparateur modifié',
  vendeur_paye: 'Vendeur payé', tentative_intrusion: "Tentative d'intrusion", vente_modifiee: 'Vente modifiée',
  modification_refusee: 'Modification refusée', paiement_confirme: 'Paiement confirmé', remboursement_client: 'Remboursement client',
  dette_reparateur_ajoutee: 'Dette réparateur ajoutée', dette_reparateur_modifiee: 'Dette réparateur modifiée', dette_reparateur_supprimee: 'Dette réparateur supprimée',
  devis_cree: 'Devis créé', devis_converti: 'Devis converti en vente', vente_annulee: 'Vente annulée', horaires_modifies: 'Horaires modifiés',
};
function formatDetails(d) {
  if (!d) return '';
  const p = [];
  if (d.reference) p.push('réf ' + d.reference);
  if (d.motif) p.push('raison : ' + d.motif);
  if (d.montant != null) p.push('montant ' + gnf(d.montant));
  if (d.mode) p.push('mode ' + d.mode);
  if (d.periode) p.push('période ' + d.periode);
  if (d.demande_par) p.push('demandé par ' + d.demande_par);
  if (d.avant && d.apres) p.push('changement : ' + JSON.stringify(d.avant) + ' → ' + JSON.stringify(d.apres));
  if (d.champs) p.push('champs : ' + (Array.isArray(d.champs) ? d.champs.join(', ') : d.champs));
  if (d.chemin) p.push('page ' + d.chemin);
  if (d.tentative) p.push('tentative n°' + d.tentative);
  if (d.compte_bloque) p.push('Compte bloqué');
  if (d.vente) p.push('vente ' + d.vente);
  return p.join(' · ');
}
async function adminJournal() {
  adminShell('journal', 'Journal des activités', `
    <div class="field"><label>Filtrer</label><select id="jf">
      <option value="">Toutes les actions</option>
      <option value="vente_annulee">Ventes annulées</option>
      <option value="vente_modifiee">Ventes modifiées</option>
      <option value="tentative_intrusion">Tentatives d'intrusion</option>
      <option value="vendeur_paye">Paies des vendeurs</option>
      <option value="paiement_confirme">Paiements confirmés</option>
      <option value="connexion">Connexions</option>
    </select></div>
    <div id="liste"><div class="spinner"></div></div>`);
  const rendre = async () => {
    const f = $('#jf').value;
    try {
      const d = await api('GET', '/admin/journal' + (f ? '?action=' + f : ''));
      const items = d.data || [];
      $('#liste').innerHTML = items.length ? `<div class="card">${items.map(j => `
        <div class="list-item"><div class="main">
          <div class="name">${esc(ACTIONS_LBL[j.action] || j.action)}</div>
          <div class="sub">${esc(j.auteur)}${j.role ? ' (' + esc(j.role) + ')' : ''} · ${esc(j.date)}</div>
          ${j.details ? `<div class="sub">${esc(formatDetails(j.details))}</div>` : ''}
        </div></div>`).join('')}</div>` : '<p class="muted center mt">Aucune activité.</p>';
    } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  $('#jf').onchange = rendre; rendre();
}

/* Coquille admin : barre latérale + barre du haut + zone de contenu #vue. */
function adminShell(actif, titre, contenuHTML) {
  document.body.classList.add('admin-mode'); // menu latéral toujours visible sur ordinateur
  const menu = menuAdmin();
  const nom = store.user ? ((store.user.prenom || '') + ' ' + (store.user.nom || '')).trim() : '';
  app.innerHTML = `
    <div class="wp">
      <aside class="wp-side" id="wp-side">
        <div class="wp-brand">MOK<span>-Business</span></div>
        <nav>${menu.map(m => `<button class="wp-item ${m[0] === actif ? 'on' : ''}" data-k="${m[0]}"><span class="emoji">${icTile(m[1], 28)}</span>${m[2]}</button>`).join('')}</nav>
      </aside>
      <div class="wp-backdrop" id="wp-bd"></div>
      <div class="wp-body">
        <header class="wp-top">
          <button class="icon-btn wp-burger" id="wp-burger" title="Menu">${ic('menu')}</button>
          <div class="wp-ttl">${esc(titre)}</div>
          <div class="spacer"></div>
          <span class="wp-hello">Bonjour, ${esc(nom)}</span>
          <button class="icon-btn" id="wp-theme" title="Thème">${ic('theme')}</button>
          <button class="icon-btn" id="wp-logout" title="Se déconnecter">${ic('logout')}</button>
        </header>
        <main class="wp-main"><div id="vue">${contenuHTML}</div></main>
      </div>
    </div>`;
  const side = $('#wp-side'), bd = $('#wp-bd');
  const fermer = () => { side.classList.remove('open'); bd.classList.remove('show'); };
  $('#wp-burger').onclick = () => { side.classList.toggle('open'); bd.classList.toggle('show'); };
  bd.onclick = fermer;
  $('#wp-theme').onclick = basculerTheme;
  $('#wp-logout').onclick = deconnexion;
  const map = Object.fromEntries(menu.map(m => [m[0], m[3]]));
  app.querySelectorAll('.wp-item').forEach(b => b.onclick = () => { fermer(); map[b.dataset.k](); });
}

async function adminPaiementsConfirmer() {
  adminShell('confirm', 'Paiements à confirmer', `<div id="pc"><div class="spinner"></div></div>`);
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

const MVT_STOCK_LBL = {
  vente: `<span style="color:var(--danger)">${ic('out', 15)}</span> Vente`,
  approvisionnement: `<span style="color:var(--success)">${ic('in', 15)}</span> Entrée`,
  annulation: `${ic('undo', 15)} Retour`,
};
async function adminTableau() {
  adminShell('tableau', 'Tableau de bord', `<div id="tb"><div class="spinner"></div></div>`);
  try {
    const [d, vd] = await Promise.all([
      api('GET', '/admin/tableau-de-bord'),
      api('GET', '/admin/utilisateurs?role=vendeur').catch(() => ({ data: [] })),
    ]);
    const ca = d.chiffre_affaires || {};
    // Ventes du mois par vendeur (depuis le classement du tableau de bord).
    const ventesParVendeur = {};
    (d.top_vendeurs_mois || []).forEach(v => { ventesParVendeur[v.vendeur_id] = Number(v.montant); });
    const vendeurs = vd.data || [];

    const glance = (icone, val, lbl, go, alerte) =>
      `<div class="glance ${go ? 'lien' : ''} ${alerte ? 'alerte' : ''}" ${go ? `data-go="${go}"` : ''}>
        <span class="g-emoji">${icTile(icone, 34)}</span><span class="g-val">${val}</span><span class="g-lbl">${lbl}</span></div>`;

    $('#tb').innerHTML = `
      <div class="card">
        <h3>D'un coup d'œil</h3>
        <div class="glance-grid">
          ${glance('coins', gnf(ca.mois), "CA du mois")}
          ${glance('trendUp', gnf(ca.semaine), "CA de la semaine")}
          ${glance('receipt', gnf(ca.aujourdhui), "CA aujourd'hui")}
          ${glance('cart', d.ventes_aujourdhui ?? 0, "Ventes aujourd'hui", 'ventes')}
          ${glance('trendUp', gnf(d.marge_mois), "Marge du mois")}
          ${glance('wallet', gnf(d.caisse?.solde), "Solde en caisse", 'caisse')}
          ${glance('trendDown', gnf(d.caisse?.depenses), "Dépenses du jour", 'caisse', d.caisse?.depenses > 0)}
          ${glance('box', d.stock?.en_rupture ?? 0, "En rupture", 'produits', d.stock?.en_rupture)}
          ${glance('alert', d.stock?.stock_bas ?? 0, "Stock bas", 'produits')}
          ${glance('wrench', d.reparateurs?.en_alerte ?? 0, "Réparateurs en alerte", 'reparateurs', d.reparateurs?.en_alerte)}
          ${glance('card', gnf(d.reparateurs?.dette_totale), "Dette réparateurs", 'reparateurs')}
          ${glance('checkCircle', d.paiements_en_attente ?? 0, "Paiements à confirmer", 'confirm', d.paiements_en_attente)}
          ${glance('message', d.messages_non_lus ?? 0, "Messages non lus", 'messages', d.messages_non_lus)}
          ${glance('user', d.clients?.total ?? 0, "Clients")}
          ${glance('users', (d.comptes?.verrouilles ?? 0), "Vendeurs verrouillés", 'comptes')}
        </div>
      </div>

      <div class="card">
        <h3>Ventes des 30 derniers jours</h3>
        ${chartCourbe((d.ventes_par_jour || []).map(x => ({ label: x.jour, value: Number(x.montant), n: Number(x.nombre) })))}
      </div>

      ${blocTop('Meilleurs produits (mois)', (d.top_produits_mois || []).map(p => ({ nom: p.produit?.nom || '—', value: Number(p.quantite), suffix: ' vendus' })), 'c1')}
      ${blocTop('Meilleurs vendeurs (mois)', (d.top_vendeurs_mois || []).map(v => ({ nom: ((v.vendeur?.prenom || '') + ' ' + (v.vendeur?.name || '')).trim() || '—', value: Number(v.montant) })), 'c2')}
      ${blocTop('Chiffre d\'affaires par région (mois)', (d.ca_par_region_mois || []).map(r => ({ nom: r.region || '—', value: Number(r.montant) })), 'c3')}

      <div class="card">
        <div class="row-between"><h3>Vendeurs</h3><span class="badge">${vendeurs.length}</span></div>
        ${vendeurs.length ? vendeurs.map(v => `
          <div class="list-item tap" data-uid="${v.id}" role="button" tabindex="0">
            <div class="main"><div class="name">${esc(v.prenom)} ${esc(v.nom)}</div>
              <div class="sub">${esc(v.identifiant)}${v.region ? ' · ' + esc(v.region) : ''} · ventes du mois : ${gnf(ventesParVendeur[v.id] || 0)}</div></div>
            <span class="badge" style="color:${v.est_verrouille ? 'var(--danger)' : 'var(--success)'}">${v.est_verrouille ? 'Verrouillé' : 'Actif'}</span>
          </div>`).join('') : '<p class="muted">Aucun vendeur. Créez-en dans « Comptes ».</p>'}
      </div>

      <div class="card">
        <div class="row-between"><h3>Mouvements de stock</h3>
          <span class="badge"><span style="color:var(--success)">${ic('in', 14)}</span> ${d.mouvements_stock?.entrees_jour ?? 0} · <span style="color:var(--danger)">${ic('out', 14)}</span> ${d.mouvements_stock?.sorties_jour ?? 0} aujourd'hui</span></div>
        ${(d.mouvements_stock?.recents || []).length ? d.mouvements_stock.recents.map(m => `
          <div class="list-item"><div class="main">
            <div class="name">${MVT_STOCK_LBL[m.type] || m.type} — ${esc(m.produit || '—')}</div>
            <div class="sub">${m.quantite > 0 ? '+' + m.quantite : m.quantite} · reste ${m.stock_apres}${m.par ? ' · ' + esc(m.par) : ''} · ${esc(m.date)}</div></div>
          </div>`).join('') : '<p class="muted">Aucun mouvement de stock pour le moment.</p>'}
      </div>`;

    const aller = { ventes: adminVentes, produits: adminProduits, reparateurs: adminReparateurs, confirm: adminPaiementsConfirmer, messages: adminMessages, comptes: adminComptes, caisse: adminCaisse };
    $('#tb').querySelectorAll('[data-go]').forEach(b => b.onclick = () => aller[b.dataset.go]());
    $('#tb').querySelectorAll('[data-uid]').forEach(b => b.onclick = () => adminDetailCompte(+b.dataset.uid));
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
  adminShell('comptes', 'Comptes vendeurs', `
    <button class="btn block" id="nouveau">${ic('plus')} Nouveau compte</button>
    <div class="field mt"><input id="q" placeholder="Rechercher (nom, identifiant, téléphone)…"></div>
    <div id="liste"><div class="spinner"></div></div>`);
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
      <button class="btn block" id="b-modif">${ic('edit')} Modifier</button>
      ${u.role === 'vendeur' ? `<button class="btn block secondary" id="b-horaires">${ic('clock')} Définir les horaires</button>` : ''}
      ${u.telephone ? `<button class="btn block secondary" id="b-wa">${ic('message')} WhatsApp</button>` : ''}
      ${u.est_verrouille
        ? `<button class="btn block" id="b-activer" style="background:var(--success);color:#fff">${ic('unlock')} Réactiver le compte</button>`
        : `<button class="btn block danger" id="b-verrou">${ic('lock')} Verrouiller le compte</button>`}
      <button class="btn block secondary" id="b-mdp">${ic('key')} Réinitialiser le mot de passe</button>`;
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
  adminShell('produits', 'Produits', `
    <button class="btn block" id="nouveau">${ic('plus')} Nouveau produit</button>
    <button class="btn block secondary" id="stockbas">${ic('alert')} Stock bas (à recommander)</button>
    <div class="field mt"><input id="q" placeholder="Rechercher un produit…"></div>
    <div id="liste"><div class="spinner"></div></div>`);
  $('#nouveau').onclick = adminFormProduit;
  $('#stockbas').onclick = adminStockBas;
  const rendre = async (q = '') => {
    try {
      const d = await api('GET', '/admin/produits' + (q ? '?recherche=' + encodeURIComponent(q) : ''));
      const items = d.data || [];
      $('#liste').innerHTML = `<div class="card">${items.map(p => `
        <div class="list-item tap" data-id="${p.id}" role="button" tabindex="0">
          <div class="main"><div class="name">${esc(p.nom)}</div><div class="sub">${esc(p.categorie || '')} · ${gnf(p.prix_unitaire)} · stock ${p.stock}${p.actif ? '' : ' · inactif'}</div></div>
          <span class="badge">${ic('plus',14)} stock</span>
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
  adminShell('paie', 'Paie des vendeurs', `
    <div class="card">
      <div class="field"><label>Fréquence</label><select id="freq"><option value="mois">Mensuelle</option><option value="semaine">Hebdomadaire</option><option value="jour">Journalière</option></select></div>
      <div class="field"><label>Mode de paiement</label><select id="mode"><option value="orange_money">Orange Money</option><option value="especes">Espèces</option><option value="carte">Carte</option></select></div>
    </div>
    <div id="liste"><div class="spinner"></div></div>`);
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
  adminShell('alertes', 'Alertes', `<div id="a"><div class="spinner"></div></div>`);
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
              <button class="icon-btn" style="color:var(--success)" data-ok="${x.id}">${ic('check')}</button>
              <button class="icon-btn" style="color:var(--danger)" data-no="${x.id}">${ic('close')}</button></div>
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
  adminShell('reparateurs', 'Réparateurs', `
    <button class="btn block" id="nouveau">${ic('plus')} Nouveau réparateur</button>
    <div id="liste"><div class="spinner"></div></div>`);
  $('#nouveau').onclick = adminFormReparateur;
  try {
    const d = await api('GET', '/admin/reparateurs');
    const seuil = d.seuil || 500000;
    const items = d.reparateurs || [];
    $('#liste').innerHTML = `<p class="muted" style="font-size:.85rem">Alerte si la dette dépasse ${gnf(seuil)}.</p>
      <div class="card">${items.map(r => `
        <div class="list-item tap" data-id="${r.id}" role="button" tabindex="0">
          <div class="main"><div class="name">${esc(r.nom)} ${r.en_alerte ? '<span style="color:var(--danger)">' + ic('dot', 12) + '</span>' : ''}</div>
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
/* Types d'article qu'un réparateur peut prendre (écran, plaquette, batterie…). */
const TYPES_ARTICLE = ['Écran de téléphone', 'Plaquette', 'Batterie', 'Connecteur de charge', 'Vitre arrière', 'Autre'];
/* Menu déroulant des types. Sélectionne la valeur actuelle, ou « Autre » si elle n'est pas dans la liste. */
function selectTypeArticle(idSel, valeur = '') {
  const connu = TYPES_ARTICLE.includes(valeur);
  const opts = TYPES_ARTICLE.map(t => `<option value="${esc(t)}" ${t === valeur || (!connu && t === 'Autre') ? 'selected' : ''}>${esc(t === 'Autre' ? 'Autre…' : t)}</option>`).join('');
  return `<div class="field"><label>Type d'article</label><select id="${idSel}">${opts}</select></div>`;
}

async function adminReparateurDetail(id) {
  cadre('Réparateur', `<div id="d"><div class="spinner"></div></div>`, null, null, { retour: adminReparateurs });
  try {
    const r = await api('GET', '/admin/reparateurs/' + id);
    const prisesMap = Object.fromEntries((r.prises || []).map(p => [p.id, p]));
    $('#d').innerHTML = `
      <div class="card"><h3>${esc(r.nom)}</h3>
        <p class="muted">${esc(r.telephone || '')} ${r.email ? '· ' + esc(r.email) : ''}</p>
        <div class="value" style="font-size:1.4rem">Dette : ${gnf(r.dette)}</div></div>

      <div class="card"><h3>Prendre un article du stock</h3>
        ${champ('p-prod', 'Article / écran (numéro du produit)', '', 'number')}
        ${champ('p-qte', 'Quantité', '1', 'number')}
        ${champ('p-paye', 'Argent donné (GNF, 0 = rien payé)', '0', 'number')}
        <p class="muted" style="font-size:.82rem;margin:-4px 0 8px">L'argent qui reste à payer est calculé automatiquement.</p>
        <div class="error-msg" id="pr-err"></div>
        <button class="btn" id="pr-btn">Enregistrer</button></div>

      <div class="card"><h3>Ajouter une dette (article pris)</h3>
        ${selectTypeArticle('d-type', 'Écran de téléphone')}
        <div class="field" id="d-autre-wrap" style="display:none"><label>Préciser le type</label><input id="d-autre" placeholder="ex. Haut-parleur, caméra…"></div>
        ${champ('d-prix', 'Prix (GNF)', '', 'number')}
        ${champ('d-qte', 'Quantité', '1', 'number')}
        ${champ('d-paye', 'Argent déjà donné (GNF, 0 = rien)', '0', 'number')}
        <details style="margin-bottom:8px"><summary class="muted" style="font-size:.85rem;cursor:pointer">Précision facultative (modèle de téléphone, ou choisir dans le catalogue)</summary>
          <div class="field" style="margin-top:8px"><label>Chercher dans le catalogue</label><input id="d-search" placeholder="ex. A14, iPhone 13…"></div>
          <div id="d-res"></div>
          ${champ('d-modele', 'Modèle / précision (ex. A14)')}
        </details>
        <div class="error-msg" id="d-err"></div>
        <button class="btn" id="d-btn">${ic('plus')} Ajouter la dette</button></div>

      <div class="card"><h3>Remboursement</h3>
        ${champ('rb-montant', 'Montant reçu (GNF)', '', 'number')}
        <div class="field"><label>Mode</label><select id="rb-mode"><option value="especes">Espèces</option><option value="orange_money">Orange Money</option><option value="carte">Carte</option></select></div>
        <div class="error-msg" id="rb-err"></div>
        <button class="btn secondary" id="rb-btn">Enregistrer le remboursement</button></div>

      <div class="card"><h3>Dettes (articles pris)</h3>${(r.prises || []).length ? r.prises.map(p => `
        <div class="list-item"><div class="main"><div class="name">${esc(p.article)} ×${p.quantite}${p.manuelle ? '' : ' <span class="badge cat">stock</span>'}</div>
          <div class="sub">${esc(p.date)} · prix ${gnf(p.montant_total)} · donné ${gnf(p.montant_paye)} · reste ${gnf(p.reste)}</div></div>
          <div class="btn-row" style="width:auto">
            <button class="icon-btn" data-edit="${p.id}" title="Modifier">${ic('edit')}</button>
            <button class="icon-btn" data-del="${p.id}" title="Supprimer" style="color:var(--danger)">${ic('trash')}</button>
          </div></div>`).join('') : '<p class="muted">Aucune.</p>'}</div>`;

    $('#pr-btn').onclick = async () => {
      const err = $('#pr-err'); err.textContent = '';
      const corps = { produit_id: parseInt($('#p-prod').value, 10), quantite: parseInt($('#p-qte').value, 10), montant_paye: parseInt($('#p-paye').value, 10) || 0 };
      if (!corps.produit_id || !corps.quantite) { err.textContent = 'Produit et quantité obligatoires.'; return; }
      try { const res = await api('POST', '/reparateurs/' + id + '/prises', corps); toast('Enregistré. Argent donné ' + gnf(res.montant_paye) + ' · reste à payer ' + gnf(res.reste)); adminReparateurDetail(id); }
      catch (ex) { err.textContent = ex.premierDetail(); }
    };
    // Afficher le champ « préciser » seulement quand on choisit « Autre ».
    const majAutre = () => { $('#d-autre-wrap').style.display = $('#d-type').value === 'Autre' ? '' : 'none'; };
    $('#d-type').addEventListener('change', majAutre); majAutre();
    let td; $('#d-search').addEventListener('input', e => { clearTimeout(td); td = setTimeout(() => chercherModeleDette(e.target.value.trim()), 300); });
    $('#d-btn').onclick = async () => {
      const err = $('#d-err'); err.textContent = '';
      const type = $('#d-type').value;
      const categorie = type === 'Autre' ? ($('#d-autre').value.trim() || null) : type;
      const corps = { categorie, modele: $('#d-modele').value.trim() || null, marque: null, prix: parseInt($('#d-prix').value, 10) || 0, quantite: parseInt($('#d-qte').value, 10) || 1, montant_paye: parseInt($('#d-paye').value, 10) || 0 };
      if (!categorie) { err.textContent = 'Choisissez le type d\'article (ou précisez-le).'; return; }
      if (!corps.prix) { err.textContent = 'Indiquez le prix.'; return; }
      try { const res = await api('POST', '/admin/reparateurs/' + id + '/dette', corps); toast('Dette ajoutée. Reste à payer ' + gnf(res.reste)); adminReparateurDetail(id); }
      catch (ex) { err.textContent = ex.premierDetail(); }
    };
    $('#rb-btn').onclick = async () => {
      const err = $('#rb-err'); err.textContent = '';
      const montant = parseInt($('#rb-montant').value, 10);
      if (!montant || montant < 1) { err.textContent = 'Montant invalide.'; return; }
      try { const res = await api('POST', '/reparateurs/' + id + '/paiements', { montant, mode_paiement: $('#rb-mode').value }); toast('Reçu. Dette restante : ' + gnf(res.dette_restante)); adminReparateurDetail(id); }
      catch (ex) { err.textContent = ex.premierDetail(); }
    };
    $('#d').querySelectorAll('[data-edit]').forEach(b => b.onclick = () => adminModifierDette(id, prisesMap[b.dataset.edit]));
    $('#d').querySelectorAll('[data-del]').forEach(b => b.onclick = async () => {
      if (!confirm('Supprimer cette dette ?')) return;
      try { const res = await api('DELETE', '/admin/prises/' + b.dataset.del); toast('Supprimé. Dette : ' + gnf(res.dette)); adminReparateurDetail(id); }
      catch (ex) { toast(ex.premierDetail()); }
    });
  } catch (ex) { $('#d').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* Recherche d'un modèle dans le catalogue → remplit modèle, catégorie et prix. */
async function chercherModeleDette(q) {
  const el = $('#d-res'); if (!el) return;
  if (!q) { el.innerHTML = ''; return; }
  try {
    const d = await api('GET', '/admin/produits?recherche=' + encodeURIComponent(q));
    const items = (d.data || []).slice(0, 8);
    el.innerHTML = items.length ? `<div class="card" style="margin:6px 0">${items.map(p => `
      <div class="list-item tap" data-mod="${esc(p.modele || p.nom)}" data-cat="${esc(p.categorie || '')}" data-prix="${p.prix_unitaire}" role="button" tabindex="0">
        <div class="main"><div class="name">${esc(p.nom)}</div><div class="sub">${esc(p.categorie || '')} · ${gnf(p.prix_unitaire)} · stock ${p.stock}</div></div>
        <span class="badge cat">Choisir</span></div>`).join('')}</div>`
      : '<p class="muted" style="font-size:.85rem">Aucun résultat. Vous pouvez taper le modèle à la main ci-dessous.</p>';
    el.querySelectorAll('.tap').forEach(x => x.onclick = () => {
      $('#d-modele').value = x.dataset.mod;
      $('#d-prix').value = x.dataset.prix;
      const cat = x.dataset.cat;
      if (cat) {
        if (TYPES_ARTICLE.includes(cat)) { $('#d-type').value = cat; }
        else { $('#d-type').value = 'Autre'; $('#d-autre').value = cat; }
        $('#d-autre-wrap').style.display = $('#d-type').value === 'Autre' ? '' : 'none';
      }
      el.innerHTML = ''; $('#d-search').value = '';
      toast('Choisi : ' + x.dataset.mod);
    });
  } catch (ex) { el.innerHTML = `<p class="muted">${esc(ex.message)}</p>`; }
}

/* Modifier une ligne de dette (modèle, marque, prix, quantité, catégorie, argent donné). */
function adminModifierDette(reparateurId, prise) {
  cadre('Modifier la dette', `
    <div class="card">
      ${selectTypeArticle('m-type', prise.categorie || '')}
      <div class="field" id="m-autre-wrap" style="display:none"><label>Préciser le type</label><input id="m-autre" value="${esc(TYPES_ARTICLE.includes(prise.categorie) ? '' : (prise.categorie || ''))}"></div>
      ${champ('m-prix', 'Prix (GNF)', prise.prix_unitaire, 'number')}
      ${champ('m-qte', 'Quantité', prise.quantite, 'number')}
      ${champ('m-paye', 'Argent donné (GNF)', prise.montant_paye, 'number')}
      ${champ('m-modele', 'Modèle / précision (facultatif)', prise.modele || '')}
      <div class="error-msg" id="m-err"></div>
      <button class="btn" id="m-save">Enregistrer</button>
    </div>`, null, null, { retour: () => adminReparateurDetail(reparateurId) });
  const majAutreM = () => { $('#m-autre-wrap').style.display = $('#m-type').value === 'Autre' ? '' : 'none'; };
  $('#m-type').addEventListener('change', majAutreM); majAutreM();
  $('#m-save').onclick = async () => {
    const err = $('#m-err'); err.textContent = '';
    const type = $('#m-type').value;
    const categorie = type === 'Autre' ? ($('#m-autre').value.trim() || null) : type;
    const corps = { categorie, modele: $('#m-modele').value.trim() || null, marque: prise.marque || null, prix: parseInt($('#m-prix').value, 10) || 0, quantite: parseInt($('#m-qte').value, 10) || 1, montant_paye: parseInt($('#m-paye').value, 10) || 0 };
    if (!categorie) { err.textContent = 'Choisissez le type d\'article.'; return; }
    if (!corps.prix) { err.textContent = 'Indiquez le prix.'; return; }
    try { const res = await api('PUT', '/admin/prises/' + prise.id, corps); toast('Modifié. Reste à payer ' + gnf(res.reste)); adminReparateurDetail(reparateurId); }
    catch (ex) { err.textContent = ex.premierDetail(); }
  };
}

/* ---- Messagerie admin : un fil par vendeur ---- */
async function adminMessages() {
  adminShell('messages', 'Messages des vendeurs', `<div id="b"><div class="spinner"></div></div>`);
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
      <button class="btn" id="vc-valider">Valider la vente à crédit</button></div>`, null, null, { retour: adminCreditsClients });

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
    <div class="btn-row" style="width:auto"><button class="icon-btn" data-m="${i}">${ic('minus')}</button><span style="min-width:28px;text-align:center;align-self:center">${l.quantite}</span><button class="icon-btn" data-p="${i}">${ic('plus')}</button><button class="icon-btn" data-s="${i}" style="color:var(--danger)">${ic('trash')}</button></div></div>`).join('');
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
  adminShell('credits', 'Crédits clients', `<button class="btn block" id="nouvelle-credit">${ic('plus')} Nouvelle vente à crédit</button><div id="cc"><div class="spinner"></div></div>`);
  $('#nouvelle-credit').onclick = adminVenteCredit;
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
      ${d.client.telephone ? `<button class="btn block secondary" id="wa-cli">${ic('message')} Rappeler sur WhatsApp</button>` : ''}
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
/* Ouvre WhatsApp avec un message, en laissant choisir le destinataire (ex. DL-Service). */
function partagerWhatsApp(texte) { window.open('https://wa.me/?text=' + encodeURIComponent(texte), '_blank'); }

/* ---- Admin : stock bas à recommander (chez DL-Service) ---- */
async function adminStockBas() {
  adminShell('produits', 'Stock bas — à recommander', `<div id="sb"><div class="spinner"></div></div>`);
  try {
    const d = await api('GET', '/admin/produits?stock_bas=1');
    const items = d.data || [];
    const liste = items.map(p => '- ' + p.nom + ' (reste ' + p.stock + ')').join('\n');
    $('#sb').innerHTML = `
      <button class="btn block secondary" id="retour-prod">${ic('back')} Retour aux produits</button>
      <p class="muted" style="font-size:.85rem">Articles dont le stock est bas — à recommander chez DL-Service (Bamako).</p>
      <button class="btn block" id="wa">${ic('message')} Envoyer la liste sur WhatsApp</button>
      <div class="card">${items.length ? items.map(p => `<div class="list-item"><div class="main"><div class="name">${esc(p.nom)}</div><div class="sub">${esc(p.categorie || '')} · reste ${p.stock} (seuil ${p.seuil_alerte})</div></div><button class="btn" style="width:auto;padding:6px 10px" data-appro="${p.id}">${ic('plus',14)} stock</button></div>`).join('') : '<p class="muted">Aucun produit en stock bas.</p>'}</div>`;
    $('#retour-prod').onclick = adminProduits;
    $('#wa').onclick = () => partagerWhatsApp('Bonjour DL-Service, commande MOK-Business :\n' + (liste || '(liste vide)'));
    $('#sb').querySelectorAll('[data-appro]').forEach(b => b.onclick = () => adminApprovisionner(+b.dataset.appro));
  } catch (ex) { $('#sb').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* Télécharge un fichier (CSV/Excel) en passant le jeton d'authentification. */
async function telechargerCSV(chemin, nomFichier) {
  try {
    const res = await fetch(API + chemin, { headers: { 'Authorization': 'Bearer ' + store.token } });
    if (!res.ok) throw new Error('Export impossible.');
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = nomFichier; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
    toast('Export téléchargé.');
  } catch (ex) { toast(ex.message); }
}

/* ---- Admin : toutes les ventes ---- */
async function adminVentes() {
  adminShell('ventes', 'Toutes les ventes', `
    <div class="card">
      <div class="field"><label>Période</label><select id="periode"><option value="jour">Aujourd'hui</option><option value="semaine">Cette semaine</option><option value="mois">Ce mois</option><option value="">Toutes</option></select></div>
      <div class="field"><label>Statut</label><select id="statut"><option value="">Tous</option><option value="payee">Payées</option><option value="a_credit">À crédit</option><option value="en_attente_paiement">En attente</option><option value="annulee">Annulées</option></select></div>
      <button class="btn secondary block" id="export">${ic('download')} Exporter en Excel</button>
    </div>
    <div id="liste"><div class="spinner"></div></div>`);
  $('#export').onclick = () => telechargerCSV('/admin/ventes/export?periode=' + $('#periode').value, 'ventes.csv');
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
          <div class="btn-row" style="width:auto">
            <button class="icon-btn" title="Facture" data-fact="${v.id}">${ic('receipt')}</button>
            ${v.statut !== 'annulee' && v.reste > 0 ? `<button class="icon-btn" title="Encaisser le reste" data-encaisser="${v.id}" data-reste="${v.reste}" style="color:var(--success,#16a34a)">${ic('banknote')}</button>` : ''}
            ${v.statut !== 'annulee' ? `<button class="icon-btn" title="Annuler (retour)" data-annuler="${v.id}" style="color:var(--danger)">${ic('undo')}</button>` : ''}
            ${v.latitude ? `<button class="icon-btn" title="Voir sur la carte" data-lat="${v.latitude}" data-lng="${v.longitude}">${ic('pin')}</button>` : ''}
          </div>
        </div>`).join('')}</div>` : `<p class="muted center mt">Aucune vente.</p>`;
      $('#liste').querySelectorAll('[data-lat]').forEach(b => b.onclick = () => window.open('https://www.google.com/maps?q=' + b.dataset.lat + ',' + b.dataset.lng, '_blank'));
      $('#liste').querySelectorAll('[data-fact]').forEach(b => b.onclick = () => voirFacture(+b.dataset.fact, adminVentes));
      $('#liste').querySelectorAll('[data-annuler]').forEach(b => b.onclick = async () => {
        const motif = prompt('Annuler cette vente ? Indiquez la raison (les articles reviennent en stock) :');
        if (motif === null) return; // l'administrateur a annulé l'action
        try { await api('POST', '/admin/ventes/' + b.dataset.annuler + '/annuler', { motif }); toast('Vente annulée, stock remis.'); rendre(); }
        catch (ex) { toast(ex.premierDetail()); }
      });
      $('#liste').querySelectorAll('[data-encaisser]').forEach(b => b.onclick = async () => {
        const reste = +b.dataset.reste;
        const saisie = prompt('Reste à payer : ' + gnf(reste) + '\nMontant reçu du client (GNF) :', reste);
        if (saisie === null) return; // l'administrateur a annulé l'action
        const montant = parseInt(String(saisie).replace(/\D/g, ''), 10);
        if (!montant || montant < 1) { toast('Montant invalide.'); return; }
        try {
          const r = await api('POST', '/admin/ventes/' + b.dataset.encaisser + '/encaisser', { montant });
          toast(r.reste > 0 ? 'Paiement enregistré. Reste ' + gnf(r.reste) : 'Payé entièrement');
          rendre();
        } catch (ex) { toast(ex.premierDetail()); }
      });
    } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  $('#periode').onchange = rendre; $('#statut').onchange = rendre; rendre();
}

/* ---- Admin : rapport de fin de journée ---- */
const MODE_LBL = { especes: 'Espèces', orange_money: 'Orange Money', carte: 'Carte', credit: 'Crédit' };
async function adminRapportJour() {
  adminShell('rapport', 'Rapport du jour', `
    <div class="card"><div class="field"><label>Date</label><input type="date" id="rdate" value="${new Date().toISOString().slice(0, 10)}"></div></div>
    <div id="r"><div class="spinner"></div></div>`);
  const glc = (icone, val, lbl) => `<div class="glance"><span class="g-emoji">${icTile(icone, 34)}</span><span class="g-val">${val}</span><span class="g-lbl">${lbl}</span></div>`;
  const rendre = async () => {
    $('#r').innerHTML = '<div class="spinner"></div>';
    try {
      const d = await api('GET', '/admin/rapport-jour?date=' + $('#rdate').value);
      const v = d.ventes, c = d.caisse;
      let html = `<div class="glance-grid">
        ${glc('receipt', v.nombre, 'Ventes')}
        ${glc('banknote', gnf(v.total_encaisse), 'Encaissé')}
        ${glc('tag', gnf(v.total_vendu), 'Vendu')}
        ${glc('clock', gnf(v.total_reste), 'Reste dû')}
      </div>`;
      html += `<div class="card"><h3>Encaissé par mode</h3>${v.total_encaisse || d.par_mode.length ? d.par_mode.map(m => `<div class="info-row"><span class="lbl">${MODE_LBL[m.mode] || m.mode} (${m.nombre})</span><span class="val">${gnf(m.encaisse)}</span></div>`).join('') : '<p class="muted">Aucune vente.</p>'}
        <div class="info-row"><span class="lbl">Ventes à crédit accordées</span><span class="val">${v.credits_accordes}</span></div>
        <div class="info-row"><span class="lbl">Ventes annulées</span><span class="val">${v.annulees}</span></div></div>`;
      html += `<div class="card"><h3>Caisse</h3>
        <div class="info-row"><span class="lbl">Fond d'ouverture</span><span class="val">${gnf(c.fond_ouverture)}</span></div>
        <div class="info-row"><span class="lbl">Espèces reçues</span><span class="val">${gnf(c.ventes_especes)}</span></div>
        <div class="info-row"><span class="lbl">Apports / Dépenses / Retraits</span><span class="val">${gnf(c.apports)} / ${gnf(c.depenses)} / ${gnf(c.retraits)}</span></div>
        <div class="info-row"><span class="lbl">Solde théorique</span><span class="val">${gnf(c.solde_theorique)}</span></div>
        ${c.cloturee ? `<div class="info-row"><span class="lbl">Compté (réel)</span><span class="val">${gnf(c.montant_reel)}</span></div><div class="info-row"><span class="lbl">Écart</span><span class="val" style="color:${c.ecart === 0 ? 'var(--success)' : 'var(--danger)'}">${gnf(c.ecart)}</span></div>` : '<p class="muted">Caisse pas encore clôturée.</p>'}</div>`;
      html += `<div class="card"><h3>Top produits du jour</h3>${d.top_produits.length ? d.top_produits.map(p => `<div class="info-row"><span class="lbl">${esc(p.nom)} ×${p.quantite}</span><span class="val">${gnf(p.montant)}</span></div>`).join('') : '<p class="muted">Aucune vente.</p>'}</div>`;
      html += `<div class="card"><h3>Par vendeur</h3>${d.par_vendeur.length ? d.par_vendeur.map(x => `<div class="info-row"><span class="lbl">${esc(x.vendeur || '—')} (${x.nombre})</span><span class="val">${gnf(x.total)}</span></div>`).join('') : '<p class="muted">Aucune vente.</p>'}</div>`;
      html += `<button class="btn block secondary" id="rwa">${ic('message')} Envoyer le résumé sur WhatsApp</button>`;
      $('#r').innerHTML = html;
      $('#rwa').onclick = () => partagerWhatsApp(resumeRapportTexte(d));
    } catch (ex) { $('#r').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  $('#rdate').onchange = rendre; rendre();
}
function resumeRapportTexte(d) {
  const v = d.ventes, c = d.caisse;
  let t = `MOK-Business — Rapport du ${d.date}\n`;
  t += `Ventes : ${v.nombre} · Encaissé : ${gnf(v.total_encaisse)} · Vendu : ${gnf(v.total_vendu)}\n`;
  t += `Reste dû : ${gnf(v.total_reste)} · Crédits : ${v.credits_accordes} · Annulées : ${v.annulees}\n`;
  d.par_mode.forEach(m => { t += `- ${MODE_LBL[m.mode] || m.mode} : ${gnf(m.encaisse)}\n`; });
  t += `Caisse : espèces ${gnf(c.ventes_especes)} · solde théorique ${gnf(c.solde_theorique)}`;
  if (c.cloturee) t += ` · écart ${gnf(c.ecart)}`;
  return t;
}

/* ---- Admin : tous les restes à payer dus à la boutique ---- */
async function adminRestes() {
  adminShell('restes', 'Restes à payer', `<div id="r"><div class="spinner"></div></div>`);
  const rendre = async () => {
    try {
      const d = await api('GET', '/admin/restes-a-payer');
      let html = `<div class="card center"><p class="muted">Total dû à la boutique</p>
        <div class="value" style="font-size:1.6rem;color:var(--danger)">${gnf(d.total_du)}</div>
        <p class="muted">${d.nombre_clients} client(s) qui doivent</p></div>`;
      html += d.clients.length ? d.clients.map(c => `
        <div class="card">
          <div class="row-between"><div><b>${esc(c.nom)}</b>
            <div class="muted" style="font-size:.85rem">${c.telephone ? esc(c.telephone) + ' · ' : ''}reste <b style="color:var(--danger)">${gnf(c.total_reste)}</b></div></div>
            ${c.telephone ? `<button class="btn secondary" style="width:auto;padding:6px 10px" data-wa="${esc(c.telephone)}" data-nom="${esc(c.nom)}" data-reste="${c.total_reste}">${ic('message')}</button>` : ''}
          </div>
          ${c.ventes.map(x => `<div class="list-item"><div class="main"><div class="name">${esc(x.reference)}</div>
            <div class="sub">${esc(x.date)} · payé ${gnf(x.montant_paye)} · reste ${gnf(x.reste)}</div></div>
            <button class="icon-btn" title="Encaisser le reste" data-enc="${x.id}" data-reste="${x.reste}" style="color:var(--success,#16a34a)">${ic('banknote')}</button></div>`).join('')}
        </div>`).join('') : '<p class="muted center mt">Personne ne doit d\'argent.</p>';
      $('#r').innerHTML = html;
      $('#r').querySelectorAll('[data-wa]').forEach(b => b.onclick = () => ouvrirWhatsApp(b.dataset.wa, 'Bonjour ' + b.dataset.nom + ', votre reste à payer chez MOK-Business est de ' + gnf(+b.dataset.reste) + '. Merci de passer régler.'));
      $('#r').querySelectorAll('[data-enc]').forEach(b => b.onclick = async () => {
        const reste = +b.dataset.reste;
        const saisie = prompt('Reste à payer : ' + gnf(reste) + '\nMontant reçu du client (GNF) :', reste);
        if (saisie === null) return;
        const montant = parseInt(String(saisie).replace(/\D/g, ''), 10);
        if (!montant || montant < 1) { toast('Montant invalide.'); return; }
        try { const r = await api('POST', '/admin/ventes/' + b.dataset.enc + '/encaisser', { montant }); toast(r.reste > 0 ? 'Enregistré. Reste ' + gnf(r.reste) : 'Payé entièrement'); rendre(); }
        catch (ex) { toast(ex.premierDetail()); }
      });
    } catch (ex) { $('#r').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  rendre();
}

/* ---- Admin : gestion de caisse ---- */
const TYPE_CAISSE = { apport: 'Apport', depense: 'Dépense', retrait: 'Retrait' };
async function adminCaisse() {
  adminShell('caisse', 'Caisse du jour', `<div id="c"><div class="spinner"></div></div>`);
  const glc = (icone, val, lbl, alerte) => `<div class="glance ${alerte ? 'alerte' : ''}"><span class="g-emoji">${icTile(icone, 34)}</span><span class="g-val">${val}</span><span class="g-lbl">${lbl}</span></div>`;
  const rendre = async () => {
    try {
      const d = await api('GET', '/admin/caisse');
      let html = '';

      // 1. En-tête selon l'état de la caisse
      if (d.cloturee) {
        const mot = d.ecart === 0 ? 'Caisse juste' : (d.ecart > 0 ? 'Excédent' : 'Manquant');
        const couleur = d.ecart === 0 ? 'var(--success)' : 'var(--danger)';
        html += `<div class="card center">
          <h3>Caisse clôturée</h3>
          <div class="info-row"><span class="lbl">Solde théorique (attendu)</span><span class="val">${gnf(d.solde_theorique)}</span></div>
          <div class="info-row"><span class="lbl">Argent compté (réel)</span><span class="val">${gnf(d.montant_reel)}</span></div>
          <div class="value" style="font-size:1.5rem;color:${couleur};margin-top:8px">${mot}${d.ecart !== 0 ? ' : ' + gnf(Math.abs(d.ecart)) : ''}</div>
          ${d.note_cloture ? `<p class="muted mt">${esc(d.note_cloture)}</p>` : ''}</div>`;
      } else if (!d.ouverture_faite) {
        html += `<div class="card"><h3>Ouvrir la caisse (matin)</h3>
          <p class="muted" style="font-size:.85rem">Indiquez le <b>fond de caisse</b> : l'argent déjà présent dans le tiroir ce matin.</p>
          ${champ('o-fond', 'Fond de caisse (GNF)', '0', 'number')}
          <div class="error-msg" id="o-err"></div>
          <button class="btn" id="o-btn">Ouvrir la caisse</button></div>`;
      }

      // 2. Résumé (toujours affiché)
      html += `<div class="card"><h3>Solde théorique : ${gnf(d.solde_theorique)}</h3>
        <div class="glance-grid">
          ${glc('key', gnf(d.fond_ouverture), "Fond d'ouverture")}
          ${glc('cart', gnf(d.ventes_especes), 'Ventes espèces')}
          ${glc('plus', gnf(d.apports), 'Apports')}
          ${glc('trendDown', gnf(d.depenses), 'Dépenses', d.depenses > 0)}
          ${glc('bank', gnf(d.retraits), 'Retraits')}
        </div></div>`;

      // 3. Mouvements + clôture (seulement si pas encore clôturée)
      if (!d.cloturee) {
        html += `<div class="card"><h3>Ajouter un mouvement</h3>
          <div class="field"><label>Type</label><select id="m-type">
            <option value="depense">Dépense (sortie d'argent)</option>
            <option value="apport">Apport (argent ajouté en caisse)</option>
            <option value="retrait">Retrait / dépôt banque (sortie)</option></select></div>
          ${champ('m-montant', 'Montant (GNF)', '', 'number')}
          ${champ('m-desc', 'Description (ex. achat scotch, dépôt banque)')}
          <div class="error-msg" id="m-err"></div>
          <button class="btn" id="m-add">Enregistrer le mouvement</button></div>`;

        if (d.ouverture_faite) {
          html += `<div class="card"><h3>Clôturer la caisse (soir)</h3>
            <p class="muted" style="font-size:.85rem">Comptez l'argent réel dans le tiroir et saisissez-le. L'application calcule l'écart (manquant ou excédent).</p>
            ${champ('cl-reel', 'Argent compté dans le tiroir (GNF)', '', 'number')}
            ${champ('cl-note', 'Note (facultatif)')}
            <div class="error-msg" id="cl-err"></div>
            <button class="btn danger" id="cl-btn">Clôturer la caisse</button></div>`;
        }
      }

      // 4. Liste des mouvements
      html += `<div class="card"><h3>Mouvements d'aujourd'hui</h3>${d.mouvements.length ? d.mouvements.map(m => `
        <div class="list-item"><div class="main"><div class="name">${TYPE_CAISSE[m.type] || m.type} · ${gnf(m.montant)}</div>
          <div class="sub">${esc(m.description || '')}${m.par ? ' · ' + esc(m.par) : ''} · ${esc(m.date)}</div></div></div>`).join('') : '<p class="muted">Aucun mouvement manuel aujourd\'hui.</p>'}</div>`;

      $('#c').innerHTML = html;

      if ($('#o-btn')) $('#o-btn').onclick = async () => {
        const err = $('#o-err'); err.textContent = '';
        const fond = parseInt($('#o-fond').value, 10);
        if (isNaN(fond) || fond < 0) { err.textContent = 'Montant invalide.'; return; }
        try { await api('POST', '/admin/caisse/ouvrir', { fond_ouverture: fond }); toast('Caisse ouverte.'); rendre(); }
        catch (ex) { err.textContent = ex.premierDetail(); }
      };
      if ($('#m-add')) $('#m-add').onclick = async () => {
        const err = $('#m-err'); err.textContent = '';
        const montant = parseInt($('#m-montant').value, 10);
        if (!montant || montant < 1) { err.textContent = 'Montant invalide.'; return; }
        try { await api('POST', '/admin/caisse/mouvement', { type: $('#m-type').value, montant, description: $('#m-desc').value.trim() || null }); toast('Mouvement enregistré.'); rendre(); }
        catch (ex) { err.textContent = ex.premierDetail(); }
      };
      if ($('#cl-btn')) $('#cl-btn').onclick = async () => {
        const err = $('#cl-err'); err.textContent = '';
        const reel = parseInt($('#cl-reel').value, 10);
        if (isNaN(reel) || reel < 0) { err.textContent = 'Saisissez l\'argent compté.'; return; }
        if (!confirm('Clôturer la caisse du jour ? On ne pourra plus ajouter de mouvement aujourd\'hui.')) return;
        try { const r = await api('POST', '/admin/caisse/cloturer', { montant_reel: reel, note: $('#cl-note').value.trim() || null }); toast('Caisse clôturée. Écart : ' + gnf(r.ecart)); rendre(); }
        catch (ex) { err.textContent = ex.premierDetail(); }
      };
    } catch (ex) { $('#c').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  rendre();
}

/* ---- Admin : clients + historique d'achats ---- */
async function adminClients() {
  adminShell('clients', 'Clients', `<div class="field"><input id="q" placeholder="Rechercher un client (nom, téléphone)…"></div><div id="liste"><div class="spinner"></div></div>`);
  const rendre = async (q = '') => {
    try {
      const d = await api('GET', '/clients' + (q ? '?recherche=' + encodeURIComponent(q) : ''));
      const items = d.data || [];
      $('#liste').innerHTML = items.length ? `<div class="card">${items.map(c => `
        <div class="list-item tap" data-id="${c.id}" role="button" tabindex="0"><div class="main"><div class="name">${esc(c.prenom)} ${esc(c.nom)}</div><div class="sub">${esc(c.telephone)}${c.ville ? ' · ' + esc(c.ville) : ''}</div></div><span class="badge cat">Voir</span></div>`).join('')}</div>` : '<p class="muted center mt">Aucun client. Les vendeurs et conseillers les enregistrent.</p>';
      $('#liste').querySelectorAll('.tap').forEach(el => el.onclick = () => adminClientHistorique(+el.dataset.id));
    } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
  };
  let t; $('#q').addEventListener('input', e => { clearTimeout(t); t = setTimeout(() => rendre(e.target.value.trim()), 300); });
  rendre();
}
async function adminClientHistorique(id) {
  cadre('Client', `<div id="h"><div class="spinner"></div></div>`, null, null, { retour: adminClients });
  try {
    const d = await api('GET', '/admin/clients/' + id + '/historique');
    const c = d.client;
    $('#h').innerHTML = `
      <div class="card"><h3>${esc(c.prenom)} ${esc(c.nom)}</h3>
        <p class="muted">${esc(c.telephone || '')}${c.email ? ' · ' + esc(c.email) : ''}<br>${esc(c.ville || '')} ${esc(c.pays || '')}</p>
        <div class="row-between"><span>Achats : <b>${d.nombre_achats}</b></span><span>Total : <b>${gnf(d.total_achats)}</b></span></div></div>
      ${c.telephone ? `<button class="btn block secondary" id="wa">${ic('message')} WhatsApp</button>` : ''}
      <div class="card"><h3>Historique d'achats</h3>${d.ventes.length ? d.ventes.map(v => `
        <div class="list-item"><div class="main"><div class="name">${esc(v.reference)} — ${gnf(v.montant_total)}</div>
          <div class="sub">${esc(v.date)} · ${esc(v.statut)}<br>${esc(v.articles.join(', '))}</div></div></div>`).join('') : '<p class="muted">Aucun achat.</p>'}</div>`;
    if (c.telephone) $('#wa').onclick = () => ouvrirWhatsApp(c.telephone, 'Bonjour ' + c.prenom + ', ');
  } catch (ex) { $('#h').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
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

/* ================================================================  DEVIS & FACTURE  */

/* Document imprimable (devis ou facture). */
function docImprimable(data, estDevis) {
  const b = data.boutique || {};
  return `<div class="doc" id="doc">
    <div class="doc-head">
      <div class="doc-brand">
        <svg class="doc-emblem" viewBox="0 0 100 100" aria-hidden="true"><rect width="100" height="100" rx="22" fill="#1e3a8a"/><text x="50" y="52" dy=".35em" text-anchor="middle" font-family="system-ui, Arial, sans-serif" font-size="40" font-weight="800" fill="#fff">MB</text></svg>
        <div><div class="doc-shop">${esc(b.nom || '')}</div>
          <div class="doc-shop-info">${esc(b.ville || '')}<br>Tél : ${esc(b.telephone || '')}<br>${esc(b.email || '')}${b.rccm ? '<br>RCCM : ' + esc(b.rccm) : ''}${b.nif ? '<br>NIF : ' + esc(b.nif) : ''}</div></div>
      </div>
      <div class="doc-meta"><div class="doc-type">${estDevis ? 'DEVIS' : 'FACTURE'}</div>
        <div>N° ${esc(data.reference)}</div><div>${esc((data.date || '').slice(0, 10))}</div></div>
    </div>
    <div class="doc-client"><b>Client :</b> ${esc(data.client || '')}${data.client_tel ? '<br>Tél : ' + esc(data.client_tel) : ''}${data.client_email ? '<br>Email : ' + esc(data.client_email) : ''}${data.client_ville ? '<br>Ville : ' + esc(data.client_ville) : ''}</div>
    <table class="doc-table">
      <thead><tr><th>Désignation</th><th>Qté</th><th>Prix unit.</th><th>Total</th></tr></thead>
      <tbody>${(data.lignes || []).map(l => `<tr><td>${esc(l.designation)}</td><td>${l.quantite}</td><td>${gnf(l.prix_unitaire)}</td><td>${gnf(l.sous_total)}</td></tr>`).join('')}</tbody>
    </table>
    <div class="doc-total">TOTAL : ${gnf(data.montant_total)}</div>
    ${!estDevis && data.reste > 0 ? `<div class="doc-pay">Payé : ${gnf(data.montant_paye)} — Reste à payer : <b>${gnf(data.reste)}</b></div>` : ''}
    <div class="doc-pay">Paiement : Orange Money <b>${esc(b.orange_money || '')}</b> · Compte bancaire <b>${esc(b.banque || '')}</b></div>
    ${estDevis && data.valide_jusqu ? `<div class="doc-pay">Valable jusqu'au ${esc(data.valide_jusqu)}</div>` : ''}
    ${data.note ? `<div class="doc-note">${esc(data.note)}</div>` : ''}
    <div class="doc-foot">Merci de votre confiance — ${esc(b.nom || '')}</div>
  </div>`;
}
function afficherDocument(titre, data, estDevis, retour, actionsHTML = '') {
  cadre(titre, `
    <div class="no-print toolbar-doc">
      <button class="btn" id="imprimer">${ic('printer')} Imprimer (A4)</button>
      <button class="btn secondary" id="ticket">${ic('receipt')} Ticket court</button>
      ${actionsHTML}
    </div>
    ${docImprimable(data, estDevis)}`, null, null, { retour });
  $('#imprimer').onclick = () => { $('#doc').classList.remove('ticket'); window.print(); };
  $('#ticket').onclick = () => { $('#doc').classList.add('ticket'); window.print(); setTimeout(() => $('#doc').classList.remove('ticket'), 800); };
}

/* ---- Admin : liste des devis ---- */
async function adminDevis() {
  adminShell('devis', 'Devis', `<button class="btn block" id="nouveau">${ic('plus')} Nouveau devis</button><div id="liste"><div class="spinner"></div></div>`);
  $('#nouveau').onclick = () => formDevis(adminDevis);
  try {
    const d = await api('GET', '/devis');
    const items = d.data || [];
    $('#liste').innerHTML = items.length ? `<div class="card">${items.map(x => `
      <div class="list-item tap" data-id="${x.id}" role="button" tabindex="0">
        <div class="main"><div class="name">${esc(x.reference)} — ${gnf(x.montant_total)}</div>
          <div class="sub">${esc(x.client)} · ${esc(x.date)} · ${esc(x.statut)}</div></div>
        <span class="badge cat">Voir</span></div>`).join('')}</div>` : '<p class="muted center mt">Aucun devis.</p>';
    $('#liste').querySelectorAll('.tap').forEach(el => el.onclick = () => voirDevis(+el.dataset.id, adminDevis));
  } catch (ex) { $('#liste').innerHTML = `<p class="muted center mt">${esc(ex.message)}</p>`; }
}

/* ---- Formulaire de devis (vendeur ou admin) ---- */
let devisLignes = [];
function formDevis(retour) {
  devisLignes = [];
  cadre('Nouveau devis', `
    <div class="card">
      ${champ('dv-client', 'Nom du client')}
      <div class="field"><label>Ajouter un article du catalogue</label><input id="dv-search" placeholder="Rechercher…"></div>
      <div id="dv-res"></div>
      <button class="btn secondary block" id="dv-libre">${ic('plus')} Ajouter une ligne libre</button>
    </div>
    <div class="card"><h3>Lignes du devis</h3><div id="dv-lignes"><p class="muted">Aucune ligne.</p></div>
      <div class="info-row" style="padding-top:12px"><span class="lbl">Total</span><span class="val" id="dv-total">0 GNF</span></div></div>
    <div class="card">${champ('dv-note', 'Note (facultatif)')}
      ${champ('dv-valide', "Valable jusqu'au (facultatif)", '', 'date')}
      <div class="error-msg" id="dv-err"></div>
      <button class="btn" id="dv-save">Créer le devis</button></div>`, null, null, { retour });
  let t; $('#dv-search').addEventListener('input', e => { clearTimeout(t); t = setTimeout(() => chercherProduitDevis(e.target.value.trim()), 300); });
  $('#dv-libre').onclick = () => { devisLignes.push({ designation: '', quantite: 1, prix_unitaire: 0, produit_id: null }); rendreDevisLignes(); };
  $('#dv-save').onclick = () => enregistrerDevis(retour);
  rendreDevisLignes();
}
async function chercherProduitDevis(q) {
  const el = $('#dv-res'); if (!el) return;
  if (!q) { el.innerHTML = ''; return; }
  try {
    const d = await api('GET', '/catalogue?recherche=' + encodeURIComponent(q));
    const items = (d.data || []).slice(0, 8);
    el.innerHTML = items.length ? `<div class="card" style="margin:6px 0">${items.map(p => `
      <div class="list-item tap" data-nom="${esc(p.nom)}" data-prix="${p.prix_unitaire || 0}" data-id="${p.id}" role="button" tabindex="0">
        <div class="main"><div class="name">${esc(p.nom)}</div><div class="sub">${gnf(p.prix_unitaire)}</div></div><span class="badge cat">+ Ajouter</span></div>`).join('')}</div>` : '<p class="muted">Aucun.</p>';
    el.querySelectorAll('.tap').forEach(x => x.onclick = () => {
      devisLignes.push({ designation: x.dataset.nom, quantite: 1, prix_unitaire: +x.dataset.prix, produit_id: +x.dataset.id });
      el.innerHTML = ''; $('#dv-search').value = ''; rendreDevisLignes();
    });
  } catch (ex) { el.innerHTML = `<p class="muted">${esc(ex.message)}</p>`; }
}
function rendreDevisLignes() {
  const el = $('#dv-lignes'); if (!el) return;
  if (!devisLignes.length) { el.innerHTML = '<p class="muted">Aucune ligne. Ajoutez un article ou une ligne libre.</p>'; $('#dv-total').textContent = '0 GNF'; return; }
  el.innerHTML = devisLignes.map((l, i) => `
    <div class="card" style="padding:10px;margin-bottom:8px">
      <input data-f="designation" data-i="${i}" value="${esc(l.designation)}" placeholder="Désignation" style="margin-bottom:6px">
      <div class="btn-row">
        <input data-f="quantite" data-i="${i}" type="number" value="${l.quantite}" placeholder="Qté">
        <input data-f="prix_unitaire" data-i="${i}" type="number" value="${l.prix_unitaire}" placeholder="Prix">
        <button class="icon-btn" data-sup="${i}" style="color:var(--danger)">${ic('trash')}</button>
      </div>
      <div class="muted" style="font-size:.8rem;margin-top:4px">Sous-total : ${gnf((l.prix_unitaire || 0) * (l.quantite || 0))}</div>
    </div>`).join('');
  el.querySelectorAll('input').forEach(inp => inp.oninput = () => {
    const i = +inp.dataset.i, f = inp.dataset.f;
    devisLignes[i][f] = f === 'designation' ? inp.value : (parseInt(inp.value, 10) || 0);
    $('#dv-total').textContent = gnf(devisLignes.reduce((s, l) => s + (l.prix_unitaire || 0) * (l.quantite || 0), 0));
  });
  el.querySelectorAll('[data-sup]').forEach(b => b.onclick = () => { devisLignes.splice(+b.dataset.sup, 1); rendreDevisLignes(); });
  $('#dv-total').textContent = gnf(devisLignes.reduce((s, l) => s + (l.prix_unitaire || 0) * (l.quantite || 0), 0));
}
async function enregistrerDevis(retour) {
  const err = $('#dv-err'); err.textContent = '';
  const lignes = devisLignes.filter(l => l.designation && l.quantite > 0);
  if (!lignes.length) { err.textContent = 'Ajoutez au moins une ligne avec une désignation.'; return; }
  const corps = { client_nom: $('#dv-client').value.trim() || null, note: $('#dv-note').value.trim() || null, valide_jusqu: $('#dv-valide').value || null, lignes };
  try {
    const d = await api('POST', '/devis', corps);
    toast('Devis ' + d.reference + ' créé.');
    afficherDocument('Devis ' + d.reference, d, true, retour, `<button class="btn secondary" id="dv-conv">Convertir en vente</button>`);
    brancherConversion(d.id, retour);
  } catch (ex) { err.textContent = ex.premierDetail(); }
}
async function voirDevis(id, retour) {
  try {
    const d = await api('GET', '/devis/' + id);
    const conv = d.statut === 'converti' ? '' : `<button class="btn secondary" id="dv-conv">Convertir en vente</button>`;
    afficherDocument('Devis ' + d.reference, d, true, retour, conv);
    if (d.statut !== 'converti') brancherConversion(id, retour);
  } catch (ex) { toast(ex.premierDetail ? ex.premierDetail() : ex.message); }
}
function brancherConversion(id, retour) {
  const btn = $('#dv-conv'); if (!btn) return;
  btn.onclick = async () => {
    const mode = prompt('Mode de paiement : especes, orange_money ou carte ?', 'especes');
    if (!mode) return;
    try { const r = await api('POST', '/devis/' + id + '/convertir', { mode_paiement: mode }); toast('Vente créée : ' + r.reference_vente); retour(); }
    catch (ex) { toast(ex.premierDetail()); }
  };
}

/* Facture d'une vente. */
async function voirFacture(venteId, retour) {
  try {
    const d = await api('GET', '/ventes/' + venteId + '/facture');
    afficherDocument('Facture ' + d.reference, d, false, retour, '<button class="btn secondary" id="fact-wa">' + ic('message') + ' Envoyer au client</button>');
    const wa = $('#fact-wa'); if (wa) wa.onclick = () => envoyerFactureWhatsApp(d);
  } catch (ex) { toast(ex.premierDetail ? ex.premierDetail() : ex.message); }
}
/* Construit un ticket texte lisible et l'ouvre dans WhatsApp (au client si son numéro est connu). */
function envoyerFactureWhatsApp(d) {
  const b = d.boutique || {};
  let t = `${b.nom || 'MOK-Business'}\nFacture ${d.reference} (${(d.date || '').slice(0, 10)})\n`;
  if (d.client) t += `Client : ${d.client}\n`;
  t += '--------------------\n';
  (d.lignes || []).forEach(l => { t += `${l.designation} x${l.quantite} = ${gnf(l.sous_total)}\n`; });
  t += '--------------------\n';
  t += `TOTAL : ${gnf(d.montant_total)}\n`;
  t += d.reste > 0 ? `Payé : ${gnf(d.montant_paye)} — Reste à payer : ${gnf(d.reste)}\n` : `Payé entièrement\n`;
  if (b.orange_money) t += `Paiement Orange Money : ${b.orange_money}\n`;
  t += 'Merci de votre confiance.';
  if (d.client_tel) ouvrirWhatsApp(d.client_tel, t); else partagerWhatsApp(t);
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

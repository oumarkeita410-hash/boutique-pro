<?php

return [
    'nom' => 'MOK-Business',
    'devise' => 'GNF',

    // Au-delà de ce montant dû par un réparateur, l'administrateur est alerté.
    'seuil_dette_reparateur' => (int) env('SEUIL_DETTE_REPARATEUR', 500000),

    // Coordonnées de la boutique (affichées, jamais secrètes).
    'contact' => [
        'email' => env('BOUTIQUE_EMAIL', 'oumarkeita410@gmail.com'),
        'telephone' => env('BOUTIQUE_TELEPHONE', '+224621952252'),
        'whatsapp' => env('BOUTIQUE_WHATSAPP', '+224621952252'),
        'ville' => env('BOUTIQUE_VILLE', 'Conakry, Guinée'),
        'orange_money' => env('OM_NUMERO', '621952252'),
        'banque' => env('BOUTIQUE_BANQUE', '916 507 906'),
        'rccm' => env('BOUTIQUE_RCCM'),  // numéro registre du commerce (affiché si renseigné)
        'nif' => env('BOUTIQUE_NIF'),    // numéro d'identification fiscale (affiché si renseigné)
    ],

    // --- Orange Money Guinée (API marchand) ---
    // TOUTES ces valeurs vont dans le fichier .env du serveur, jamais dans le code.
    // Le numéro de compte marchand et les clés ne doivent JAMAIS être versionnés.
    'orange_money' => [
        // Numéro Orange Money personnel de la boutique (paiement manuel, affiché au vendeur).
        'numero' => env('OM_NUMERO', '621952252'),
        // 'manuel' (compte personnel) ou 'marchand' (API, nécessite un compte marchand).
        'mode' => env('OM_MODE', 'manuel'),
        'base_url' => env('OM_BASE_URL', 'https://api.orange.com'),
        'client_id' => env('OM_CLIENT_ID'),
        'client_secret' => env('OM_CLIENT_SECRET'),
        'merchant_key' => env('OM_MERCHANT_KEY'),
        'devise' => env('OM_DEVISE', 'GNF'),
        // URL où Orange renvoie le client, et URL serveur-à-serveur (webhook) :
        'return_url' => env('OM_RETURN_URL'),
        'cancel_url' => env('OM_CANCEL_URL'),
        'notif_url' => env('OM_NOTIF_URL'),
    ],

    // --- Carte bancaire via agrégateur (optionnel) ---
    'carte' => [
        'fournisseur' => env('CARTE_FOURNISSEUR'), // paydunya, cinetpay, stripe…
        'api_key' => env('CARTE_API_KEY'),
        'api_secret' => env('CARTE_API_SECRET'),
    ],

    // --- WhatsApp Business (Meta Cloud API) ---
    'whatsapp' => [
        'base_url' => env('WA_BASE_URL', 'https://graph.facebook.com/v21.0'),
        'phone_number_id' => env('WA_PHONE_NUMBER_ID'),
        'token' => env('WA_TOKEN'),
        'actif' => (bool) env('WA_ACTIF', false),
    ],
];

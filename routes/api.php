<?php

use App\Http\Controllers\Admin\CaisseController;
use App\Http\Controllers\Admin\CreditClientController;
use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\LocalisationController;
use App\Http\Controllers\Admin\PaieController;
use App\Http\Controllers\Admin\ProduitController;
use App\Http\Controllers\Admin\ReparateurController;
use App\Http\Controllers\Admin\TableauDeBordController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\Admin\VenteController as AdminVenteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DevisController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\HoraireController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ModificationVenteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\ReparateurOperationController;
use App\Http\Controllers\VenteController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

// 5 essais par minute : bloque les tentatives de deviner un mot de passe.
Route::post('/connexion', [AuthController::class, 'connexion'])->middleware('throttle:6,1');

// Liste des comptes d'un rôle pour l'écran de connexion (nom + identifiant ; mot de passe requis).
Route::get('/comptes-connexion', [AuthController::class, 'comptesConnexion'])->middleware('throttle:30,1');

// Webhook Orange Money : appelé par les serveurs d'Orange, pas par un navigateur.
// Pas d'authentification de session ; la confiance vient de la vérification dans le service.
Route::post('/webhooks/orange-money', [PaiementController::class, 'webhookOrangeMoney']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/deconnexion', [AuthController::class, 'deconnexion']);

    // Tous les comptes actifs (admin, vendeur, conseiller).
    Route::middleware('role:admin,vendeur,conseiller')->group(function () {
        Route::get('/moi', [AuthController::class, 'moi']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/lue', [NotificationController::class, 'marquerLue']);

        Route::get('/messages', [MessageController::class, 'monFil']);
        Route::post('/messages', [MessageController::class, 'envoyer'])->middleware('throttle:10,1');

        Route::get('/mon-horaire', [HoraireController::class, 'moi']);

        Route::get('/whatsapp/contact', [WhatsAppController::class, 'contact']);

        // Clients : saisis par vendeurs et conseillers. Modification : admin seul (plus bas).
        Route::get('/clients', [ClientController::class, 'index']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::post('/clients', [ClientController::class, 'store']);
    });

    Route::middleware(['role:vendeur,admin', 'throttle:60,1'])->group(function () {
        Route::get('/catalogue', [CatalogueController::class, 'index']);
        Route::post('/ventes', [VenteController::class, 'store']);

        // Paiement de la vente.
        Route::get('/ventes/{vente}/instructions-paiement', [PaiementController::class, 'instructions']);
        Route::post('/ventes/{vente}/reference-paiement', [PaiementController::class, 'enregistrerReference']);
        Route::get('/ventes/{vente}/statut-paiement', [PaiementController::class, 'statut']);
        // Orange Money API marchand (si un jour un compte marchand est ouvert).
        Route::post('/ventes/{vente}/payer-orange-money', [PaiementController::class, 'orangeMoney']);

        // Modifier une vente = demande soumise à l'accord de l'administrateur.
        Route::post('/ventes/{vente}/demande-modification', [ModificationVenteController::class, 'demander']);

        // Mes ventes (argent payé / reste à payer) — le vendeur voit les siennes.
        Route::get('/mes-ventes', [VenteController::class, 'mesVentes']);

        // Facture imprimable d'une vente.
        Route::get('/ventes/{vente}/facture', [FactureController::class, 'facture']);

        // Devis (proposition de prix). Créés par vendeur ou admin.
        Route::get('/devis', [DevisController::class, 'index']);
        Route::post('/devis', [DevisController::class, 'store']);
        Route::get('/devis/{devis}', [DevisController::class, 'show']);
        Route::post('/devis/{devis}/convertir', [DevisController::class, 'convertir']);
        Route::post('/devis/{devis}/statut', [DevisController::class, 'changerStatut']);

        Route::get('/reparateurs', [ReparateurOperationController::class, 'index']);
        Route::post('/reparateurs/{reparateur}/prises', [ReparateurOperationController::class, 'prise']);
        Route::post('/reparateurs/{reparateur}/paiements', [ReparateurOperationController::class, 'paiement']);
    });

    // La paie des vendeurs est entièrement réservée à l'administrateur (aucun accès vendeur).

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('tableau-de-bord', TableauDeBordController::class);

        // Toutes les ventes (admin voit tout).
        Route::get('ventes', [AdminVenteController::class, 'index']);
        Route::get('ventes-resume', [AdminVenteController::class, 'resume']);
        Route::get('ventes/export', [AdminVenteController::class, 'export']);
        Route::post('ventes/{vente}/annuler', [AdminVenteController::class, 'annuler']);

        // Historique d'achats d'un client.
        Route::get('clients/{client}/historique', [ClientController::class, 'historique']);

        // Journal des activités (trace de qui a fait quoi).
        Route::get('journal', [JournalController::class, 'index']);

        // Gestion de caisse (espèces).
        Route::get('caisse', [CaisseController::class, 'jour']);
        Route::post('caisse/mouvement', [CaisseController::class, 'ajouter']);
        Route::post('caisse/ouvrir', [CaisseController::class, 'ouvrir']);
        Route::post('caisse/cloturer', [CaisseController::class, 'cloturer']);

        Route::apiResource('utilisateurs', UtilisateurController::class)->except('destroy');
        Route::post('utilisateurs/{utilisateur}/verrouiller', [UtilisateurController::class, 'verrouiller']);
        Route::post('utilisateurs/{utilisateur}/deverrouiller', [UtilisateurController::class, 'deverrouiller']);
        Route::post('utilisateurs/{utilisateur}/reinitialiser-mot-de-passe', [UtilisateurController::class, 'reinitialiserMotDePasse']);
        Route::get('utilisateurs/{utilisateur}/horaires', [HoraireController::class, 'show']);
        Route::put('utilisateurs/{utilisateur}/horaires', [HoraireController::class, 'update']);
        Route::get('utilisateurs/{utilisateur}/paie', [PaieController::class, 'historique']);

        Route::get('localisation', [LocalisationController::class, 'index']);
        Route::get('localisation/{utilisateur}', [LocalisationController::class, 'historique']);

        Route::get('paie', [PaieController::class, 'index']);
        Route::post('paie', [PaieController::class, 'store']);
        Route::post('paie/tout', [PaieController::class, 'payerTout']);

        Route::get('messages', [MessageController::class, 'boite']);
        Route::get('messages/{utilisateur}', [MessageController::class, 'lire']);
        Route::post('messages/{utilisateur}', [MessageController::class, 'repondre']);

        Route::get('modifications-vente', [ModificationVenteController::class, 'index']);
        Route::post('modifications-vente/{demande}/approuver', [ModificationVenteController::class, 'approuver']);
        Route::post('modifications-vente/{demande}/refuser', [ModificationVenteController::class, 'refuser']);

        // Paiements Orange Money / carte à confirmer par l'administrateur.
        Route::get('paiements-a-confirmer', [PaiementController::class, 'aConfirmer']);
        Route::post('ventes/{vente}/confirmer-paiement', [PaiementController::class, 'confirmer']);

        // Crédits clients (vente à crédit + remboursements) — admin seulement.
        Route::get('credits', [CreditClientController::class, 'index']);
        Route::get('credits/{client}', [CreditClientController::class, 'show']);
        Route::post('credits/{client}/remboursement', [CreditClientController::class, 'rembourser']);

        Route::post('whatsapp/message', [WhatsAppController::class, 'envoyer']);

        Route::put('clients/{client}', [ClientController::class, 'update']);

        Route::apiResource('produits', ProduitController::class)->except('destroy');
        Route::post('produits/{produit}/approvisionner', [ProduitController::class, 'approvisionner']);

        Route::apiResource('reparateurs', ReparateurController::class)->except('destroy');
        // Modifier la dette d'un réparateur (ajouter / modifier / supprimer une ligne).
        Route::post('reparateurs/{reparateur}/dette', [ReparateurController::class, 'ajouterDette']);
        Route::put('prises/{prise}', [ReparateurController::class, 'modifierDette']);
        Route::delete('prises/{prise}', [ReparateurController::class, 'supprimerDette']);
    });
});

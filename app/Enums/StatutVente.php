<?php

namespace App\Enums;

enum StatutVente: string
{
    case EnAttentePaiement = 'en_attente_paiement';
    case ACredit = 'a_credit'; // vente à crédit, en partie ou pas encore remboursée
    case Payee = 'payee';
    case Annulee = 'annulee';
}

<?php

namespace App\Enums;

enum ModePaiement: string
{
    case Especes = 'especes';
    case OrangeMoney = 'orange_money';
    case Carte = 'carte';
    case Credit = 'credit'; // vente à crédit à un client — autorisée par l'administrateur seul
}

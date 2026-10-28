<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Vendeur = 'vendeur';
    case Conseiller = 'conseiller';
}

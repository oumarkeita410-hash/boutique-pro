<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglage modifiable par l'administrateur (clé/valeur).
 * Exemple : le numéro du SAV. Si aucune valeur n'est enregistrée,
 * on retombe sur la valeur par défaut (souvent celle du fichier .env).
 */
class Reglage extends Model
{
    protected $table = 'reglages';

    protected $fillable = ['cle', 'valeur'];

    /** Valeur enregistrée pour cette clé, ou la valeur par défaut si vide. */
    public static function valeur(string $cle, $defaut = null)
    {
        $r = static::where('cle', $cle)->first();

        return $r && $r->valeur !== null && $r->valeur !== '' ? $r->valeur : $defaut;
    }

    /** Enregistre (ou met à jour) la valeur d'une clé. */
    public static function definir(string $cle, $valeur): void
    {
        static::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
    }
}

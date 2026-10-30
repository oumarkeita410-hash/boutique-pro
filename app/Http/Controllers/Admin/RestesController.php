<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutVente;
use App\Http\Controllers\Controller;
use App\Models\Vente;

/**
 * Tous les restes à payer dus à la boutique, regroupés par client —
 * clients enregistrés ET clients simplement tapés à la main à la vente.
 */
class RestesController extends Controller
{
    /**
     * GET /api/admin/restes-a-payer
     */
    public function index()
    {
        $ventes = Vente::where('statut', StatutVente::ACredit)
            ->whereColumn('montant_paye', '<', 'montant_total')
            ->with(['client:id,nom,prenom,telephone', 'vendeur:id,name,prenom'])
            ->latest()
            ->get();

        $groupes = [];
        foreach ($ventes as $v) {
            $reste = $v->reste();
            if ($reste <= 0) {
                continue;
            }

            // Clé de regroupement : client enregistré, sinon nom/téléphone tapé à la main.
            if ($v->client) {
                $cle = 'c:'.$v->client->id;
                $nom = trim($v->client->prenom.' '.$v->client->nom) ?: 'Client';
                $tel = $v->client->telephone;
                $clientId = $v->client->id;
            } elseif (trim((string) $v->client_nom) !== '' || trim((string) $v->client_tel) !== '') {
                $nom = trim((string) $v->client_nom) ?: 'Client';
                $tel = $v->client_tel;
                $cle = 'l:'.mb_strtolower($nom).'|'.$tel;
                $clientId = null;
            } else {
                $cle = 'v:'.$v->id;
                $nom = 'Client non identifié';
                $tel = null;
                $clientId = null;
            }

            if (! isset($groupes[$cle])) {
                $groupes[$cle] = [
                    'client_id' => $clientId,
                    'nom' => $nom,
                    'telephone' => $tel,
                    'total_reste' => 0,
                    'nombre_ventes' => 0,
                    'ventes' => [],
                ];
            }

            $groupes[$cle]['total_reste'] += $reste;
            $groupes[$cle]['nombre_ventes']++;
            $groupes[$cle]['ventes'][] = [
                'id' => $v->id,
                'reference' => $v->reference,
                'date' => $v->created_at->toDateString(),
                'vendeur' => $v->vendeur ? trim($v->vendeur->prenom.' '.$v->vendeur->name) : null,
                'montant_total' => $v->montant_total,
                'montant_paye' => $v->montant_paye,
                'reste' => $reste,
            ];
        }

        $clients = array_values($groupes);
        usort($clients, fn ($a, $b) => $b['total_reste'] <=> $a['total_reste']);

        return response()->json([
            'total_du' => array_sum(array_column($clients, 'total_reste')),
            'nombre_clients' => count($clients),
            'clients' => $clients,
        ]);
    }
}

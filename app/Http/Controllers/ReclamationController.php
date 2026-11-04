<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Reclamation;
use App\Models\ReclamationInteraction;
use App\Models\Reglage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Service après-vente (SAV). Les conseillers (et l'admin) ouvrent et traitent
 * les réclamations ; ils ajoutent des appels/messages (résumé + note vocale).
 * L'administrateur voit tout et est le SEUL à pouvoir modifier ou supprimer.
 */
class ReclamationController extends Controller
{
    private const STATUTS = ['ouverte', 'en_cours', 'resolue', 'fermee'];
    private const TYPES = ['appel', 'message', 'note'];
    // Types de fichiers son acceptés pour une note vocale. On inclut les
    // variantes "video/*" car un fichier WebM/MP4 audio-seul est souvent
    // reconnu ainsi par l'inspection du contenu.
    private const AUDIO_MIMES = 'audio/webm,video/webm,audio/ogg,video/ogg,application/ogg,audio/mp4,video/mp4,audio/mpeg,audio/aac,audio/wav,audio/x-wav,audio/x-m4a';

    /** GET /api/reclamations : la liste (admin voit tout ; conseiller aussi). */
    public function index(Request $request)
    {
        $q = Reclamation::with([
            'client:id,prenom,nom,telephone',
            'conseiller:id,name,prenom',
            'creePar:id,name,prenom',
        ])->withCount('interactions')->latest();

        $statut = $request->query('statut');
        if (is_string($statut) && in_array($statut, self::STATUTS, true)) {
            $q->where('statut', $statut);
        }

        return response()->json([
            'data' => $q->limit(200)->get()->map(fn (Reclamation $r) => $this->presenter($r)),
            'sav_numero' => Reglage::valeur('sav_numero', config('boutique.sav.numero')),
            'ouvertes' => Reclamation::whereIn('statut', ['ouverte', 'en_cours'])->count(),
        ]);
    }

    /** POST /api/reclamations : ouvrir une réclamation (conseiller ou admin). */
    public function store(Request $request)
    {
        $d = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_nom' => ['nullable', 'string', 'max:150'],
            'client_tel' => ['nullable', 'string', 'max:30'],
            'vente_ref' => ['nullable', 'string', 'max:60'],
            'produit' => ['required', 'string', 'max:180'],
            'probleme' => ['required', 'string', 'max:2000'],
            'conseiller_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // On garde une copie du nom et du numéro du client (utile pour appeler,
        // et conservée même si la fiche client change plus tard).
        $nom = $d['client_nom'] ?? null;
        $tel = $d['client_tel'] ?? null;
        if (! empty($d['client_id'])) {
            $c = Client::find($d['client_id']);
            if ($c) {
                $nom = $nom ?: trim($c->prenom.' '.$c->nom);
                $tel = $tel ?: $c->telephone;
            }
        }

        if (! $nom && ! $tel) {
            abort(422, 'Indiquez au moins le nom ou le numéro du client.');
        }

        $user = $request->user();
        $r = Reclamation::create([
            'numero' => (int) Reclamation::max('numero') + 1,
            'client_id' => $d['client_id'] ?? null,
            'client_nom' => $nom,
            'client_tel' => $tel,
            'vente_ref' => $d['vente_ref'] ?? null,
            'produit' => $d['produit'],
            'probleme' => $d['probleme'],
            'statut' => 'ouverte',
            // Le conseiller qui ouvre la réclamation se l'attribue ; l'admin peut désigner.
            'conseiller_id' => $d['conseiller_id'] ?? ($user->role === Role::Conseiller ? $user->id : null),
            'cree_par' => $user->id,
        ]);

        return response()->json(
            $this->presenter($r->load(['client:id,prenom,nom,telephone', 'conseiller:id,name,prenom', 'creePar:id,name,prenom'])->loadCount('interactions')),
            201
        );
    }

    /** GET /api/reclamations/{reclamation} : une réclamation + son historique. */
    public function show(Reclamation $reclamation)
    {
        $reclamation->load([
            'client:id,prenom,nom,telephone',
            'conseiller:id,name,prenom',
            'creePar:id,name,prenom',
            'interactions' => fn ($q) => $q->with('auteur:id,name,prenom')->oldest(),
        ]);

        $data = $this->presenter($reclamation);
        $data['interactions'] = $reclamation->interactions->map(fn (ReclamationInteraction $i) => $this->presenterInteraction($reclamation, $i));

        return response()->json($data);
    }

    /** POST /api/reclamations/{reclamation}/interactions : ajouter un appel/message. */
    public function ajouterInteraction(Request $request, Reclamation $reclamation)
    {
        $d = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'resume' => ['nullable', 'string', 'max:2000'],
            'audio' => ['nullable', 'file', 'mimetypes:'.self::AUDIO_MIMES, 'max:10240'],
            'duree' => ['nullable', 'integer', 'min:0', 'max:7200'],
        ]);

        if (empty($d['resume']) && ! $request->hasFile('audio')) {
            abort(422, 'Ajoutez un résumé écrit ou une note vocale.');
        }

        $chemin = null;
        $type = null;
        if ($request->hasFile('audio')) {
            $f = $request->file('audio');
            $chemin = $f->store('reclamations', 'local');
            $type = $f->getMimeType();
        }

        $i = $reclamation->interactions()->create([
            'type' => $d['type'],
            'resume' => $d['resume'] ?? null,
            'audio_chemin' => $chemin,
            'audio_type' => $type,
            'audio_duree' => $d['duree'] ?? null,
            'auteur_id' => $request->user()->id,
        ]);

        // Dès le premier échange, la réclamation passe « en cours ».
        if ($reclamation->statut === 'ouverte') {
            $reclamation->update(['statut' => 'en_cours']);
        }

        return response()->json($this->presenterInteraction($reclamation, $i->load('auteur:id,name,prenom')), 201);
    }

    /** POST /api/reclamations/{reclamation}/statut : changer le statut. */
    public function changerStatut(Request $request, Reclamation $reclamation)
    {
        $d = $request->validate(['statut' => ['required', 'in:'.implode(',', self::STATUTS)]]);

        $maj = ['statut' => $d['statut']];
        if ($d['statut'] === 'resolue' && ! $reclamation->resolue_le) {
            $maj['resolue_le'] = now();
        }
        $reclamation->update($maj);

        return response()->json(['message' => 'Statut mis à jour.', 'statut' => $reclamation->statut]);
    }

    /** GET …/interactions/{interaction}/audio : écouter la note vocale. */
    public function audio(Reclamation $reclamation, ReclamationInteraction $interaction)
    {
        abort_unless($interaction->reclamation_id === $reclamation->id, 404);
        abort_unless($interaction->audio_chemin && Storage::disk('local')->exists($interaction->audio_chemin), 404);

        return response()->file(
            Storage::disk('local')->path($interaction->audio_chemin),
            ['Content-Type' => $interaction->audio_type ?: 'audio/webm']
        );
    }

    // ===================== Administrateur seulement =====================

    /** PUT /api/reclamations/{reclamation} : modifier (admin seul). */
    public function modifier(Request $request, Reclamation $reclamation)
    {
        $d = $request->validate([
            'produit' => ['sometimes', 'string', 'max:180'],
            'probleme' => ['sometimes', 'string', 'max:2000'],
            'client_nom' => ['sometimes', 'nullable', 'string', 'max:150'],
            'client_tel' => ['sometimes', 'nullable', 'string', 'max:30'],
            'conseiller_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'statut' => ['sometimes', 'in:'.implode(',', self::STATUTS)],
        ]);
        $reclamation->update($d);

        return response()->json($this->presenter(
            $reclamation->fresh(['client:id,prenom,nom,telephone', 'conseiller:id,name,prenom', 'creePar:id,name,prenom'])->loadCount('interactions')
        ));
    }

    /** DELETE /api/reclamations/{reclamation} : supprimer (admin seul). */
    public function supprimer(Reclamation $reclamation)
    {
        foreach ($reclamation->interactions as $i) {
            if ($i->audio_chemin) {
                Storage::disk('local')->delete($i->audio_chemin);
            }
        }
        $reclamation->delete();

        return response()->json(['message' => 'Réclamation supprimée.']);
    }

    /** PUT …/interactions/{interaction} : corriger un résumé (admin seul). */
    public function modifierInteraction(Request $request, Reclamation $reclamation, ReclamationInteraction $interaction)
    {
        abort_unless($interaction->reclamation_id === $reclamation->id, 404);

        $d = $request->validate(['resume' => ['nullable', 'string', 'max:2000']]);
        $interaction->update(['resume' => $d['resume'] ?? null]);

        return response()->json($this->presenterInteraction($reclamation, $interaction->fresh('auteur:id,name,prenom')));
    }

    /** DELETE …/interactions/{interaction} : supprimer une entrée (admin seul). */
    public function supprimerInteraction(Reclamation $reclamation, ReclamationInteraction $interaction)
    {
        abort_unless($interaction->reclamation_id === $reclamation->id, 404);

        if ($interaction->audio_chemin) {
            Storage::disk('local')->delete($interaction->audio_chemin);
        }
        $interaction->delete();

        return response()->json(['message' => 'Entrée supprimée.']);
    }

    // ===================== Présentation =====================

    private function presenter(Reclamation $r): array
    {
        return [
            'id' => $r->id,
            'numero' => $r->numero,
            'client' => $r->client_nom ?: ($r->client ? trim($r->client->prenom.' '.$r->client->nom) : 'Client'),
            'client_id' => $r->client_id,
            'telephone' => $r->client_tel ?: ($r->client->telephone ?? null),
            'vente_ref' => $r->vente_ref,
            'produit' => $r->produit,
            'probleme' => $r->probleme,
            'statut' => $r->statut,
            'conseiller' => $r->conseiller ? trim($r->conseiller->prenom.' '.$r->conseiller->name) : null,
            'conseiller_id' => $r->conseiller_id,
            'cree_par' => $r->creePar ? trim($r->creePar->prenom.' '.$r->creePar->name) : null,
            'nb_interactions' => $r->interactions_count ?? $r->interactions()->count(),
            'date' => $r->created_at->format('d/m/Y H:i'),
            'resolue_le' => $r->resolue_le?->format('d/m/Y H:i'),
        ];
    }

    private function presenterInteraction(Reclamation $r, ReclamationInteraction $i): array
    {
        return [
            'id' => $i->id,
            'type' => $i->type,
            'resume' => $i->resume,
            'a_audio' => (bool) $i->audio_chemin,
            'audio_url' => $i->audio_chemin ? "/reclamations/{$r->id}/interactions/{$i->id}/audio" : null,
            'duree' => $i->audio_duree,
            'auteur' => $i->auteur ? trim($i->auteur->prenom.' '.$i->auteur->name) : null,
            'date' => $i->created_at->format('d/m/Y H:i'),
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use Illuminate\Http\Request;

/**
 * Journal des activités : trace de chaque action sensible (qui, quoi, quand, détails).
 * Réservé à l'administrateur.
 */
class JournalController extends Controller
{
    public function index(Request $request)
    {
        return JournalActivite::query()
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->with('auteur:id,name,prenom,role')
            ->latest()
            ->paginate(60)
            ->through(fn (JournalActivite $j) => [
                'id' => $j->id,
                'action' => $j->action,
                'auteur' => $j->auteur ? trim($j->auteur->prenom.' '.$j->auteur->name) : '—',
                'role' => $j->auteur?->role?->value,
                'details' => $j->details,
                'date' => $j->created_at->toDateTimeString(),
            ]);
    }
}

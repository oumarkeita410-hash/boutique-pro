<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Alertes de l'utilisateur connecté : dettes réparateurs (admin),
 * rappels « il est l'heure de travailler » (vendeurs)…
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->unreadNotifications()->latest()->limit(100)->get()
            ->map(fn ($n) => ['id' => $n->id, 'date' => $n->created_at->toDateTimeString()] + $n->data);
    }

    public function marquerLue(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->noContent();
    }
}

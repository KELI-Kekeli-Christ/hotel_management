<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Récupère les notifications de l'utilisateur connecté
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Notification::with('user')->where('user_id', $user->id)->latest();

        if ($request->boolean('unread_only')) {
            $query->where('read', false);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(Notification $notification)
    {
        $user = auth()->user();

        // Vérifier que la notification appartient à l'utilisateur
        if ($notification->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marquée comme lue', 'notification' => $notification]);
    }

    /**
     * Marque toutes les notifications comme lues
     */
    public function markAllAsRead()
    {
        $user = auth()->user();

        Notification::where('user_id', $user->id)
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues']);
    }

    /**
     * Compte les notifications non lues
     */
    public function unreadCount()
    {
        $user = auth()->user();
        $count = Notification::where('user_id', $user->id)->where('read', false)->count();

        return response()->json(['count' => $count]);
    }
}

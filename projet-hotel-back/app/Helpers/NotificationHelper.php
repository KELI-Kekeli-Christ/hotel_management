<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;

class NotificationHelper
{
    /**
     * Crée une notification pour un utilisateur
     */
    public static function createNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Notifie les admins d'une nouvelle commande
     */
    public static function notifyAdminsOfNewOrder($order)
    {
        $admins = User::role(['Super-Admin', 'Receptionist', 'Service-Manager'])->get();

        foreach ($admins as $admin) {
            self::createNotification(
                $admin->id,
                'order_created',
                'Nouvelle commande',
                "Une nouvelle commande #{$order->id} a été créée par {$order->user->name}",
                ['order_id' => $order->id, 'user_id' => $order->user_id]
            );
        }
    }

    /**
     * Notifie les admins d'une nouvelle réservation
     */
    public static function notifyAdminsOfNewReservation($reservation)
    {
        $admins = User::role(['Super-Admin', 'Receptionist'])->get();

        foreach ($admins as $admin) {
            self::createNotification(
                $admin->id,
                'reservation_created',
                'Nouvelle réservation',
                "Une nouvelle réservation #{$reservation->numero_reservation} a été créée par {$reservation->user->name}",
                ['reservation_id' => $reservation->id, 'user_id' => $reservation->user_id]
            );
        }
    }

    /**
     * Notifie le client que sa réservation est confirmée
     */
    public static function notifyClientOfReservationConfirmation($reservation)
    {
        self::createNotification(
            $reservation->user_id,
            'reservation_confirmed',
            'Réservation confirmée',
            "Votre réservation #{$reservation->numero_reservation} a été confirmée",
            ['reservation_id' => $reservation->id]
        );
    }

    /**
     * Notifie le client qu'une facture a été générée
     */
    public static function notifyClientOfInvoiceGenerated($invoice)
    {
        self::createNotification(
            $invoice->reservation->user_id,
            'invoice_generated',
            'Facture générée',
            "Une facture #{$invoice->invoice_number} a été générée pour votre réservation",
            ['invoice_id' => $invoice->id, 'reservation_id' => $invoice->reservation_id]
        );
    }

    /**
     * Notifie le client qu'une facture a été payée
     */
    public static function notifyClientOfInvoicePaid($invoice)
    {
        self::createNotification(
            $invoice->reservation->user_id,
            'invoice_paid',
            'Facture payée',
            "Votre facture #{$invoice->invoice_number} a été payée avec succès",
            ['invoice_id' => $invoice->id, 'reservation_id' => $invoice->reservation_id]
        );
    }
}


<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Chambre;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReleaseRoomAfterInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    /**
     * Create a new job instance.
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Recharger la facture avec ses relations
            $invoice = Invoice::with('reservation.chambre')->find($this->invoice->id);
            
            if (!$invoice) {
                Log::warning('ReleaseRoomAfterInvoice: Invoice not found', ['invoice_id' => $this->invoice->id]);
                return;
            }

            if (!$invoice->reservation) {
                Log::warning('ReleaseRoomAfterInvoice: Reservation not found for invoice', ['invoice_id' => $invoice->id]);
                return;
            }

            $chambre = $invoice->reservation->chambre;

            if (!$chambre) {
                Log::warning('ReleaseRoomAfterInvoice: Chambre not found for reservation', [
                    'invoice_id' => $invoice->id,
                    'reservation_id' => $invoice->reservation_id
                ]);
                return;
            }

            // Vérifier si la chambre a encore des réservations actives
            $hasActiveReservations = $invoice->reservation
                ->chambre
                ->reservations()
                ->where('id', '!=', $invoice->reservation_id)
                ->whereNotIn('status', [
                    \App\Models\Reservation::STATUS_CANCELLED,
                    \App\Models\Reservation::STATUS_FAILED,
                    \App\Models\Reservation::STATUS_EXPIRED,
                    \App\Models\Reservation::STATUS_CHECKED_OUT,
                    'cancelled',
                    'failed',
                    'expired',
                    'checked_out'
                ])
                ->where(function($query) {
                    $query->where('date_arrive', '<=', now())
                          ->where('date_depart', '>=', now());
                })
                ->exists();

            // Libérer la chambre seulement si elle n'a pas d'autres réservations actives
            if (!$hasActiveReservations) {
                $chambre->disponibilite = true;
                $chambre->save();

                Log::info('ReleaseRoomAfterInvoice: Room released successfully', [
                    'invoice_id' => $invoice->id,
                    'chambre_id' => $chambre->id,
                    'chambre_number' => $chambre->numero_chambre
                ]);
            } else {
                Log::info('ReleaseRoomAfterInvoice: Room not released - has active reservations', [
                    'invoice_id' => $invoice->id,
                    'chambre_id' => $chambre->id
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ReleaseRoomAfterInvoice: Error releasing room', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage()
            ]);
            
            // Réessayer le job en cas d'erreur
            throw $e;
        }
    }
}

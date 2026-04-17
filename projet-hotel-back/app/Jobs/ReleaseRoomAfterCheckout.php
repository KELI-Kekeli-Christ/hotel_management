<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Models\Chambre;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReleaseRoomAfterCheckout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $reservation;

    /**
     * Create a new job instance.
     */
    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Recharger la réservation avec ses relations
            $reservation = Reservation::with('chambre')->find($this->reservation->id);
            
            if (!$reservation) {
                Log::warning('ReleaseRoomAfterCheckout: Reservation not found', ['reservation_id' => $this->reservation->id]);
                return;
            }

            $chambre = $reservation->chambre;

            if (!$chambre) {
                Log::warning('ReleaseRoomAfterCheckout: Chambre not found for reservation', [
                    'reservation_id' => $reservation->id
                ]);
                return;
            }

            // Ne libérer la chambre que si la réservation est terminée (date de départ passée)
            $dateDepart = \Carbon\Carbon::parse($reservation->date_depart);
            if ($dateDepart->isFuture()) {
                Log::info('ReleaseRoomAfterCheckout: Reservation checkout date not yet reached', [
                    'reservation_id' => $reservation->id,
                    'date_depart' => $reservation->date_depart,
                    'now' => now()
                ]);
                return;
            }

            // Vérifier que la réservation n'est pas annulée, échouée ou expirée
            $inactiveStatuses = [
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_FAILED,
                Reservation::STATUS_EXPIRED,
                'cancelled',
                'failed',
                'expired'
            ];

            if (in_array($reservation->status, $inactiveStatuses)) {
                Log::info('ReleaseRoomAfterCheckout: Reservation is inactive, room may already be released', [
                    'reservation_id' => $reservation->id,
                    'status' => $reservation->status
                ]);
                // La chambre devrait déjà être libérée, mais on vérifie quand même
                if (!$chambre->disponibilite) {
                    $chambre->disponibilite = true;
                    $chambre->save();
                    Log::info('ReleaseRoomAfterCheckout: Room released (was inactive reservation)');
                }
                return;
            }

            // Vérifier si la chambre a encore des réservations actives après cette date
            $hasActiveReservations = $reservation
                ->chambre
                ->reservations()
                ->where('id', '!=', $reservation->id)
                ->whereNotIn('status', $inactiveStatuses)
                ->where(function($query) use ($dateDepart) {
                    $query->where('date_arrive', '<=', $dateDepart)
                          ->where('date_depart', '>', $dateDepart);
                })
                ->exists();

            // Libérer la chambre seulement si elle n'a pas d'autres réservations actives
            if (!$hasActiveReservations) {
                $chambre->disponibilite = true;
                $chambre->save();

                // Marquer la réservation comme checked_out si elle ne l'est pas déjà
                if ($reservation->status !== Reservation::STATUS_CHECKED_OUT) {
                    $reservation->status = Reservation::STATUS_CHECKED_OUT;
                    $reservation->save();
                }

                Log::info('ReleaseRoomAfterCheckout: Room released successfully', [
                    'reservation_id' => $reservation->id,
                    'chambre_id' => $chambre->id,
                    'chambre_number' => $chambre->numero_chambre,
                    'date_depart' => $reservation->date_depart
                ]);
            } else {
                Log::info('ReleaseRoomAfterCheckout: Room not released - has active reservations', [
                    'reservation_id' => $reservation->id,
                    'chambre_id' => $chambre->id
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ReleaseRoomAfterCheckout: Error releasing room', [
                'reservation_id' => $this->reservation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Réessayer le job en cas d'erreur
            throw $e;
        }
    }
}


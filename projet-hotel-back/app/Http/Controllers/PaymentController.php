<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Reservation;
use App\Models\Chambre;
use App\Jobs\ReleaseRoomAfterCheckout;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentController extends Controller
{
    // note: webhook doit être public (pas auth) - route sans sanctum
    /**
     * Vérifie le statut d'une transaction FedaPay.
     * POST /api/payment/status/{transactionId}
     */
    public function checkStatus(Request $request, $transactionId)
    {
        try {
            if (!$transactionId) {
                return response()->json(['status' => 'error', 'message' => 'transactionId manquant dans l\'URL'], 400);
            }

            $apiBase = env('FEDAPAY_API_BASE', 'https://sandbox-api.fedapay.com');
            $skipVerify = (bool) env('FEDAPAY_SKIP_VERIFY', false); // uniquement dev
            $token = env('FEDAPAY_SECRET_KEY');

            if (!$token) {
                Log::warning('FEDAPAY_SECRET_KEY not set');
                return response()->json(['status' => 'error', 'message' => 'Clé API non configurée'], 500);
            }

            $url = rtrim($apiBase, '/') . '/v1/transactions/' . urlencode($transactionId);

            $response = Http::withToken($token)
                ->acceptJson()
                ->withOptions(['verify' => !$skipVerify])
                ->get($url);

            if ($response->failed()) {
                Log::warning('FedaPay API failed', [
                    'tx' => $transactionId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Impossible de récupérer le statut du paiement',
                    'details' => $response->body()
                ], 502);
            }

            $data = $response->json();

            // Tentatives de lecture flexible selon version du SDK / API
            $transaction = $data['transaction'] ?? $data['v1/transaction'] ?? $data['data'] ?? null;
            if (is_array($transaction) && isset($transaction['transaction'])) { // edge case
                $transaction = $transaction['transaction'];
            }

            if (!$transaction) {
                Log::warning('FedaPay: transaction key missing in response', ['response' => $data]);
                return response()->json(['status' => 'error', 'message' => 'Transaction introuvable dans la réponse API', 'raw_response' => $data], 500);
            }

            $fedapayStatus = strtolower($transaction['status'] ?? 'unknown');

            $mapped = match ($fedapayStatus) {
                'approved', 'paid', 'completed', 'successful' => 'success',
                'declined', 'failed', 'rejected', 'cancelled' => 'failed',
                'pending', 'waiting' => 'pending',
                default => 'unknown',
            };

            return response()->json([
                'status' => $mapped,
                'raw_status' => $fedapayStatus,
                'transaction' => $transaction
            ], $mapped === 'pending' ? 202 : 200);
        } catch (\Throwable $e) {
            Log::error('PaymentController::checkStatus exception', ['err' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle incoming webhook from FedaPay
     * POST /api/webhook/fedapay
     *
     * Le webhook doit être configuré côté FedaPay vers cette URL (ex: https://yourdomain/api/webhook/fedapay)
     */
    public function handleWebhook(Request $request)
    {
        try {
            $raw = $request->getContent();
            $signatureHeader = $request->header('X-FEDAPAY-SIGNATURE') ?? $request->header('FedaPay-Signature') ?? null;
            $secret = env('FEDAPAY_WEBHOOK_SECRET');

            if (!$secret) {
                Log::warning('FEDAPAY_WEBHOOK_SECRET not configured — refusing to process webhook');
                return response()->json(['status' => 'error', 'message' => 'Webhook secret not configured'], 400);
            }

            if (!$signatureHeader || !hash_equals(hash_hmac('sha256', $raw, $secret), $signatureHeader)) {
                Log::warning('Invalid webhook signature', ['header' => $signatureHeader]);
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }

            $payload = json_decode($raw, true);
            if (json_last_error() !== \JSON_ERROR_NONE) {
                Log::error('Webhook payload not JSON', ['error' => json_last_error_msg(), 'raw' => $raw]);
                return response()->json(['status' => 'error', 'message' => 'Invalid JSON payload'], 400);
            }

            // Normaliser event name & data selon payload
            $eventName = $payload['event'] ?? $payload['name'] ?? $payload['type'] ?? null;
            $eventData = $payload['data'] ?? $payload['payload'] ?? $payload;

            Log::info('Received Fedapay webhook', ['event' => $eventName, 'data' => $eventData]);

            // récupérer transaction id et optional pending_key
            $txId = $eventData['id'] ?? $eventData['transaction_id'] ?? $request->query('id') ?? $request->input('id') ?? null;
            $pendingKey = $eventData['pending_key'] ?? $request->query('pending_key') ?? $request->input('pending_key') ?? null;

            // Si on n'a ni transaction ni pending_key, on peut juste répondre OK
            if (!$txId && !$pendingKey) {
                Log::warning('Webhook without txId or pending_key', ['payload' => $payload]);
                return response()->json(['status' => 'ok', 'message' => 'no_action'], 200);
            }

            // Essayer de trouver la réservation correspondante
            $reservation = null;
            if ($txId) {
                $reservation = Reservation::where('transaction_id', $txId)->first();
            }
            if (!$reservation && $pendingKey) {
                $reservation = Reservation::where('pending_key', $pendingKey)->first();
            }

            // Obtenir statut depuis payload si fourni
            $rawStatus = strtolower($eventData['status'] ?? $eventData['state'] ?? '');

            // Si le payload ne contient pas de statut fiable, interroger l'API Fedapay
            if (empty($rawStatus) && $txId) {
                try {
                    $apiBase = env('FEDAPAY_API_BASE', 'https://sandbox-api.fedapay.com');
                    $skipVerify = (bool) env('FEDAPAY_SKIP_VERIFY', false);
                    $token = env('FEDAPAY_SECRET_KEY');
                    $resp = Http::withToken($token)->acceptJson()->withOptions(['verify' => !$skipVerify])
                        ->get(rtrim($apiBase, '/') . "/v1/transactions/{$txId}");
                    $body = $resp->json();
                    $tx = $body['transaction'] ?? $body['v1/transaction'] ?? $body['data'] ?? null;
                    if (is_array($tx) && isset($tx['status'])) {
                        $rawStatus = strtolower($tx['status']);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to re-check transaction status', ['tx' => $txId, 'err' => $e->getMessage()]);
                }
            }

            // map to action
            $successStates = ['approved', 'paid', 'completed', 'successful'];
            $failedStates = ['declined', 'failed', 'rejected', 'canceled', 'cancelled'];

            // Si reservation trouvée, appliquer changements en transaction DB
            if ($reservation) {
                try {
                    DB::transaction(function () use ($reservation, $rawStatus, $pendingKey, $txId, $successStates, $failedStates) {
                        $res = Reservation::lockForUpdate()->find($reservation->id);
                        // si déjà confirmé, pas d'action
                        if (in_array($res->status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CANCELLED])) {
                            return;
                        }

                        if (in_array($rawStatus, $successStates)) {
                            $res->status = Reservation::STATUS_CONFIRMED;
                            $res->numero_reservation = 'RES-' . strtoupper(uniqid());
                            if ($txId) $res->transaction_id = $txId;
                            $res->save();

                            $ch = Chambre::findOrFail($res->chambre_id);
                            $ch->disponibilite = 0;
                            $ch->save();
                            
                            // Planifier la libération de la chambre après la date de départ (le jour suivant la date de départ)
                            $dateDepart = Carbon::parse($res->date_depart);
                            if ($dateDepart->isFuture()) {
                                $releaseDate = $dateDepart->copy()->addDay()->startOfDay();
                                ReleaseRoomAfterCheckout::dispatch($res)->delay($releaseDate);
                            }
                        } elseif (in_array($rawStatus, $failedStates)) {
                            $res->status = Reservation::STATUS_CANCELLED;
                            if ($txId) $res->transaction_id = $txId;
                            $res->save();

                            $ch = Chambre::findOrFail($res->chambre_id);
                            $ch->disponibilite = 1;
                            $ch->save();
                        } else {
                            // leave pending, just save tx id/pending_key maybe
                            $res->transaction_id = $res->transaction_id ?? $txId;
                            $res->pending_key = $res->pending_key ?? $pendingKey;
                            $res->save();
                        }
                    });

                    // cleanup cache if any
                    if ($pendingKey) Cache::forget($pendingKey);

                    Log::info('Webhook processed for reservation', ['reservation_id' => $reservation->id, 'status' => $rawStatus]);
                    return response()->json(['status' => 'ok'], 200);
                } catch (\Throwable $e) {
                    Log::error('Failed to process webhook for reservation', ['err' => $e->getMessage()]);
                    return response()->json(['status' => 'error', 'message' => 'db_error'], 500);
                }
            }

            // If no reservation matched, optionally store mapping in cache for later lookup
            if ($pendingKey) {
                Cache::put('pending_result_' . $pendingKey, ['tx' => $txId, 'status' => $rawStatus], now()->addHours(2));
            }

            Log::info('Webhook processed (no reservation found)', ['tx' => $txId, 'pending_key' => $pendingKey, 'status' => $rawStatus]);
            return response()->json(['status' => 'ok', 'message' => 'no_reservation_found'], 200);
        } catch (\Throwable $e) {
            Log::error('PaymentController::handleWebhook exception', ['err' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}

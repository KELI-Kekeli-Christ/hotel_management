<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\HotelConfig;
use App\Jobs\ReleaseRoomAfterInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Helpers\NotificationHelper;
use FedaPay\FedaPay;
use FedaPay\Transaction;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['handleFedaPayCallback']);
        
        FedaPay::setEnvironment(env('FEDAPAY_ENVIRONMENT', 'sandbox'));
        FedaPay::setApiKey(apiKey: env('FEDAPAY_SECRET_KEY'));
    }

    /**
     * Génère une facture pour une réservation donnée.
     * C'est la fonction principale de ce module.
     */
    public function generateForReservation(Reservation $reservation)
    {
        // Vérifier si une facture existe déjà pour éviter les doublons
        if (Invoice::where('reservation_id', $reservation->id)->exists()) {
            return response()->json(['message' => 'Une facture existe déjà pour cette réservation.'], 409);
        }

        $invoice = DB::transaction(function () use ($reservation) {
            // 1. Créer la facture de base
            $newInvoice = Invoice::create([
                'reservation_id' => $reservation->id,
                'invoice_number' => 'INV-' . \date('Y') . '-' . \str_pad(Invoice::count() + 1, 4, '0', \STR_PAD_LEFT),
                'status' => Invoice::STATUS_UNPAID,
                'issued_date' => now(),
                'due_date' => $reservation->date_depart,
            ]);

            $total = 0;

            // 2. Ajouter le coût de la chambre comme première ligne
            $roomCost = $reservation->dure_reservation * $reservation->chambre->prix_nuite;
            $newInvoice->items()->create([
                'description' => "Séjour Chambre {$reservation->chambre->numero_chambre} ({$reservation->dure_reservation} nuits)",
                'quantity' => $reservation->dure_reservation,
                'unit_price' => $reservation->chambre->prix_nuite,
                'line_total' => $roomCost,
            ]);
            $total += $roomCost;

            // 3. Trouver et ajouter toutes les commandes (bar, restaurant) pendant le séjour
            $orders = Order::where('user_id', $reservation->user_id)
                ->whereBetween('created_at', [$reservation->date_arrive, $reservation->date_depart])
                ->get();

            foreach ($orders as $order) {
                $newInvoice->items()->create([
                    'description' => "Commande #" . $order->id . " ({$order->order_type})",
                    'quantity' => 1,
                    'unit_price' => $order->total,
                    'line_total' => $order->total,
                ]);
                $total += $order->total;
            }

            // 4. Mettre à jour le montant total de la facture
            $newInvoice->total_amount = $total;
            $newInvoice->save();

            return $newInvoice;
        });

        // Charger les relations pour la notification
        $invoice->load('reservation.user');

        // Notifier le client qu'une facture a été générée
        NotificationHelper::notifyClientOfInvoiceGenerated($invoice);

        // Planifier la libération de la chambre 1 jour après la génération de la facture
        ReleaseRoomAfterInvoice::dispatch($invoice)->delay(now()->addDay());

        return response()->json([
            'message' => 'Facture générée avec succès. La chambre sera libérée automatiquement dans 24 heures.',
            'invoice' => $invoice->load('items'),
        ], 201);
    }

    /**
     * Affiche toutes les factures (pour admin).
     */
    public function index()
    {
        $user = auth()->user();
        
        // Si l'utilisateur est un client, on ne devrait pas arriver ici (route protégée)
        // Mais par sécurité, on filtre quand même
        $canViewAll = $user->hasRole('Super-Admin') || 
                      $user->hasRole('Receptionist') || 
                      $user->hasRole('Économiste');
        
        if (!$canViewAll) {
            // Les clients ne devraient pas accéder à cette route
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        $invoices = Invoice::with('reservation.user')->latest()->paginate(15);
        return response()->json($invoices);
    }

    /**
     * Affiche les factures de l'utilisateur connecté (client).
     */
    public function myInvoices(Request $request)
    {
        $user = auth()->user();
        
        $invoices = Invoice::whereHas('reservation', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->with(['reservation.chambre', 'items'])
        ->latest()
        ->paginate($request->input('per_page', 10));

        return response()->json($invoices);
    }

    /**
     * Affiche une facture spécifique.
     */
    public function show(Invoice $invoice)
    {
        $user = auth()->user();

        // Charger la relation reservation pour la vérification
        $invoice->load('reservation');

        // Si c'est un client, vérifier qu'il est propriétaire de la facture
        // Les admins, réceptionnistes et économistes peuvent voir toutes les factures
        $canViewAll = $user->hasRole('Super-Admin') || 
                      $user->hasRole('Receptionist') || 
                      $user->hasRole('Économiste');
        
        if (!$canViewAll) {
            if (!$invoice->reservation || $invoice->reservation->user_id !== $user->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }
        }

        return response()->json($invoice->load(['items', 'reservation.user', 'reservation.chambre']));
    }

    /**
     * Met à jour une facture en ajoutant les nouvelles commandes.
     */
    public function updateFromOrders(Invoice $invoice)
    {
        $user = auth()->user();
        
        // Vérifier les permissions
        $canUpdate = $user->hasRole('Super-Admin') || 
                     $user->hasRole('Receptionist');
        
        if (!$canUpdate) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Ne pas mettre à jour si la facture est déjà payée
        if ($invoice->status === Invoice::STATUS_PAID) {
            return response()->json(['message' => 'Impossible de modifier une facture payée.'], 400);
        }

        $invoice->load('reservation');
        if (!$invoice->reservation) {
            return response()->json(['message' => 'Réservation introuvable pour cette facture.'], 404);
        }

        $updated = DB::transaction(function () use ($invoice) {
            $reservation = $invoice->reservation;
            
            // Récupérer les IDs des commandes déjà dans la facture
            $existingOrderDescriptions = $invoice->items()
                ->where('description', 'like', 'Commande #%')
                ->pluck('description')
                ->map(function ($desc) {
                    if (preg_match('/Commande #(\d+)/', $desc, $matches)) {
                        return (int)$matches[1];
                    }
                    return null;
                })
                ->filter()
                ->toArray();
            
            // Trouver les nouvelles commandes non encore facturées
            $newOrders = Order::where('user_id', $reservation->user_id)
                ->whereBetween('created_at', [$reservation->date_arrive, $reservation->date_depart])
                ->whereNotIn('id', $existingOrderDescriptions)
                ->get();
            
            $totalAdded = 0;
            
            foreach ($newOrders as $order) {
                $invoice->items()->create([
                    'description' => "Commande #" . $order->id . " ({$order->order_type})",
                    'quantity' => 1,
                    'unit_price' => $order->total,
                    'line_total' => $order->total,
                ]);
                $totalAdded += $order->total;
            }
            
            // Mettre à jour le montant total
            if ($totalAdded > 0) {
                $invoice->total_amount += $totalAdded;
                $invoice->save();
            }
            
            return [
                'new_orders_count' => $newOrders->count(),
                'total_added' => $totalAdded,
            ];
        });

        $invoice->load('items');
        
        return response()->json([
            'message' => $updated['new_orders_count'] > 0 
                ? "Facture mise à jour : {$updated['new_orders_count']} nouvelle(s) commande(s) ajoutée(s) ({$updated['total_added']} XOF)"
                : 'Aucune nouvelle commande à ajouter.',
            'invoice' => $invoice,
            'updated' => $updated,
        ]);
    }

    /**
     * Initialise le paiement d'une facture via FedaPay.
     */
    public function initPayment(Invoice $invoice, Request $request)
    {
        $user = auth()->user();
        
        // Vérifier que l'utilisateur est propriétaire de la facture ou admin
        $invoice->load('reservation');
        $canViewAll = $user->hasRole('Super-Admin') || 
                      $user->hasRole('Receptionist') || 
                      $user->hasRole('Économiste');
        
        if (!$canViewAll) {
            if (!$invoice->reservation || $invoice->reservation->user_id !== $user->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }
        }

        // Vérifier que la facture n'est pas déjà payée
        if ($invoice->status === Invoice::STATUS_PAID) {
            return response()->json(['message' => 'Cette facture est déjà payée.'], 400);
        }

        // Vérifier que la réservation existe
        if (!$invoice->reservation) {
            return response()->json(['message' => 'Réservation introuvable pour cette facture.'], 404);
        }

        $client = $invoice->reservation->user;
        if (!$client) {
            return response()->json(['message' => 'Client introuvable pour cette facture.'], 404);
        }

        try {
            // Générer un pending_key unique
            $pendingKey = 'pending_inv_' . (string) Str::uuid();
            
            // Stocker les données dans le cache
            $pendingData = [
                'invoice_id' => $invoice->id,
                'reservation_id' => $invoice->reservation_id,
                'user_id' => $client->id,
                'total_amount' => $invoice->total_amount,
                'invoice_number' => $invoice->invoice_number,
                'created_at' => now()->toDateTimeString(),
            ];
            Cache::put($pendingKey, $pendingData, now()->addHour());

            // Préparer l'URL de callback
            $callbackUrl = route('api.fedapay.invoice.callback', ['pending_key' => $pendingKey]);
            
            // Préparer les données du client
            $nameParts = explode(' ', $client->name);
            $firstName = $nameParts[0] ?? '';
            $lastName = implode(' ', array_slice($nameParts, 1)) ?? '';
            
            $customerData = [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $client->email,
            ];

            // Ajouter le numéro de téléphone si disponible
            $phoneNumber = $request->input('phone_number', $client->phone ?? '00000000');
            if ($phoneNumber && $phoneNumber !== '00000000') {
                $customerData['phone_number'] = [
                    'number' => $phoneNumber,
                    'country' => 'TG'
                ];
            }

            // Créer la transaction FedaPay
            $transaction = Transaction::create([
                'description' => "Paiement facture {$invoice->invoice_number}",
                'amount' => intval($invoice->total_amount),
                'currency' => ['iso' => 'XOF'],
                'callback_url' => $callbackUrl,
                'customer' => $customerData
            ]);

            $token = $transaction->generateToken();

            if ($token) {
                // Mettre à jour la facture avec le pending_key et transaction_id
                $invoice->pending_key = $pendingKey;
                $invoice->transaction_id = $transaction->id ?? null;
                $invoice->save();

                // Mettre à jour le cache avec les informations de transaction
                Cache::put($pendingKey, array_merge($pendingData, [
                    'transaction_id' => $transaction->id ?? null,
                    'token_url' => $token->url
                ]), now()->addHour());

                return response()->json([
                    'success' => true,
                    'redirect_url' => $token->url,
                    'pending_key' => $pendingKey,
                    'invoice' => $invoice->load('items')
                ], 200);
            }

            return response()->json(['error' => 'link_generation_failed', 'message' => 'Impossible de générer le lien de paiement.'], 500);
        } catch (\Throwable $e) {
            Log::error('FedaPay invoice payment init failed', [
                'err' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'payment_init_failed',
                'message' => 'Erreur lors de l\'initialisation du paiement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gère le callback FedaPay pour le paiement d'une facture.
     */
    public function handleFedaPayCallback(Request $request)
    {
        // Gérer les requêtes GET (redirections après annulation)
        // Pour les factures, on redirige simplement vers le frontend
        if ($request->isMethod('GET')) {
            $pendingKey = $request->query('pending_key');
            $status = $request->query('status');
            $transactionId = $request->query('id') ?? $request->query('transaction_id');
            
            Log::info('FedaPay invoice redirect (GET)', [
                'pendingKey' => $pendingKey,
                'status' => $status,
                'transactionId' => $transactionId
            ]);
            
            $frontendBase = config('app.frontend_url');
            $redirectTo = $frontendBase . "/client/invoices";
            
            return redirect()->away($redirectTo);
        }

        // Gérer les requêtes POST (webhooks)
        try {
            $rawPayload = $request->getContent();
            $sigHeader = $request->header('X-FEDAPAY-SIGNATURE') ?? $request->header('FedaPay-Signature');
            $endpointSecret = env('FEDAPAY_WEBHOOK_SECRET');

            if ($endpointSecret && class_exists(\FedaPay\Webhook::class)) {
                $event = \FedaPay\Webhook::constructEvent($rawPayload, $sigHeader, $endpointSecret);
                $eventName = $event->name ?? null;
                $payloadData = $event->data ?? json_decode($rawPayload, true);
            } else {
                $payloadData = json_decode($rawPayload, true);
                $eventName = $payloadData['event'] ?? ($payloadData['type'] ?? null);
            }
        } catch (\Throwable $e) {
            Log::error('Invoice webhook signature invalid', ['error' => $e->getMessage(), 'payload' => $request->all()]);
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $validEvents = ['transaction.approved', 'transaction.succeeded', 'transaction.updated'];
        if (!in_array($eventName, $validEvents)) {
            return response()->json(['ok' => true, 'message' => 'ignored_event'], 200);
        }

        $pendingKey = $request->query('pending_key') ?? ($payloadData['pending_key'] ?? null) ?? ($payloadData['data']['pending_key'] ?? null);
        if (!$pendingKey) {
            Log::warning('Invoice webhook without pending_key', ['payload' => $payloadData]);
            return response()->json(['error' => 'missing_pending_key'], 400);
        }

        // Trouver la facture par pending_key
        $invoice = Invoice::where('pending_key', $pendingKey)->first();
        if (!$invoice) {
            $cached = Cache::get($pendingKey);
            if (!$cached || empty($cached['invoice_id'])) {
                Log::warning('Pending invoice not found (db+cache)', ['pending_key' => $pendingKey]);
                return response()->json(['error' => 'pending_not_found'], 404);
            }
            $invoice = Invoice::find($cached['invoice_id']);
        }

        if (!$invoice) {
            return response()->json(['error' => 'invoice_not_found'], 404);
        }

        $transactionId = $invoice->transaction_id ?? ($payloadData['data']['id'] ?? $payloadData['id'] ?? null);
        if (!$transactionId) {
            Log::error('No transaction id for invoice', ['invoice_id' => $invoice->id, 'payload' => $payloadData]);
            return response()->json(['error' => 'transaction_id_missing'], 400);
        }

        try {
            $tx = \FedaPay\Transaction::retrieve($transactionId);
            $status = strtolower($tx->status ?? ($payloadData['data']['status'] ?? 'unknown'));
        } catch (\Throwable $e) {
            Log::error('FedaPay retrieve failed for invoice', ['err' => $e->getMessage(), 'txid' => $transactionId]);
            return response()->json(['error' => 'cannot_verify_payment'], 500);
        }

        // Si le paiement est réussi, marquer la facture comme payée
        if (in_array($status, ['approved', 'paid', 'completed', 'succeeded'])) {
            try {
                DB::transaction(function () use ($invoice) {
                    $inv = Invoice::lockForUpdate()->find($invoice->id);
                    // Si déjà payée, ne rien faire
                    if ($inv->status === Invoice::STATUS_PAID) {
                        return;
                    }
                    $inv->status = Invoice::STATUS_PAID;
                    $inv->amount_paid = $inv->total_amount;
                    $inv->save();
                });

                // Recharger la facture avec les relations
                $invoice->refresh()->load(['reservation.user', 'items']);

                // Notifier le client que sa facture est payée
                NotificationHelper::notifyClientOfInvoicePaid($invoice);

                Cache::forget($pendingKey);
                Log::info('Invoice marked as paid after payment', ['invoice_id' => $invoice->id, 'tx' => $transactionId]);
                return response()->json(['ok' => true], 200);
            } catch (\Throwable $e) {
                Log::error('Failed to mark invoice as paid', ['err' => $e->getMessage(), 'invoice_id' => $invoice->id]);
                return response()->json(['error' => 'failed_to_mark_paid'], 500);
            }
        }

        // Si le paiement a échoué, on ne change rien (la facture reste unpaid)
        if (in_array($status, ['declined', 'failed', 'canceled', 'rejected'])) {
            Cache::forget($pendingKey);
            Log::info('Invoice payment failed', ['invoice_id' => $invoice->id, 'tx' => $transactionId, 'status' => $status]);
            return response()->json(['ok' => true, 'message' => 'payment_failed'], 200);
        }

        return response()->json(['ok' => true, 'message' => 'status_unchanged'], 200);
    }

    /**
     * Marque une facture comme payée.
     */
    public function markAsPaid(Invoice $invoice)
    {
        $invoice->status = Invoice::STATUS_PAID;
        $invoice->amount_paid = $invoice->total_amount;
        $invoice->save();

        return response()->json(['message' => 'Facture marquée comme payée.', 'invoice' => $invoice]);
    }

    /**
     * Génère et télécharge une facture en PDF.
     */
    public function downloadPdf(Invoice $invoice)
    {
        $user = auth()->user();

        // Charger la relation reservation pour la vérification
        $invoice->load('reservation');

        // Si c'est un client, vérifier qu'il est propriétaire de la facture
        // Les admins, réceptionnistes et économistes peuvent télécharger toutes les factures
        $canViewAll = $user->hasRole('Super-Admin') || 
                      $user->hasRole('Receptionist') || 
                      $user->hasRole('Économiste');
        
        if (!$canViewAll) {
            if (!$invoice->reservation || $invoice->reservation->user_id !== $user->id) {
                return response()->json(['message' => 'Non autorisé'], 403)
                    ->header('Content-Type', 'application/json');
            }
        }

        try {
            // Charger toutes les relations nécessaires
            $invoice->load(['items', 'reservation.user', 'reservation.chambre']);
            
            // Vérifier que la réservation existe
            if (!$invoice->reservation) {
                \Log::error('PDF Generation Error: Reservation not found', ['invoice_id' => $invoice->id]);
                return response()->json(['message' => 'Réservation introuvable pour cette facture.'], 404)
                    ->header('Content-Type', 'application/json');
            }
            
            // Vérifier que l'utilisateur de la réservation existe
            if (!$invoice->reservation->user) {
                \Log::error('PDF Generation Error: User not found for reservation', [
                    'invoice_id' => $invoice->id,
                    'reservation_id' => $invoice->reservation->id
                ]);
                return response()->json(['message' => 'Client introuvable pour cette réservation.'], 404)
                    ->header('Content-Type', 'application/json');
            }
            
            // Récupérer la configuration de l'hôtel depuis la base de données
            try {
                $hotelConfig = HotelConfig::getConfig();
            } catch (\Exception $e) {
                \Log::warning('PDF Generation: Could not load hotel config, using defaults', ['error' => $e->getMessage()]);
                $hotelConfig = (object) [
                    'name' => 'Hôtel',
                    'address' => '',
                    'phone' => '',
                    'email' => ''
                ];
            }
            
            // Construire le chemin complet du tampon "PAYÉ" si la facture est payée
            // Pour DomPDF, on doit utiliser le chemin absolu du fichier
            $paidStampPath = null;
            if ($invoice->status === 'paid' && $hotelConfig->paid_stamp) {
                $storagePath = storage_path('app/public/' . $hotelConfig->paid_stamp);
                if (file_exists($storagePath)) {
                    $paidStampPath = $storagePath;
                    \Log::info('PDF Generation: Tampon PAYÉ trouvé', ['path' => $paidStampPath]);
                } else {
                    \Log::warning('PDF Generation: Tampon PAYÉ introuvable', ['path' => $storagePath]);
                }
            }
            
            $pdf = Pdf::loadView('invoices.pdf', [
                'invoice' => $invoice,
                'hotel' => [
                    'name' => $hotelConfig->name ?? 'Hôtel',
                    'address' => $hotelConfig->address ?? '',
                    'phone' => $hotelConfig->phone ?? '',
                    'email' => $hotelConfig->email ?? '',
                ],
                'paid_stamp_path' => $paidStampPath,
            ])->setPaper('a4', 'portrait')->setOption('isHtml5ParserEnabled', true)->setOption('isRemoteEnabled', false);

            // Générer le PDF une seule fois
            $pdfOutput = $pdf->output();
            
            if (empty($pdfOutput)) {
                \Log::error('PDF Generation Error: PDF output is empty', ['invoice_id' => $invoice->id]);
                return response()->json(['message' => 'Le PDF généré est vide.'], 500)
                    ->header('Content-Type', 'application/json');
            }

            // Retourner le PDF avec les headers appropriés
            return response($pdfOutput, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="facture-' . $invoice->invoice_number . '.pdf"')
                ->header('Content-Length', strlen($pdfOutput));
        } catch (\Exception $e) {
            \Log::error('Error generating PDF: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Log::error('Invoice data: ', [
                'invoice_id' => $invoice->id ?? null,
                'has_reservation' => isset($invoice->reservation),
                'has_items' => isset($invoice->items),
            ]);
            return response()->json([
                'message' => 'Erreur lors de la génération du PDF.',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue lors de la génération du PDF.'
            ], 500)->header('Content-Type', 'application/json');
        }
    }
}

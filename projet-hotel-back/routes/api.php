<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChambreController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BarStatsController;
use App\Http\Controllers\DashboardStatsController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\HotelConfigController;
use App\Http\Controllers\RoleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Ce fichier définit toutes les routes pour l'API. La structure sépare
| clairement les routes publiques des routes qui nécessitent une authentification.
|
*/

// --- SECTION 1 : ROUTES PUBLIQUES ---
// Accessibles par n'importe qui, sans token d'authentification.

// Authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Consultation publique du site
Route::get('/chambres', [ChambreController::class, 'index']);
Route::get('/chambres/{chambre}', [ChambreController::class, 'show']); // Route model binding
Route::get('/menu-items', [MenuItemController::class, 'index']); // Services publics
Route::get('/menu-items/{menuItem}', [MenuItemController::class, 'show']); // Service public
Route::get('/hotel-config', [HotelConfigController::class, 'show']); // Config publique
Route::post('/contact', [ContactController::class, 'send']);

// Webhooks et Callbacks (doivent être publics pour que les services externes y accèdent)
Route::post('/webhook/fedapay', [PaymentController::class, 'handleWebhook'])->name('webhook.fedapay');
Route::match(['GET', 'POST'], '/fedapay/callback', [ReservationController::class, 'handleFedaPayCallback'])->name('api.fedapay.callback');
Route::match(['GET', 'POST'], '/fedapay/invoice/callback', [InvoiceController::class, 'handleFedaPayCallback'])->name('api.fedapay.invoice.callback');
Route::match(['GET', 'POST'], '/fedapay/order/callback', [OrderController::class, 'handleFedaPayCallback'])->name('api.fedapay.order.callback');
Route::get('/fedapay/redirect', [ReservationController::class, 'handleFedaPayRedirect'])->name('fedapay.redirect');
Route::get('/reservation/pending-status', [ReservationController::class, 'pendingStatus']);
Route::post('/payment/status/{transactionId}', [PaymentController::class, 'checkStatus']);


// --- SECTION 2 : ROUTES PROTÉGÉES ---
// Nécessitent un token d'authentification valide (via le middleware 'auth:sanctum').
// Les permissions spécifiques sont gérées à l'intérieur des contrôleurs via les Policies.

Route::middleware('auth:api')->group(function () {

    // Authentification
    Route::post('/logout', [AuthController::class, 'logout']);
     Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [UserController::class, 'me']); // Obtenir l'utilisateur connecté

    // --- RESSOURCES CRUD ---
    // Laravel `apiResource` crée automatiquement les routes index, show, store, update, destroy.

    // Gestion des Utilisateurs (Admin)
    Route::apiResource('users', UserController::class);

    // Gestion des Chambres (Admin/Receptionist)
    // On exclut 'index' et 'show' qui sont déjà définies en public.
    Route::apiResource('chambres', ChambreController::class)->except(['index', 'show']);

    // Gestion des Réservations
    Route::get('reservations/my', [ReservationController::class, 'myReservations']);
    Route::apiResource('reservations', ReservationController::class);

    // Gestion des Services (Bar/Restaurant)
    Route::get('orders/my', [OrderController::class, 'myOrders']);
    Route::apiResource('orders', OrderController::class);
    Route::apiResource('menu-items', MenuItemController::class)->except(['index', 'show']); // index et show sont publics
    Route::get('categories', [CategoryController::class, 'index']); // Simple liste, protégée
    Route::get('stats', [BarStatsController::class, 'index']); // Stats du bar/restaurant
    Route::get('dashboard/stats', [DashboardStatsController::class, 'index']); // Stats générales du dashboard

    // Gestion de la Facturation (Admin/Receptionist)
    Route::post('invoices/generate/{reservation}', [InvoiceController::class, 'generateForReservation']);
    Route::post('invoices/{invoice}/update-from-orders', [InvoiceController::class, 'updateFromOrders']);
    Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'initPayment']); // Initier le paiement
    Route::patch('invoices/{invoice}/pay', [InvoiceController::class, 'markAsPaid']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
    Route::get('invoices/my', [InvoiceController::class, 'myInvoices']); // Factures de l'utilisateur connecté
    Route::apiResource('invoices', InvoiceController::class)->except(['store', 'update']);

    // Notifications
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::apiResource('notifications', NotificationController::class)->only(['index']);

    // Configuration de l'hôtel (show est public, update nécessite auth)
    Route::put('hotel-config', [HotelConfigController::class, 'update']);
    Route::post('hotel-config', [HotelConfigController::class, 'update']); // Pour FormData avec _method=PUT

    // Rôles (pour l'admin)
    Route::get('roles', [RoleController::class, 'index']);
});

<?php

namespace App\Providers;

use App\Models\Chambre;
use App\Models\Reservation;
use App\Policies\ChambrePolicy;
use App\Models\User;
use App\Policies\ReservationPolicy;
use App\Policies\UserPolicy;
use App\Models\Order;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Chambre::class => ChambrePolicy::class,
        User::class => UserPolicy::class,
        Reservation::class => ReservationPolicy::class,
        Order::class => OrderPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}

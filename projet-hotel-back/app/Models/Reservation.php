<?php

namespace App\Models;

use App\Models\Chambre;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;
    
    protected $fillable = ['user_id', 'chambre_id','numero_reservation','date_arrive', 'date_depart','dure_reservation', 'status','pending_key','transaction_id'];
   
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_REFUNDED = 'refunded';

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
    public function chambre()
    {
        return $this->belongsTo(Chambre::class);
    }

     public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Une réservation peut avoir une facture
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

}
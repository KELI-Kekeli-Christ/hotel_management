<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'reservation_id',
        'invoice_number',
        'total_amount',
        'amount_paid',
        'status',
        'issued_date',
        'due_date',
        'pending_key',
        'transaction_id',
    ];

    // Une facture appartient à une réservation
    public function reservation()
    {
        return $this->belongsTo(Reservation::class);

    }

    // Une facture a plusieurs lignes
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}

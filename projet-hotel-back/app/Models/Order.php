<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\OrderItem;
use App\Models\User;

class Order extends Model
{
    public const TYPE_ROOM_SERVICE = 'room_service';
    public const TYPE_RESTAURANT = 'restaurant';
    protected $fillable = ['location', 'order_type', 'status', 'total', 'user_id', 'note', 'pending_key', 'transaction_id'];


    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

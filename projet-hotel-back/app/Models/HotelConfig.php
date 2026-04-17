<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelConfig extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'logo',
        'paid_stamp',
        'description',
    ];
    
    /**
     * Récupère la configuration de l'hôtel (singleton)
     */
    public static function getConfig(): self
    {
        $config = self::first();
        
        if (!$config) {
            $config = self::create([
                'name' => env('HOTEL_NAME', 'Hôtel'),
                'address' => env('HOTEL_ADDRESS', ''),
                'phone' => env('HOTEL_PHONE', ''),
                'email' => env('HOTEL_EMAIL', ''),
            ]);
        }
        
        return $config;
    }
}

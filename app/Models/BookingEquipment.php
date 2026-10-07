<?php

namespace App\Models;

use App\Models\Booking;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingEquipment extends Model
{
    use HasFactory;

    protected $table = 'booking_equipment';

    protected $fillable = [
        'booking_id', 'equipment_id', 'quantity', 'price', 'subtotal'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}

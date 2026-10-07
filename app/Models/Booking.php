<?php

namespace App\Models;

use App\Models\Package;
use App\Models\BookingEquipment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_code', 'package_id', 'booking_date', 'number_of_people', 'name', 'email', 'phone', 'total_package', 'total_equipment', 'total_price', 'status'
    ];

    protected $casts = [
        'booking_date' => 'date',
        'total_package' => 'decimal:2',
        'total_equipment' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function bookingEquipment()
    {
        return $this->hasMany(BookingEquipment::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}

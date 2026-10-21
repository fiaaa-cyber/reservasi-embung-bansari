<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    public const CAPACITY_BLOCKING_STATUSES = [
        'pending_confirmation',
        'confirmed',
        'checked_in',
        'checked_out',
    ];

    protected $fillable = [
        'booking_code',
        'package_id',
        'booking_date',
        'number_of_people',
        'name',
        'email',
        'phone',
        'total_package',
        'total_equipment',
        'total_price',
        'status',
        'checked_in_at',
        'checked_out_at',
        'no_show_at',
        'attendance_tracking_enabled_at',
        'checked_in_by',
        'checked_out_by',
        'no_show_confirmed_by',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'total_package' => 'decimal:2',
        'total_equipment' => 'decimal:2',
        'total_price' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'no_show_at' => 'datetime',
        'attendance_tracking_enabled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->attendance_tracking_enabled_at ??= now('Asia/Jakarta');
        });
    }

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

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function noShowConfirmedBy()
    {
        return $this->belongsTo(User::class, 'no_show_confirmed_by');
    }

    public function capacityPeople(): int
    {
        return $this->package_id !== null
            ? (int) ($this->package?->capacity ?? $this->number_of_people ?? 0)
            : (int) $this->number_of_people;
    }
}

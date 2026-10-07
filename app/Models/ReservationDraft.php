<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'draft_code',
        'reservation_type',
        'package_id',
        'booking_date',
        'number_of_people',
        'name',
        'email',
        'phone',
        'equipment',
        'total_package',
        'total_equipment',
        'total_price',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'equipment' => 'array',
        'total_package' => 'decimal:2',
        'total_equipment' => 'decimal:2',
        'total_price' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}

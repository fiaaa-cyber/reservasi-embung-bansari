<?php

use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Equipment;
use App\Models\Package;
use App\Models\Payment;
use App\Support\PaymentMethodLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('invoice download renders reservation data and leaves records unchanged', function (string $reservationType, string $reservationStatus, string $paymentStatus, bool $withEquipment) {
    $package = $reservationType === 'package'
        ? Package::create([
            'name' => 'Paket Embung',
            'description' => 'Paket camping',
            'price' => 200000,
            'capacity' => 4,
            'is_active' => true,
        ])
        : null;
    $equipment = $withEquipment
        ? Equipment::create([
            'name' => 'Tenda Dome',
            'price' => 30000,
            'stock' => 7,
            'is_active' => true,
        ])
        : null;

    $booking = Booking::create([
        'booking_code' => 'DRF-INV-' . strtoupper($reservationStatus . $paymentStatus),
        'package_id' => $package?->id,
        'booking_date' => now()->addDays(14)->toDateString(),
        'number_of_people' => $package ? null : 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => $package ? 200000 : 30000,
        'total_equipment' => $equipment ? 30000 : 0,
        'total_price' => $equipment ? 230000 : ($package ? 200000 : 30000),
        'status' => $reservationStatus,
    ]);

    $bookingEquipment = $equipment
        ? BookingEquipment::create([
            'booking_id' => $booking->id,
            'equipment_id' => $equipment->id,
            'quantity' => 1,
            'price' => 30000,
            'subtotal' => 30000,
        ])
        : null;

    $totalAmount = (float) $booking->total_price;
    $paymentAmount = $paymentStatus === 'dp' ? $totalAmount * 0.5 : $totalAmount;
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => match ($paymentStatus) {
            'unpaid' => 'cash',
            'dp' => 'qris_dp',
            default => 'qris_full',
        },
        'transaction_id' => 'TXN-INVOICE-' . strtoupper($paymentStatus),
        'amount' => $paymentAmount,
        'status' => $paymentStatus,
        'paid_at' => $paymentStatus === 'unpaid' ? null : now(),
    ]);

    $bookingSnapshot = Booking::findOrFail($booking->id)->getRawOriginal();
    $paymentSnapshot = Payment::findOrFail($payment->id)->getRawOriginal();
    $equipmentSnapshot = $equipment ? Equipment::findOrFail($equipment->id)->getRawOriginal() : null;
    $bookingEquipmentSnapshot = $bookingEquipment
        ? BookingEquipment::findOrFail($bookingEquipment->id)->getRawOriginal()
        : null;

    $booking->load(['package', 'payment', 'bookingEquipment.equipment']);
    $html = view('invoice', compact('booking'))->render();

    $reservationLabel = match ($reservationStatus) {
        'pending_confirmation' => 'Menunggu Konfirmasi',
        'confirmed' => 'Dikonfirmasi',
        default => 'Dibatalkan',
    };
    $paymentLabel = match ($paymentStatus) {
        'unpaid' => 'Belum Dibayar',
        'dp' => 'DP 50%',
        default => 'Lunas',
    };

    expect($html)->toContain($reservationLabel, $paymentLabel, 'Ayu Lestari', '081234567890');
    expect($html)->toContain(PaymentMethodLabels::label($payment->payment_method));

    if ($package) {
        expect($html)->toContain('Paket Embung', '4 orang', 'Rp200.000');
    } else {
        expect($html)->toContain('Non-Paket', '2 orang', 'Rp30.000');
    }

    if ($equipment) {
        expect($html)->toContain('Tenda Dome', 'Rp30.000');
    } else {
        expect($html)->toContain('Tidak ada equipment tambahan.');
    }

    if ($paymentStatus === 'unpaid') {
        expect($html)->toContain('Rp0');
    } elseif ($paymentStatus === 'dp') {
        expect($html)->toContain('Nominal DP', 'Sisa Pembayaran', 'Rp115.000');
    } else {
        expect($html)->not->toContain('Sisa Pembayaran');
    }

    $response = $this->get(route('reservasi.invoice', ['booking_code' => $booking->booking_code]));
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition', 'attachment; filename=bukti-reservasi-' . \Illuminate\Support\Str::slug($booking->booking_code) . '.pdf');
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');

    $bookingAfter = Booking::findOrFail($booking->id)->getRawOriginal();
    $paymentAfter = Payment::findOrFail($payment->id)->getRawOriginal();
    $equipmentAfter = $equipment ? Equipment::findOrFail($equipment->id)->getRawOriginal() : null;
    $bookingEquipmentAfter = $bookingEquipment
        ? BookingEquipment::findOrFail($bookingEquipment->id)->getRawOriginal()
        : null;
    ksort($bookingSnapshot);
    ksort($bookingAfter);
    ksort($paymentSnapshot);
    ksort($paymentAfter);
    if ($equipmentSnapshot !== null) {
        ksort($equipmentSnapshot);
        ksort($equipmentAfter);
    }
    if ($bookingEquipmentSnapshot !== null) {
        ksort($bookingEquipmentSnapshot);
        ksort($bookingEquipmentAfter);
    }

    expect($bookingAfter)->toBe($bookingSnapshot);
    expect($paymentAfter)->toBe($paymentSnapshot);
    expect($equipmentAfter)->toBe($equipmentSnapshot);
    expect($bookingEquipmentAfter)->toBe($bookingEquipmentSnapshot);
})->with([
    'unpaid package without equipment' => ['package', 'pending_confirmation', 'unpaid', false],
    'DP non-package with equipment' => ['non_package', 'confirmed', 'dp', true],
    'paid cancelled booking with equipment' => ['package', 'cancelled', 'paid', true],
]);

test('invoice route returns 404 for an unknown booking code', function () {
    $this->get(route('reservasi.invoice', ['booking_code' => 'UNKNOWN-BOOKING-CODE']))
        ->assertNotFound();
});

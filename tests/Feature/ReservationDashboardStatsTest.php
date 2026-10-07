<?php

use App\Filament\Widgets\ReservationPaymentStats;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ReservationDraft;
use App\Support\PaymentMethodLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payment method labels preserve internal payment method values', function () {
    expect(PaymentMethodLabels::label('qris_full'))->toBe('Pembayaran Online - Lunas');
    expect(PaymentMethodLabels::label('qris_dp'))->toBe('Pembayaran Online - DP 50%');
    expect(PaymentMethodLabels::label('cash'))->toBe('Tunai');
    expect(PaymentMethodLabels::options())->toBe([
        'qris_full' => 'Pembayaran Online - Lunas',
        'qris_dp' => 'Pembayaran Online - DP 50%',
        'cash' => 'Tunai',
    ]);
});

test('payment selection displays online labels while preserving payment method values', function () {
    $draft = ReservationDraft::create([
        'draft_code' => 'DRAFT-PAYMENT-LABELS',
        'reservation_type' => 'without_package',
        'booking_date' => now()->addDays(10)->toDateString(),
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 30000,
        'total_equipment' => 0,
        'total_price' => 30000,
        'status' => 'pending',
    ]);

    $this->get(route('reservasi.payment', ['draft_code' => $draft->draft_code]))
        ->assertOk()
        ->assertSee('Pembayaran Online - Lunas')
        ->assertSee('Pembayaran Online - DP 50%')
        ->assertSee('Bayar melalui metode pembayaran yang tersedia di Midtrans.')
        ->assertSee('Tunai')
        ->assertSee('value="qris_full"', false)
        ->assertSee('value="qris_dp"', false)
        ->assertSee('value="cash"', false);
});

test('reservation dashboard stats read payment and booking statuses without writes', function () {
    $createBooking = function (string $code, string $status): Booking {
        return Booking::create([
            'booking_code' => $code,
            'booking_date' => now()->addDays(10)->toDateString(),
            'number_of_people' => 2,
            'name' => 'Ayu Lestari',
            'email' => 'ayu@gmail.com',
            'phone' => '081234567890',
            'total_package' => 0,
            'total_equipment' => 0,
            'total_price' => 2500000,
            'status' => $status,
        ]);
    };

    $pendingBooking = $createBooking('DASH-PENDING', 'pending_confirmation');
    $confirmedBooking = $createBooking('DASH-CONFIRMED', 'confirmed');
    $cancelledBooking = $createBooking('DASH-CANCELLED', 'cancelled');

    $createPayment = function (Booking $booking, string $method, string $status, float $amount): Payment {
        return Payment::create([
            'booking_id' => $booking->id,
            'payment_method' => $method,
            'transaction_id' => 'TXN-' . $booking->booking_code,
            'amount' => $amount,
            'status' => $status,
            'paid_at' => $status === 'unpaid' ? null : now(),
        ]);
    };

    $createPayment($pendingBooking, 'qris_full', 'paid', 2400000);
    $createPayment($confirmedBooking, 'qris_dp', 'dp', 50000);
    $createPayment($cancelledBooking, 'cash', 'unpaid', 2500000);

    $bookingsBefore = Booking::query()->orderBy('id')->get()->map->getAttributes()->all();
    $paymentsBefore = Payment::query()->orderBy('id')->get()->map->getAttributes()->all();

    $widget = new class extends ReservationPaymentStats
    {
        public function statsForTest(): array
        {
            return $this->getStats();
        }
    };

    $stats = $widget->statsForTest();

    expect($stats)->toHaveCount(4);
    expect($stats[0]->getLabel())->toBe('Total Pembayaran Diterima');
    expect($stats[0]->getValue())->toBe('Rp2.450.000');
    expect($stats[1]->getLabel())->toBe('Menunggu Konfirmasi');
    expect($stats[1]->getValue())->toBe(1);
    expect($stats[2]->getLabel())->toBe('Reservasi Dikonfirmasi');
    expect($stats[2]->getValue())->toBe(1);
    expect($stats[3]->getLabel())->toBe('Belum Dibayar');
    expect($stats[3]->getValue())->toBe(1);

    expect(Booking::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($bookingsBefore);
    expect(Payment::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($paymentsBefore);
});

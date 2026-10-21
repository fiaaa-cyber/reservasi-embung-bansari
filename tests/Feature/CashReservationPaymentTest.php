<?php

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Equipment;
use App\Models\Package;
use App\Models\Payment;
use App\Models\ReservationDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('cash payment creates a searchable booking and consumes equipment once', function () {
    $equipment = Equipment::create([
        'name' => 'Tenda',
        'price' => 25000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $draft = ReservationDraft::create([
        'draft_code' => 'DRAFT-CASH-001',
        'reservation_type' => 'without_package',
        'booking_date' => now()->addDays(10)->toDateString(),
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'equipment' => [[
            'equipment_id' => $equipment->id,
            'quantity' => 2,
            'price' => 25000,
        ]],
        'total_package' => 0,
        'total_equipment' => 50000,
        'total_price' => 80000,
        'status' => 'pending',
    ]);

    $legacyCashBooking = Booking::create([
        'booking_code' => 'BOOKING-LEGACY-CASH',
        'booking_date' => $draft->booking_date->toDateString(),
        'number_of_people' => 1,
        'name' => 'Pemesan Cash Lama',
        'email' => 'legacy@gmail.com',
        'phone' => '081111111111',
        'total_package' => 15000,
        'total_equipment' => 0,
        'total_price' => 15000,
        'status' => 'confirmed',
    ]);
    $legacyPaidAt = now()->subWeek();
    $legacyCashPayment = Payment::create([
        'booking_id' => $legacyCashBooking->id,
        'payment_method' => 'cash',
        'transaction_id' => 'CASH-LEGACY-PAID',
        'amount' => 15000,
        'status' => 'paid',
        'paid_at' => $legacyPaidAt,
    ]);

    expect(ReservationDraft::whereDate('booking_date', $draft->booking_date->toDateString())->exists())->toBeTrue();

    $response = $this->post(route('reservasi.payment.process'), [
        'draft_code' => $draft->draft_code,
        'payment_method' => 'cash',
    ]);

    $response->assertRedirect(route('reservasi.success', ['booking_code' => $draft->draft_code]));

    $booking = Booking::where('booking_code', $draft->draft_code)->firstOrFail();
    expect($booking->status)->toBe('pending_confirmation');
    $this->assertDatabaseHas('payments', [
        'booking_id' => $booking->id,
        'payment_method' => 'cash',
        'amount' => 80000,
        'status' => 'unpaid',
    ]);
    expect($booking->payment->paid_at)->toBeNull();
    expect($draft->fresh()->status)->toBe('paid');
    expect(BookingEquipment::where('booking_id', $booking->id)->where('equipment_id', $equipment->id)->value('quantity'))->toBe(2);
    expect($equipment->fresh()->stock)->toBe(3);
    expect($legacyCashPayment->fresh()->status)->toBe('paid');
    expect($legacyCashBooking->fresh()->status)->toBe('confirmed');
    expect($legacyCashPayment->fresh()->paid_at->toDateTimeString())->toBe($legacyPaidAt->toDateTimeString());

    $searchByCode = $this->post(route('reservasi.check.search'), [
        'search_type' => 'booking_code',
        'booking_code' => $draft->draft_code,
    ]);
    expect($searchByCode->status())->toBe(200);
    $searchByCode->assertSee($draft->draft_code);
    $searchByCode->assertSee('Menunggu Konfirmasi')->assertSee('Tunai')->assertSee('Belum Dibayar');

    $this->get(route('reservasi.success', ['booking_code' => $draft->draft_code]))
        ->assertOk()
        ->assertSee('Reservasi berhasil dibuat dan sedang menunggu konfirmasi admin.')
        ->assertSee('Tunai')
        ->assertSee('Belum Dibayar');

    $this->post(route('reservasi.check.search'), [
        'search_type' => 'customer',
        'name' => 'ayu lestari',
        'phone' => '081234567890',
    ])->assertOk()->assertSee($draft->draft_code);

    $this->post(route('reservasi.payment.process'), [
        'draft_code' => $draft->draft_code,
        'payment_method' => 'cash',
    ])->assertRedirect(route('reservasi.success', ['booking_code' => $draft->draft_code]));

    expect(Booking::where('booking_code', $draft->draft_code)->count())->toBe(1);
    expect(Payment::where('booking_id', $booking->id)->count())->toBe(1);
    expect(BookingEquipment::where('booking_id', $booking->id)->count())->toBe(1);
    expect($equipment->fresh()->stock)->toBe(3);
});

test('active reservation and payment statuses continue to consume daily capacity', function (string $paymentStatus, string $bookingStatus) {
    $bookingDate = now()->addDays(10)->toDateString();

    $existingBooking = Booking::create([
        'booking_code' => 'BOOKING-FULL-CAPACITY',
        'booking_date' => $bookingDate,
        'number_of_people' => 100,
        'name' => 'Peserta Terdahulu',
        'email' => 'existing@example.test',
        'phone' => '081111111111',
        'total_package' => 0,
        'total_equipment' => 0,
        'total_price' => 100000,
        'status' => $bookingStatus,
    ]);

    Payment::create([
        'booking_id' => $existingBooking->id,
        'payment_method' => match ($paymentStatus) {
            'dp' => 'qris_dp',
            'paid' => 'qris_full',
            default => 'cash',
        },
        'transaction_id' => 'PAYMENT-EXISTING-'.strtoupper($paymentStatus),
        'amount' => 100000,
        'status' => $paymentStatus,
        'paid_at' => $paymentStatus === 'unpaid' ? null : now(),
    ]);

    $draft = ReservationDraft::create([
        'draft_code' => 'DRAFT-CASH-FULL',
        'reservation_type' => 'without_package',
        'booking_date' => $bookingDate,
        'number_of_people' => 1,
        'name' => 'Peserta Baru',
        'email' => 'new@example.test',
        'phone' => '082222222222',
        'total_package' => 0,
        'total_equipment' => 0,
        'total_price' => 15000,
        'status' => 'pending',
    ]);

    $this->post(route('reservasi.payment.process'), [
        'draft_code' => $draft->draft_code,
        'payment_method' => 'cash',
    ])->assertSessionHasErrors('payment');

    expect(Booking::where('booking_code', $draft->draft_code)->exists())->toBeFalse();
    expect(Payment::where('booking_id', $existingBooking->id)->count())->toBe(1);
    expect($draft->fresh()->status)->toBe('pending');
})->with([
    'unpaid pending confirmation' => ['unpaid', 'pending_confirmation'],
    'dp pending confirmation' => ['dp', 'pending_confirmation'],
    'paid pending confirmation' => ['paid', 'pending_confirmation'],
    'unpaid confirmed' => ['unpaid', 'confirmed'],
    'dp confirmed' => ['dp', 'confirmed'],
    'paid confirmed' => ['paid', 'confirmed'],
]);

test('daily camping capacity uses active people totals for the requested date', function (array $existingBookings, string $reservationType, int $requestedPeople, int $requestDateOffset, bool $allowed, int $expectedUsedCapacity) {
    $baseDate = now()->addDays(10);
    $dateForOffset = fn (int $offset): string => $baseDate->copy()->addDays($offset)->toDateString();

    foreach ($existingBookings as $index => $existing) {
        $packageId = null;
        if ($existing['package'] ?? false) {
            $packageId = Package::create([
                'name' => 'Existing Package '.$index,
                'description' => 'Test package',
                'price' => 250000,
                'capacity' => 4,
                'is_active' => true,
            ])->id;
        }

        $booking = Booking::create([
            'booking_code' => 'BOOKING-CAPACITY-'.$index,
            'package_id' => $packageId,
            'booking_date' => $dateForOffset($existing['date_offset'] ?? 0),
            'number_of_people' => $packageId === null ? ($existing['people'] ?? 1) : null,
            'name' => 'Existing Customer '.$index,
            'email' => 'existing'.$index.'@gmail.com',
            'phone' => '081234567'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'total_package' => 0,
            'total_equipment' => 0,
            'total_price' => 0,
            'status' => $existing['status'] ?? 'pending_confirmation',
        ]);

        if (isset($existing['payment_status'])) {
            Payment::create([
                'booking_id' => $booking->id,
                'payment_method' => $existing['payment_status'] === 'dp' ? 'qris_dp' : 'qris_full',
                'transaction_id' => 'PAYMENT-CAPACITY-'.$index,
                'amount' => 1,
                'status' => $existing['payment_status'],
            ]);
        }
    }

    $payload = [
        'reservation_type' => $reservationType,
        'booking_date' => $dateForOffset($requestDateOffset),
        'name' => 'New Capacity Customer',
        'email' => 'newcapacity@gmail.com',
        'phone' => '089876543210',
    ];

    if ($reservationType === 'package') {
        $payload['package_id'] = Package::create([
            'name' => 'New Capacity Package',
            'description' => 'Test package',
            'price' => 250000,
            'capacity' => 4,
            'is_active' => true,
        ])->id;
    } else {
        $payload['number_of_people'] = $requestedPeople;
    }

    $response = $this->from(route('reservasi'))->post(route('reservasi.store'), $payload);

    if ($allowed) {
        $response->assertRedirect();
        expect(ReservationDraft::count())->toBe(1);
    } else {
        $response->assertRedirect(route('reservasi'))
            ->assertSessionHas('capacity_reservation', fn (array $summary): bool => $summary['used_capacity'] === $expectedUsedCapacity
                && $summary['requested_capacity'] === $requestedPeople
            );
        expect(ReservationDraft::count())->toBe(0);
    }
})->with([
    'empty capacity accepts a package' => [[], 'package', 4, 0, true, 0],
    '96 plus package 4 reaches exactly 100' => [[['people' => 96]], 'package', 4, 0, true, 96],
    '96 plus non-package 5 exceeds capacity' => [[['people' => 96]], 'without_package', 5, 0, false, 96],
    'full capacity rejects one more person' => [[['people' => 100]], 'without_package', 1, 0, false, 100],
    '90 plus non-package 10 reaches exactly 100' => [[['people' => 90]], 'without_package', 10, 0, true, 90],
    '90 plus non-package 11 exceeds capacity' => [[['people' => 90]], 'without_package', 11, 0, false, 90],
    'cancelled four people do not consume capacity' => [[['people' => 96], ['package' => true, 'status' => 'cancelled', 'payment_status' => 'paid']], 'package', 4, 0, true, 96],
    'checked-in bookings continue to consume capacity' => [[['people' => 100, 'status' => 'checked_in']], 'package', 4, 0, false, 100],
    'checked-out bookings continue to consume capacity' => [[['people' => 100, 'status' => 'checked_out']], 'package', 4, 0, false, 100],
    'no-show bookings do not consume capacity' => [[['people' => 100, 'status' => 'no_show']], 'package', 4, 0, true, 0],
    'other date capacity is isolated' => [[['people' => 100, 'date_offset' => 0]], 'package', 4, 1, true, 0],
    'mixed packages and non-package total 18 people' => [[['package' => true], ['package' => true], ['people' => 10]], 'without_package', 83, 0, false, 18],
    'three packages total 12 people' => [[['package' => true], ['package' => true], ['package' => true]], 'without_package', 89, 0, false, 12],
    'non-active booking status is ignored despite paid payment' => [[['people' => 100, 'status' => 'pending', 'payment_status' => 'paid']], 'package', 4, 0, true, 0],
]);

test('finalization rechecks capacity after another draft consumes the remaining places', function () {
    $bookingDate = now()->addDays(10)->toDateString();
    $package = Package::create([
        'name' => 'Capacity package',
        'description' => 'Four people',
        'price' => 250000,
        'capacity' => 4,
        'is_active' => true,
    ]);
    Booking::create([
        'booking_code' => 'BOOKING-CAPACITY-96',
        'booking_date' => $bookingDate,
        'number_of_people' => 96,
        'name' => 'Existing Group',
        'email' => 'existinggroup@gmail.com',
        'phone' => '081234567890',
        'total_package' => 0,
        'total_equipment' => 0,
        'total_price' => 0,
        'status' => 'confirmed',
    ]);

    $drafts = collect(['FIRST', 'SECOND'])->map(fn (string $suffix): ReservationDraft => ReservationDraft::create([
        'draft_code' => 'DRAFT-CAPACITY-'.$suffix,
        'reservation_type' => 'package',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Customer '.$suffix,
        'email' => strtolower($suffix).'@gmail.com',
        'phone' => $suffix === 'FIRST' ? '081111111111' : '082222222222',
        'total_package' => 250000,
        'total_equipment' => 0,
        'total_price' => 250000,
        'status' => 'pending',
    ]));

    $this->post(route('reservasi.payment.process'), [
        'draft_code' => $drafts[0]->draft_code,
        'payment_method' => 'cash',
    ])->assertRedirect(route('reservasi.success', ['booking_code' => $drafts[0]->draft_code]));

    $this->post(route('reservasi.payment.process'), [
        'draft_code' => $drafts[1]->draft_code,
        'payment_method' => 'cash',
    ])->assertSessionHasErrors('payment');

    expect(Booking::whereDate('booking_date', $bookingDate)->count())->toBe(2);
    expect(Booking::where('booking_code', $drafts[1]->draft_code)->exists())->toBeFalse();
});

test('package capacity uses the configured package capacity', function (int $packageCapacity, int $existingPeople, bool $allowed) {
    $package = Package::create([
        'name' => 'Capacity '.$packageCapacity,
        'price' => 100000,
        'capacity' => $packageCapacity,
        'is_active' => true,
    ]);
    $bookingDate = now()->addDays(10)->toDateString();

    if ($existingPeople > 0) {
        Booking::create([
            'booking_code' => 'BOOKING-PACKAGE-CAPACITY-'.$packageCapacity,
            'booking_date' => $bookingDate,
            'number_of_people' => $existingPeople,
            'name' => 'Existing Capacity Guest',
            'email' => 'existing.capacity@gmail.com',
            'phone' => '081234567890',
            'total_package' => 0,
            'total_equipment' => 0,
            'total_price' => 0,
            'status' => 'confirmed',
        ]);
    }

    $response = $this->from(route('reservasi'))->post(route('reservasi.store'), [
        'reservation_type' => 'package',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'New Capacity Guest',
        'email' => 'new.capacity@gmail.com',
        'phone' => '082234567890',
    ]);

    if ($allowed) {
        $response->assertRedirect();
        expect(ReservationDraft::count())->toBe(1);
    } else {
        $response->assertRedirect(route('reservasi'))
            ->assertSessionHas('capacity_reservation', fn (array $summary): bool => $summary['requested_capacity'] === $packageCapacity
                && $summary['used_capacity'] === $existingPeople
            );
        expect(ReservationDraft::count())->toBe(0);
    }
})->with([
    'four-person package fills remaining capacity' => [4, 96, true],
    'six-person package exceeds remaining capacity' => [6, 95, false],
    'ten-person package fills remaining capacity' => [10, 90, true],
]);

test('exact active package duplicate is rejected before creating a draft', function (string $bookingStatus) {
    $package = Package::create([
        'name' => 'Paket Tenda',
        'description' => 'Paket camping',
        'price' => 250000,
        'capacity' => 4,
        'is_active' => true,
    ]);
    $bookingDate = now()->addDays(10)->toDateString();

    $existingBooking = Booking::create([
        'booking_code' => 'BOOKING-ACTIVE-EXACT',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 250000,
        'total_equipment' => 0,
        'total_price' => 250000,
        'status' => $bookingStatus,
    ]);

    $this->from(route('reservasi'))
        ->post(route('reservasi.store'), [
            'reservation_type' => 'package',
            'package_id' => $package->id,
            'booking_date' => $bookingDate,
            'name' => ' Ayu Lestari ',
            'email' => 'AYU@GMAIL.COM',
            'phone' => '081234567890',
        ])
        ->assertRedirect(route('reservasi'))
        ->assertSessionHas('duplicate_reservation', true)
        ->assertSessionHasInput('email', 'AYU@GMAIL.COM');

    expect(ReservationDraft::count())->toBe(0);
    expect($existingBooking->fresh()->status)->toBe($bookingStatus);

    $this->get(route('reservasi'))
        ->assertOk()
        ->assertSee('Reservasi Sudah Ada')
        ->assertSee('Kembali ke Form')
        ->assertSee('value="Ayu Lestari"', false)
        ->assertSee('value="AYU@GMAIL.COM"', false);
})->with([
    'pending confirmation' => ['pending_confirmation'],
    'confirmed' => ['confirmed'],
]);

test('a package reservation is allowed when one exact-match field differs', function (string $difference) {
    $package = Package::create([
        'name' => 'Paket Tenda',
        'description' => 'Paket camping',
        'price' => 250000,
        'capacity' => 4,
        'is_active' => true,
    ]);
    $otherPackage = Package::create([
        'name' => 'Paket Danau',
        'description' => 'Paket camping lain',
        'price' => 300000,
        'capacity' => 4,
        'is_active' => true,
    ]);
    $bookingDate = now()->addDays(10)->toDateString();

    Booking::create([
        'booking_code' => 'BOOKING-MISMATCH-'.strtoupper($difference),
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 250000,
        'total_equipment' => 0,
        'total_price' => 250000,
        'status' => 'confirmed',
    ]);

    $reservation = [
        'reservation_type' => 'package',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
    ];

    match ($difference) {
        'date' => $reservation['booking_date'] = now()->addDays(11)->toDateString(),
        'email' => $reservation['email'] = 'ayu.baru@gmail.com',
        'phone' => $reservation['phone'] = '081234567891',
        'name' => $reservation['name'] = 'Ayu Lestari Baru',
        'package' => $reservation['package_id'] = $otherPackage->id,
    };

    $this->post(route('reservasi.store'), $reservation)->assertRedirect();

    expect(ReservationDraft::count())->toBe(1);
})->with(['date', 'email', 'phone', 'name', 'package']);

test('cancelled exact package booking does not block a new draft', function () {
    $package = Package::create([
        'name' => 'Paket Tenda',
        'description' => 'Paket camping',
        'price' => 250000,
        'capacity' => 4,
        'is_active' => true,
    ]);
    $bookingDate = now()->addDays(10)->toDateString();

    Booking::create([
        'booking_code' => 'BOOKING-CANCELLED-EXACT',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 250000,
        'total_equipment' => 0,
        'total_price' => 250000,
        'status' => 'cancelled',
    ]);

    $this->from(route('reservasi'))->post(route('reservasi.store'), [
        'reservation_type' => 'package',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
    ])->assertRedirect();

    expect(ReservationDraft::count())->toBe(1);
});

test('duplicate check endpoint uses the same exact active matching rules', function (string $bookingStatus, string $email, bool $exists) {
    $package = Package::create([
        'name' => 'Paket Tenda',
        'description' => 'Paket camping',
        'price' => 250000,
        'capacity' => 4,
        'is_active' => true,
    ]);
    $bookingDate = now()->addDays(10)->toDateString();

    Booking::create([
        'booking_code' => 'BOOKING-CHECK-'.strtoupper($bookingStatus.$email),
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 250000,
        'total_equipment' => 0,
        'total_price' => 250000,
        'status' => $bookingStatus,
    ]);

    $this->get(route('reservasi.duplicate-check', [
        'reservation_type' => 'package',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => $email,
        'phone' => '081234567890',
    ]))->assertOk()->assertExactJson(['exists' => $exists]);
})->with([
    'exact active match' => ['pending_confirmation', 'ayu@gmail.com', true],
    'email differs' => ['confirmed', 'ayu.baru@gmail.com', false],
    'cancelled exact match' => ['cancelled', 'ayu@gmail.com', false],
]);

test('non-package duplicate matching uses null package id', function () {
    $bookingDate = now()->addDays(10)->toDateString();
    Booking::create([
        'booking_code' => 'BOOKING-NON-PACKAGE',
        'booking_date' => $bookingDate,
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 30000,
        'total_equipment' => 0,
        'total_price' => 30000,
        'status' => 'pending_confirmation',
    ]);

    $this->get(route('reservasi.duplicate-check', [
        'reservation_type' => 'without_package',
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
    ]))->assertOk()->assertExactJson(['exists' => true]);

    $this->from(route('reservasi'))->post(route('reservasi.store'), [
        'reservation_type' => 'without_package',
        'booking_date' => $bookingDate,
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
    ])->assertRedirect(route('reservasi'))->assertSessionHas('duplicate_reservation', true);

    expect(ReservationDraft::count())->toBe(0);
});

test('online callback refuses a duplicate that appears after form submission', function () {
    config(['services.midtrans.server_key' => 'test-server-key']);
    $equipment = Equipment::create([
        'name' => 'Tenda',
        'price' => 20000,
        'stock' => 2,
        'is_active' => true,
    ]);
    $bookingDate = now()->addDays(10)->toDateString();
    $existingBooking = Booking::create([
        'booking_code' => 'BOOKING-RACE-FIRST',
        'booking_date' => $bookingDate,
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 30000,
        'total_equipment' => 20000,
        'total_price' => 50000,
        'status' => 'pending_confirmation',
    ]);
    BookingEquipment::create([
        'booking_id' => $existingBooking->id,
        'equipment_id' => $equipment->id,
        'quantity' => 1,
        'price' => 20000,
        'subtotal' => 20000,
    ]);
    Payment::create([
        'booking_id' => $existingBooking->id,
        'payment_method' => 'qris_full',
        'transaction_id' => 'MIDTRANS-RACE-FIRST',
        'amount' => 50000,
        'status' => 'unpaid',
    ]);
    $draft = ReservationDraft::create([
        'draft_code' => 'DRAFT-RACE-SECOND',
        'reservation_type' => 'without_package',
        'booking_date' => $bookingDate,
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'equipment' => [[
            'equipment_id' => $equipment->id,
            'quantity' => 1,
            'price' => 20000,
            'subtotal' => 20000,
        ]],
        'total_package' => 30000,
        'total_equipment' => 20000,
        'total_price' => 50000,
        'status' => 'pending',
    ]);
    $orderId = $draft->draft_code;
    $statusCode = '200';
    $grossAmount = '50000.00';

    $this->post(route('midtrans.callback'), [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => hash('sha512', $orderId.$statusCode.$grossAmount.'test-server-key'),
        'transaction_status' => 'settlement',
        'transaction_id' => 'MIDTRANS-RACE-SECOND',
    ])->assertStatus(409);

    expect(Booking::count())->toBe(1);
    expect(BookingEquipment::count())->toBe(1);
    expect(Payment::count())->toBe(1);
    expect($equipment->fresh()->stock)->toBe(2);
    expect($existingBooking->fresh()->status)->toBe('pending_confirmation');
});

test('verified Midtrans full and DP payments receive the correct payment status', function (string $paymentMethod, string $grossAmount, string $expectedStatus) {
    config(['services.midtrans.server_key' => 'test-server-key']);

    $draft = ReservationDraft::create([
        'draft_code' => 'DRAFT-MIDTRANS-'.strtoupper(Str::random(4)),
        'reservation_type' => 'without_package',
        'booking_date' => now()->addDays(10)->toDateString(),
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 100000,
        'total_equipment' => 0,
        'total_price' => 100000,
        'status' => 'pending',
    ]);
    $orderId = $draft->draft_code.'-20261003123456';
    $statusCode = '200';
    $signature = hash('sha512', $orderId.$statusCode.$grossAmount.config('services.midtrans.server_key'));

    $this->post(route('midtrans.callback'), [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'settlement',
        'transaction_id' => 'MIDTRANS-'.strtoupper($paymentMethod),
    ])->assertOk();

    $booking = Booking::where('booking_code', $draft->draft_code)->firstOrFail();
    $payment = Payment::where('booking_id', $booking->id)->firstOrFail();

    expect($booking->status)->toBe('pending_confirmation');
    expect($payment->payment_method)->toBe($paymentMethod);
    expect($payment->status)->toBe($expectedStatus);
    expect((float) $payment->amount)->toBe((float) $grossAmount);
    expect($payment->paid_at)->not->toBeNull();
})->with([
    'full' => ['qris_full', '100000.00', 'paid'],
    'DP' => ['qris_dp', '50000.00', 'dp'],
]);

test('admin confirmation changes only pending reservation status', function (string $reservationStatus, string $paymentStatus) {
    $equipment = Equipment::create([
        'name' => 'Equipment status test',
        'price' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $booking = Booking::create([
        'booking_code' => 'BOOKING-RESERVATION-STATUS-'.strtoupper($reservationStatus.'-'.$paymentStatus),
        'booking_date' => now()->addDays(10)->toDateString(),
        'number_of_people' => 1,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 15000,
        'total_equipment' => 0,
        'total_price' => 15000,
        'status' => $reservationStatus,
    ]);
    $bookingEquipment = BookingEquipment::create([
        'booking_id' => $booking->id,
        'equipment_id' => $equipment->id,
        'quantity' => 2,
        'price' => 10000,
        'subtotal' => 20000,
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => match ($paymentStatus) {
            'dp' => 'qris_dp',
            'paid' => 'qris_full',
            default => 'cash',
        },
        'transaction_id' => 'TXN-INDEPENDENT-'.strtoupper($reservationStatus.'-'.$paymentStatus),
        'amount' => $paymentStatus === 'dp' ? 7500 : 15000,
        'status' => $paymentStatus,
        'paid_at' => $paymentStatus === 'unpaid' ? null : now()->subDay(),
    ]);
    $originalBookingCode = $booking->booking_code;
    $originalBookingDate = $booking->booking_date->toDateString();
    $originalTransactionId = $payment->transaction_id;
    $originalAmount = (float) $payment->amount;
    $originalPaidAt = $payment->paid_at?->toDateTimeString();

    $action = BookingResource::confirmationAction();
    expect($action->isConfirmationRequired())->toBeTrue();
    ($action->getActionFunction())($booking);

    $booking->refresh();
    $payment->refresh();
    expect($booking->status)->toBe('confirmed');
    expect($booking->booking_code)->toBe($originalBookingCode);
    expect($booking->booking_date->toDateString())->toBe($originalBookingDate);
    expect($payment->status)->toBe($paymentStatus);
    expect((float) $payment->amount)->toBe($originalAmount);
    expect($payment->transaction_id)->toBe($originalTransactionId);
    expect($payment->paid_at?->toDateTimeString())->toBe($originalPaidAt);
    expect(Booking::whereKey($booking->id)->count())->toBe(1);
    expect(Payment::where('booking_id', $booking->id)->count())->toBe(1);
    expect(BookingEquipment::whereKey($bookingEquipment->id)->exists())->toBeTrue();
    expect($equipment->fresh()->stock)->toBe(5);
})->with([
    'unpaid while pending' => ['pending_confirmation', 'unpaid'],
    'dp while pending' => ['pending_confirmation', 'dp'],
    'paid while pending' => ['pending_confirmation', 'paid'],
    'unpaid while confirmed' => ['confirmed', 'unpaid'],
    'dp while confirmed' => ['confirmed', 'dp'],
    'paid while confirmed' => ['confirmed', 'paid'],
]);

test('admin confirms unpaid Cash and DP as paid without changing transaction details', function (string $initialStatus) {
    $equipment = Equipment::create([
        'name' => 'Tenda admin',
        'price' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $booking = Booking::create([
        'booking_code' => 'BOOKING-ADMIN-'.strtoupper($initialStatus),
        'booking_date' => now()->addDays(10)->toDateString(),
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 30000,
        'total_equipment' => 0,
        'total_price' => 30000,
        'status' => 'confirmed',
    ]);
    BookingEquipment::create([
        'booking_id' => $booking->id,
        'equipment_id' => $equipment->id,
        'quantity' => 2,
        'price' => 10000,
        'subtotal' => 20000,
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => $initialStatus === 'dp' ? 'qris_dp' : 'cash',
        'transaction_id' => 'TXN-UNCHANGED-'.strtoupper($initialStatus),
        'amount' => $initialStatus === 'dp' ? 15000 : 30000,
        'status' => $initialStatus,
        'paid_at' => $initialStatus === 'dp' ? now()->subDay() : null,
    ]);
    $originalAmount = (float) $payment->amount;
    $originalTransactionId = $payment->transaction_id;

    $action = PaymentResource::statusAction();
    ($action->getActionFunction())($payment, ['status' => 'paid']);

    $payment->refresh();
    expect($payment->status)->toBe('paid');
    expect((float) $payment->amount)->toBe($originalAmount);
    expect($payment->transaction_id)->toBe($originalTransactionId);
    expect($payment->paid_at)->not->toBeNull();
    expect(Booking::whereKey($booking->id)->count())->toBe(1);
    expect(BookingEquipment::where('booking_id', $booking->id)->count())->toBe(1);
    expect($equipment->fresh()->stock)->toBe(5);
})->with(['unpaid', 'dp']);

test('admin cannot downgrade paid payment', function () {
    $booking = Booking::create([
        'booking_code' => 'BOOKING-PAID-NO-DOWNGRADE',
        'booking_date' => now()->addDays(10)->toDateString(),
        'number_of_people' => 1,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 15000,
        'total_equipment' => 0,
        'total_price' => 15000,
        'status' => 'confirmed',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => 'qris_full',
        'transaction_id' => 'TXN-PAID-NO-DOWNGRADE',
        'amount' => 15000,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $action = PaymentResource::statusAction();

    expect(fn () => ($action->getActionFunction())($payment, ['status' => 'unpaid']))
        ->toThrow(ValidationException::class);
    expect($payment->fresh()->status)->toBe('paid');
});

test('admin cancels active reservations without changing payment or equipment data', function (string $reservationStatus, string $paymentStatus) {
    $equipment = Equipment::create([
        'name' => 'Equipment cancellation test',
        'price' => 10000,
        'stock' => 5,
        'is_active' => true,
    ]);
    $booking = Booking::create([
        'booking_code' => 'BOOKING-CANCEL-'.strtoupper($reservationStatus.'-'.$paymentStatus),
        'booking_date' => now()->addDays(10)->toDateString(),
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 30000,
        'total_equipment' => 20000,
        'total_price' => 50000,
        'status' => $reservationStatus,
    ]);
    $bookingEquipment = BookingEquipment::create([
        'booking_id' => $booking->id,
        'equipment_id' => $equipment->id,
        'quantity' => 2,
        'price' => 10000,
        'subtotal' => 20000,
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => match ($paymentStatus) {
            'dp' => 'qris_dp',
            'paid' => 'qris_full',
            default => 'cash',
        },
        'transaction_id' => 'TXN-CANCEL-'.strtoupper($reservationStatus.'-'.$paymentStatus),
        'amount' => $paymentStatus === 'dp' ? 25000 : 50000,
        'status' => $paymentStatus,
        'paid_at' => $paymentStatus === 'unpaid' ? null : now()->subDay(),
    ]);
    $originalBooking = $booking->only([
        'booking_code', 'name', 'email', 'phone', 'package_id',
        'number_of_people', 'total_package', 'total_equipment', 'total_price',
    ]);
    $originalBookingDate = $booking->booking_date->toDateString();
    $originalPayment = $payment->only([
        'payment_method', 'amount', 'transaction_id', 'paid_at', 'status',
    ]);
    $originalPayment['paid_at'] = $payment->paid_at?->toDateTimeString();
    $originalEquipment = $bookingEquipment->only(['equipment_id', 'quantity', 'price', 'subtotal']);

    $cancelAction = BookingResource::cancellationAction()->record($booking);
    $confirmAction = BookingResource::confirmationAction()->record($booking);
    expect($cancelAction->isVisible())->toBeTrue();
    expect($confirmAction->isVisible())->toBe($reservationStatus === 'pending_confirmation');
    expect($cancelAction->isConfirmationRequired())->toBeTrue();

    ($cancelAction->getActionFunction())($booking);

    $booking->refresh();
    $payment->refresh();
    $bookingEquipment->refresh();
    expect($booking->status)->toBe('cancelled');
    expect($booking->only(array_keys($originalBooking)))->toBe($originalBooking);
    expect($booking->booking_date->toDateString())->toBe($originalBookingDate);
    $currentPayment = $payment->only(array_keys($originalPayment));
    $currentPayment['paid_at'] = $payment->paid_at?->toDateTimeString();
    expect($currentPayment)->toBe($originalPayment);
    expect($bookingEquipment->only(array_keys($originalEquipment)))->toBe($originalEquipment);
    expect(Booking::whereKey($booking->id)->count())->toBe(1);
    expect(Payment::where('booking_id', $booking->id)->count())->toBe(1);
    expect(BookingEquipment::whereKey($bookingEquipment->id)->exists())->toBeTrue();
    expect($equipment->fresh()->stock)->toBe(5);
    $cancelAction = BookingResource::cancellationAction()->record($booking);
    $confirmAction = BookingResource::confirmationAction()->record($booking);
    $cancelAction->clearVisibilityCache();
    $confirmAction->clearVisibilityCache();
    expect($cancelAction->isVisible())->toBeFalse();
    expect($confirmAction->isVisible())->toBeFalse();
})->with([
    'pending confirmation and unpaid' => ['pending_confirmation', 'unpaid'],
    'pending confirmation and DP' => ['pending_confirmation', 'dp'],
    'pending confirmation and paid' => ['pending_confirmation', 'paid'],
    'confirmed and unpaid' => ['confirmed', 'unpaid'],
    'confirmed and DP' => ['confirmed', 'dp'],
    'confirmed and paid' => ['confirmed', 'paid'],
]);

test('cancelled booking cannot be confirmed again', function () {
    $booking = Booking::create([
        'booking_code' => 'BOOKING-CANCELLED-NO-REOPEN',
        'booking_date' => now()->addDays(10)->toDateString(),
        'number_of_people' => 1,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 15000,
        'total_equipment' => 0,
        'total_price' => 15000,
        'status' => 'cancelled',
    ]);

    ($action = BookingResource::confirmationAction())->getActionFunction()($booking);

    expect($booking->fresh()->status)->toBe('cancelled');
    expect($action->record($booking)->isVisible())->toBeFalse();
});

test('cancelled booking frees capacity and no longer blocks duplicate reservation', function () {
    $package = Package::create([
        'name' => 'Paket Batal',
        'description' => 'Paket camping',
        'price' => 250000,
        'capacity' => 4,
        'is_active' => true,
    ]);
    $bookingDate = now()->addDays(10)->toDateString();
    $cancelledBooking = Booking::create([
        'booking_code' => 'BOOKING-CANCELLED-FREES-SLOT',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 250000,
        'total_equipment' => 0,
        'total_price' => 250000,
        'status' => 'cancelled',
    ]);
    Payment::create([
        'booking_id' => $cancelledBooking->id,
        'payment_method' => 'qris_dp',
        'transaction_id' => 'TXN-CANCELLED-DP',
        'amount' => 125000,
        'status' => 'dp',
        'paid_at' => now(),
    ]);

    $this->from(route('reservasi'))
        ->post(route('reservasi.store'), [
            'reservation_type' => 'package',
            'package_id' => $package->id,
            'booking_date' => $bookingDate,
            'name' => 'Ayu Lestari',
            'email' => 'ayu@gmail.com',
            'phone' => '081234567890',
        ])
        ->assertRedirect();

    expect(ReservationDraft::count())->toBe(1);
});

test('no-show booking frees capacity and does not block an exact duplicate', function () {
    $package = Package::create([
        'name' => 'Paket No-show',
        'price' => 250000,
        'capacity' => 10,
        'is_active' => true,
    ]);
    $bookingDate = now()->addDays(10)->toDateString();
    $noShowBooking = Booking::create([
        'booking_code' => 'BOOKING-NO-SHOW-FREES-SLOT',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 250000,
        'total_equipment' => 0,
        'total_price' => 250000,
        'status' => 'no_show',
    ]);

    $this->from(route('reservasi'))
        ->post(route('reservasi.store'), [
            'reservation_type' => 'package',
            'package_id' => $package->id,
            'booking_date' => $bookingDate,
            'name' => 'Ayu Lestari',
            'email' => 'ayu@gmail.com',
            'phone' => '081234567890',
        ])
        ->assertRedirect()
        ->assertSessionMissing('duplicate_reservation');

    expect(ReservationDraft::count())->toBe(1);
    expect($noShowBooking->fresh()->status)->toBe('no_show');
});

test('admin checks in a cash booking by code during the WIB arrival window', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);
    $bookingDate = '2026-11-10';
    $this->travelTo(Carbon::parse($bookingDate.' 14:00:00', 'Asia/Jakarta'));

    $booking = Booking::create([
        'booking_code' => 'BOOKING-CHECK-IN-CASH',
        'booking_date' => $bookingDate,
        'number_of_people' => 2,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 30000,
        'total_equipment' => 0,
        'total_price' => 30000,
        'status' => 'confirmed',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => 'cash',
        'transaction_id' => 'CASH-CHECK-IN-UNPAID',
        'amount' => 30000,
        'status' => 'unpaid',
    ]);

    $action = BookingResource::checkInAction()->record($booking);
    expect($action->isVisible())->toBeTrue();
    ($action->getActionFunction())($booking, ['booking_code' => $booking->booking_code]);

    expect($booking->fresh()->status)->toBe('checked_in');
    expect($booking->fresh()->checked_in_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'))
        ->toBe($bookingDate.' 14:00:00');
    expect($booking->fresh()->checked_in_by)->toBe($admin->id);
    expect($payment->fresh()->status)->toBe('unpaid');
    expect($payment->fresh()->transaction_id)->toBe('CASH-CHECK-IN-UNPAID');
});

test('an already paid online booking uses the same late-arrival check-in window', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);
    $bookingDate = '2026-11-10';
    $this->travelTo(Carbon::parse($bookingDate.' 23:59:59', 'Asia/Jakarta'));
    $package = Package::create([
        'name' => 'Paket Late Check-in',
        'price' => 200000,
        'capacity' => 6,
        'is_active' => true,
    ]);
    $booking = Booking::create([
        'booking_code' => 'BOOKING-CHECK-IN-ONLINE',
        'package_id' => $package->id,
        'booking_date' => $bookingDate,
        'name' => 'Guest Online',
        'email' => 'online@gmail.com',
        'phone' => '081234567890',
        'total_package' => 200000,
        'total_equipment' => 0,
        'total_price' => 200000,
        'status' => 'pending_confirmation',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => 'qris_dp',
        'transaction_id' => 'MIDTRANS-ONLINE-CHECK-IN',
        'amount' => 100000,
        'status' => 'dp',
        'paid_at' => now('Asia/Jakarta')->subDays(2),
    ]);
    $paymentBefore = Payment::findOrFail($payment->id)->getRawOriginal();
    ksort($paymentBefore);

    BookingResource::checkInAction()->getActionFunction()($booking, [
        'booking_code' => $booking->booking_code,
    ]);

    expect($booking->fresh()->status)->toBe('checked_in');
    expect($booking->fresh()->checked_in_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'))
        ->toBe($bookingDate.' 23:59:59');
    expect($booking->fresh()->capacityPeople())->toBe(6);
    $paymentAfter = $payment->fresh()->getRawOriginal();
    ksort($paymentAfter);
    expect($paymentAfter)->toBe($paymentBefore);
});

test('check-in rejects the wrong code, an invalid window, repeated attempts, and cancelled bookings', function (string $bookingDate, string $currentTime, string $status, string $code, string $message) {
    $this->actingAs(User::factory()->create());
    $this->travelTo(Carbon::parse($currentTime, 'Asia/Jakarta'));
    $booking = Booking::create([
        'booking_code' => 'BOOKING-CHECK-IN-REJECT',
        'booking_date' => $bookingDate,
        'number_of_people' => 1,
        'name' => 'Guest',
        'email' => 'guest@gmail.com',
        'phone' => '081234567890',
        'total_package' => 15000,
        'total_equipment' => 0,
        'total_price' => 15000,
        'status' => $status,
    ]);
    if ($status === 'checked_in') {
        $booking->checked_in_at = Carbon::parse('2026-11-10 15:00:00', 'Asia/Jakarta');
        $booking->save();
    }

    try {
        BookingResource::checkInAction()->getActionFunction()($booking, ['booking_code' => $code]);
        test()->fail('Expected check-in validation to fail.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->implode(' '))->toContain($message);
    }

    expect($booking->fresh()->status)->toBe($status);
})->with([
    'before 14:00' => ['2026-11-10', '2026-11-10 13:59:59', 'confirmed', 'BOOKING-CHECK-IN-REJECT', 'pukul 14.00'],
    'at midnight after check-in date' => ['2026-11-10', '2026-11-11 00:00:00', 'confirmed', 'BOOKING-CHECK-IN-REJECT', 'pukul 14.00'],
    'wrong booking code' => ['2026-11-10', '2026-11-10 15:00:00', 'confirmed', 'WRONG-CODE', 'Kode booking tidak cocok'],
    'repeated check-in' => ['2026-11-10', '2026-11-10 16:00:00', 'checked_in', 'BOOKING-CHECK-IN-REJECT', 'tidak dapat diproses'],
    'cancelled booking' => ['2026-11-10', '2026-11-10 16:00:00', 'cancelled', 'BOOKING-CHECK-IN-REJECT', 'tidak dapat diproses'],
]);

test('check-out requires check-in and is recorded from 08:00 WIB the next day', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);
    $bookingDate = '2026-11-10';
    $booking = Booking::create([
        'booking_code' => 'BOOKING-CHECK-OUT',
        'booking_date' => $bookingDate,
        'number_of_people' => 2,
        'name' => 'Guest',
        'email' => 'guest@gmail.com',
        'phone' => '081234567890',
        'total_package' => 30000,
        'total_equipment' => 0,
        'total_price' => 30000,
        'status' => 'confirmed',
    ]);
    $checkout = BookingResource::checkOutAction()->getActionFunction();

    try {
        $checkout($booking);
        test()->fail('Expected check-out without check-in to fail.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->implode(' '))->toContain('harus sudah check-in');
    }

    expect($booking->fresh()->status)->toBe('confirmed');

    $booking->update([
        'status' => 'checked_in',
        'checked_in_at' => Carbon::parse($bookingDate.' 15:00:00', 'Asia/Jakarta'),
        'checked_in_by' => $admin->id,
    ]);
    $this->travelTo(Carbon::parse('2026-11-11 07:59:59', 'Asia/Jakarta'));

    try {
        $checkout($booking->fresh());
        test()->fail('Expected check-out before 08:00 to fail.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->implode(' '))->toContain('pukul 08.00');
    }

    expect($booking->fresh()->status)->toBe('checked_in');

    $this->travelTo(Carbon::parse('2026-11-11 08:00:00', 'Asia/Jakarta'));
    $checkout($booking->fresh());

    expect($booking->fresh()->status)->toBe('checked_out');
    expect($booking->fresh()->checked_out_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'))
        ->toBe('2026-11-11 08:00:00');
    expect($booking->fresh()->checked_out_by)->toBe($admin->id);
});

test('no-show remains a candidate until an admin confirms it and releases unhanded stock once', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);
    $this->travelTo(Carbon::parse('2026-11-10 14:00:00', 'Asia/Jakarta'));
    $equipment = Equipment::create([
        'name' => 'Tenda No-show',
        'price' => 20000,
        'stock' => 3,
        'is_active' => true,
    ]);
    $handedEquipment = Equipment::create([
        'name' => 'Matras No-show',
        'price' => 10000,
        'stock' => 4,
        'is_active' => true,
    ]);
    $booking = Booking::create([
        'booking_code' => 'BOOKING-NO-SHOW-STOCK',
        'booking_date' => '2026-11-10',
        'number_of_people' => 2,
        'name' => 'Guest',
        'email' => 'guest@gmail.com',
        'phone' => '081234567890',
        'total_package' => 30000,
        'total_equipment' => 30000,
        'total_price' => 60000,
        'status' => 'confirmed',
    ]);
    $unhandedLine = BookingEquipment::create([
        'booking_id' => $booking->id,
        'equipment_id' => $equipment->id,
        'quantity' => 2,
        'price' => 20000,
        'subtotal' => 40000,
    ]);
    $handedLine = BookingEquipment::create([
        'booking_id' => $booking->id,
        'equipment_id' => $handedEquipment->id,
        'quantity' => 1,
        'price' => 10000,
        'subtotal' => 10000,
        'handed_over_at' => now('Asia/Jakarta')->subHours(2),
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => 'qris_full',
        'transaction_id' => 'MIDTRANS-NO-SHOW-PAID',
        'amount' => 60000,
        'status' => 'paid',
        'paid_at' => now('Asia/Jakarta')->subDays(2),
    ]);
    $paymentBefore = Payment::findOrFail($payment->id)->getRawOriginal();
    ksort($paymentBefore);

    expect($booking->fresh()->status)->toBe('confirmed');
    expect(BookingResource::markNoShowAction()->record($booking)->isVisible())->toBeTrue();

    $action = BookingResource::markNoShowAction();
    ($action->getActionFunction())($booking, [
        'booking_code' => $booking->booking_code,
        'absence_verified' => true,
        'equipment_not_handed_over' => true,
    ]);

    expect($booking->fresh()->status)->toBe('no_show');
    expect($booking->fresh()->no_show_confirmed_by)->toBe($admin->id);
    expect($booking->fresh()->no_show_at)->not->toBeNull();
    expect($equipment->fresh()->stock)->toBe(5);
    expect($handedEquipment->fresh()->stock)->toBe(4);
    expect($unhandedLine->fresh()->stock_released_at)->not->toBeNull();
    expect($handedLine->fresh()->stock_released_at)->toBeNull();
    $paymentAfter = $payment->fresh()->getRawOriginal();
    ksort($paymentAfter);
    expect($paymentAfter)->toBe($paymentBefore);

    try {
        ($action->getActionFunction())($booking->fresh(), [
            'booking_code' => $booking->booking_code,
            'absence_verified' => true,
            'equipment_not_handed_over' => true,
        ]);
        test()->fail('Expected repeated no-show confirmation to fail.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->implode(' '))->toContain('belum memenuhi syarat');
    }

    expect($equipment->fresh()->stock)->toBe(5);
    $paymentAfter = $payment->fresh()->getRawOriginal();
    ksort($paymentAfter);
    expect($paymentAfter)->toBe($paymentBefore);
});

test('a no-show cannot be confirmed before the arrival window has ended', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(Carbon::parse('2026-11-10 13:59:59', 'Asia/Jakarta'));
    $booking = Booking::create([
        'booking_code' => 'BOOKING-NO-SHOW-TOO-EARLY',
        'booking_date' => '2026-11-10',
        'number_of_people' => 1,
        'name' => 'Guest',
        'email' => 'guest@gmail.com',
        'phone' => '081234567890',
        'total_package' => 15000,
        'total_equipment' => 0,
        'total_price' => 15000,
        'status' => 'confirmed',
    ]);

    try {
        BookingResource::markNoShowAction()->getActionFunction()($booking, [
            'booking_code' => $booking->booking_code,
            'absence_verified' => true,
            'equipment_not_handed_over' => true,
        ]);
        test()->fail('Expected early no-show confirmation to fail.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->implode(' '))->toContain('belum memenuhi syarat');
    }

    expect($booking->fresh()->status)->toBe('confirmed');
});

test('check-in and no-show actions become available together at 14:00 WIB on booking date', function () {
    $this->actingAs(User::factory()->create());
    $booking = Booking::create([
        'booking_code' => 'BOOKING-ARRIVAL-ACTIONS-14',
        'booking_date' => '2026-11-10',
        'number_of_people' => 1,
        'name' => 'Guest',
        'email' => 'guest.actions@gmail.com',
        'phone' => '081234567890',
        'total_package' => 15000,
        'total_equipment' => 0,
        'total_price' => 15000,
        'status' => 'confirmed',
    ]);
    $checkInAction = BookingResource::checkInAction();
    $noShowAction = BookingResource::markNoShowAction();

    $this->travelTo(Carbon::parse('2026-11-10 13:59:59', 'Asia/Jakarta'));
    expect($checkInAction->record($booking)->isVisible())->toBeFalse();
    expect($noShowAction->record($booking)->isVisible())->toBeFalse();

    $this->travelTo(Carbon::parse('2026-11-10 14:00:00', 'Asia/Jakarta'));
    expect($checkInAction->record($booking)->isVisible())->toBeTrue();
    expect($noShowAction->record($booking)->isVisible())->toBeTrue();
});

test('no-show rejects check-in and check-out timestamps even when booking status is inconsistent', function (string $status, ?string $checkedInAt, ?string $checkedOutAt) {
    $this->actingAs(User::factory()->create());
    $this->travelTo(Carbon::parse('2026-11-11 00:00:00', 'Asia/Jakarta'));
    $booking = Booking::create([
        'booking_code' => 'BOOKING-NO-SHOW-INCONSISTENT-'.strtoupper($status.($checkedInAt ? '-IN' : '').($checkedOutAt ? '-OUT' : '')),
        'booking_date' => '2026-11-10',
        'number_of_people' => 1,
        'name' => 'Guest',
        'email' => 'guest@gmail.com',
        'phone' => '081234567890',
        'total_package' => 15000,
        'total_equipment' => 0,
        'total_price' => 15000,
        'status' => $status,
        'checked_in_at' => $checkedInAt ? Carbon::parse($checkedInAt, 'Asia/Jakarta') : null,
        'checked_out_at' => $checkedOutAt ? Carbon::parse($checkedOutAt, 'Asia/Jakarta') : null,
    ]);

    expect(BookingResource::markNoShowAction()->record($booking)->isVisible())->toBeFalse();

    try {
        BookingResource::markNoShowAction()->getActionFunction()($booking, [
            'booking_code' => $booking->booking_code,
            'absence_verified' => true,
            'equipment_not_handed_over' => true,
        ]);
        test()->fail('Expected inconsistent attendance data to block no-show.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->implode(' '))->toContain('belum memenuhi syarat');
    }

    expect($booking->fresh()->status)->toBe($status)
        ->and($booking->fresh()->checked_in_at?->toDateTimeString())->toBe($checkedInAt
            ? Carbon::parse($checkedInAt, 'Asia/Jakarta')->toDateTimeString()
            : null)
        ->and($booking->fresh()->checked_out_at?->toDateTimeString())->toBe($checkedOutAt
            ? Carbon::parse($checkedOutAt, 'Asia/Jakarta')->toDateTimeString()
            : null);
})->with([
    'checked-in status without check-in timestamp' => ['checked_in', null, null],
    'checked-out status without check-out timestamp' => ['checked_out', null, null],
    'confirmed status with check-in timestamp' => ['confirmed', '2026-11-10 15:00:00', null],
    'confirmed status with check-out timestamp' => ['confirmed', null, '2026-11-11 08:00:00'],
    'checked-in status with check-out timestamp' => ['checked_in', null, '2026-11-11 08:00:00'],
]);

test('legacy paid booking requires explicit no-show verification and never automatically releases uncertain equipment', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(Carbon::parse('2026-11-11 00:00:00', 'Asia/Jakarta'));
    $equipment = Equipment::create([
        'name' => 'Legacy No-show Tent',
        'price' => 20000,
        'stock' => 1,
        'is_active' => true,
    ]);
    $booking = Booking::create([
        'booking_code' => 'BOOKING-LEGACY-NO-SHOW',
        'booking_date' => '2026-11-10',
        'number_of_people' => 2,
        'name' => 'Legacy Guest',
        'email' => 'legacy.guest@gmail.com',
        'phone' => '081234567890',
        'total_package' => 30000,
        'total_equipment' => 40000,
        'total_price' => 70000,
        'status' => 'confirmed',
    ]);
    $booking->attendance_tracking_enabled_at = null;
    $booking->save();
    $bookingEquipment = BookingEquipment::create([
        'booking_id' => $booking->id,
        'equipment_id' => $equipment->id,
        'quantity' => 2,
        'price' => 20000,
        'subtotal' => 40000,
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => 'qris_full',
        'transaction_id' => 'MIDTRANS-LEGACY-NO-SHOW-PAID',
        'amount' => 70000,
        'status' => 'paid',
        'paid_at' => now('Asia/Jakarta')->subDays(2),
    ]);
    $paymentBefore = Payment::findOrFail($payment->id)->getRawOriginal();
    ksort($paymentBefore);

    expect($booking->fresh()->attendance_tracking_enabled_at)->toBeNull();

    $action = BookingResource::markNoShowAction();
    ($action->getActionFunction())($booking, [
        'booking_code' => $booking->booking_code,
        'absence_verified' => true,
        'equipment_not_handed_over' => false,
    ]);

    expect($booking->fresh()->status)->toBe('no_show');
    expect($booking->fresh()->no_show_confirmed_by)->not->toBeNull();
    expect($equipment->fresh()->stock)->toBe(1);
    expect($bookingEquipment->fresh()->stock_released_at)->toBeNull();

    $paymentAfter = $payment->fresh()->getRawOriginal();
    ksort($paymentAfter);
    expect($paymentAfter)->toBe($paymentBefore);
    expect(BookingResource::markNoShowAction()->record($booking->fresh())->isVisible())->toBeFalse();
});

test('legacy equipment is released only after the admin explicitly verifies it was not handed over', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(Carbon::parse('2026-11-11 00:00:00', 'Asia/Jakarta'));
    $equipment = Equipment::create([
        'name' => 'Verified Legacy Tent',
        'price' => 20000,
        'stock' => 1,
        'is_active' => true,
    ]);
    $booking = Booking::create([
        'booking_code' => 'BOOKING-LEGACY-EQUIPMENT-VERIFIED',
        'booking_date' => '2026-11-10',
        'number_of_people' => 1,
        'name' => 'Legacy Guest',
        'email' => 'legacy.verify@gmail.com',
        'phone' => '081234567890',
        'total_package' => 15000,
        'total_equipment' => 20000,
        'total_price' => 35000,
        'status' => 'pending_confirmation',
    ]);
    $booking->attendance_tracking_enabled_at = null;
    $booking->save();
    $bookingEquipment = BookingEquipment::create([
        'booking_id' => $booking->id,
        'equipment_id' => $equipment->id,
        'quantity' => 2,
        'price' => 20000,
        'subtotal' => 40000,
    ]);

    BookingResource::markNoShowAction()->getActionFunction()($booking, [
        'booking_code' => $booking->booking_code,
        'absence_verified' => true,
        'equipment_not_handed_over' => true,
    ]);

    expect($booking->fresh()->status)->toBe('no_show');
    expect($equipment->fresh()->stock)->toBe(3);
    expect($bookingEquipment->fresh()->stock_released_at)->not->toBeNull();
});

test('legacy bookings remain readable with null attendance fields', function () {
    $booking = Booking::create([
        'booking_code' => 'BOOKING-LEGACY-ATTENDANCE',
        'booking_date' => now()->addDays(10)->toDateString(),
        'number_of_people' => 3,
        'name' => 'Legacy Guest',
        'email' => 'legacy@gmail.com',
        'phone' => '081234567890',
        'total_package' => 45000,
        'total_equipment' => 0,
        'total_price' => 45000,
        'status' => 'confirmed',
    ]);

    expect($booking->fresh()->checked_in_at)->toBeNull()
        ->and($booking->fresh()->checked_out_at)->toBeNull()
        ->and($booking->fresh()->no_show_at)->toBeNull()
        ->and($booking->fresh()->checkedInBy)->toBeNull()
        ->and($booking->fresh()->checkedOutBy)->toBeNull()
        ->and($booking->fresh()->noShowConfirmedBy)->toBeNull();
});

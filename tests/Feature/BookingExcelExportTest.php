<?php

use App\Filament\Resources\Bookings\Exports\BookingsExcelExport;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Equipment;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

uses(RefreshDatabase::class);

function readBookingsExcelResponse(BinaryFileResponse $response): array
{
    $path = $response->getFile()->getPathname();
    $spreadsheet = IOFactory::load($path);

    try {
        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    } finally {
        $spreadsheet->disconnectWorksheets();
        @unlink($path);
    }
}

function createExcelExportBooking(array $attributes = []): Booking
{
    static $sequence = 0;
    $sequence++;

    return Booking::create(array_merge([
        'booking_code' => 'EXPORT-BOOKING-' . $sequence,
        'booking_date' => '2026-10-15',
        'number_of_people' => 3,
        'name' => 'Ayu Lestari',
        'email' => 'ayu@gmail.com',
        'phone' => '081234567890',
        'total_package' => 45000,
        'total_equipment' => 0,
        'total_price' => 45000,
        'status' => 'pending_confirmation',
    ], $attributes));
}

function exportBookings(array $filters): array
{
    $response = (new BookingsExcelExport($filters))->download();

    return [
        'name' => $response->headers->get('Content-Disposition'),
        'rows' => readBookingsExcelResponse($response),
    ];
}

function defaultBookingExportFilters(array $overrides = []): array
{
    return array_merge([
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'status' => 'all',
        'reservation_type' => 'all',
    ], $overrides);
}

test('export downloads a valid spreadsheet containing only bookings in the selected period', function () {
    createExcelExportBooking(['booking_code' => 'OCT-START', 'booking_date' => '2026-10-01']);
    createExcelExportBooking(['booking_code' => 'OCT-END', 'booking_date' => '2026-10-31', 'status' => 'confirmed']);
    createExcelExportBooking(['booking_code' => 'OCT-CANCELLED', 'booking_date' => '2026-10-15', 'status' => 'cancelled']);
    createExcelExportBooking(['booking_code' => 'NOVEMBER', 'booking_date' => '2026-11-01']);

    $export = exportBookings(defaultBookingExportFilters());

    expect($export['name'])->toContain('reservasi-embung-bansari-2026-10-01-sampai-2026-10-31.xlsx');
    expect($export['rows'][0])->toBe([
        'No',
        'Kode Booking',
        'Nama',
        'Email',
        'No. HP',
        'Tanggal Reservasi',
        'Jenis Reservasi',
        'Paket',
        'Jumlah Orang',
        'Equipment',
        'Total Harga',
        'Status Reservasi',
        'Metode Pembayaran',
        'Status Pembayaran',
        'Dibuat Pada',
    ]);
    expect(array_column(array_slice($export['rows'], 1), 1))->toBe(['OCT-START', 'OCT-CANCELLED', 'OCT-END']);
});

test('export filters reservation status correctly', function (string $status, string $expectedCode) {
    createExcelExportBooking(['booking_code' => 'PENDING', 'status' => 'pending_confirmation']);
    createExcelExportBooking(['booking_code' => 'CONFIRMED', 'status' => 'confirmed']);
    createExcelExportBooking(['booking_code' => 'CANCELLED', 'status' => 'cancelled']);

    $export = exportBookings(defaultBookingExportFilters(['status' => $status]));

    expect(array_column(array_slice($export['rows'], 1), 1))->toBe([$expectedCode]);
})->with([
    'confirmed status' => ['confirmed', 'CONFIRMED'],
    'pending confirmation status' => ['pending_confirmation', 'PENDING'],
    'cancelled status' => ['cancelled', 'CANCELLED'],
]);

test('export filters reservation type from package id nullability', function (string $type, string $expectedCode) {
    $package = Package::create([
        'name' => 'Paket Oktober',
        'description' => 'Paket camping',
        'price' => 250000,
        'capacity' => 4,
        'is_active' => true,
    ]);
    createExcelExportBooking(['booking_code' => 'PACKAGE', 'package_id' => $package->id]);
    createExcelExportBooking(['booking_code' => 'NON-PACKAGE', 'package_id' => null]);

    $export = exportBookings(defaultBookingExportFilters(['reservation_type' => $type]));

    expect(array_column(array_slice($export['rows'], 1), 1))->toBe([$expectedCode]);
})->with([
    'package filter' => ['package', 'PACKAGE'],
    'non-package filter' => ['without_package', 'NON-PACKAGE'],
]);

test('export applies date status and reservation type filters together', function () {
    $package = Package::create([
        'name' => 'Paket Oktober',
        'description' => 'Paket camping',
        'price' => 250000,
        'capacity' => 4,
        'is_active' => true,
    ]);
    createExcelExportBooking([
        'booking_code' => 'MATCH',
        'booking_date' => '2026-10-10',
        'status' => 'confirmed',
        'package_id' => $package->id,
    ]);
    createExcelExportBooking([
        'booking_code' => 'WRONG-STATUS',
        'status' => 'pending_confirmation',
        'package_id' => $package->id,
    ]);
    createExcelExportBooking([
        'booking_code' => 'WRONG-TYPE',
        'status' => 'confirmed',
        'package_id' => null,
    ]);
    createExcelExportBooking([
        'booking_code' => 'WRONG-DATE',
        'booking_date' => '2026-11-01',
        'status' => 'confirmed',
        'package_id' => $package->id,
    ]);

    $export = exportBookings(defaultBookingExportFilters([
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-20',
        'status' => 'confirmed',
        'reservation_type' => 'package',
    ]));

    expect(array_column(array_slice($export['rows'], 1), 1))->toBe(['MATCH']);
});

test('export writes equipment in one cell with correct quantities', function () {
    $booking = createExcelExportBooking(['package_id' => null, 'number_of_people' => 5]);
    $tent = Equipment::create(['name' => 'Tenda', 'price' => 25000, 'stock' => 5, 'is_active' => true]);
    $mat = Equipment::create(['name' => 'Matras', 'price' => 10000, 'stock' => 5, 'is_active' => true]);

    BookingEquipment::create(['booking_id' => $booking->id, 'equipment_id' => $tent->id, 'quantity' => 1, 'price' => 25000, 'subtotal' => 25000]);
    BookingEquipment::create(['booking_id' => $booking->id, 'equipment_id' => $mat->id, 'quantity' => 4, 'price' => 10000, 'subtotal' => 40000]);

    $export = exportBookings(defaultBookingExportFilters());
    $row = $export['rows'][1];

    expect($row[9])->toBe('Tenda x1; Matras x4')
        ->and((int) $row[8])->toBe(5);
});

test('export uses a dash when booking has no equipment or payment', function () {
    createExcelExportBooking(['package_id' => null, 'number_of_people' => 1]);

    $export = exportBookings(defaultBookingExportFilters());

    expect($export['rows'][1][9])->toBe('-')
        ->and($export['rows'][1][12])->toBe('-')
        ->and($export['rows'][1][13])->toBe('-');
});

test('export formats package people labels payment labels and dates for admins', function () {
    $package = Package::create([
        'name' => 'Paket A',
        'description' => 'Paket camping',
        'price' => 250000,
        'capacity' => 8,
        'is_active' => true,
    ]);
    $booking = createExcelExportBooking([
        'booking_code' => 'FORMATTED-BOOKING',
        'package_id' => $package->id,
        'number_of_people' => null,
        'booking_date' => '2026-10-12',
        'status' => 'confirmed',
        'total_price' => 250000,
    ]);
    Payment::create([
        'booking_id' => $booking->id,
        'payment_method' => 'qris_dp',
        'transaction_id' => 'PAYMENT-EXPORT',
        'amount' => 125000,
        'status' => 'dp',
    ]);

    $export = exportBookings(defaultBookingExportFilters());
    $row = $export['rows'][1];

    expect($row[5])->toBe('12/10/2026')
        ->and($row[6])->toBe('Paket')
        ->and($row[7])->toBe('Paket A')
        ->and((int) $row[8])->toBe(4)
        ->and($row[10])->toBe('Rp 250.000')
        ->and($row[11])->toBe('Dikonfirmasi')
        ->and($row[12])->toBe('Pembayaran Online - DP 50%')
        ->and($row[13])->toBe('DP 50%');
});

    test('admin export route requires auth middleware and downloads the workbook', function () {
        $filters = defaultBookingExportFilters();
        $url = route('admin.bookings.export-excel', $filters);
        $route = app('router')->getRoutes()->getByName('admin.bookings.export-excel');

        expect($route->gatherMiddleware())->toContain('auth');
        $this->get($url)->assertRedirect(route('login'));

        $response = $this->actingAs(User::factory()->create())->get($url);

        $response->assertOk();
        expect($response->headers->get('Content-Disposition'))
        ->toContain('reservasi-embung-bansari-2026-10-01-sampai-2026-10-31.xlsx');
    });

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationController;
use App\Filament\Resources\Bookings\Exports\BookingsExcelExport;
use App\Models\About;
use App\Models\Gallery;
use App\Models\Package;

Route::redirect('/login', '/admin/login')
    ->name('login');

Route::get('/admin/bookings/export-excel', function () {
    return (new BookingsExcelExport([
        'start_date' => request()->query('start_date'),
        'end_date' => request()->query('end_date'),
        'status' => request()->query('status'),
        'reservation_type' => request()->query('reservation_type'),
    ]))->download();
})
    ->middleware('auth')
    ->name('admin.bookings.export-excel');

Route::get('/', function () {
    $packages = Package::where('is_active', true)->get();
    $galleries = Gallery::where('is_active', true)->latest()->get();
    $about = About::where('is_active', true)->latest()->first();

    return view('home', compact('packages', 'galleries', 'about'));
});

Route::view('/kontak', 'contact')
    ->name('kontak');

Route::view('/ketentuan-reservasi', 'reservation_terms')
    ->name('ketentuan.reservasi');

Route::view('/kebijakan-privasi', 'privacy_policy')
    ->name('kebijakan.privasi');

Route::view('/faq', 'faq')
    ->name('faq');

Route::get('/cek-reservasi', [ReservationController::class, 'checkReservation'])
    ->name('reservasi.check');

Route::post('/cek-reservasi', [ReservationController::class, 'searchReservation'])
    ->name('reservasi.check.search');

Route::get('/lupa-kode-booking', [ReservationController::class, 'forgotBookingCode'])
    ->name('reservasi.forgot_code');

Route::post('/lupa-kode-booking', [ReservationController::class, 'searchForgotBookingCode'])
    ->name('reservasi.forgot_code.search');

Route::get('/reservasi', [ReservationController::class, 'index'])
    ->name('reservasi');

Route::get('/reservasi/cek-duplikat', [ReservationController::class, 'checkDuplicateReservation'])
    ->name('reservasi.duplicate-check');

Route::post('/reservasi', [ReservationController::class, 'store'])
    ->name('reservasi.store');

Route::get('/reservasi/agreement', [ReservationController::class, 'agreement'])
    ->name('reservasi.agreement');

Route::post('/reservasi/continue-payment', [ReservationController::class, 'continuePayment'])
    ->name('reservasi.continue_payment');

Route::get('/reservasi/payment', [ReservationController::class, 'payment'])
    ->name('reservasi.payment');

Route::post('/reservasi/payment', [ReservationController::class, 'processPayment'])
    ->name('reservasi.payment.process');

Route::post(
    '/reservasi/payment/verify-local',
    [ReservationController::class, 'verifyPaymentLocal']
)->name('reservasi.payment.verify_local');

Route::post('/midtrans/callback', [ReservationController::class, 'midtransCallback'])
    ->name('midtrans.callback');

Route::get('/reservasi/success', [ReservationController::class, 'reservationSuccess'])
    ->name('reservasi.success');

Route::get('/reservasi/{booking_code}/invoice', [ReservationController::class, 'invoice'])
    ->name('reservasi.invoice');

Route::post(
    '/reservasi/payment/simulate-success',
    [ReservationController::class, 'simulatePaymentSuccess']
)->name('reservasi.payment.simulate_success');

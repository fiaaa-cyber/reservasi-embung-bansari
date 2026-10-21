@extends('layouts.app')

@section('title', 'Cek Status Reservasi')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Cek Status Reservasi</h1>
    <p class="mt-2 text-sm text-gray-600">Pilih salah satu cara untuk melihat status reservasi Anda.</p>

    @if(! isset($booking))
        @error('search')
            <p class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>
        @enderror

        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold">A. Cek dengan Kode Booking</h2>
                <form method="POST" action="{{ route('reservasi.check.search') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="search_type" value="booking_code">
                    <div>
                        <label for="booking_code" class="block text-sm font-semibold text-gray-700">Kode Booking</label>
                        <input
                            id="booking_code"
                            name="booking_code"
                            type="text"
                            value="{{ old('booking_code', request('booking_code')) }}"
                            maxlength="50"
                            required
                            class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                        >
                        @error('booking_code')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="rounded-md bg-amber-600 px-5 py-2 text-white">Cek Reservasi</button>
                </form>
                <div class="mt-5 border-t border-gray-100 pt-4">
                    <a href="{{ route('reservasi.forgot_code') }}" class="font-semibold text-amber-700 hover:text-amber-800">Lupa Kode Booking?</a>
                    <p class="mt-1 text-sm text-gray-600">Klik di sini untuk mencari kembali kode booking menggunakan nama, email, dan nomor HP.</p>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold">B. Cek dengan Data Pemesan</h2>
                <form method="POST" action="{{ route('reservasi.check.search') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="search_type" value="customer">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700">Nama</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            maxlength="255"
                            required
                            class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                        >
                        @error('name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-semibold text-gray-700">Nomor HP</label>
                        <input
                            id="phone"
                            name="phone"
                            type="text"
                            value="{{ old('phone') }}"
                            maxlength="20"
                            required
                            class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                        >
                        @error('phone')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="rounded-md bg-amber-600 px-5 py-2 text-white">Cek Reservasi</button>
                </form>
            </section>
        </div>
    @else
        @php
            $payment = $booking->payment;
            $bookingTotal = (float) $booking->total_price;
            $paymentAmount = $payment ? (float) $payment->amount : 0;
            $remainingAmount = max(0, $bookingTotal - $paymentAmount);
            $paymentStatusLabels = [
                'unpaid' => 'Belum Dibayar',
                'dp' => 'DP 50%',
                'paid' => 'Lunas',
                'pending' => 'Menunggu Pembayaran',
                'failed' => 'Gagal',
                'cancelled' => 'Dibatalkan',
            ];
            $bookingStatusLabels = [
                'pending_confirmation' => 'Menunggu Konfirmasi',
                'confirmed' => 'Dikonfirmasi',
                'cancelled' => 'Dibatalkan',
                'checked_in' => 'Sudah Check-in',
                'checked_out' => 'Sudah Check-out',
                'no_show' => 'No-show',
            ];
            $bookingStatus = $bookingStatusLabels[$booking->status] ?? ucfirst((string) $booking->status);
            $paymentStatus = $paymentStatusLabels[$payment?->status] ?? ucfirst((string) ($payment?->status ?? 'Belum tercatat'));
        @endphp

        <div class="mt-6 grid grid-cols-1 gap-6">
            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Informasi Reservasi</h2>
                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm text-gray-700 sm:grid-cols-2">
                    <div>
                        <dt class="font-semibold">Kode Booking</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-3">
                            <span>{{ $booking->booking_code }}</span>
                            <button type="button" data-copy-booking-code="{{ $booking->booking_code }}" title="Salin kode booking" aria-label="Salin kode booking" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-amber-800 hover:bg-amber-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-600">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="8" y="8" width="12" height="12" rx="2" />
                                    <path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3" />
                                </svg>
                            </button>
                            <span data-copy-feedback role="status" aria-live="polite" class="text-sm text-green-700"></span>
                        </dd>
                    </div>
                    <div><dt class="font-semibold">Nama Pemesan</dt><dd>{{ $booking->name }}</dd></div>
                    <div><dt class="font-semibold">Email</dt><dd>{{ $booking->email }}</dd></div>
                    <div><dt class="font-semibold">Nomor HP</dt><dd>{{ $booking->phone }}</dd></div>
                    <div><dt class="font-semibold">Tanggal Camping</dt><dd>{{ $booking->booking_date->toDateString() }}</dd></div>
                    <div><dt class="font-semibold">Jenis Reservasi</dt><dd>{{ $booking->package ? 'Paket' : 'Tanpa Paket' }}</dd></div>
                    <div><dt class="font-semibold">Jumlah Orang</dt><dd>{{ $booking->number_of_people }}</dd></div>
                    @if($booking->package)
                        <div><dt class="font-semibold">Nama Paket</dt><dd>{{ $booking->package->name }}</dd></div>
                    @endif
                </dl>
                <div class="mt-5 flex justify-end">
                    <a href="{{ route('reservasi.invoice', $booking->booking_code) }}" class="inline-flex min-h-10 w-full items-center justify-center rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 sm:w-auto">Download Invoice</a>
                </div>
            </section>

            @if(! $booking->package)
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-lg font-semibold">Perlengkapan</h2>
                    @if($booking->bookingEquipment->isEmpty())
                        <p class="mt-4 text-sm text-gray-600">Tidak ada perlengkapan yang dipesan.</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-700">
                                <thead><tr class="border-b"><th class="py-2">Perlengkapan</th><th class="py-2">Jumlah</th><th class="py-2">Subtotal</th></tr></thead>
                                <tbody>
                                    @foreach($booking->bookingEquipment as $item)
                                        <tr class="border-b">
                                            <td class="py-2">{{ $item->equipment?->name ?? 'Perlengkapan #' . $item->equipment_id }}</td>
                                            <td class="py-2">{{ $item->quantity }}</td>
                                            <td class="py-2">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Informasi Pembayaran</h2>
                <dl class="mt-4 space-y-3 text-sm text-gray-700">
                    <div><dt class="font-semibold">Metode Pembayaran</dt><dd>{{ \App\Support\PaymentMethodLabels::label($payment?->payment_method) }}</dd></div>
                    <div><dt class="font-semibold">Nominal Tercatat</dt><dd>Rp {{ number_format($paymentAmount, 0, ',', '.') }}</dd></div>
                    <div><dt class="font-semibold">Status Pembayaran</dt><dd>{{ $paymentStatus }}</dd></div>
                    @if($payment?->transaction_id)
                        <div><dt class="font-semibold">ID Transaksi</dt><dd>{{ $payment->transaction_id }}</dd></div>
                    @endif
                </dl>
                @if($payment?->status === 'dp')
                    <p class="mt-4 text-sm text-amber-700">Sisa pembayaran: Rp {{ number_format($remainingAmount, 0, ',', '.') }}</p>
                @elseif($payment?->status === 'paid')
                    <p class="mt-4 text-sm text-green-700">Pembayaran lunas.</p>
                    @if($payment?->payment_method === 'qris_dp' && $paymentAmount < $bookingTotal)
                        <p class="mt-2 text-sm text-gray-600">Nominal DP awal tetap tercatat; nominal pelunasan tidak dicatat terpisah.</p>
                    @endif
                @elseif($payment?->status === 'unpaid')
                    <p class="mt-4 text-sm text-amber-700">Pembayaran belum diterima.</p>
                @endif
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Status Reservasi</h2>
                <p class="mt-3 text-sm text-gray-700">{{ $bookingStatus }}</p>
            </section>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('reservasi.check') }}" class="rounded-md bg-amber-600 px-5 py-2 text-white">Cek Reservasi Lain</a>
            <a href="{{ url('/') }}" class="rounded-md border border-gray-300 px-5 py-2 text-gray-700">Kembali ke Halaman Utama</a>
        </div>
    @endif
</div>
<script>
    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-copy-booking-code]');
        if (!button) {
            return;
        }

        const feedback = button.parentElement.querySelector('[data-copy-feedback]');

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(button.dataset.copyBookingCode);
            } else {
                const input = document.createElement('textarea');
                input.value = button.dataset.copyBookingCode;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                const copied = document.execCommand('copy');
                input.remove();

                if (!copied) {
                    throw new Error('Clipboard copy failed.');
                }
            }

            feedback.textContent = 'Kode booking berhasil disalin.';
        } catch (error) {
            feedback.textContent = 'Kode booking gagal disalin.';
        }
    });
</script>
@endsection

@extends('layouts.app')

@section('title', 'Reservasi Berhasil')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Reservasi Berhasil</h1>

    <section class="mt-6 rounded-lg border-2 border-amber-400 bg-amber-50 p-6 text-amber-950">
        <h2 class="text-lg font-bold">Jangan lupa simpan kode booking Anda!</h2>
        <p class="mt-2 text-sm">
            Kode booking diperlukan untuk melakukan pengecekan reservasi dan saat melakukan check-in di lokasi.
        </p>
        <p class="mt-3 text-sm font-medium">
            Simpan atau screenshot halaman ini untuk memudahkan Anda saat tiba di lokasi.
        </p>
    </section>

    @if(! $booking)
        <section class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-6">
            <h2 class="text-lg font-semibold text-amber-900">Pembayaran sedang diproses</h2>
            <p class="mt-2 text-sm text-amber-800">
                Booking belum tersedia. Silakan tunggu beberapa saat sementara pembayaran dikonfirmasi.
            </p>
            @if($bookingCode)
                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-amber-900">
                    <p><strong>Kode Reservasi:</strong> <span>{{ $bookingCode }}</span></p>
                    <button type="button" data-copy-booking-code="{{ $bookingCode }}" title="Salin kode booking" aria-label="Salin kode booking" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-amber-900 hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-700">
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="8" y="8" width="12" height="12" rx="2" />
                            <path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3" />
                        </svg>
                    </button>
                    <span data-copy-feedback role="status" aria-live="polite" class="text-sm"></span>
                </div>
            @endif
        </section>
    @else
        @php
            $payment = $booking->payment;
            $paymentAmount = $payment ? (float) $payment->amount : 0;
            $bookingTotal = (float) $booking->total_price;
            $isDownPayment = $payment?->status === 'dp';
            $remainingAmount = max(0, $bookingTotal - $paymentAmount);
            $paymentStatusLabels = [
                'unpaid' => 'Belum Dibayar',
                'dp' => 'DP 50%',
                'paid' => 'Lunas',
                'pending' => 'Menunggu Pembayaran',
                'failed' => 'Gagal',
                'cancelled' => 'Dibatalkan',
            ];
            $reservationStatusLabels = [
                'pending_confirmation' => 'Menunggu Konfirmasi',
                'confirmed' => 'Dikonfirmasi',
                'cancelled' => 'Dibatalkan',
                'checked_in' => 'Sudah Check-in',
                'checked_out' => 'Sudah Check-out',
                'no_show' => 'No-show',
            ];
        @endphp

        <div class="mt-6 grid grid-cols-1 gap-6">
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-6">
                <h2 class="text-lg font-semibold text-amber-900">Status Reservasi</h2>
                <p class="mt-2 text-sm text-amber-800">
                    @if($booking->status === 'pending_confirmation')
                        Reservasi berhasil dibuat dan sedang menunggu konfirmasi admin.
                    @else
                        {{ $reservationStatusLabels[$booking->status] ?? ucfirst($booking->status) }}
                    @endif
                </p>
                <p class="mt-2 text-sm font-medium text-amber-900">{{ $reservationStatusLabels[$booking->status] ?? ucfirst($booking->status) }}</p>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Detail Reservasi</h2>
                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm text-gray-700 sm:grid-cols-2">
                    <div>
                        <dt class="font-semibold">Kode Reservasi</dt>
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
                    <div><dt class="font-semibold">Tanggal Reservasi</dt><dd>{{ $booking->booking_date->toDateString() }}</dd></div>
                    @if($booking->package)
                        <div><dt class="font-semibold">Paket</dt><dd>{{ $booking->package->name }}</dd></div>
                    @else
                        <div><dt class="font-semibold">Jumlah Orang</dt><dd>{{ $booking->number_of_people }}</dd></div>
                    @endif
                </dl>
                <div class="mt-5 flex justify-end">
                    <a href="{{ route('reservasi.invoice', $booking->booking_code) }}" class="inline-flex min-h-10 w-full items-center justify-center rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 sm:w-auto">Download Invoice</a>
                </div>
            </section>

            @if($booking->bookingEquipment->isNotEmpty())
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-lg font-semibold">Perlengkapan</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-700">
                            <thead>
                                <tr class="border-b">
                                    <th class="py-2">Nama</th>
                                    <th class="py-2">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($booking->bookingEquipment as $item)
                                    <tr class="border-b">
                                        <td class="py-2">{{ $item->equipment?->name ?? 'Perlengkapan #' . $item->equipment_id }}</td>
                                        <td class="py-2">{{ $item->quantity }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Pembayaran</h2>
                <dl class="mt-4 space-y-3 text-sm text-gray-700">
                    <div><dt class="font-semibold">Total Harga Reservasi</dt><dd>Rp {{ number_format($bookingTotal, 0, ',', '.') }}</dd></div>
                    <div><dt class="font-semibold">Metode Pembayaran</dt><dd>{{ \App\Support\PaymentMethodLabels::label($payment?->payment_method) }}</dd></div>
                    <div><dt class="font-semibold">Nominal Tercatat</dt><dd>Rp {{ number_format($paymentAmount, 0, ',', '.') }}</dd></div>
                    <div><dt class="font-semibold">Status Pembayaran</dt><dd>{{ $paymentStatusLabels[$payment?->status] ?? 'Belum tercatat' }}</dd></div>
                </dl>
                @if($isDownPayment)
                    <p class="mt-4 text-sm text-amber-700">
                        Pembayaran yang diterima adalah DP 50%. Sisa pembayaran: Rp {{ number_format($remainingAmount, 0, ',', '.') }}.
                    </p>
                @elseif($payment?->status === 'paid')
                    <p class="mt-4 text-sm text-green-700">Pembayaran lunas.</p>
                    @if($payment?->payment_method === 'qris_dp' && $paymentAmount < $bookingTotal)
                        <p class="mt-2 text-sm text-gray-600">Nominal DP awal tetap tercatat; nominal pelunasan tidak dicatat terpisah.</p>
                    @endif
                @elseif($payment?->status === 'unpaid')
                    <p class="mt-4 text-sm text-amber-700">Pembayaran belum diterima.</p>
                @endif
            </section>
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

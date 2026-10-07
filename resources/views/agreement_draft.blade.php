@extends('layouts.app')

@section('title', 'Agreement Reservasi (Draft)')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Konfirmasi Reservasi (Draft)</h1>
    <p class="mt-2 text-gray-600">Ini adalah draft reservasi. Periksa data sebelum melanjutkan pembayaran.</p>
    <div class="mt-6 grid grid-cols-1 gap-6">
        <div class="bg-white p-6 rounded-lg border border-gray-200">
            <h2 class="text-lg font-semibold mb-2">Ringkasan Reservasi</h2>
            <div class="text-sm text-gray-700 space-y-2">
                <div><strong>Kode Reservasi:</strong> {{ $draft->draft_code }}</div>
                <div><strong>Tanggal Camping:</strong> {{ \Illuminate\Support\Carbon::parse($draft->booking_date)->toDateString() }}</div>
                <div><strong>Nama Pemesan:</strong> {{ $draft->name }}</div>
                <div><strong>Email:</strong> {{ $draft->email }}</div>
                <div><strong>Nomor HP:</strong> {{ $draft->phone }}</div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg border border-gray-200">
            <h2 class="text-lg font-semibold mb-2">Detail Reservasi</h2>

            @if($draft->reservation_type === 'package')
                <div class="text-sm text-gray-700 space-y-2">
                    <div><strong>Paket:</strong> {{ $draft->package ? $draft->package->name : '-' }}</div>
                    <div><strong>Kapasitas Paket:</strong> {{ $draft->package ? $draft->package->capacity : '-' }} orang</div>
                    <div><strong>Harga Paket:</strong> Rp {{ number_format($draft->package ? $draft->package->price : $draft->total_package, 0, ',', '.') }}</div>
                    <div class="mt-2 text-sm text-gray-600">Perlengkapan sudah termasuk dalam paket.</div>
                </div>
            @else
                <div class="text-sm text-gray-700 space-y-2">
                    <div><strong>Jumlah Orang:</strong> {{ $draft->number_of_people }}</div>
                    @php $rate = config('reservasi.rate_per_person', 15000); @endphp
                    <div><strong>Biaya Camping:</strong> Rp {{ number_format(($draft->number_of_people ?? 0) * $rate, 0, ',', '.') }}</div>
                </div>

                <div class="mt-4">
                    <h3 class="font-semibold">Perlengkapan Tambahan</h3>
                    @if(empty($draft->equipment))
                        <div class="text-sm text-gray-600">Tidak ada perlengkapan tambahan.</div>
                    @else
                        <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead>
                                <tr>
                                    <th class="py-2">Nama</th>
                                    <th class="py-2">Jumlah</th>
                                    <th class="py-2">Harga Satuan</th>
                                    <th class="py-2">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($draft->equipment as $item)
                                    @php
                                        $eq = \App\Models\Equipment::find($item['equipment_id']);
                                        $name = $eq ? $eq->name : 'Perlengkapan #' . $item['equipment_id'];
                                        $price = $eq ? $eq->price : ($item['price'] ?? 0);
                                        $qty = $item['quantity'] ?? 0;
                                        $subtotal = $price * $qty;
                                    @endphp
                                    <tr class="border-t">
                                        <td class="py-2">{{ $name }}</td>
                                        <td class="py-2">{{ $qty }}</td>
                                        <td class="py-2">Rp {{ number_format($price, 0, ',', '.') }}</td>
                                        <td class="py-2">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="bg-white p-6 rounded-lg border border-gray-200">
            <h2 class="text-lg font-semibold mb-2">Rincian Biaya</h2>
            <div class="text-sm text-gray-700 space-y-2">
                <div><strong>Total Camping/Paket:</strong> Rp {{ number_format($draft->total_package, 0, ',', '.') }}</div>
                <div><strong>Total Perlengkapan:</strong> Rp {{ number_format($draft->total_equipment, 0, ',', '.') }}</div>
                <div class="mt-2 text-lg font-semibold"><strong>TOTAL PEMBAYARAN:</strong> Rp {{ number_format($draft->total_price, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Agreement</h2>
            <p class="mt-2 text-sm text-gray-600">Pastikan data reservasi Anda sudah benar sebelum melanjutkan pembayaran.</p>
            <div class="mt-5 text-right">
                <button id="open-agreement" type="button" class="rounded-md bg-amber-600 px-5 py-2 font-semibold text-white">Lanjut ke Pembayaran</button>
            </div>
        </div>
    </div>
</div>

<div id="agreement-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="agreement-title">
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-7">
            <div>
                <h2 id="agreement-title" class="text-xl font-semibold">Ketentuan Reservasi Camping</h2>
                <p class="mt-1 text-sm text-gray-600">Embung Bansari</p>
            </div>
            <button id="close-agreement" type="button" aria-label="Tutup ketentuan" class="rounded-md border border-gray-300 px-3 py-1 text-2xl leading-none text-gray-700 hover:bg-gray-50">X</button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-7" tabindex="0">
            @include('components.reservation-terms')
        </div>

        <footer class="shrink-0 border-t border-gray-200 bg-white px-5 py-4 sm:px-7">
            <form method="POST" action="{{ route('reservasi.continue_payment') }}">
                @csrf
                <input type="hidden" name="draft_code" value="{{ $draft->draft_code }}" />
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="agree" value="1" required class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500" @checked(old('agree')) />
                    <span class="text-sm text-gray-700">Saya telah membaca dan menyetujui seluruh ketentuan reservasi.</span>
                </label>
                @error('agree')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button id="cancel-agreement" type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit" class="rounded-md bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700">Saya Setuju &amp; Lanjutkan</button>
                </div>
            </form>
        </footer>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('agreement-modal');
        const openButton = document.getElementById('open-agreement');
        const closeButtons = [
            document.getElementById('close-agreement'),
            document.getElementById('cancel-agreement')
        ];

        function closeModal() {
            modal.classList.add('hidden');
            openButton.focus();
        }

        openButton.addEventListener('click', function () {
            modal.classList.remove('hidden');
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    })();
</script>

@endsection

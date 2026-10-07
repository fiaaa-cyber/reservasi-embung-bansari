@extends('layouts.app')

@section('title', 'Pembayaran Reservasi')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Pembayaran Reservasi</h1>

    @if($errors->any())
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6">
        <section class="rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Data Reservasi</h2>
            <dl class="mt-4 grid grid-cols-1 gap-3 text-sm text-gray-700 sm:grid-cols-2">
                <div><dt class="font-semibold">Kode Reservasi/Draft</dt><dd>{{ $draft->draft_code }}</dd></div>
                <div><dt class="font-semibold">Tanggal Camping</dt><dd>{{ $draft->booking_date->toDateString() }}</dd></div>
                <div><dt class="font-semibold">Nama Pemesan</dt><dd>{{ $draft->name }}</dd></div>
                <div><dt class="font-semibold">Email</dt><dd>{{ $draft->email }}</dd></div>
                <div><dt class="font-semibold">Nomor HP</dt><dd>{{ $draft->phone }}</dd></div>
            </dl>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Ringkasan Pesanan</h2>
            @if($draft->reservation_type === 'package')
                <div class="mt-4 space-y-2 text-sm text-gray-700">
                    <div><strong>Nama Paket:</strong> {{ $draft->package?->name ?? '-' }}</div>
                    <div><strong>Harga Paket:</strong> Rp {{ number_format($draft->total_package, 0, ',', '.') }}</div>
                    <p class="text-gray-600">Perlengkapan sudah termasuk dalam paket.</p>
                </div>
            @else
                <div class="mt-4 space-y-2 text-sm text-gray-700">
                    <div><strong>Jumlah Orang:</strong> {{ $draft->number_of_people }}</div>
                    <div><strong>Biaya Camping:</strong> Rp {{ number_format($draft->total_package, 0, ',', '.') }}</div>
                </div>
                @if(empty($draft->equipment))
                    <p class="mt-4 text-sm text-gray-600">Tidak ada equipment tambahan.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-700">
                            <thead><tr class="border-b"><th class="py-2">Equipment</th><th class="py-2">Quantity</th><th class="py-2">Harga Satuan</th><th class="py-2">Subtotal</th></tr></thead>
                            <tbody>
                                @foreach($draft->equipment as $item)
                                    @php
                                        $quantity = (int) ($item['quantity'] ?? 0);
                                        $unitPrice = (float) ($item['price'] ?? 0);
                                        $subtotal = $unitPrice * $quantity;
                                        $equipmentName = $equipment->get($item['equipment_id'])?->name ?? 'Equipment #' . ($item['equipment_id'] ?? '-');
                                    @endphp
                                    <tr class="border-b">
                                        <td class="py-2">{{ $equipmentName }}</td>
                                        <td class="py-2">{{ $quantity }}</td>
                                        <td class="py-2">Rp {{ number_format($unitPrice, 0, ',', '.') }}</td>
                                        <td class="py-2">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-right text-sm font-semibold">Total Equipment: Rp {{ number_format($draft->total_equipment, 0, ',', '.') }}</div>
                @endif
            @endif
            <div class="mt-6 border-t pt-4 text-lg font-semibold">Total Pembayaran: Rp {{ number_format($draft->total_price, 0, ',', '.') }}</div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Pilih Metode Pembayaran</h2>
            <form method="POST" action="{{ route('reservasi.payment.process') }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="draft_code" value="{{ $draft->draft_code }}">
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-gray-200 p-4">
                    <input type="radio" name="payment_method" value="qris_full" required class="mt-1" @checked(old('payment_method') === 'qris_full')>
                    <span><strong>Pembayaran Online - Lunas</strong><span class="block text-sm text-gray-600">Bayar melalui metode pembayaran yang tersedia di Midtrans.</span><span class="block text-sm font-semibold">Rp {{ number_format($draft->total_price, 0, ',', '.') }}</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-gray-200 p-4">
                    <input type="radio" name="payment_method" value="qris_dp" class="mt-1" @checked(old('payment_method') === 'qris_dp')>
                    <span><strong>Pembayaran Online - DP 50%</strong><span class="block text-sm text-gray-600">Bayar melalui metode pembayaran yang tersedia di Midtrans.</span><span class="block text-sm font-semibold">Rp {{ number_format((float) $draft->total_price * 0.5, 0, ',', '.') }}</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-gray-200 p-4">
                    <input type="radio" name="payment_method" value="cash" class="mt-1" @checked(old('payment_method') === 'cash')>
                    <span><strong>Tunai</strong><span class="block text-sm text-gray-600">Pembayaran dilakukan secara tunai</span><span class="block text-sm font-semibold">Rp {{ number_format($draft->total_price, 0, ',', '.') }}</span></span>
                </label>
                <button type="submit" class="mt-3 rounded-md bg-amber-600 px-5 py-2 text-white">Lanjutkan</button>
            </form>
        </section>
    </div>
</div>
@endsection

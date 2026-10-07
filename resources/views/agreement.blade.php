
@extends('layouts.app')

@section('title', 'Agreement Reservasi')

@section('content')

<div class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Konfirmasi Reservasi</h1>
    <p class="mt-2 text-gray-600">Pastikan data reservasi di bawah ini sudah benar sebelum melanjutkan pembayaran.</p>

    <div class="mt-6 bg-white p-6 rounded-lg border border-gray-200">
        <div class="space-y-2 text-sm text-gray-700">
            <div><strong>Kode Booking:</strong> {{ $booking->booking_code }}</div>
            <div><strong>Tanggal Camping:</strong> {{ \Illuminate\Support\Carbon::parse($booking->booking_date)->toDateString() }}</div>
            <div><strong>Nama Pemesan:</strong> {{ $booking->name }}</div>
            <div><strong>Email:</strong> {{ $booking->email }}</div>
            <div><strong>Nomor HP:</strong> {{ $booking->phone }}</div>
        </div>

        <hr class="my-4" />

        @if($booking->package)
            <div class="mb-4">
                <h3 class="font-semibold">Paket Camping</h3>
                <div class="text-sm text-gray-700">
                    <div><strong>Nama Paket:</strong> {{ $booking->package->name }}</div>
                    <div><strong>Harga Paket:</strong> Rp {{ number_format($booking->package->price, 0, ',', '.') }}</div>
                    <div><strong>Kapasitas Paket:</strong> {{ $booking->package->capacity }} orang</div>
                </div>
            </div>
        @else
            <div class="mb-4">
                <h3 class="font-semibold">Camping Tanpa Paket</h3>
                @php $rate = config('reservasi.rate_per_person', 15000); @endphp
                <div class="text-sm text-gray-700">
                    <div><strong>Jumlah Orang:</strong> {{ $booking->number_of_people }}</div>
                    <div><strong>Tarif per Orang:</strong> Rp {{ number_format($rate, 0, ',', '.') }}</div>
                    <div><strong>Subtotal Camping:</strong> Rp {{ number_format(($booking->number_of_people ?? 0) * $rate, 0, ',', '.') }}</div>
                </div>
            </div>
        @endif

        <div class="mb-4">
            <h3 class="font-semibold">Perlengkapan</h3>
            @if($booking->bookingEquipment->isEmpty())
                <div class="text-sm text-gray-600">Tidak ada perlengkapan tambahan.</div>
            @else
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
                        @foreach($booking->bookingEquipment as $be)
                            <tr class="border-t">
                                <td class="py-2">{{ $be->equipment->name }}</td>
                                <td class="py-2">{{ $be->quantity }}</td>
                                <td class="py-2">Rp {{ number_format($be->price, 0, ',', '.') }}</td>
                                <td class="py-2">Rp {{ number_format($be->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="pt-4 border-t text-sm text-gray-700">
            <div><strong>Total Camping/Paket:</strong> Rp {{ number_format($booking->total_package, 0, ',', '.') }}</div>
            <div><strong>Total Perlengkapan:</strong> Rp {{ number_format($booking->total_equipment, 0, ',', '.') }}</div>
            <div class="mt-2 text-lg font-semibold"><strong>TOTAL PEMBAYARAN:</strong> Rp {{ number_format($booking->total_price, 0, ',', '.') }}</div>
        </div>

        <form class="mt-6">
            <p class="text-sm text-gray-600">Pastikan data reservasi Anda sudah benar.</p>
            <div class="mt-3 flex items-center space-x-3">
                <input type="checkbox" id="agree" name="agree" class="form-checkbox" />
                <label for="agree" class="text-sm">Saya menyetujui data reservasi dan ketentuan camping.</label>
            </div>

            <div class="mt-6 text-right">
                <a href="#" class="inline-block bg-amber-600 text-white px-5 py-2 rounded-md">Lanjutkan Pembayaran</a>
            </div>
        </form>
    </div>
</div>

@endsection

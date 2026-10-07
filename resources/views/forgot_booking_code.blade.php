@extends('layouts.app')

@section('title', 'Lupa Kode Booking')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Lupa Kode Booking?</h1>
    <p class="mt-2 text-sm text-gray-600">Masukkan nama, email, dan nomor HP yang digunakan saat melakukan reservasi.</p>

    @if(! isset($booking))
        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6">
            <form method="POST" action="{{ route('reservasi.forgot_code.search') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-semibold text-gray-700">Nama Lengkap</label>
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
                    <label for="email" class="block text-sm font-semibold text-gray-700">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        maxlength="255"
                        required
                        class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    >
                    @error('email')
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
                <button type="submit" class="rounded-md bg-amber-600 px-5 py-2 text-white">Cari Kode Booking</button>
            </form>
        </section>
    @else
        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Kode Booking Anda</h2>
            <p class="mt-3 text-2xl font-bold text-amber-700">{{ $booking->booking_code }}</p>
            <dl class="mt-5 space-y-3 text-sm text-gray-700">
                <div><dt class="font-semibold">Nama</dt><dd>{{ $booking->name }}</dd></div>
                <div><dt class="font-semibold">Tanggal Camping</dt><dd>{{ $booking->booking_date->toDateString() }}</dd></div>
                @if($booking->package)
                    <div><dt class="font-semibold">Nama Paket</dt><dd>{{ $booking->package->name }}</dd></div>
                @endif
            </dl>
        </section>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('reservasi.check', ['booking_code' => $booking->booking_code]) }}" class="rounded-md bg-amber-600 px-5 py-2 text-white">Cek Detail Reservasi</a>
            <a href="{{ url('/') }}" class="rounded-md border border-gray-300 px-5 py-2 text-gray-700">Kembali ke Beranda</a>
        </div>
    @endif
</div>
@endsection

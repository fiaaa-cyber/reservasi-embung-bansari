@extends('layouts.app')

@section('title', 'Embung Bansari - Reservasi Camping')

@section('content')

<!-- HERO -->
<section class="relative bg-gray-800 text-white">
    <div class="absolute inset-0 bg-[url('https://via.placeholder.com/1600x900.png?text=Embung+Bansari')] bg-center bg-cover opacity-60"></div>
    <div class="relative max-w-7xl mx-auto px-4 py-24">
        <span class="inline-block bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full text-sm">Camping di Embung Bansari</span>
        <h1 class="mt-6 text-3xl md:text-4xl font-bold">Rasakan Serunya Camping dengan Pemandangan Alam Embung Bansari</h1>
        <p class="mt-4 max-w-2xl text-gray-100">Nikmati malam di bawah bintang dengan suasana tenang dan pemandangan embung yang memukau.</p>
        <div class="mt-6 flex flex-col sm:flex-row sm:space-x-4 space-y-3 sm:space-y-0">
            <a href="{{ route('reservasi') }}" class="inline-block bg-emerald-600 text-white px-5 py-3 rounded-md">Pesan Sekarang</a>
            <a href="#paket" class="inline-block bg-white text-emerald-700 px-5 py-3 rounded-md">Lihat Paket</a>
        </div>
    </div>
</section>

<!-- TENTANG -->
<section id="tentang" class="py-16">
    <div class="max-w-7xl mx-auto px-4 grid md:grid-cols-2 gap-8 items-center">
        <div>
            <h2 class="text-2xl font-semibold">Tentang Embung Bansari</h2>
            <p class="mt-4 text-gray-700">Embung Bansari menawarkan pengalaman camping yang dekat dengan alam, cocok untuk keluarga dan kelompok teman. Area yang luas, pemandangan indah, dan fasilitas dasar yang mendukung kenyamanan.</p>
            <ul class="mt-4 space-y-2 text-gray-600">
                <li>• Pemandangan alam yang menenangkan</li>
                <li>• Area camping yang rapi dan aman</li>
                <li>• Suasana nyaman untuk keluarga</li>
                <li>• Cocok untuk acara kecil dan berkumpul</li>
            </ul>
        </div>
        <div>
            @if($about && $about->image)
                <img src="{{ asset('storage/' . $about->image) }}" alt="{{ $about->title }}" class="w-full h-64 md:h-96 object-cover rounded-lg shadow-sm">
            @else
                <div class="w-full h-64 md:h-96 flex items-center justify-center rounded-lg bg-gray-200 shadow-sm">
                    <span class="text-gray-500">Foto belum tersedia</span>
                </div>
            @endif
        </div>
    </div>
</section>

<!-- PAKET -->
<section id="paket" class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-2xl font-semibold">Pilihan Paket Camping</h2>
        <p class="mt-2 text-gray-600">Pilih paket yang sesuai dengan kebutuhanmu. Semua paket sudah termasuk area camping dan fasilitas dasar.</p>

        <div class="mt-8 grid gap-6 grid-cols-1 md:grid-cols-3">
            @foreach($packages as $index => $package)
                <div class="p-6 rounded-lg shadow-sm bg-white border border-gray-200 transition duration-200 ease-in-out hover:border-emerald-500 hover:bg-emerald-50">
                    <h3 class="text-lg font-semibold text-emerald-700">{{ $package->name }}</h3>
                    <p class="mt-2 text-gray-600">Rp {{ number_format((float) $package->price, 0, ',', '.') }}</p>
                    <p class="mt-1 text-sm text-gray-500">Maks. {{ $package->capacity }} orang</p>
                    <p class="mt-3 text-sm text-gray-600">{{ $package->description }}</p>
                    <div class="mt-4">
                        <a href="{{ route('reservasi') }}" class="inline-block bg-emerald-600 text-white px-4 py-2 rounded-md">Pesan Sekarang</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- FASILITAS -->
<section id="fasilitas" class="py-16">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-2xl font-semibold">Fasilitas yang Tersedia</h2>
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            @php
                $fasilitas = [
                    ['icon' => '🏕️','name'=>'Area Camping','desc'=>'Lahan datar untuk mendirikan tenda.'],
                    ['icon' => '🚻','name'=>'Toilet','desc'=>'Toilet umum yang terawat.'],
                    ['icon' => '🅿️','name'=>'Area Parkir','desc'=>'Area parkir luas dan aman.'],
                    ['icon' => '🕌','name'=>'Mushola','desc'=>'Tempat ibadah sederhana.'],
                    ['icon' => '🌄','name'=>'Pemandangan Alam','desc'=>'Spot foto dengan panorama embung.'],
                    ['icon' => '🔥','name'=>'Area Api Unggun','desc'=>'Area khusus untuk api unggun.'],
                ];
            @endphp

            @foreach($fasilitas as $f)
                <div class="group p-4 bg-white rounded-lg shadow-sm border border-gray-200 transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-lg">
                    <div class="text-3xl transition-transform duration-300 ease-out group-hover:scale-105">{{ $f['icon'] }}</div>
                    <h4 class="mt-3 font-semibold">{{ $f['name'] }}</h4>
                    <p class="mt-1 text-sm text-gray-600">{{ $f['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- GALERI -->
<section id="galeri" class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-2xl font-semibold">Galeri Embung Bansari</h2>
        <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 gap-4">
            @forelse($galleries as $gallery)
                <div class="group overflow-hidden rounded-lg h-40 bg-gray-200 transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-lg">
                    <img src="{{ asset('storage/' . $gallery->image) }}" alt="{{ $gallery->title }}" class="w-full h-full object-cover transition-transform duration-300 ease-out group-hover:scale-105">
                </div>
            @empty
                <p class="col-span-full text-sm text-gray-600">Belum ada foto galeri.</p>
            @endforelse
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-12">
    <div class="max-w-7xl mx-auto px-4 text-center bg-emerald-50 p-8 rounded-lg">
        <h3 class="text-xl font-semibold">Siap Menikmati Pengalaman Camping di Embung Bansari?</h3>
        <p class="mt-2 text-gray-700">Pesan tempat campingmu sekarang dan nikmati suasana alam bersama orang-orang terdekat.</p>
        <div class="mt-4">
            <a href="{{ route('reservasi') }}" class="inline-block bg-emerald-600 text-white px-6 py-3 rounded-md">Pesan Sekarang</a>
        </div>
    </div>
</section>

@endsection

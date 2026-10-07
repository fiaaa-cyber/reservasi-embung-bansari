@extends('layouts.app')

@section('title', 'Kontak - Embung Bansari')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Kontak</h1>
    <p class="mt-2 text-gray-600">Informasi kontak yang tersedia pada website Embung Bansari.</p>

    <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold">Informasi Kontak</h2>
        <dl class="mt-4 space-y-4 text-sm text-gray-700">
            <div>
                <dt class="font-semibold">Telepon / WhatsApp</dt>
                <dd class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                    <a href="tel:085385737428" class="text-emerald-700 hover:underline">085385737428</a>
                    <a href="https://wa.me/6285385737428" class="text-emerald-700 hover:underline">Hubungi via WhatsApp</a>
                </dd>
            </div>
            <div>
                <dt class="font-semibold">Email</dt>
                <dd class="mt-1"><a href="mailto:embungbansari.bansari@gmail.com" class="text-emerald-700 hover:underline">embungbansari.bansari@gmail.com</a></dd>
            </div>
            <div>
                <dt class="font-semibold">Alamat</dt>
                <dd class="mt-1">Unnamed Road, Balai Desa Pongangan, Sawah, Bansari, Kec. Bansari, Kabupaten Temanggung, Jawa Tengah 56265</dd>
            </div>
        </dl>
        <a href="https://www.google.com/maps/place/Embung+Bansari/@-7.2891718,110.0319807,17z/data=!4m6!3m5!1s0x2e7075b4e3efb8cd:0x908ec2676652a817!8m2!3d-7.2891771!4d110.034561!16s%2Fg%2F11j1f18vlm?entry=ttu&g_ep=EgoyMDI2MDkzMC4wIKXMDSoASAFQAw%3D%3D" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Lihat Lokasi di Google Maps</a>
    </section>

    <section class="mt-6 border-t border-gray-200 pt-5">
        <h2 class="text-lg font-semibold">Layanan Reservasi</h2>
        <div class="mt-3 flex flex-wrap gap-3 text-sm">
            <a href="{{ route('reservasi') }}" class="rounded-md bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700">Buat Reservasi</a>
            <a href="{{ route('reservasi.check') }}" class="rounded-md border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50">Cek Reservasi</a>
        </div>
    </section>
</div>
@endsection
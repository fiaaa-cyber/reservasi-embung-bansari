@extends('layouts.app')

@section('title', 'Kebijakan Privasi - Embung Bansari')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Kebijakan Privasi</h1>
    <p class="mt-2 text-gray-600">Informasi mengenai data yang digunakan dalam proses reservasi.</p>

    <article class="mt-6 space-y-6 text-sm leading-6 text-gray-700">
        <section>
            <h2 class="text-lg font-semibold text-gray-900">Data yang digunakan</h2>
            <p class="mt-2">Saat melakukan reservasi, sistem meminta nama, nomor telepon, email, tanggal reservasi, serta rincian yang diperlukan seperti pilihan paket, jumlah orang, dan equipment yang dipilih.</p>
        </section>

        <section class="border-t border-gray-200 pt-5">
            <h2 class="text-lg font-semibold text-gray-900">Penggunaan data</h2>
            <p class="mt-2">Data tersebut digunakan untuk membuat dan mengelola reservasi, menampilkan informasi saat pengunjung mengecek reservasi, serta menyiapkan bukti reservasi. Informasi nama, email, dan nomor telepon juga diteruskan ke Midtrans untuk pemrosesan pembayaran online.</p>
        </section>

        <section class="border-t border-gray-200 pt-5">
            <h2 class="text-lg font-semibold text-gray-900">Informasi pembayaran</h2>
            <p class="mt-2">Sistem menyimpan metode pembayaran, nominal, status, waktu pembayaran jika tersedia, dan referensi transaksi yang diterima dalam proses pembayaran. Pembayaran online diproses melalui Midtrans.</p>
        </section>

        <section class="border-t border-gray-200 pt-5">
            <h2 class="text-lg font-semibold text-gray-900">Penggunaan terbatas pada layanan</h2>
            <p class="mt-2">Website menggunakan data yang diminta untuk kebutuhan reservasi dan pembayaran terkait. Website ini tidak menawarkan pendaftaran akun, newsletter, OTP, atau pelacakan promosi.</p>
        </section>
    </article>
</div>
@endsection
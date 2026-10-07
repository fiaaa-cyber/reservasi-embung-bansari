@extends('layouts.app')

@section('title', 'FAQ - Embung Bansari')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold">Pertanyaan Umum</h1>
    <p class="mt-2 text-gray-600">Informasi singkat tentang fitur reservasi yang tersedia.</p>

    <div class="mt-6 divide-y divide-gray-200">
        <section class="py-5">
            <h2 class="font-semibold">Bagaimana cara melakukan reservasi?</h2>
            <p class="mt-2 text-sm leading-6 text-gray-700">Buka halaman reservasi, pilih paket atau reservasi tanpa paket, isi data pemesan dan tanggal, lalu periksa ringkasan dan setujui ketentuan sebelum memilih pembayaran.</p>
        </section>
        <section class="py-5">
            <h2 class="font-semibold">Apakah pengunjung harus membuat akun?</h2>
            <p class="mt-2 text-sm leading-6 text-gray-700">Tidak. Form reservasi dan pengecekan reservasi tersedia tanpa proses pendaftaran akun.</p>
        </section>
        <section class="py-5">
            <h2 class="font-semibold">Apa perbedaan paket camping dan tanpa paket?</h2>
            <p class="mt-2 text-sm leading-6 text-gray-700">Reservasi paket mengikuti rincian paket yang dipilih. Pada reservasi tanpa paket, pengunjung mengisi jumlah orang dan dapat memilih equipment tambahan yang tersedia.</p>
        </section>
        <section class="py-5">
            <h2 class="font-semibold">Bagaimana pembayaran dilakukan?</h2>
            <p class="mt-2 text-sm leading-6 text-gray-700">Form menyediakan Pembayaran Online - Lunas, Pembayaran Online - DP 50%, dan Tunai. Pembayaran online diproses melalui metode yang tersedia di Midtrans.</p>
        </section>
        <section class="py-5">
            <h2 class="font-semibold">Bagaimana cara mengecek reservasi?</h2>
            <p class="mt-2 text-sm leading-6 text-gray-700">Gunakan menu Cek Reservasi. Pencarian dapat dilakukan dengan kode booking atau data nama dan nomor telepon.</p>
        </section>
        <section class="py-5">
            <h2 class="font-semibold">Apa yang dilakukan jika lupa kode booking?</h2>
            <p class="mt-2 text-sm leading-6 text-gray-700">Gunakan menu Lupa Kode Booking dan isi nama, email, serta nomor telepon yang digunakan saat reservasi.</p>
        </section>
        <section class="py-5">
            <h2 class="font-semibold">Bagaimana cara mendapatkan invoice?</h2>
            <p class="mt-2 text-sm leading-6 text-gray-700">Jika data booking ditemukan, tombol Download Invoice tersedia di halaman hasil reservasi dan halaman Cek Reservasi.</p>
        </section>
        <section class="py-5">
            <h2 class="font-semibold">Bagaimana pembatalan reservasi dilakukan?</h2>
            <p class="mt-2 text-sm leading-6 text-gray-700">Website tidak menyediakan tombol pembatalan untuk pengunjung. Status pembatalan dikelola admin; hubungi admin melalui kanal resmi yang Anda miliki untuk menyampaikan permintaan pembatalan.</p>
        </section>
    </div>
</div>
@endsection
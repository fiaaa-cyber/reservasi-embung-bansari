<nav class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-4">
                <a href="/" class="text-2xl font-semibold text-emerald-700">Embung Bansari</a>
            </div>

            <div class="hidden md:flex items-center space-x-6">
                <a href="/" class="text-gray-700 hover:text-emerald-600">Beranda</a>
                <a href="/#paket" class="text-gray-700 hover:text-emerald-600">Paket Camping</a>
                <a href="/#fasilitas" class="text-gray-700 hover:text-emerald-600">Fasilitas</a>
                <a href="/#galeri" class="text-gray-700 hover:text-emerald-600">Galeri</a>
                <a href="{{ route('reservasi.check') }}" class="text-gray-700 hover:text-emerald-600">Cek Reservasi</a>
                <a href="{{ route('reservasi') }}" class="inline-block bg-emerald-600 text-white px-4 py-2 rounded-md hover:bg-emerald-700">Pesan Sekarang</a>
            </div>

            <div class="md:hidden">
                <div class="flex flex-col items-end space-y-1">
                    <a href="/" class="text-gray-700">Beranda</a>
                    <a href="/#paket" class="text-gray-700">Paket Camping</a>
                    <a href="/#fasilitas" class="text-gray-700">Fasilitas</a>
                    <a href="/#galeri" class="text-gray-700">Galeri</a>
                    <a href="{{ route('reservasi.check') }}" class="text-gray-700">Cek Reservasi</a>
                    <a href="{{ route('reservasi') }}" class="inline-block bg-emerald-600 text-white px-3 py-2 rounded-md">Pesan Sekarang</a>
                </div>
            </div>
        </div>
    </div>
</nav>

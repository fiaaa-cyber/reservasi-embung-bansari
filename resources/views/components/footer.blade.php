<footer class="bg-gray-50 text-gray-700">
    <div class="max-w-7xl mx-auto px-4 py-12 grid md:grid-cols-4 gap-8">
        <div>
            <h3 class="text-xl font-semibold text-emerald-700">Embung Bansari</h3>
            <p class="mt-2 text-sm">Destinasi camping yang menawarkan pemandangan alam, suasana tenang, dan pengalaman outdoor untuk keluarga serta teman.</p>
        </div>

        <div>
            <h4 class="font-semibold">Navigasi</h4>
            <ul class="mt-2 space-y-1 text-sm">
                <li><a href="/" class="hover:underline">Beranda</a></li>
                <li><a href="/#paket" class="hover:underline">Paket Camping</a></li>
                <li><a href="/#fasilitas" class="hover:underline">Fasilitas</a></li>
                <li><a href="/#galeri" class="hover:underline">Galeri</a></li>
            </ul>
        </div>

        <div>
            <h4 class="font-semibold"><a href="{{ route('kontak') }}" class="hover:underline">Kontak</a></h4>
            <p class="mt-2 text-sm">Temanggung, Jawa Tengah</p>
            <p class="mt-1 text-sm"><a href="tel:085385737428" class="hover:underline">085385737428</a></p>
            <p class="mt-1 text-sm"><a href="mailto:embungbansari.bansari@gmail.com" class="hover:underline">embungbansari.bansari@gmail.com</a></p>
        </div>

        <div>
            <h4 class="font-semibold">KETENTUAN &amp; INFORMASI</h4>
            <ul class="mt-2 space-y-1 text-sm">
                <li><a href="{{ route('ketentuan.reservasi') }}" class="hover:underline">Ketentuan Reservasi</a></li>
                <li><a href="{{ route('kebijakan.privasi') }}" class="hover:underline">Kebijakan Privasi</a></li>
                <li><a href="{{ route('faq') }}" class="hover:underline">FAQ</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-4 text-xs text-center text-gray-500">
            &copy; {{ date('Y') }} Embung Bansari. Semua hak dilindungi.
        </div>
    </div>
</footer>

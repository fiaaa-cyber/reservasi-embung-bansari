@extends('layouts.app')

@section('title', 'Reservasi Camping - Embung Bansari')

@section('content')

<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-semibold">Reservasi Camping</h1>
    <p class="mt-2 text-gray-600">Isi data berikut untuk melakukan reservasi camping di Embung Bansari.</p>

    @if(session('duplicate_reservation'))
        <dialog id="duplicate-reservation-modal" class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-lg rounded-lg border-0 p-0 shadow-xl backdrop:bg-black/50">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-amber-800">Reservasi Sudah Ada</h2>
                <p class="mt-4 text-gray-700">Anda sudah memiliki reservasi dengan data yang sama untuk tanggal dan paket tersebut. Reservasi baru tidak dapat dibuat. Silakan ubah tanggal, email, nomor HP, nama, atau paket jika ingin membuat reservasi baru.</p>
                <div class="mt-6 flex justify-end">
                    <form method="dialog">
                        <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-white">Kembali ke Form</button>
                    </form>
                </div>
            </div>
        </dialog>
        <script>
            document.getElementById('duplicate-reservation-modal').showModal();
        </script>
    @endif

    @if(session('capacity_reservation'))
        @php
            $capacityReservation = session('capacity_reservation');
            $capacityBookingDate = \Illuminate\Support\Carbon::parse($capacityReservation['booking_date'])
                ->locale('id')
                ->translatedFormat('j F Y');
        @endphp
        <dialog id="capacity-reservation-modal" class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-lg rounded-lg border-0 p-0 shadow-xl backdrop:bg-black/50">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-amber-800">Kapasitas Camping Tidak Mencukupi</h2>
                <p class="mt-4 text-gray-700">Kapasitas untuk tanggal {{ $capacityBookingDate }} tidak mencukupi.</p>
                <dl class="mt-4 space-y-2 text-sm text-gray-700">
                    <div><dt class="inline font-semibold">Kapasitas total:</dt> <dd class="inline">{{ $capacityReservation['total_capacity'] }} orang</dd></div>
                    <div><dt class="inline font-semibold">Sudah terisi:</dt> <dd class="inline">{{ $capacityReservation['used_capacity'] }} orang</dd></div>
                    <div><dt class="inline font-semibold">Sisa kapasitas:</dt> <dd class="inline">{{ $capacityReservation['remaining_capacity'] }} orang</dd></div>
                    <div><dt class="inline font-semibold">Kebutuhan reservasi Anda:</dt> <dd class="inline">{{ $capacityReservation['requested_capacity'] }} orang</dd></div>
                </dl>
                <p class="mt-4 text-sm text-gray-700">Silakan pilih tanggal lain atau kurangi jumlah orang.</p>
                <div class="mt-6 flex justify-end">
                    <form method="dialog">
                        <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-gray-700">Tutup</button>
                    </form>
                </div>
            </div>
        </dialog>
        <script>
            document.getElementById('capacity-reservation-modal').showModal();
        </script>
    @endif

    <form method="POST" action="{{ route('reservasi.store') }}" class="mt-6 space-y-6">
        @csrf

        @if(session('success'))
            <div class="bg-emerald-100 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-md">
                {{ session('success') }}
            </div>
        @endif

        @php $type = old('reservation_type', 'package'); @endphp

        <!-- Jenis Reservasi (Peer-based, no JS) -->
        <section class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold">Jenis Reservasi</h2>
            <p class="mt-2 text-sm text-gray-600">Pilih apakah Anda ingin menggunakan paket camping atau melakukan camping tanpa paket.</p>

            <div class="mt-4 grid md:grid-cols-2 gap-4">
                <!-- Package option + controlled section -->
                <div>
                    <input id="rt_package" type="radio" name="reservation_type" value="package" class="peer sr-only" {{ old('reservation_type', 'package') === 'package' ? 'checked' : '' }} />
                    <label for="rt_package" class="inline-flex items-center space-x-2 cursor-pointer">
                        <span class="w-3 h-3 inline-block rounded-full border-2 border-emerald-600 bg-white"></span>
                        <span>Pilih Paket Camping</span>
                    </label>

                    <div class="mt-4 hidden peer-checked:block">
                        <h3 class="text-sm font-medium text-gray-700">Pilih Paket</h3>
                        <div class="mt-3 grid gap-4 md:grid-cols-3">
                            @foreach($packages as $package)
                                <label class="border rounded-lg p-4 flex flex-col cursor-pointer">
                                    <input type="radio" name="package_id" value="{{ $package->id }}" class="form-radio" {{ old('package_id') == $package->id ? 'checked' : '' }} />
                                    <strong class="mt-2">{{ $package->name }}</strong>
                                    <span class="text-sm text-gray-600">Rp {{ number_format($package->price, 0, ',', '.') }} — Maks. {{ $package->capacity }} orang</span>
                                    @if($package->description)
                                        <p class="mt-2 text-sm text-gray-500">{{ $package->description }}</p>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        @error('package_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <!-- Without-package option + controlled section -->
                <div>
                    <input id="rt_without" type="radio" name="reservation_type" value="without_package" class="peer sr-only" {{ old('reservation_type') === 'without_package' ? 'checked' : '' }} />
                    <label for="rt_without" class="inline-flex items-center space-x-2 cursor-pointer">
                        <span class="w-3 h-3 inline-block rounded-full border-2 border-amber-600 bg-white"></span>
                        <span>Camping Tanpa Paket</span>
                    </label>

                    <div class="mt-4 hidden peer-checked:block">
                        <h3 class="text-sm font-medium text-gray-700">Camping Tanpa Paket</h3>
                        <p class="mt-1 text-sm text-gray-500">Masukkan jumlah pengunjung untuk perhitungan biaya.</p>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700">Jumlah Pengunjung</label>
                            <input type="number" name="number_of_people" min="1" value="{{ old('number_of_people', 1) }}" placeholder="Masukkan jumlah pengunjung" class="mt-1 block w-full border-gray-200 rounded-md" {{ old('reservation_type') === 'without_package' ? 'required' : '' }} />
                            @error('number_of_people')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <!-- Perlengkapan hanya untuk tanpa paket -->
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-700">Perlengkapan Tambahan</h4>
                            <p class="mt-2 text-sm text-gray-600">Masukkan jumlah unit untuk setiap perlengkapan (0 berarti tidak disewa).</p>
                            <div class="mt-4 grid gap-4">
                                @foreach($equipment as $item)
                                    <div class="flex items-center justify-between space-x-4">
                                        <div>
                                            <div class="font-medium">{{ $item->name }}</div>
                                            <div class="text-sm text-gray-600">Rp {{ number_format($item->price, 0, ',', '.') }} / unit</div>
                                            <div class="text-xs text-gray-500">Stok: {{ $item->stock }}</div>
                                        </div>
                                        <div class="w-32">
                                            <label class="sr-only">{{ $item->name }} jumlah</label>
                                            <input type="number" name="equipment[{{ $item->id }}]" min="0" max="{{ $item->stock }}" value="{{ old('equipment.'.$item->id, 0) }}" class="mt-1 block w-full border-gray-200 rounded-md text-right" />
                                            @error('equipment.' . $item->id)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div
            >
            @error('reservation_type')<p class="text-sm text-red-600 mt-3">{{ $message }}</p>@enderror
        </section>

        <!-- 2. Tanggal Camping -->
        <section class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold">2. Tanggal Camping</h2>
            <p class="mt-2 text-sm text-gray-600">Silakan pilih tanggal camping yang diinginkan.</p>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700">Tanggal Camping</label>
                <input type="date" name="booking_date" required value="{{ old('booking_date') }}" class="mt-1 block w-full border-gray-200 rounded-md" />
                @error('booking_date')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <!-- 4. Data Pemesan -->
        <section class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold">4. Data Pemesan</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                    <input type="text" name="name" required placeholder="Nama lengkap" value="{{ old('name') }}" class="mt-1 block w-full border-gray-200 rounded-md" />
                    @error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" required pattern="[^@\s]+@(gmail\.com|yahoo\.com|outlook\.com|hotmail\.com)" title="Email harus menggunakan domain Gmail, Yahoo, Outlook, atau Hotmail." placeholder="email@gmail.com" value="{{ old('email') }}" class="mt-1 block w-full border-gray-200 rounded-md" />
                    @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Nomor WhatsApp</label>
                    <input type="tel" name="phone" inputmode="numeric" pattern="08[0-9]{8,11}" minlength="10" maxlength="13" title="Nomor HP harus berupa nomor Indonesia yang valid (contoh: 081234567890)." oninput="this.value = this.value.replace(/[^0-9]/g, '')" required placeholder="081234567890" value="{{ old('phone') }}" class="mt-1 block w-full border-gray-200 rounded-md" />
                    @error('phone')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <!-- Ringkasan dipindah ke Agreement (WF-03) -->

        <div class="text-right">
            <button type="submit" class="inline-block bg-emerald-600 text-white px-6 py-2 rounded-md">Pesan Sekarang</button>
        </div>

    </form>

    <dialog id="duplicate-reservation-precheck" class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-lg rounded-lg border-0 p-0 shadow-xl backdrop:bg-black/50">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-amber-800">Reservasi Sudah Ada</h2>
            <p class="mt-4 text-gray-700">Anda sudah memiliki reservasi dengan data yang sama untuk tanggal dan paket tersebut. Reservasi baru tidak dapat dibuat. Silakan ubah tanggal, email, nomor HP, nama, atau paket jika ingin membuat reservasi baru.</p>
            <div class="mt-6 flex justify-end">
                <form method="dialog">
                    <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-white">Kembali ke Form</button>
                </form>
            </div>
        </div>
    </dialog>

    <script>
        (() => {
            const form = document.querySelector('form[action="{{ route('reservasi.store') }}"]');
            const dialog = document.getElementById('duplicate-reservation-precheck');
            const endpoint = @json(route('reservasi.duplicate-check'));
            let debounceTimer;
            let activeRequest;
            let requestVersion = 0;

            function checkDuplicate() {
                window.clearTimeout(debounceTimer);
                requestVersion += 1;
                activeRequest?.abort();

                const name = form.elements.name.value.trim();
                const email = form.elements.email.value.trim();
                const phone = form.elements.phone.value.trim();
                const bookingDate = form.elements.booking_date.value;
                const reservationType = form.querySelector('input[name="reservation_type"]:checked')?.value;
                const packageId = form.querySelector('input[name="package_id"]:checked')?.value;

                if (!name || !email || !phone || !bookingDate || !reservationType || (reservationType === 'package' && !packageId)) {
                    return;
                }

                const version = requestVersion;
                debounceTimer = window.setTimeout(async () => {
                    const controller = new AbortController();
                    activeRequest = controller;

                    try {
                        const params = new URLSearchParams({
                            name,
                            email,
                            phone,
                            booking_date: bookingDate,
                            reservation_type: reservationType,
                        });
                        if (packageId) params.set('package_id', packageId);

                        const response = await fetch(`${endpoint}?${params}`, {
                            headers: { Accept: 'application/json' },
                            signal: controller.signal,
                        });
                        if (!response.ok) return;

                        const result = await response.json();
                        if (version === requestVersion && result.exists && !dialog.open) {
                            dialog.showModal();
                        }
                    } catch (error) {
                        // Pre-check failures must not interfere with form submission.
                    } finally {
                        if (activeRequest === controller) activeRequest = null;
                    }
                }, 350);
            }

            const checkedFields = new Set(['name', 'email', 'phone', 'booking_date', 'reservation_type', 'package_id']);
            form.addEventListener('input', (event) => {
                if (checkedFields.has(event.target.name)) checkDuplicate();
            });
            form.addEventListener('change', (event) => {
                if (checkedFields.has(event.target.name)) checkDuplicate();
            });
        })();
    </script>
</div>

@endsection

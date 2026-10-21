<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Equipment;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Payment;
use App\Models\ReservationDraft;
use App\Support\PaymentMethodLabels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class ReservationController extends Controller
{
    public function index()
    {
        $packages = Package::where('is_active', true)->get();
        $equipment = Equipment::where('is_active', true)->get();

        return view('reservasi', compact('packages', 'equipment'));
    }

    public function checkDuplicateReservation(Request $request)
    {
        $data = $request->validate([
            'reservation_type' => ['required', Rule::in(['package', 'without_package'])],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'regex:/\A[^@\s]+@(gmail\.com|yahoo\.com|outlook\.com|hotmail\.com)\z/i', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/\A08[0-9]{8,11}\z/'],
            'package_id' => ['required_if:reservation_type,package', 'nullable', 'integer', 'exists:packages,id'],
        ]);

        return response()->json([
            'exists' => $this->hasExactDuplicateBooking(
                $data['reservation_type'],
                $data['name'],
                $data['email'],
                $data['phone'],
                $data['booking_date'],
                $data['reservation_type'] === 'package' ? (int) $data['package_id'] : null,
            ),
        ]);
    }

    private function lockBookingCapacityDate(ReservationDraft $draft): void
    {
        ReservationDraft::whereDate('booking_date', $draft->booking_date->toDateString())
            ->orderBy('id')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function bookingCapacitySummary(string $bookingDate, ?int $packageId, ?int $numberOfPeople): array
    {
        $bookingDate = Carbon::parse($bookingDate)->toDateString();
        $bookedPeople = Booking::query()
            ->with('package')
            ->whereDate('booking_date', $bookingDate)
            ->whereIn('status', Booking::CAPACITY_BLOCKING_STATUSES)
            ->get(['package_id', 'number_of_people'])
            ->sum(fn (Booking $booking): int => $booking->capacityPeople());

        $capacity = (int) config('reservasi.location_capacity', 100);
        $requestedPeople = $packageId !== null
            ? (int) (Package::query()->whereKey($packageId)->value('capacity') ?? 0)
            : (int) $numberOfPeople;

        return [
            'booking_date' => $bookingDate,
            'total_capacity' => $capacity,
            'used_capacity' => $bookedPeople,
            'remaining_capacity' => max(0, $capacity - $bookedPeople),
            'requested_capacity' => $requestedPeople,
        ];
    }

    private function ensureBookingCapacity(ReservationDraft $draft): void
    {
        $capacity = $this->bookingCapacitySummary(
            $draft->booking_date->toDateString(),
            $draft->package_id,
            $draft->number_of_people,
        );

        if ($capacity['requested_capacity'] > $capacity['remaining_capacity']) {
            throw new \DomainException(
                "Kapasitas pada tanggal {$capacity['booking_date']} sudah tidak mencukupi. Silakan pilih tanggal lain."
            );
        }
    }

    private function hasExactDuplicateBooking(
        string $reservationType,
        string $name,
        string $email,
        string $phone,
        string $bookingDate,
        ?int $packageId,
    ): bool {
        $query = Booking::query()
            ->whereDate('booking_date', Carbon::parse($bookingDate)->toDateString())
            ->whereIn('status', Booking::CAPACITY_BLOCKING_STATUSES);

        if ($reservationType === 'package') {
            $query->where('package_id', $packageId);
        } else {
            $query->whereNull('package_id');
        }

        $normalizedName = trim($name);
        $normalizedEmail = strtolower(trim($email));
        $normalizedPhone = trim($phone);

        return $query->get(['name', 'email', 'phone'])->contains(
            fn (Booking $booking): bool => trim($booking->name) === $normalizedName
                && strtolower(trim($booking->email)) === $normalizedEmail
                && trim($booking->phone) === $normalizedPhone
        );
    }

    private function ensureNoDuplicateActiveBooking(ReservationDraft $draft): void
    {
        if ($this->hasExactDuplicateBooking(
            $draft->reservation_type,
            $draft->name,
            $draft->email,
            $draft->phone,
            $draft->booking_date->toDateString(),
            $draft->package_id,
        )) {
            throw new \DomainException(
                'Anda sudah memiliki reservasi dengan data yang sama untuk tanggal dan paket tersebut.',
                1
            );
        }
    }

    private function redirectToDuplicateReservationForm(ReservationDraft $draft)
    {
        return redirect()->route('reservasi')
            ->withInput([
                'reservation_type' => $draft->reservation_type,
                'package_id' => $draft->package_id,
                'booking_date' => $draft->booking_date->toDateString(),
                'number_of_people' => $draft->number_of_people,
                'name' => $draft->name,
                'email' => $draft->email,
                'phone' => $draft->phone,
            ])
            ->with('duplicate_reservation', true);
    }

    private function completeCashPayment(ReservationDraft $draft)
    {
        try {
            $booking = DB::transaction(function () use ($draft): Booking {
                $this->lockBookingCapacityDate($draft);
                $draft = ReservationDraft::where('draft_code', $draft->draft_code)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($draft->expires_at && $draft->expires_at->isPast()) {
                    throw new \RuntimeException('Draft reservasi telah kadaluarsa. Silakan buat reservasi baru.');
                }

                $existingBooking = Booking::where('booking_code', $draft->draft_code)->first();
                if ($existingBooking) {
                    return $existingBooking;
                }

                $this->ensureNoDuplicateActiveBooking($draft);
                $this->ensureBookingCapacity($draft);

                $booking = Booking::create([
                    'booking_code' => $draft->draft_code,
                    'package_id' => $draft->package_id,
                    'booking_date' => $draft->booking_date,
                    'number_of_people' => $draft->number_of_people,
                    'name' => $draft->name,
                    'email' => $draft->email,
                    'phone' => $draft->phone,
                    'total_package' => $draft->total_package,
                    'total_equipment' => $draft->total_equipment,
                    'total_price' => $draft->total_price,
                    'status' => 'pending_confirmation',
                ]);

                foreach ($draft->equipment ?? [] as $item) {
                    $quantity = (int) ($item['quantity'] ?? 0);
                    if ($quantity < 1) {
                        continue;
                    }

                    $equipment = Equipment::whereKey($item['equipment_id'] ?? null)
                        ->lockForUpdate()
                        ->first();

                    if (! $equipment || $equipment->stock < $quantity) {
                        throw new \RuntimeException('Stok equipment tidak mencukupi untuk menyelesaikan reservasi.');
                    }

                    $unitPrice = (float) ($item['price'] ?? $equipment->price);
                    BookingEquipment::create([
                        'booking_id' => $booking->id,
                        'equipment_id' => $equipment->id,
                        'quantity' => $quantity,
                        'price' => $unitPrice,
                        'subtotal' => $unitPrice * $quantity,
                    ]);

                    $equipment->decrement('stock', $quantity);
                }

                Payment::create([
                    'booking_id' => $booking->id,
                    'payment_method' => 'cash',
                    'transaction_id' => 'CASH-' . strtoupper(Str::random(12)),
                    'amount' => round((float) $draft->total_price, 2),
                        'status' => 'unpaid',
                        'paid_at' => null,
                ]);

                $draft->update(['status' => 'paid']);

                return $booking;
            });
        } catch (\DomainException $exception) {
            if ($exception->getCode() === 1) {
                return $this->redirectToDuplicateReservationForm($draft);
            }

            return back()
                ->withInput()
                ->withErrors(['payment' => $exception->getMessage()]);
        } catch (\RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()->route('reservasi.success', [
            'booking_code' => $booking->booking_code,
        ]);
    }

    public function store(Request $request)
    {
        // Build validation rules based on reservation type to avoid rejecting equipment when package is selected
        $reservationType = $request->input('reservation_type');

        $rules = [
            'reservation_type' => ['required', Rule::in(['package', 'without_package'])],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'regex:/\A[^@\s]+@(gmail\.com|yahoo\.com|outlook\.com|hotmail\.com)\z/i', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/\A08[0-9]{8,11}\z/'],
        ];

        $messages = [
            'email.email' => 'Format email tidak valid.',
            'email.regex' => 'Email harus menggunakan domain Gmail, Yahoo, Outlook, atau Hotmail.',
            'email.max' => 'Format email tidak valid.',
            'phone.regex' => 'Nomor HP harus berupa nomor Indonesia yang valid (contoh: 081234567890).',
            'phone.max' => 'Nomor HP harus berupa nomor Indonesia yang valid (contoh: 081234567890).',
        ];

        if ($reservationType === 'package') {
            $rules['package_id'] = ['required', 'exists:packages,id'];
            // number_of_people not required and will be stored as null
        } else {
            // without_package
            $rules['number_of_people'] = ['required', 'integer', 'min:1'];
            $rules['equipment'] = ['sometimes', 'array'];
            $rules['equipment.*'] = ['integer', 'min:0'];
        }

        $data = $request->validate($rules, $messages);

        // Package handling: ensure package exists and is active
        $package = null;
        if ($reservationType === 'package') {
            $package = Package::find($data['package_id']);
            if (! $package || ! $package->is_active) {
                return back()->withErrors(['package_id' => 'Paket tidak ditemukan atau tidak aktif.'])->withInput();
            }

        }

        if ($this->hasExactDuplicateBooking(
            $reservationType,
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['booking_date'],
            $reservationType === 'package' ? (int) $package->id : null,
        )) {
            return back()
                ->withInput()
                ->with('duplicate_reservation', true);
        }

        $capacity = $this->bookingCapacitySummary(
            $data['booking_date'],
            $package?->id,
            $reservationType === 'without_package' ? (int) $data['number_of_people'] : null,
        );

        if ($capacity['requested_capacity'] > $capacity['remaining_capacity']) {
            return back()
                ->withInput()
                ->with('capacity_reservation', $capacity);
        }

        // Equipment processing only allowed for without_package; ignore equipment if reservation is package
        $equipmentRecords = [];
        $totalEquipment = 0;

        if ($reservationType === 'without_package') {
            $equipmentInput = $request->input('equipment', []);
            foreach ($equipmentInput as $eid => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $eq = Equipment::find($eid);
                if (! $eq || ! $eq->is_active) {
                    return back()->withErrors(["equipment.$eid" => 'Perlengkapan tidak ditemukan atau tidak aktif.'])->withInput();
                }
                if ($qty > $eq->stock) {
                    return back()->withErrors(["equipment.$eid" => 'Jumlah melebihi stok yang tersedia.'])->withInput();
                }
                $subtotal = $eq->price * $qty;
                $equipmentRecords[] = ['equipment' => $eq, 'quantity' => $qty, 'subtotal' => $subtotal];
                $totalEquipment += $subtotal;
            }
        }

        // Calculate totals according to business rules
        if ($reservationType === 'without_package') {
            $ratePerPerson = config('reservasi.rate_per_person', 15000);
            $numberOfPeople = (int) $data['number_of_people'];
            $totalPackage = $numberOfPeople * $ratePerPerson;
        } else {
            // package
            $numberOfPeople = null;
            $totalPackage = $package ? $package->price : 0;
            // For package reservations, equipment is not counted
            $totalEquipment = 0;
            $equipmentRecords = [];
        }

        $totalPrice = $totalPackage + $totalEquipment;

        // Generate unique draft code
        $datePart = Carbon::parse($data['booking_date'])->format('Ymd');
        do {
            $random = strtoupper(Str::random(4));
            $draftCode = "DRF-{$datePart}-{$random}";
        } while (ReservationDraft::where('draft_code', $draftCode)->exists());

        // Prepare equipment payload for draft (only for without_package)
        $equipmentPayload = [];
        if ($reservationType === 'without_package') {
            foreach ($equipmentRecords as $rec) {
                $equipmentPayload[] = [
                    'equipment_id' => $rec['equipment']->id,
                    'quantity' => $rec['quantity'],
                    'price' => $rec['equipment']->price,
                    'subtotal' => $rec['subtotal'],
                ];
            }
        }

        // Transactional save: create ReservationDraft only
        $draft = DB::transaction(function () use ($data, $package, $equipmentPayload, $totalPackage, $totalEquipment, $totalPrice, $draftCode, $numberOfPeople) {
            $draft = ReservationDraft::create([
                'draft_code' => $draftCode,
                'reservation_type' => request()->input('reservation_type'),
                'package_id' => $package ? $package->id : null,
                'booking_date' => $data['booking_date'],
                'number_of_people' => $numberOfPeople,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'equipment' => $equipmentPayload ?: null,
                'total_package' => $totalPackage,
                'total_equipment' => $totalEquipment,
                'total_price' => $totalPrice,
                'status' => 'pending',
                'expires_at' => Carbon::now()->addMinutes(30),
            ]);

            return $draft;
        });

        return redirect()->route('reservasi.agreement', ['draft_code' => $draft->draft_code]);
    }

    public function agreement(Request $request)
    {
        $draftCode = $request->input('draft_code');
        $draft = ReservationDraft::with('package')->where('draft_code', $draftCode)->firstOrFail();

        // Check expiry
        if ($draft->expires_at && $draft->expires_at->isPast()) {
            abort(410, 'Draft reservasi telah kadaluarsa. Silakan buat reservasi baru.');
        }

        return view('agreement_draft', compact('draft'));
    }

    public function continuePayment(Request $request)
    {
        $request->validate([
            'agree' => ['accepted'],
        ]);

        $draftCode = $request->input('draft_code');
        $draft = ReservationDraft::where('draft_code', $draftCode)->firstOrFail();

        return redirect()->route('reservasi.payment', ['draft_code' => $draft->draft_code]);
    }

    public function payment(Request $request)
    {
        $draft = ReservationDraft::with('package')
            ->where('draft_code', $request->input('draft_code'))
            ->firstOrFail();

        if ($draft->expires_at && $draft->expires_at->isPast()) {
            abort(410, 'Draft reservasi telah kadaluarsa. Silakan buat reservasi baru.');
        }

        $equipmentIds = collect($draft->equipment ?? [])->pluck('equipment_id')->filter()->all();
        $equipment = Equipment::whereIn('id', $equipmentIds)->get()->keyBy('id');

        return view('payment', compact('draft', 'equipment'));
    }

    public function processPayment(Request $request)
    {
        $data = $request->validate([
            'draft_code' => ['required', 'string'],
            'payment_method' => ['required', Rule::in(['qris_full', 'qris_dp', 'cash'])],
        ]);

        $draft = ReservationDraft::where('draft_code', $data['draft_code'])->firstOrFail();

        if ($draft->expires_at && $draft->expires_at->isPast()) {
            abort(410, 'Draft reservasi telah kadaluarsa. Silakan buat reservasi baru.');
        }

        if ($data['payment_method'] === 'cash') {
            return $this->completeCashPayment($draft);
        }

        $paymentAmount = $data['payment_method'] === 'qris_dp'
            ? round((float) $draft->total_price * 0.5, 2)
            : (float) $draft->total_price;

        $paymentMethodLabel = PaymentMethodLabels::label($data['payment_method']);

        $snapToken = null;
        $orderId = $draft->draft_code . '-' . now()->format('YmdHis');
        if ($data['payment_method'] !== 'cash') {
            Config::$serverKey = config('services.midtrans.server_key');
            Config::$isProduction = config('services.midtrans.is_production', false);
            Config::$isSanitized = true;
            Config::$is3ds = true;

            try {
                $snapToken = Snap::getSnapToken([
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => (int) $paymentAmount,
                    ],
                    'customer_details' => [
                        'first_name' => $draft->name,
                        'email' => $draft->email,
                        'phone' => $draft->phone,
                    ],
                    'enabled_payments' => ['gopay', 'qris', 'bank_transfer', 'credit_card', 'shopeepay', 'bca_klikpay', 'bri_epay', 'cimb_clicks', 'danamon_online', 'akulaku', 'alfamart', 'indomaret'],
                ]);
            } catch (\Throwable $exception) {
                return back()
                    ->withInput()
                    ->withErrors(['payment' => 'Pembayaran belum dapat disiapkan. Silakan coba lagi.']);
            }
        }

        return view('payment_next', [
            'draftCode' => $draft->draft_code,
            'orderId' => $orderId,
            'paymentMethod' => $paymentMethodLabel,
            'paymentMethodCode' => $data['payment_method'],
            'paymentAmount' => $paymentAmount,
            'snapToken' => $snapToken,
            'midtransClientKey' => config('services.midtrans.client_key'),
        ]);
    }

    public function verifyPaymentLocal(Request $request)
    {
        abort_unless(app()->environment('local'), 404);

        $data = $request->validate([
            'order_id' => ['required', 'string'],
            'draft_code' => ['required', 'string'],
        ]);

        $draft = ReservationDraft::where('draft_code', $data['draft_code'])->firstOrFail();

        if ($draft->expires_at && $draft->expires_at->isPast()) {
            return response()->json(['status' => 'failed', 'message' => 'Draft reservasi telah kadaluarsa.'], 410);
        }

        if (! Str::startsWith($data['order_id'], $draft->draft_code . '-')) {
            return response()->json(['status' => 'failed', 'message' => 'Order tidak sesuai dengan draft reservasi.'], 422);
        }

        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;

        try {
            $transaction = Transaction::status($data['order_id']);
        } catch (\Throwable $exception) {
            return response()->json(['status' => 'error', 'message' => 'Status pembayaran belum dapat diperiksa.'], 502);
        }

        $transactionStatus = (string) data_get($transaction, 'transaction_status', '');
        if ($transactionStatus === 'pending') {
            return response()->json(['status' => 'pending']);
        }

        $isSuccessful = $transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && data_get($transaction, 'fraud_status') === 'accept');

        if (! $isSuccessful) {
            return response()->json(['status' => 'failed', 'message' => 'Pembayaran belum berhasil.']);
        }

        $fullAmount = round((float) $draft->total_price, 2);
        $dpAmount = round($fullAmount * 0.5, 2);
        $receivedAmount = round((float) data_get($transaction, 'gross_amount', 0), 2);
        $paymentMethod = null;

        if ($receivedAmount === $fullAmount) {
            $paymentMethod = 'qris_full';
        } elseif ($receivedAmount === $dpAmount) {
            $paymentMethod = 'qris_dp';
        } else {
            return response()->json(['status' => 'failed', 'message' => 'Nominal transaksi tidak sesuai.'], 422);
        }

        try {
            DB::transaction(function () use ($draft, $paymentMethod, $receivedAmount, $transaction, $data): void {
                $this->lockBookingCapacityDate($draft);
                $draft = ReservationDraft::where('draft_code', $draft->draft_code)->lockForUpdate()->firstOrFail();

                if ($draft->expires_at && $draft->expires_at->isPast()) {
                    throw new \RuntimeException('Draft expired.');
                }

                if (Booking::where('booking_code', $draft->draft_code)->exists()) {
                    return;
                }

                $this->ensureNoDuplicateActiveBooking($draft);
                $this->ensureBookingCapacity($draft);

                $booking = Booking::create([
                    'booking_code' => $draft->draft_code,
                    'package_id' => $draft->package_id,
                    'booking_date' => $draft->booking_date,
                    'number_of_people' => $draft->number_of_people,
                    'name' => $draft->name,
                    'email' => $draft->email,
                    'phone' => $draft->phone,
                    'total_package' => $draft->total_package,
                    'total_equipment' => $draft->total_equipment,
                    'total_price' => $draft->total_price,
                    'status' => 'pending_confirmation',
                ]);

                foreach ($draft->equipment ?? [] as $item) {
                    $quantity = (int) ($item['quantity'] ?? 0);
                    if ($quantity < 1) {
                        continue;
                    }

                    $equipment = Equipment::whereKey($item['equipment_id'] ?? null)->lockForUpdate()->first();
                    if (! $equipment || $equipment->stock < $quantity) {
                        throw new \RuntimeException('Equipment stock is insufficient.');
                    }

                    $unitPrice = (float) ($item['price'] ?? $equipment->price);
                    BookingEquipment::create([
                        'booking_id' => $booking->id,
                        'equipment_id' => $equipment->id,
                        'quantity' => $quantity,
                        'price' => $unitPrice,
                        'subtotal' => $unitPrice * $quantity,
                    ]);

                    $equipment->decrement('stock', $quantity);
                }

                Payment::create([
                    'booking_id' => $booking->id,
                    'payment_method' => $paymentMethod,
                    'transaction_id' => data_get($transaction, 'transaction_id', $data['order_id']),
                    'amount' => $receivedAmount,
                    'status' => $paymentMethod === 'qris_dp' ? 'dp' : 'paid',
                    'paid_at' => now(),
                ]);

                $draft->update(['status' => 'paid']);
            });
        } catch (\DomainException $exception) {
            return response()->json([
                'status' => $exception->getCode() === 1 ? 'duplicate_booking' : 'capacity_unavailable',
                'message' => $exception->getCode() === 1
                    ? 'Reservasi tidak dapat diselesaikan. Silakan kembali ke halaman reservasi.'
                    : $exception->getMessage(),
            ], 409);
        } catch (\RuntimeException $exception) {
            return response()->json(['status' => 'error', 'message' => 'Reservasi tidak dapat diselesaikan.'], 409);
        }

        return response()->json([
            'status' => 'success',
            'booking_code' => $draft->draft_code,
            'redirect_url' => route('reservasi.success', ['booking_code' => $draft->draft_code]),
        ]);
    }

    public function simulatePaymentSuccess(Request $request)
{
    // Hanya boleh digunakan saat development lokal
    abort_unless(app()->environment('local'), 404);

    $data = $request->validate([
        'draft_code' => ['required', 'string'],
        'payment_method' => [
            'required',
            Rule::in(['qris_full', 'qris_dp', 'cash']),
        ],
    ]);

    $draft = ReservationDraft::where('draft_code', $data['draft_code'])->firstOrFail();

    if ($draft->expires_at && $draft->expires_at->isPast()) {
        abort(410, 'Draft reservasi telah kadaluarsa.');
    }

    if ($data['payment_method'] === 'cash') {
        return $this->completeCashPayment($draft);
    }

    // Jangan membuat booking dua kali
    $existingBooking = Booking::where(
        'booking_code',
        $draft->draft_code
    )->first();

    if ($existingBooking) {
        return redirect()->route('reservasi.success', [
            'booking_code' => $existingBooking->booking_code,
        ]);
    }

    $fullAmount = round((float) $draft->total_price, 2);

    $paymentAmount = $data['payment_method'] === 'qris_dp'
        ? round($fullAmount * 0.5, 2)
        : $fullAmount;

    try {
        $booking = DB::transaction(function () use (
            $draft,
            $data,
            $paymentAmount
        ) {
            $this->lockBookingCapacityDate($draft);
            $draft = ReservationDraft::where('draft_code', $draft->draft_code)
                ->lockForUpdate()
                ->firstOrFail();

            if ($draft->expires_at && $draft->expires_at->isPast()) {
                throw new \RuntimeException('Draft reservasi telah kadaluarsa.');
            }

            $existingBooking = Booking::where('booking_code', $draft->draft_code)->first();
            if ($existingBooking) {
                return $existingBooking;
            }

            $this->ensureNoDuplicateActiveBooking($draft);
            $this->ensureBookingCapacity($draft);

            $booking = Booking::create([
                'booking_code' => $draft->draft_code,
                'package_id' => $draft->package_id,
                'booking_date' => $draft->booking_date,
                'number_of_people' => $draft->number_of_people,
                'name' => $draft->name,
                'email' => $draft->email,
                'phone' => $draft->phone,
                'total_package' => $draft->total_package,
                'total_equipment' => $draft->total_equipment,
                'total_price' => $draft->total_price,
                'status' => 'pending_confirmation',
            ]);

            foreach ($draft->equipment ?? [] as $item) {
                $quantity = (int) ($item['quantity'] ?? 0);

                if ($quantity < 1) {
                    continue;
                }

                $equipment = Equipment::whereKey(
                    $item['equipment_id'] ?? null
                )
                    ->lockForUpdate()
                    ->first();

                if (!$equipment || $equipment->stock < $quantity) {
                    throw new \RuntimeException(
                        'Stok perlengkapan tidak mencukupi.'
                    );
                }

                $unitPrice = (float) (
                    $item['price'] ?? $equipment->price
                );

                BookingEquipment::create([
                    'booking_id' => $booking->id,
                    'equipment_id' => $equipment->id,
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                    'subtotal' => $unitPrice * $quantity,
                ]);

                $equipment->decrement('stock', $quantity);
            }

            Payment::create([
                'booking_id' => $booking->id,
                'payment_method' => $data['payment_method'],
                'transaction_id' => 'TEST-' . strtoupper(Str::random(12)),
                'amount' => $paymentAmount,
                'status' => $data['payment_method'] === 'qris_dp' ? 'dp' : 'paid',
                'paid_at' => now(),
            ]);

            $draft->update([
                'status' => 'paid',
            ]);

            return $booking;
        });
    } catch (\DomainException $exception) {
            if ($exception->getCode() === 1) {
                return $this->redirectToDuplicateReservationForm($draft);
            }

        return redirect()
            ->route('reservasi.payment', ['draft_code' => $draft->draft_code])
            ->withErrors(['reservation' => $exception->getMessage()]);
    }

    return redirect()->route('reservasi.success', [
        'booking_code' => $booking->booking_code,
    ]);
}

    public function midtransCallback(Request $request)
    {
        $notification = $request->all();
        $orderId = (string) ($notification['order_id'] ?? '');
        $statusCode = (string) ($notification['status_code'] ?? '');
        $grossAmount = (string) ($notification['gross_amount'] ?? '');
        $signatureKey = (string) ($notification['signature_key'] ?? '');

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
            return response()->json(['message' => 'Invalid notification.'], 400);
        }

        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . config('services.midtrans.server_key'));
        if (! hash_equals($expectedSignature, $signatureKey)) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $draftCode = $orderId;
        if (! ReservationDraft::where('draft_code', $draftCode)->exists()
            && preg_match('/^(.*)-\d{14}$/', $orderId, $matches)) {
            $draftCode = $matches[1];
        }

        $draft = ReservationDraft::where('draft_code', $draftCode)->first();
        if (! $draft) {
            return response()->json(['message' => 'Draft not found.'], 404);
        }

        if ($draft->expires_at && $draft->expires_at->isPast()) {
            return response()->json(['message' => 'Draft expired.'], 409);
        }

        $fullAmount = round((float) $draft->total_price, 2);
        $dpAmount = round($fullAmount * 0.5, 2);
        $receivedAmount = round((float) $grossAmount, 2);
        $paymentMethod = null;

        if ($receivedAmount === $fullAmount) {
            $paymentMethod = 'qris_full';
        } elseif ($receivedAmount === $dpAmount) {
            $paymentMethod = 'qris_dp';
        } else {
            return response()->json(['message' => 'Invalid transaction amount.'], 422);
        }

        $transactionStatus = (string) ($notification['transaction_status'] ?? '');
        $isSuccessful = $transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && ($notification['fraud_status'] ?? '') === 'accept');

        if (! $isSuccessful) {
            return response()->json(['message' => 'Notification acknowledged.']);
        }

        try {
            DB::transaction(function () use ($draft, $draftCode, $orderId, $notification, $paymentMethod, $receivedAmount): void {
                $this->lockBookingCapacityDate($draft);
                $draft = ReservationDraft::where('draft_code', $draftCode)->lockForUpdate()->firstOrFail();

                if ($draft->expires_at && $draft->expires_at->isPast()) {
                    throw new \RuntimeException('Draft expired.');
                }

                if (Booking::where('booking_code', $draft->draft_code)->exists()) {
                    return;
                }

                $this->ensureNoDuplicateActiveBooking($draft);
                $this->ensureBookingCapacity($draft);

                $booking = Booking::create([
                    'booking_code' => $draft->draft_code,
                    'package_id' => $draft->package_id,
                    'booking_date' => $draft->booking_date,
                    'number_of_people' => $draft->number_of_people,
                    'name' => $draft->name,
                    'email' => $draft->email,
                    'phone' => $draft->phone,
                    'total_package' => $draft->total_package,
                    'total_equipment' => $draft->total_equipment,
                    'total_price' => $draft->total_price,
                    'status' => 'pending_confirmation',
                ]);

                foreach ($draft->equipment ?? [] as $item) {
                    $quantity = (int) ($item['quantity'] ?? 0);
                    if ($quantity < 1) {
                        continue;
                    }

                    $equipment = Equipment::whereKey($item['equipment_id'] ?? null)->lockForUpdate()->first();
                    if (! $equipment || $equipment->stock < $quantity) {
                        throw new \RuntimeException('Equipment stock is insufficient.');
                    }

                    $unitPrice = (float) ($item['price'] ?? $equipment->price);
                    BookingEquipment::create([
                        'booking_id' => $booking->id,
                        'equipment_id' => $equipment->id,
                        'quantity' => $quantity,
                        'price' => $unitPrice,
                        'subtotal' => $unitPrice * $quantity,
                    ]);

                    $equipment->decrement('stock', $quantity);
                }

                Payment::create([
                    'booking_id' => $booking->id,
                    'payment_method' => $paymentMethod,
                    'transaction_id' => $notification['transaction_id'] ?? $orderId,
                    'amount' => $receivedAmount,
                    'status' => $paymentMethod === 'qris_dp' ? 'dp' : 'paid',
                    'paid_at' => now(),
                ]);

                $draft->update(['status' => 'paid']);
            });
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => 'Reservation could not be completed.'], 409);
        }

        return response()->json(['message' => 'Notification processed.']);
    }

    public function invoice(string $bookingCode)
    {
        $booking = Booking::with([
            'package',
            'payment',
            'bookingEquipment.equipment',
        ])
            ->where('booking_code', $bookingCode)
            ->firstOrFail();

        return Pdf::loadView('invoice', compact('booking'))
            ->setPaper('a4', 'portrait')
            ->download('bukti-reservasi-' . Str::slug($booking->booking_code) . '.pdf');
    }

    public function reservationSuccess(Request $request)
    {
        $bookingCode = $request->input('booking_code');
        $booking = null;

        if ($bookingCode) {
            $booking = Booking::with(['package', 'bookingEquipment.equipment', 'payment'])
                ->where('booking_code', $bookingCode)
                ->first();
        }

        return view('reservation_success', compact('booking', 'bookingCode'));
    }

    public function checkReservation(Request $request)
    {
        $booking = null;
        $bookingCode = $request->query('booking_code');

        if ($bookingCode !== null) {
            $booking = Booking::with(['package', 'bookingEquipment.equipment', 'payment'])
                ->where('booking_code', $bookingCode)
                ->first();

            if (! $booking) {
                return redirect()
                    ->route('reservasi.check')
                    ->withInput(['booking_code' => $bookingCode])
                    ->withErrors(['booking_code' => 'Kode booking tidak ditemukan. Silakan periksa kembali kode booking Anda.']);
            }
        }

        return view('check_reservation', compact('booking'));
    }

    public function searchReservation(Request $request)
    {
        $data = $request->validate([
            'search_type' => ['required', 'in:booking_code,customer'],
            'booking_code' => ['required_if:search_type,booking_code', 'nullable', 'string', 'max:50'],
            'name' => ['required_if:search_type,customer', 'nullable', 'string', 'max:255'],
            'phone' => ['required_if:search_type,customer', 'nullable', 'string', 'max:20'],
        ]);

        $bookings = Booking::with(['package', 'bookingEquipment.equipment', 'payment']);

        if ($data['search_type'] === 'booking_code') {
            $booking = $bookings
                ->where('booking_code', $data['booking_code'])
                ->first();

            if (! $booking) {
                return redirect()
                    ->route('reservasi.check')
                    ->withInput()
                    ->withErrors(['booking_code' => 'Kode booking tidak ditemukan. Silakan periksa kembali kode booking Anda.']);
            }
        } else {
            $booking = $bookings
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($data['name']))])
                ->where('phone', trim($data['phone']))
                ->first();

            if (! $booking) {
                return redirect()
                    ->route('reservasi.check')
                    ->withInput()
                    ->withErrors(['search' => 'Data reservasi tidak ditemukan. Pastikan nama dan nomor HP sesuai dengan data reservasi Anda.']);
            }
        }

        return view('check_reservation', compact('booking'));
    }

    public function forgotBookingCode()
    {
        return view('forgot_booking_code');
    }

    public function searchForgotBookingCode(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $booking = Booking::with('package')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($data['name']))])
            ->where('email', $data['email'])
            ->where('phone', $data['phone'])
            ->first();

        if (! $booking) {
            return redirect()
                ->route('reservasi.forgot_code')
                ->withInput()
                ->withErrors(['email' => 'Data reservasi tidak ditemukan. Pastikan nama, email, dan nomor HP yang Anda masukkan sesuai dengan data reservasi.']);
        }

        return view('forgot_booking_code', compact('booking'));
    }
}

@php
    $reservationStatusLabels = [
        'pending_confirmation' => 'Menunggu Konfirmasi',
        'confirmed' => 'Dikonfirmasi',
        'cancelled' => 'Dibatalkan',
    ];
    $paymentStatusLabels = [
        'unpaid' => 'Belum Dibayar',
        'dp' => 'DP 50%',
        'paid' => 'Lunas',
    ];
    $payment = $booking->payment;
    $paymentStatus = $paymentStatusLabels[$payment?->status] ?? 'Belum tercatat';
    $paymentMethod = \App\Support\PaymentMethodLabels::label($payment?->payment_method, 'Belum tercatat');
    $totalAmount = (float) $booking->total_price;
    $paymentAmount = (float) ($payment?->amount ?? 0);
    $amountReceived = $payment?->status === 'unpaid' ? 0 : $paymentAmount;
    $remainingAmount = max(0, $totalAmount - $paymentAmount);
    $ratePerPerson = config('reservasi.rate_per_person', 15000);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Reservasi {{ $booking->booking_code }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 22mm 18mm;
        }

        body {
            color: #202a35;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.5;
        }

        h1, h2, p {
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #c58a2b;
            margin-bottom: 20px;
            padding-bottom: 14px;
        }

        .brand {
            color: #8b5e19;
            font-size: 19px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .title {
            color: #263442;
            font-size: 13px;
            font-weight: bold;
            margin-top: 4px;
        }

        .section {
            margin-top: 16px;
        }

        .section-title {
            border-bottom: 1px solid #d8dee5;
            color: #344454;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 7px;
            padding-bottom: 4px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .details td {
            padding: 3px 5px 3px 0;
            vertical-align: top;
        }

        .details .label {
            color: #596775;
            width: 33%;
        }

        .items th,
        .items td {
            border: 1px solid #d8dee5;
            padding: 6px 7px;
            text-align: left;
        }

        .items th {
            background: #f1f4f6;
            color: #344454;
            font-weight: bold;
        }

        .amount {
            text-align: right !important;
            white-space: nowrap;
        }

        .total-row td {
            background: #f8f5ee;
            font-weight: bold;
        }

        .note {
            border-top: 1px solid #d8dee5;
            color: #596775;
            font-size: 9px;
            margin-top: 22px;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="brand">EMBUNG BANSARI</h1>
        <p class="title">BUKTI RESERVASI CAMPING</p>
    </header>

    <section class="section">
        <h2 class="section-title">Informasi Reservasi</h2>
        <table class="details">
            <tr><td class="label">Kode Booking</td><td>{{ $booking->booking_code }}</td></tr>
            <tr><td class="label">Tanggal Reservasi</td><td>{{ $booking->booking_date->format('d/m/Y') }}</td></tr>
            <tr><td class="label">Status Reservasi</td><td>{{ $reservationStatusLabels[$booking->status] ?? ucfirst($booking->status) }}</td></tr>
        </table>
    </section>

    <section class="section">
        <h2 class="section-title">Data Pemesan</h2>
        <table class="details">
            <tr><td class="label">Nama</td><td>{{ $booking->name }}</td></tr>
            <tr><td class="label">Nomor HP</td><td>{{ $booking->phone }}</td></tr>
            <tr><td class="label">Email</td><td>{{ $booking->email }}</td></tr>
        </table>
    </section>

    <section class="section">
        <h2 class="section-title">Detail Reservasi</h2>
        <table class="items">
            <thead>
                <tr>
                    <th>Jenis Reservasi</th>
                    <th>Detail</th>
                    <th class="amount">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @if($booking->package)
                    <tr>
                        <td>Paket</td>
                        <td>{{ $booking->package->name }} ({{ $booking->package->capacity }} orang)</td>
                        <td class="amount">Rp{{ number_format((float) $booking->total_package, 0, ',', '.') }}</td>
                    </tr>
                @else
                    <tr>
                        <td>Non-Paket</td>
                        <td>{{ $booking->number_of_people }} orang × Rp{{ number_format((float) $ratePerPerson, 0, ',', '.') }}</td>
                        <td class="amount">Rp{{ number_format((float) $booking->total_package, 0, ',', '.') }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </section>

    <section class="section">
        <h2 class="section-title">Equipment Tambahan</h2>
        @if($booking->bookingEquipment->isEmpty())
            <p>Tidak ada equipment tambahan.</p>
        @else
            <table class="items">
                <thead>
                    <tr>
                        <th>Nama Equipment</th>
                        <th class="amount">Jumlah</th>
                        <th class="amount">Harga Satuan</th>
                        <th class="amount">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($booking->bookingEquipment as $item)
                        <tr>
                            <td>{{ $item->equipment?->name ?? 'Equipment tidak tersedia' }}</td>
                            <td class="amount">{{ $item->quantity }}</td>
                            <td class="amount">Rp{{ number_format((float) $item->price, 0, ',', '.') }}</td>
                            <td class="amount">Rp{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="section">
        <h2 class="section-title">Pembayaran</h2>
        <table class="items">
            <tbody>
                <tr><td>Metode Pembayaran</td><td>{{ $paymentMethod }}</td></tr>
                <tr><td>Status Pembayaran</td><td>{{ $paymentStatus }}</td></tr>
                <tr><td>Jumlah Dibayar / Nominal Payment</td><td class="amount">Rp{{ number_format($amountReceived, 0, ',', '.') }}</td></tr>
                <tr class="total-row"><td>Total Harga Reservasi</td><td class="amount">Rp{{ number_format($totalAmount, 0, ',', '.') }}</td></tr>
                @if($payment?->status === 'dp')
                    <tr><td>Nominal DP</td><td class="amount">Rp{{ number_format($paymentAmount, 0, ',', '.') }}</td></tr>
                    <tr><td>Sisa Pembayaran</td><td class="amount">Rp{{ number_format($remainingAmount, 0, ',', '.') }}</td></tr>
                @endif
            </tbody>
        </table>
    </section>

    <p class="note">Harap simpan bukti reservasi ini dan tunjukkan kode booking kepada petugas saat diperlukan.</p>
</body>
</html>

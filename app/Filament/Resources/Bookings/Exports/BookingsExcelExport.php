<?php

namespace App\Filament\Resources\Bookings\Exports;

use App\Models\Booking;
use App\Support\PaymentMethodLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookingsExcelExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private const PACKAGE_CAPACITY = 4;

    private const HEADERS = [
        'No',
        'Kode Booking',
        'Nama',
        'Email',
        'No. HP',
        'Tanggal Reservasi',
        'Jenis Reservasi',
        'Paket',
        'Jumlah Orang',
        'Equipment',
        'Total Harga',
        'Status Reservasi',
        'Metode Pembayaran',
        'Status Pembayaran',
        'Dibuat Pada',
    ];

    private int $rowNumber = 0;

    private array $filters;

    public function __construct(array $filters)
    {
        $this->filters = Validator::make($filters, [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:all,pending_confirmation,confirmed,cancelled'],
            'reservation_type' => ['required', 'in:all,package,without_package'],
        ])->validate();
    }

    public function download(): BinaryFileResponse
    {
        return ExcelFacade::download($this, $this->fileName(), ExcelWriter::XLSX);
    }

    public function query(): Builder
    {
        return Booking::query()
            ->with(['package', 'payment', 'bookingEquipment.equipment'])
            ->whereDate('booking_date', '>=', Carbon::parse($this->filters['start_date'])->toDateString())
            ->whereDate('booking_date', '<=', Carbon::parse($this->filters['end_date'])->toDateString())
            ->when(
                $this->filters['status'] !== 'all',
                fn (Builder $query): Builder => $query->where('status', $this->filters['status']),
            )
            ->when($this->filters['reservation_type'] === 'package', fn (Builder $query): Builder => $query->whereNotNull('package_id'))
            ->when($this->filters['reservation_type'] === 'without_package', fn (Builder $query): Builder => $query->whereNull('package_id'))
            ->orderBy('booking_date')
            ->orderBy('id');
    }

    public function headings(): array
    {
        return self::HEADERS;
    }

    public function map($booking): array
    {
        $isPackage = $booking->package_id !== null;
        $equipment = $booking->bookingEquipment
            ->map(fn ($item): string => ($item->equipment?->name ?? 'Equipment') . ' x' . (int) $item->quantity)
            ->implode('; ');

        return [
            ++$this->rowNumber,
            $booking->booking_code,
            $booking->name,
            $booking->email,
            $booking->phone,
            $booking->booking_date?->format('d/m/Y') ?? '-',
            $isPackage ? 'Paket' : 'Non-Paket',
            $isPackage ? ($booking->package?->name ?? '-') : '-',
            $isPackage ? self::PACKAGE_CAPACITY : (int) $booking->number_of_people,
            $equipment !== '' ? $equipment : '-',
            'Rp ' . number_format((float) $booking->total_price, 0, ',', '.'),
            match ($booking->status) {
                'pending_confirmation' => 'Menunggu Konfirmasi',
                'confirmed' => 'Dikonfirmasi',
                'cancelled' => 'Dibatalkan',
                default => ucfirst($booking->status),
            },
            PaymentMethodLabels::label($booking->payment?->payment_method),
            match ($booking->payment?->status) {
                'unpaid' => 'Belum Dibayar',
                'dp' => 'DP 50%',
                'paid' => 'Lunas',
                default => '-',
            },
            $booking->created_at?->format('d/m/Y H:i') ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 22,
            'C' => 25,
            'D' => 32,
            'E' => 18,
            'F' => 20,
            'G' => 18,
            'H' => 24,
            'I' => 15,
            'J' => 45,
            'K' => 22,
            'L' => 25,
            'M' => 38,
            'N' => 20,
            'O' => 22,
        ];
    }

    private function fileName(): string
    {
        $startDate = Carbon::parse($this->filters['start_date'])->toDateString();
        $endDate = Carbon::parse($this->filters['end_date'])->toDateString();

        return "reservasi-embung-bansari-{$startDate}-sampai-{$endDate}.xlsx";
    }
}

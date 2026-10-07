<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Payment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReservationPaymentStats extends StatsOverviewWidget
{
    protected int | array | null $columns = 4;

    protected function getStats(): array
    {
        $receivedAmount = (float) Payment::query()
            ->whereIn('status', ['paid', 'dp'])
            ->sum('amount');

        return [
            Stat::make('Total Pembayaran Diterima', 'Rp' . number_format($receivedAmount, 0, ',', '.'))
                ->icon(Heroicon::OutlinedCurrencyRupee)
                ->color('success'),
            Stat::make('Menunggu Konfirmasi', Booking::query()->where('status', 'pending_confirmation')->count())
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),
            Stat::make('Reservasi Dikonfirmasi', Booking::query()->where('status', 'confirmed')->count())
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
            Stat::make('Belum Dibayar', Payment::query()->where('status', 'unpaid')->count())
                ->icon(Heroicon::OutlinedExclamationCircle)
                ->color('danger'),
        ];
    }
}
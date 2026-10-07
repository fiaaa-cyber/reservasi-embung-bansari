<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use App\Support\PaymentMethodLabels;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Booking')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('booking_code')->label('Kode Booking')->copyable(),
                        TextEntry::make('name')->label('Nama Pemesan'),
                        TextEntry::make('email')->label('Email'),
                        TextEntry::make('phone')->label('Nomor HP'),
                        TextEntry::make('booking_date')->label('Tanggal Reservasi')->date('d/m/Y'),
                        TextEntry::make('reservation_type')
                            ->label('Jenis Reservasi')
                            ->state(fn (Booking $record): string => $record->package_id !== null ? 'Paket' : 'Tanpa Paket')
                            ->badge(),
                        TextEntry::make('package.name')->label('Paket')->placeholder('Tanpa Paket'),
                        TextEntry::make('number_of_people')
                            ->label('Jumlah Orang')
                            ->state(fn (Booking $record): ?int => $record->package_id !== null
                                ? (int) ($record->package?->capacity ?? 4)
                                : $record->number_of_people)
                            ->formatStateUsing(fn ($state): string => filled($state) ? "{$state} orang" : '-'),
                        TextEntry::make('total_price')
                            ->label('Total Harga')
                            ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('status')
                            ->label('Status Reservasi')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending_confirmation' => 'Menunggu Konfirmasi',
                                'confirmed' => 'Dikonfirmasi',
                                'cancelled' => 'Dibatalkan',
                                default => ucfirst($state),
                            })
                            ->badge(),
                        TextEntry::make('created_at')->label('Dibuat Pada')->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Informasi Pembayaran')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('payment.payment_method')
                            ->label('Metode Pembayaran')
                            ->formatStateUsing(fn (?string $state): string => PaymentMethodLabels::label($state)),
                        TextEntry::make('payment.transaction_id')
                            ->label('Transaction ID')
                            ->placeholder('-'),
                        TextEntry::make('payment.amount')
                            ->label('Amount')
                            ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('payment.status')
                            ->label('Status Pembayaran')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'unpaid' => 'Belum Dibayar',
                                'dp' => 'DP 50%',
                                'paid' => 'Lunas',
                                'pending' => 'Menunggu Pembayaran',
                                'failed' => 'Gagal',
                                'cancelled' => 'Dibatalkan',
                                default => '-',
                            })
                            ->badge(),
                        TextEntry::make('payment.paid_at')
                            ->label('Waktu Pembayaran')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                    ]),
                Section::make('Equipment Tambahan')
                    ->schema([
                        RepeatableEntry::make('bookingEquipment')
                            ->label('Daftar Equipment')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('equipment.name')->label('Nama Equipment')->placeholder('-'),
                                TextEntry::make('quantity')->label('Quantity'),
                                TextEntry::make('price')
                                    ->label('Harga')
                                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                            ]),
                    ])
                    ->visible(fn (Booking $record): bool => $record->bookingEquipment->isNotEmpty()),
            ]);
    }
}

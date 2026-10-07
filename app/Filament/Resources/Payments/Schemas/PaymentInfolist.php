<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use App\Support\PaymentMethodLabels;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pembayaran')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('transaction_id')->label('Transaction ID')->placeholder('-'),
                        TextEntry::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->formatStateUsing(fn (?string $state): string => PaymentMethodLabels::label($state)),
                        TextEntry::make('amount')
                            ->label('Amount')
                            ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'unpaid' => 'Belum Dibayar',
                                'dp' => 'DP 50%',
                                'paid' => 'Lunas',
                                default => ucfirst($state),
                            })
                            ->badge(),
                        TextEntry::make('paid_at')->label('Paid At')->dateTime('d/m/Y H:i')->placeholder('-'),
                        TextEntry::make('created_at')->label('Created At')->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Informasi Booking')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('booking.booking_code')->label('Booking Code')->placeholder('-'),
                        TextEntry::make('booking.name')->label('Nama')->placeholder('-'),
                        TextEntry::make('booking.email')->label('Email')->placeholder('-'),
                        TextEntry::make('booking.phone')->label('Nomor HP')->placeholder('-'),
                        TextEntry::make('booking.booking_date')->label('Tanggal Reservasi')->date('d/m/Y')->placeholder('-'),
                        TextEntry::make('booking.package.name')
                            ->label('Nama Package')
                            ->visible(fn (Payment $record): bool => $record->booking?->package_id !== null),
                        TextEntry::make('booking.number_of_people')
                            ->label('Jumlah Orang')
                            ->formatStateUsing(fn ($state): string => filled($state) ? "{$state} orang" : '-')
                            ->visible(fn (Payment $record): bool => $record->booking?->package_id === null),
                    ]),
            ]);
    }
}

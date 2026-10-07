<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\ViewAction;
use App\Filament\Resources\Payments\PaymentResource;
use App\Support\PaymentMethodLabels;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking.booking_code')
                    ->label('Booking Code')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('booking.name')
                    ->label('Nama Pemesan')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->formatStateUsing(fn (?string $state): string => PaymentMethodLabels::label($state)),
                TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unpaid' => 'Belum Dibayar',
                        'dp' => 'DP 50%',
                        'paid' => 'Lunas',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'dp' => 'info',
                        'unpaid' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Pembayaran')
                    ->options([
                        'unpaid' => 'Belum Dibayar',
                        'dp' => 'DP 50%',
                        'paid' => 'Lunas',
                    ]),
                SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options(PaymentMethodLabels::options()),
                Filter::make('paid_at')
                    ->label('Rentang Tanggal Pembayaran')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('paid_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('paid_at', '<=', $date))),
            ])
            ->recordActions([
                PaymentResource::statusAction(),
                ViewAction::make(),
            ]);
    }
}

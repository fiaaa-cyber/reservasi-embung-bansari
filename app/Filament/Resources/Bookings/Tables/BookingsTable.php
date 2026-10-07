<?php

namespace App\Filament\Resources\Bookings\Tables;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use App\Models\Booking;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking_code')
                    ->label('Kode Booking')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Nama Pemesan')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Nomor HP')
                    ->searchable(),
                TextColumn::make('booking_date')
                    ->label('Tanggal Reservasi')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('reservation_type')
                    ->label('Jenis Reservasi')
                    ->state(fn (Booking $record): string => $record->package_id !== null ? 'Paket' : 'Tanpa Paket')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Paket' ? 'info' : 'gray'),
                TextColumn::make('package.name')
                    ->label('Paket')
                    ->placeholder('Tanpa Paket'),
                TextColumn::make('number_of_people')
                    ->label('Jumlah Orang')
                    ->state(fn (Booking $record): ?int => $record->package_id !== null
                        ? (int) ($record->package?->capacity ?? 4)
                        : $record->number_of_people)
                    ->formatStateUsing(fn ($state): string => filled($state) ? "{$state} orang" : '-')
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label('Total Harga')
                    ->formatStateUsing(fn ($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status Reservasi')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending_confirmation' => 'Menunggu Konfirmasi',
                        'confirmed' => 'Dikonfirmasi',
                        'cancelled' => 'Dibatalkan',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'pending_confirmation' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('success')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('Tanggal Akhir')
                            ->required()
                            ->afterOrEqual('start_date'),
                        Select::make('status')
                            ->label('Status Reservasi')
                            ->options([
                                'all' => 'Semua',
                                'pending_confirmation' => 'Menunggu Konfirmasi',
                                'confirmed' => 'Dikonfirmasi',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->default('all')
                            ->required(),
                        Select::make('reservation_type')
                            ->label('Jenis Reservasi')
                            ->options([
                                'all' => 'Semua',
                                'package' => 'Paket',
                                'without_package' => 'Non-Paket',
                            ])
                            ->default('all')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        return redirect()->route('admin.bookings.export-excel', [
                            'start_date' => $data['start_date'],
                            'end_date' => $data['end_date'],
                            'status' => $data['status'],
                            'reservation_type' => $data['reservation_type'],
                        ]);
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Reservasi')
                    ->options([
                        'pending_confirmation' => 'Menunggu Konfirmasi',
                        'confirmed' => 'Dikonfirmasi',
                        'cancelled' => 'Dibatalkan',
                    ]),
                SelectFilter::make('reservation_type')
                    ->label('Jenis Reservasi')
                    ->options([
                        'package' => 'Paket',
                        'without_package' => 'Tanpa Paket',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'package' => $query->whereNotNull('package_id'),
                        'without_package' => $query->whereNull('package_id'),
                        default => $query,
                    }),
                Filter::make('booking_date')
                    ->label('Tanggal Reservasi')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('booking_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('booking_date', '<=', $date))),
            ])
            ->recordActions([
                BookingResource::confirmationAction(),
                BookingResource::cancellationAction(),
                ViewAction::make(),
            ]);
    }
}

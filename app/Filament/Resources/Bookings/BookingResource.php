<?php

namespace App\Filament\Resources\Bookings;

use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\Schemas\BookingInfolist;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'booking_code';

    public static function infolist(Schema $schema): Schema
    {
        return BookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
    }

    public static function confirmationAction(): Action
    {
        return Action::make('confirmReservation')
            ->label('Konfirmasi Reservasi')
            ->color('success')
            ->visible(fn (Booking $record): bool => $record->status === 'pending_confirmation')
            ->requiresConfirmation()
            ->modalHeading('Konfirmasi Reservasi')
            ->modalDescription('Apakah Anda sudah memeriksa reservasi ini dan ingin menandainya sebagai sudah dikonfirmasi?')
            ->action(function (Booking $record): void {
                DB::transaction(function () use ($record): void {
                    $booking = Booking::query()
                        ->lockForUpdate()
                        ->findOrFail($record->getKey());

                    if ($booking->status !== 'pending_confirmation') {
                        return;
                    }

                    $booking->status = 'confirmed';
                    $booking->save();
                });

                Notification::make()
                    ->title('Reservasi berhasil dikonfirmasi.')
                    ->success()
                    ->send();
            });
    }

    public static function cancellationAction(): Action
    {
        return Action::make('cancelReservation')
            ->label('Batalkan Reservasi')
            ->color('danger')
            ->visible(fn (Booking $record): bool => in_array($record->status, ['pending_confirmation', 'confirmed'], true))
            ->requiresConfirmation()
            ->modalHeading('Batalkan Reservasi')
            ->modalDescription('Apakah Anda yakin ingin membatalkan reservasi ini?')
            ->action(function (Booking $record): void {
                $cancelled = DB::transaction(function () use ($record): bool {
                    $booking = Booking::query()
                        ->lockForUpdate()
                        ->findOrFail($record->getKey());

                    if (! in_array($booking->status, ['pending_confirmation', 'confirmed'], true)) {
                        return false;
                    }

                    $booking->status = 'cancelled';
                    $booking->save();

                    return true;
                });

                if (! $cancelled) {
                    return;
                }

                Notification::make()
                    ->title('Reservasi berhasil dibatalkan.')
                    ->success()
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'view' => new PageRegistration(
                page: ViewBooking::class,
                route: fn (Panel $panel): Route => ViewBooking::route('/{record}')
                    ->registerRoute($panel)
                    ->whereNumber('record'),
            ),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['package', 'bookingEquipment.equipment']);
    }
}

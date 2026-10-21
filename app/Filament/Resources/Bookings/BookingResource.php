<?php

namespace App\Filament\Resources\Bookings;

use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\Schemas\BookingInfolist;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Equipment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public static function checkInAction(): Action
    {
        return Action::make('checkIn')
            ->label('Verifikasi Check-in')
            ->color('success')
            ->visible(fn (Booking $record): bool => self::canCheckIn($record))
            ->schema([
                TextInput::make('booking_code')
                    ->label('Kode Booking')
                    ->required(),
            ])
            ->action(function (Booking $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    $booking = Booking::query()
                        ->lockForUpdate()
                        ->findOrFail($record->getKey());

                    self::verifyBookingCode($booking, $data['booking_code'] ?? null);

                    if (! self::isActiveForArrival($booking) || $booking->checked_in_at !== null) {
                        throw ValidationException::withMessages([
                            'booking_code' => 'Booking ini tidak dapat diproses untuk check-in.',
                        ]);
                    }

                    $now = now('Asia/Jakarta');
                    $checkInStart = Carbon::parse($booking->booking_date->toDateString(), 'Asia/Jakarta')
                        ->setTime(14, 0);
                    $checkInEnd = $checkInStart->copy()->addDay()->startOfDay();

                    if ($now->lt($checkInStart) || $now->gte($checkInEnd)) {
                        throw ValidationException::withMessages([
                            'booking_code' => 'Check-in hanya dapat dilakukan pada tanggal reservasi pukul 14.00 sampai sebelum pukul 24.00 WIB.',
                        ]);
                    }

                    $booking->status = 'checked_in';
                    $booking->checked_in_at = $now;
                    $booking->checked_in_by = self::adminId();
                    $booking->save();
                });

                Notification::make()
                    ->title('Kedatangan berhasil diverifikasi.')
                    ->success()
                    ->send();
            });
    }

    public static function checkOutAction(): Action
    {
        return Action::make('checkOut')
            ->label('Catat Check-out')
            ->color('gray')
            ->visible(fn (Booking $record): bool => $record->status === 'checked_in' && $record->checked_out_at === null)
            ->requiresConfirmation()
            ->modalHeading('Catat Check-out')
            ->modalDescription('Check-out hanya dapat dicatat mulai pukul 08.00 WIB pada hari setelah tanggal reservasi.')
            ->action(function (Booking $record): void {
                DB::transaction(function () use ($record): void {
                    $booking = Booking::query()
                        ->lockForUpdate()
                        ->findOrFail($record->getKey());

                    if ($booking->status !== 'checked_in' || $booking->checked_in_at === null || $booking->checked_out_at !== null) {
                        throw ValidationException::withMessages([
                            'record' => 'Booking harus sudah check-in dan belum check-out.',
                        ]);
                    }

                    $checkOutAt = Carbon::parse($booking->booking_date->toDateString(), 'Asia/Jakarta')
                        ->addDay()
                        ->setTime(8, 0);
                    $now = now('Asia/Jakarta');

                    if (! $now->isSameDay($checkOutAt) || $now->lt($checkOutAt)) {
                        throw ValidationException::withMessages([
                            'record' => 'Check-out hanya dapat dicatat mulai pukul 08.00 WIB pada hari setelah tanggal reservasi.',
                        ]);
                    }

                    $booking->status = 'checked_out';
                    $booking->checked_out_at = $now;
                    $booking->checked_out_by = self::adminId();
                    $booking->save();
                });

                Notification::make()
                    ->title('Check-out berhasil dicatat.')
                    ->success()
                    ->send();
            });
    }

    public static function equipmentHandoverAction(): Action
    {
        return Action::make('markEquipmentHandedOver')
            ->label('Catat Perlengkapan Diserahkan')
            ->color('warning')
            ->visible(fn (Booking $record): bool => $record->status === 'checked_in'
                && $record->bookingEquipment()->whereNull('handed_over_at')->exists())
            ->requiresConfirmation()
            ->modalHeading('Catat Penyerahan Perlengkapan')
            ->modalDescription('Konfirmasi bahwa semua perlengkapan booking ini sudah diserahkan kepada pengunjung.')
            ->action(function (Booking $record): void {
                DB::transaction(function () use ($record): void {
                    $booking = Booking::query()
                        ->lockForUpdate()
                        ->findOrFail($record->getKey());

                    if ($booking->status !== 'checked_in') {
                        throw ValidationException::withMessages([
                            'record' => 'Perlengkapan hanya dapat diserahkan setelah check-in.',
                        ]);
                    }

                    $now = now('Asia/Jakarta');
                    $booking->bookingEquipment()
                        ->whereNull('handed_over_at')
                        ->whereNull('stock_released_at')
                        ->lockForUpdate()
                        ->update(['handed_over_at' => $now]);
                });

                Notification::make()
                    ->title('Penyerahan perlengkapan berhasil dicatat.')
                    ->success()
                    ->send();
            });
    }

    public static function markNoShowAction(): Action
    {
        return Action::make('markNoShow')
            ->label('Tandai No-show')
            ->color('danger')
            ->visible(fn (Booking $record): bool => self::isNoShowCandidate($record))
            ->schema([
                TextInput::make('booking_code')
                    ->label('Kode Booking')
                    ->required(),
                Checkbox::make('absence_verified')
                    ->label('Saya telah memastikan pengunjung tidak datang. Untuk booking lama, saya memverifikasi secara manual karena check-in belum tercatat.')
                    ->required()
                    ->rules(['accepted']),
                Checkbox::make('equipment_not_handed_over')
                    ->label('Saya memastikan perlengkapan yang belum ditandai diserahkan memang belum diberikan kepada pengunjung.')
                    ->default(false),
            ])
            ->requiresConfirmation()
            ->modalHeading('Konfirmasi No-show')
            ->modalDescription('Periksa kembali bahwa pengunjung tidak datang. Booking hanya akan berstatus no-show setelah tindakan ini dikonfirmasi.')
            ->action(function (Booking $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    $booking = Booking::query()
                        ->lockForUpdate()
                        ->findOrFail($record->getKey());

                    self::verifyBookingCode($booking, $data['booking_code'] ?? null);

                    if (! self::isNoShowCandidate($booking)) {
                        throw ValidationException::withMessages([
                            'booking_code' => 'Booking ini belum memenuhi syarat untuk ditandai no-show.',
                        ]);
                    }

                    if (! ($data['absence_verified'] ?? false)) {
                        throw ValidationException::withMessages([
                            'absence_verified' => 'Admin harus memverifikasi bahwa pengunjung tidak datang.',
                        ]);
                    }

                    $unhandedEquipment = BookingEquipment::query()
                        ->where('booking_id', $booking->getKey())
                        ->whereNull('handed_over_at')
                        ->whereNull('stock_released_at')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $now = now('Asia/Jakarta');
                    $isLegacyBooking = $booking->attendance_tracking_enabled_at === null;
                    $adminVerifiedLegacyEquipment = (bool) ($data['equipment_not_handed_over'] ?? false);

                    if (! $isLegacyBooking || $adminVerifiedLegacyEquipment) {
                        foreach ($unhandedEquipment as $bookingEquipment) {
                            $equipment = Equipment::query()
                                ->lockForUpdate()
                                ->findOrFail($bookingEquipment->equipment_id);

                            $equipment->increment('stock', $bookingEquipment->quantity);
                            $bookingEquipment->stock_released_at = $now;
                            $bookingEquipment->save();
                        }
                    }

                    $booking->status = 'no_show';
                    $booking->no_show_at = $now;
                    $booking->no_show_confirmed_by = self::adminId();
                    $booking->save();
                });

                Notification::make()
                    ->title('Booking ditandai no-show; stok yang belum diserahkan telah dikembalikan.')
                    ->success()
                    ->send();
            });
    }

    private static function verifyBookingCode(Booking $booking, mixed $bookingCode): void
    {
        if (! is_string($bookingCode) || ! hash_equals($booking->booking_code, trim($bookingCode))) {
            throw ValidationException::withMessages([
                'booking_code' => 'Kode booking tidak cocok.',
            ]);
        }
    }

    private static function adminId(): int
    {
        $adminId = auth()->id();

        if ($adminId === null) {
            throw ValidationException::withMessages([
                'record' => 'Admin harus login untuk melakukan tindakan ini.',
            ]);
        }

        return (int) $adminId;
    }

    private static function isActiveForArrival(Booking $booking): bool
    {
        return in_array($booking->status, ['pending_confirmation', 'confirmed'], true);
    }

    private static function canCheckIn(Booking $booking): bool
    {
        if (! self::isActiveForArrival($booking) || $booking->checked_in_at !== null) {
            return false;
        }

        $now = now('Asia/Jakarta');
        $checkInStart = Carbon::parse($booking->booking_date->toDateString(), 'Asia/Jakarta')
            ->setTime(14, 0);
        $checkInEnd = $checkInStart->copy()->addDay()->startOfDay();

        return $now->gte($checkInStart) && $now->lt($checkInEnd);
    }

    private static function isNoShowCandidate(Booking $booking): bool
    {
        $checkInStart = Carbon::parse($booking->booking_date->toDateString(), 'Asia/Jakarta')
            ->setTime(14, 0);

        return self::isActiveForArrival($booking)
            && ! in_array($booking->status, ['checked_in', 'checked_out'], true)
            && $booking->checked_in_at === null
            && $booking->checked_out_at === null
            && $booking->no_show_at === null
            && now('Asia/Jakarta')->gte($checkInStart);
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
            ->with([
                'package',
                'bookingEquipment.equipment',
                'checkedInBy',
                'checkedOutBy',
                'noShowConfirmedBy',
            ]);
    }
}

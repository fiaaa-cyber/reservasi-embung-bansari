<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'transaction_id';

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function statusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Ubah Status')
            ->color('warning')
            ->visible(fn (Payment $record): bool => in_array($record->status, ['unpaid', 'dp'], true))
            ->fillForm(['status' => 'paid'])
            ->schema([
                Select::make('status')
                    ->label('Konfirmasi status akhir')
                    ->options(['paid' => 'Lunas'])
                    ->required(),
            ])
            ->modalHeading('Ubah Status Pembayaran')
            ->modalDescription(fn (Payment $record): string => $record->status === 'dp'
                ? 'Status saat ini: DP 50%. Konfirmasi bahwa sisa pembayaran telah diterima dan reservasi sudah lunas. Nominal dan transaction ID DP awal tidak akan diubah.'
                : 'Status saat ini: Belum Dibayar. Konfirmasi bahwa pembayaran tunai benar-benar telah diterima.')
            ->modalSubmitActionLabel('Konfirmasi Lunas')
            ->action(function (Payment $record, array $data): void {
                if (($data['status'] ?? null) !== 'paid') {
                    throw ValidationException::withMessages([
                        'status' => 'Status akhir pembayaran harus Lunas.',
                    ]);
                }

                DB::transaction(function () use ($record): void {
                    $payment = Payment::query()
                        ->lockForUpdate()
                        ->findOrFail($record->getKey());

                    if ($payment->status === 'paid') {
                        return;
                    }

                    if (! in_array($payment->status, ['unpaid', 'dp'], true)) {
                        throw ValidationException::withMessages([
                            'status' => 'Hanya pembayaran Belum Dibayar atau DP 50% yang dapat dikonfirmasi Lunas.',
                        ]);
                    }

                    $payment->paid_at ??= now();
                    $payment->status = 'paid';
                    $payment->save();
                });

                Notification::make()
                    ->title('Status pembayaran berhasil diperbarui.')
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
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['booking.package']);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('no_show_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('booking_equipment', function (Blueprint $table): void {
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('stock_released_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('booking_equipment', function (Blueprint $table): void {
            $table->dropColumn(['handed_over_at', 'stock_released_at']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['checked_in_by']);
            $table->dropForeign(['checked_out_by']);
            $table->dropForeign(['no_show_confirmed_by']);
            $table->dropColumn([
                'checked_in_at',
                'checked_out_at',
                'no_show_at',
                'checked_in_by',
                'checked_out_by',
                'no_show_confirmed_by',
            ]);
        });
    }
};

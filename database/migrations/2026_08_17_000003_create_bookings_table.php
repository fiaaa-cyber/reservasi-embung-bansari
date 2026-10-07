<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->date('booking_date');
            $table->unsignedInteger('number_of_people')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->decimal('total_package', 12, 2)->default(0);
            $table->decimal('total_equipment', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReservationDraftsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reservation_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('draft_code')->unique();
            $table->string('reservation_type');
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->date('booking_date');
            $table->unsignedInteger('number_of_people')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->json('equipment')->nullable();
            $table->decimal('total_package', 12, 2)->default(0);
            $table->decimal('total_equipment', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservation_drafts');
    }
}

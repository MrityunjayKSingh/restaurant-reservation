<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 12)->unique();
            $table->foreignId('table_id')->constrained('tables');

            // Customer details
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20);
            $table->unsignedTinyInteger('guest_count');
            $table->text('special_requests')->nullable();

            // Slot
            $table->date('reservation_date');
            $table->time('slot_start');
            $table->time('slot_end');

            // Status
            $table->enum('status', ['confirmed', 'cancelled', 'completed', 'no_show'])
                  ->default('confirmed');

            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Prevent double-booking: same table, same date, overlapping slots
            $table->unique(['table_id', 'reservation_date', 'slot_start'], 'no_double_booking');

            $table->index(['reservation_date', 'status']);
            $table->index(['customer_email']);
            $table->index(['reference_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('table_number')->unique();
            $table->unsignedTinyInteger('capacity');
            $table->enum('location', ['indoor', 'outdoor']);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['location', 'is_active']);
            $table->index(['capacity', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};

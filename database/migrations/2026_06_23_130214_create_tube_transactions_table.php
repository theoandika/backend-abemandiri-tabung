<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tube_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->index();
            $table->foreignId('tube_id')->constrained()->cascadeOnDelete();
            $table->morphs('locationable');
            $table->foreignId('tube_content_type_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type');
            $table->string('fill_status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tube_transactions');
    }
};

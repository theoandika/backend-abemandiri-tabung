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
        Schema::create('collateral_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->index();
            $table->foreignId('collateral_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tube_content_type')->constrained()->cascadeOnDelete();
            $table->string('klep_condition')->nullable();
            $table->string('tube_cap')->nullable();
            $table->unsignedInteger('tube_quantity');
            $table->unsignedBigInteger('nominal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collateral_items');
    }
};

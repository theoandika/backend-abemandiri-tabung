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
        Schema::create('supplier_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tube_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tube_transaction_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_transaction_items');
    }
};

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
        Schema::create('collaterals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->index();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('pic')->nullable();
            $table->string('document_number')->nullable();
            $table->string('member_name');
            $table->text('member_address')->nullable();
            $table->string('signatory_status')->nullable();
            $table->string('company_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('return_payment_method')->nullable();
            $table->date('return_payment_date')->nullable();
            $table->text('collateral_audit')->nullable();
            $table->text('return_audit')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaterals');
    }
};

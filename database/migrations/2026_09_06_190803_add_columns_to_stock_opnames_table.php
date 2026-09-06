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
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dateTime('date')->after('uid');
            $table->foreignId('tube_content_type_id')->after('site_id')->constrained()->cascadeOnDelete();
            $table->string('pic')->after('tube_content_type_id');
            $table->string('tube_status')->after('pic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropForeign(['tube_content_type_id']);
            $table->dropColumn(['date', 'tube_content_type_id', 'pic', 'tube_status']);
        });
    }
};

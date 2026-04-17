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
        Schema::table('reservations', function (Blueprint $table) {
            //
             // Enum des statuts
            $table->enum('status', [
                'pending','confirmed','cancelled','failed','expired','checked_in','checked_out','no_show','refunded'
            ])->default('pending')->after('dure_reservation');

            // Mapping entre pendingKey (frontend callback) et reservation
            $table->string('pending_key')->nullable()->unique()->after('status');

            // id de transaction FedaPay (ou autre)
            $table->string('transaction_id')->nullable()->unique()->after('pending_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            //
            $table->dropColumn(['transaction_id','pending_key','status']);
        });
    }
};
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
        Schema::table('orders', function (Blueprint $table) {
            // Mapping entre pendingKey (frontend callback) et order
            $table->string('pending_key')->nullable()->unique()->after('status');
            
            // id de transaction FedaPay
            $table->string('transaction_id')->nullable()->unique()->after('pending_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['transaction_id', 'pending_key']);
        });
    }
};

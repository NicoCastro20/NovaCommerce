<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_premium')->default(false)->after('company_name');
            $table->timestamp('premium_since')->nullable()->after('is_premium');
            $table->timestamp('premium_until')->nullable()->after('premium_since');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('envio_premium')->default(false)->after('shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('envio_premium');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_premium', 'premium_since', 'premium_until']);
        });
    }
};

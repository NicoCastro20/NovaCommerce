<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('original_price', 10, 2)
                ->nullable()
                ->after('price');

            $table->timestamp('offer_starts_at')
                ->nullable()
                ->after('original_price');

            $table->timestamp('offer_ends_at')
                ->nullable()
                ->after('offer_starts_at');

            $table->string('offer_label', 50)
                ->nullable()
                ->after('offer_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['original_price', 'offer_starts_at', 'offer_ends_at', 'offer_label']);
        });
    }
};

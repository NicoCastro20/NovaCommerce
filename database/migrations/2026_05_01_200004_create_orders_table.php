<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('order_number')->unique();
            // string en lugar de enum porque migraciones posteriores amplían los
            // estados válidos (p. ej. crear_sistema_devoluciones añade 'devuelto').
            $table->string('status', 20)->default('pendiente');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('shipping_address');
            $table->string('shipping_city');
            $table->string('shipping_postal_code', 10);
            $table->string('shipping_country', 60)->default('España');
            $table->string('payment_method')->nullable();
            $table->string('payment_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

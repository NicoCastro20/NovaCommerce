<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── orders.status: añadimos 'devuelto' ──────────────────────────────
        // SQLite no enforce ENUM (almacena TEXT), así que solo MySQL necesita
        // recrear la columna con los valores ampliados.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM('pendiente','pagado','enviado','entregado','cancelado','devuelto') NOT NULL DEFAULT 'pendiente'"
            );
        }

        // ── orders.delivered_at ─────────────────────────────────────────────
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable()->after('payment_id');
        });

        // ── Tabla returns ───────────────────────────────────────────────────
        Schema::create('returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // Texto libre acotado: el catálogo de motivos lo controla la app
            // (ver ReturnRequest::MOTIVOS) y puede ampliarse sin migraciones.
            $table->string('reason', 60);
            $table->text('description')->nullable();
            $table->enum('status', ['solicitada', 'aprobada', 'rechazada'])->default('solicitada');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('delivered_at');
        });

        DB::table('orders')->where('status', 'devuelto')->update(['status' => 'entregado']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM('pendiente','pagado','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente'"
            );
        }
    }
};

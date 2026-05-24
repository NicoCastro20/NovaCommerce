<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Relajamos `returns.reason` para que admita los nuevos motivos
        // genéricos del flujo de devolución automática y futuras variantes.
        // SQLite almacena ENUM como TEXT, así que solo es necesario en MySQL.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE returns MODIFY reason VARCHAR(60) NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE returns MODIFY reason ENUM('producto_defectuoso','no_coincide_descripcion','producto_dañado','error_en_pedido','otro') NOT NULL"
            );
        }
    }
};

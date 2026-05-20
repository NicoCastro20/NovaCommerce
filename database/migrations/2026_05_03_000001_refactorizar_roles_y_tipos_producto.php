<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // ── users.role: 'cliente'/'vendedor' → 'usuario'/'empresa' ───────────
        if ($driver === 'mysql') {
            // En MySQL hay que ampliar el enum primero para poder migrar valores
            // antiguos sin perderlos, hacer el UPDATE, y reducir el enum al final.
            DB::statement("ALTER TABLE users MODIFY role ENUM('cliente','vendedor','admin','usuario','empresa') NOT NULL DEFAULT 'cliente'");
            DB::statement("UPDATE users SET role = 'usuario' WHERE role = 'cliente'");
            DB::statement("UPDATE users SET role = 'empresa' WHERE role = 'vendedor'");
            DB::statement("ALTER TABLE users MODIFY role ENUM('usuario','empresa','admin') NOT NULL DEFAULT 'usuario'");
        } else {
            // SQLite (tests) y otros motores guardan el rol como TEXT, así que
            // basta con migrar los valores existentes.
            DB::table('users')->where('role', 'cliente')->update(['role' => 'usuario']);
            DB::table('users')->where('role', 'vendedor')->update(['role' => 'empresa']);
        }

        // ── products: nuevos campos type y condition ─────────────────────────
        Schema::table('products', function (Blueprint $table): void {
            $table->enum('type', ['nuevo', 'segunda_mano'])
                ->default('nuevo')
                ->after('stock');

            $table->enum('condition', ['nuevo', 'como_nuevo', 'buen_estado', 'usado'])
                ->nullable()
                ->after('type');
        });

        // Productos existentes: los que pertenezcan a empresas se quedan como
        // 'nuevo' (default); el resto pasan a 'segunda_mano'. Usamos una
        // subconsulta correlacionada para que funcione en MySQL y SQLite.
        DB::statement(
            "UPDATE products
             SET type = 'segunda_mano'
             WHERE NOT EXISTS (
                 SELECT 1 FROM users
                 WHERE users.id = products.user_id
                   AND users.role = 'empresa'
             )"
        );
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['type', 'condition']);
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('usuario','empresa','admin','cliente','vendedor') NOT NULL DEFAULT 'usuario'");
            DB::statement("UPDATE users SET role = 'cliente' WHERE role = 'usuario'");
            DB::statement("UPDATE users SET role = 'vendedor' WHERE role = 'empresa'");
            DB::statement("ALTER TABLE users MODIFY role ENUM('cliente','vendedor','admin') NOT NULL DEFAULT 'cliente'");
        } else {
            DB::table('users')->where('role', 'usuario')->update(['role' => 'cliente']);
            DB::table('users')->where('role', 'empresa')->update(['role' => 'vendedor']);
        }
    }
};

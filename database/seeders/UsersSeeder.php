<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando usuarios de prueba...');

        // Administrador
        User::create([
            'name'              => 'Administrador NovaCommerce',
            'email'             => 'admin@novacommerce.com',
            'password'          => Hash::make('password'),
            'role'              => 'admin',
            'phone'             => '612 000 001',
            'email_verified_at' => now(),
        ]);

        // Empresas (rol 'empresa', con NIF y nombre comercial)
        User::create([
            'name'              => 'Equipo TechStore',
            'email'             => 'techstore@novacommerce.com',
            'password'          => Hash::make('password'),
            'role'              => 'empresa',
            'phone'             => '612 100 001',
            'nif_cif'           => 'B12345678',
            'company_name'      => 'TechStore España S.L.',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name'              => 'Equipo MueblesDeco',
            'email'             => 'mueblesdeco@novacommerce.com',
            'password'          => Hash::make('password'),
            'role'              => 'empresa',
            'phone'             => '612 100 002',
            'nif_cif'           => 'B87654321',
            'company_name'      => 'MueblesDeco S.A.',
            'email_verified_at' => now(),
        ]);

        // Usuarios particulares (rol 'usuario')
        User::create([
            'name'              => 'Carlos García López',
            'email'             => 'carlos@ejemplo.com',
            'password'          => Hash::make('password'),
            'role'              => 'usuario',
            'phone'             => '612 200 001',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name'              => 'María Fernández Ruiz',
            'email'             => 'maria@ejemplo.com',
            'password'          => Hash::make('password'),
            'role'              => 'usuario',
            'phone'             => '612 200 002',
            'email_verified_at' => now(),
        ]);

        User::create([
            'name'              => 'Javier Martínez Torres',
            'email'             => 'javier@ejemplo.com',
            'password'          => Hash::make('password'),
            'role'              => 'usuario',
            'phone'             => '612 200 003',
            'email_verified_at' => now(),
        ]);

        $this->command->info('✓ ' . User::count() . ' usuarios creados (1 admin, 2 empresas, 3 particulares).');
        $this->command->line('  Credenciales: cualquier email / password');
    }
}

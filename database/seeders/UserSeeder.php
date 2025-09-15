<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener roles
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $employeeRole = Role::where('name', 'employee')->first();

        // Crear usuario administrador
        User::firstOrCreate(
            ['email' => 'admin@almacen.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password123'),
                'role_id' => $adminRole->id,
                'email_verified_at' => now()
            ]
        );

        // Crear usuario gerente
        User::firstOrCreate(
            ['email' => 'gerente@almacen.com'],
            [
                'name' => 'Gerente de Almacén',
                'password' => Hash::make('password123'),
                'role_id' => $managerRole->id,
                'email_verified_at' => now()
            ]
        );

        // Crear usuario empleado
        User::firstOrCreate(
            ['email' => 'empleado@almacen.com'],
            [
                'name' => 'Empleado de Almacén',
                'password' => Hash::make('password123'),
                'role_id' => $employeeRole->id,
                'email_verified_at' => now()
            ]
        );

        // Crear algunos usuarios adicionales para pruebas
        User::firstOrCreate(
            ['email' => 'juan.perez@almacen.com'],
            [
                'name' => 'Juan Pérez',
                'password' => Hash::make('password123'),
                'role_id' => $employeeRole->id,
                'email_verified_at' => now()
            ]
        );

        User::firstOrCreate(
            ['email' => 'maria.garcia@almacen.com'],
            [
                'name' => 'María García',
                'password' => Hash::make('password123'),
                'role_id' => $managerRole->id,
                'email_verified_at' => now()
            ]
        );
    }
}

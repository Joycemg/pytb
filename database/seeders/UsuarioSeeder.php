<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Usuario;

final class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        Usuario::firstOrCreate(
            ['email' => 'admin@taberna.test'],
            [
                'name' => 'Administrador',
                'username' => 'admin',
                'password' => Hash::make('secret'),
                'role' => 'admin',
                'bio' => 'Cuenta administrativa de ejemplo.',
                'honor' => 100,
            ]
        );

        // Usuario normal
        Usuario::firstOrCreate(
            ['email' => 'jugador@taberna.test'],
            [
                'name' => 'Jugador de prueba',
                'username' => 'jugador',
                'password' => Hash::make('secret'),
                'role' => 'user',
                'bio' => 'Cuenta normal para pruebas.',
                'honor' => 10,
            ]
        );
    }
}

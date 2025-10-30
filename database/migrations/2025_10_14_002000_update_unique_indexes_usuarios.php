<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            // Soltar índices únicos viejos si existen (nombrados por convención)
            try {
                $t->dropUnique('usuarios_email_unique');
            } catch (\Throwable $e) {
            }
            try {
                $t->dropUnique('usuarios_username_unique');
            } catch (\Throwable $e) {
            }

            // Asegurar que deleted_at exista (si no estás 100% seguro)
            if (!Schema::hasColumn('usuarios', 'deleted_at')) {
                $t->softDeletes()->index();
            }
        });

        Schema::table('usuarios', function (Blueprint $t) {
            // Únicos compuestos: permiten un activo y N soft-deleted del mismo valor
            $t->unique(['email', 'deleted_at'], 'usuarios_email_deleted_at_unique');
            $t->unique(['username', 'deleted_at'], 'usuarios_username_deleted_at_unique');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            try {
                $t->dropUnique('usuarios_email_deleted_at_unique');
            } catch (\Throwable $e) {
            }
            try {
                $t->dropUnique('usuarios_username_deleted_at_unique');
            } catch (\Throwable $e) {
            }

            // Volver a únicos simples (si querés revertir)
            $t->unique('email', 'usuarios_email_unique');
            $t->unique('username', 'usuarios_username_unique');
        });
    }
};

<?php
// database/migrations/2025_01_01_000000_create_jornada_apartados_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jornada_apartados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_id')->constrained('jornadas')->cascadeOnDelete();
            $table->string('titulo', 120);
            $table->unsignedTinyInteger('orden')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Columna en MESAS para vincular un apartado (si no existe)
        if (!Schema::hasColumn('mesas', 'jornada_apartado_id')) {
            Schema::table('mesas', function (Blueprint $table) {
                $table->unsignedBigInteger('jornada_apartado_id')->nullable()->after('jornada_id');
                $table->foreign('jornada_apartado_id')->references('id')->on('jornada_apartados')->onDelete('set null');
                $table->index(['jornada_id', 'jornada_apartado_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mesas') && Schema::hasColumn('mesas', 'jornada_apartado_id')) {
            Schema::table('mesas', function (Blueprint $table) {
                $table->dropForeign(['jornada_apartado_id']);
                $table->dropIndex(['jornada_id', 'jornada_apartado_id']);
                $table->dropColumn('jornada_apartado_id');
            });
        }
        Schema::dropIfExists('jornada_apartados');
    }
};

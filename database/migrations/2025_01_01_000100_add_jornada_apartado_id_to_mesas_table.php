<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            if (!Schema::hasColumn('mesas', 'jornada_apartado_id')) {
                $table->foreignId('jornada_apartado_id')
                    ->nullable()
                    ->after('jornada_id')
                    ->constrained('jornada_apartados')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            if (Schema::hasColumn('mesas', 'jornada_apartado_id')) {
                $table->dropConstrainedForeignId('jornada_apartado_id');
            }
        });
    }
};

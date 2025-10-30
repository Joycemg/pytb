<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            if (!Schema::hasColumn('mesas', 'inscripciones_abren_at')) {
                $table->dateTime('inscripciones_abren_at')->nullable()->after('opens_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            if (Schema::hasColumn('mesas', 'inscripciones_abren_at')) {
                $table->dropColumn('inscripciones_abren_at');
            }
        });
    }
};

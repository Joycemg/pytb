<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $t) {
            $t->unsignedBigInteger('jornada_id')->nullable()->after('id');
            $t->foreign('jornada_id')->references('id')->on('jornadas')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $t) {
            $t->dropForeign(['jornada_id']);
            $t->dropColumn('jornada_id');
        });
    }
};

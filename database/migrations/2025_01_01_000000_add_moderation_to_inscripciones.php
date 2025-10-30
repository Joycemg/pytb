<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inscripciones', function (Blueprint $t) {
            if (!Schema::hasColumn('inscripciones', 'moderated_at')) {
                $t->timestamp('moderated_at')->nullable()->after('is_waiting');
            }
            if (!Schema::hasColumn('inscripciones', 'moderated_by')) {
                $t->unsignedBigInteger('moderated_by')->nullable()->after('moderated_at');
                $t->foreign('moderated_by')->references('id')->on('usuarios')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inscripciones', function (Blueprint $t) {
            if (Schema::hasColumn('inscripciones', 'moderated_by')) {
                $t->dropForeign(['moderated_by']);
                $t->dropColumn('moderated_by');
            }
            if (Schema::hasColumn('inscripciones', 'moderated_at')) {
                $t->dropColumn('moderated_at');
            }
        });
    }
};

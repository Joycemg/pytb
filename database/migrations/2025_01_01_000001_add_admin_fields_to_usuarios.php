<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            if (!Schema::hasColumn('usuarios', 'approved_at'))
                $t->timestamp('approved_at')->nullable()->index();
            if (!Schema::hasColumn('usuarios', 'approved_by'))
                $t->unsignedBigInteger('approved_by')->nullable()->index();
            if (!Schema::hasColumn('usuarios', 'role'))
                $t->string('role', 50)->nullable()->index();
            if (!Schema::hasColumn('usuarios', 'locked_at'))
                $t->timestamp('locked_at')->nullable()->index();

            // Opcional FK suave (no estricta, por hosting compartido)
            // $t->foreign('approved_by')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $t) {
            if (Schema::hasColumn('usuarios', 'approved_at'))
                $t->dropColumn('approved_at');
            if (Schema::hasColumn('usuarios', 'approved_by'))
                $t->dropColumn('approved_by');
            if (Schema::hasColumn('usuarios', 'role'))
                $t->dropColumn('role');
            if (Schema::hasColumn('usuarios', 'locked_at'))
                $t->dropColumn('locked_at');
        });
    }
};

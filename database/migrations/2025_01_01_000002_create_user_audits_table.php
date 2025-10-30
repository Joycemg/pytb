<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_audits', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('actor_id')->nullable()->index();   // quién ejecuta
            $t->unsignedBigInteger('target_id')->nullable()->index();  // a quién afecta
            $t->string('action', 100)->index();                        // e.g. approve, role.set, lock, unlock, pwd.reset, bulk.approve
            $t->json('meta')->nullable();                              // detalle (antes/después, lote, etc.)
            $t->string('ip', 64)->nullable()->index();
            $t->string('ua', 255)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_audits');
    }
};

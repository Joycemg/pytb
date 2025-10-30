<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('eventos_honor', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('usuarios');
            $t->foreignId('mesa_id')->nullable()->constrained('mesas');
            $t->string('slug')->index();
            $t->integer('delta');
            $t->string('reason')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->unique(['user_id','slug']);
        });
    }
    public function down(): void { Schema::dropIfExists('eventos_honor'); }
};

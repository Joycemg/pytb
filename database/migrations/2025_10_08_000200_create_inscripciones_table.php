<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inscripciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('usuarios');
            $t->foreignId('mesa_id')->constrained('mesas');
            $t->boolean('is_waiting')->default(false);
            $t->timestamp('attendance_confirmed_at')->nullable();
            $t->timestamp('no_show_at')->nullable();
            $t->boolean('is_counted')->default(true);
            $t->json('notes')->nullable();
            $t->timestamps();
            $t->unique(['user_id','mesa_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('inscripciones'); }
};

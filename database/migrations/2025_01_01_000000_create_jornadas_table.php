<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jornadas', function (Blueprint $t) {
            $t->id();
            $t->string('titulo', 120)->nullable();      // opcional: nombre "Sábado 12/10"
            $t->string('estado', 20)->default('abierta'); // abierta | cerrada
            $t->timestamp('abierta_at')->nullable();
            $t->timestamp('cerrada_at')->nullable();
            $t->unsignedBigInteger('abierta_por')->nullable();
            $t->unsignedBigInteger('cerrada_por')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('jornadas');
    }
};

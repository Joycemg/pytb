<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usuarios', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('username')->unique()->nullable();
            $t->string('email')->unique()->nullable();
            $t->string('password')->nullable();
            $t->text('bio')->nullable();
            $t->string('avatar_path')->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('role')->nullable();
            $t->integer('honor')->default(0);
            $t->rememberToken();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('usuarios'); }
};

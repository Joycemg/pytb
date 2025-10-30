<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('mesas', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->foreignId('created_by')->constrained('usuarios');
            $t->foreignId('manager_id')->nullable()->constrained('usuarios');
            $t->unsignedSmallInteger('capacity')->default(6);
            $t->boolean('manager_counts_as_player')->default(false);
            $t->boolean('is_open')->default(true);
            $t->timestamp('opens_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->string('image_path')->nullable();
            $t->string('image_url')->nullable();
            $t->timestamps();
            $t->index(['is_open','opens_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('mesas'); }
};

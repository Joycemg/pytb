<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();

            // si querés atarlo a un usuario autenticado:
            $t->unsignedBigInteger('subscribable_id')->nullable();
            $t->string('subscribable_type')->nullable();

            $t->string('endpoint', 500)->unique();
            $t->string('p256dh', 255)->nullable();
            $t->string('auth', 255)->nullable();
            $t->string('content_encoding', 20)->default('aes128gcm');

            $t->timestamps();

            // index opcional
            $t->index(['subscribable_id', 'subscribable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};

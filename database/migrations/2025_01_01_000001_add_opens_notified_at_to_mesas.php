<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $t) {
            if (!Schema::hasColumn('mesas', 'opens_at')) {
                $t->timestamp('opens_at')->nullable()->index();
            }
            if (!Schema::hasColumn('mesas', 'opens_notified_at')) {
                $t->timestamp('opens_notified_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $t) {
            if (Schema::hasColumn('mesas', 'opens_notified_at')) {
                $t->dropColumn('opens_notified_at');
            }
        });
    }
};

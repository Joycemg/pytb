<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            if (!Schema::hasColumn('mesas', 'single_vote')) {
                $table->boolean('single_vote')->default(false)->after('manager_counts_as_player');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            if (Schema::hasColumn('mesas', 'single_vote')) {
                $table->dropColumn('single_vote');
            }
        });
    }
};

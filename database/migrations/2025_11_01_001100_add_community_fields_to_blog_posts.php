<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            if (!Schema::hasColumn('blog_posts', 'is_community')) {
                $table->boolean('is_community')->default(false)->after('content')->index();
            }

            if (!Schema::hasColumn('blog_posts', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('published_at')->index();
            }

            if (!Schema::hasColumn('blog_posts', 'approved_by')) {
                $table->foreignId('approved_by')
                    ->nullable()
                    ->after('approved_at')
                    ->constrained('usuarios')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            if (Schema::hasColumn('blog_posts', 'approved_by')) {
                $table->dropForeign(['approved_by']);
                $table->dropColumn('approved_by');
            }

            if (Schema::hasColumn('blog_posts', 'approved_at')) {
                $table->dropColumn('approved_at');
            }

            if (Schema::hasColumn('blog_posts', 'is_community')) {
                $table->dropColumn('is_community');
            }
        });
    }
};

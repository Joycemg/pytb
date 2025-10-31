<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->string('meta_title')->nullable()->after('hero_image_caption');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
            $table->string('meta_image_url', 500)->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn([
                'meta_title',
                'meta_description',
                'meta_image_url',
            ]);
        });
    }
};

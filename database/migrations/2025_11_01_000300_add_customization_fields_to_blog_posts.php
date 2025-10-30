<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->string('theme')->default('classic')->after('content');
            $table->string('accent_color', 7)->nullable()->after('theme');
            $table->string('accent_text_color', 7)->nullable()->after('accent_color');
            $table->string('hero_image_url', 500)->nullable()->after('accent_text_color');
            $table->string('hero_image_caption', 160)->nullable()->after('hero_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn([
                'theme',
                'accent_color',
                'accent_text_color',
                'hero_image_url',
                'hero_image_caption',
            ]);
        });
    }
};

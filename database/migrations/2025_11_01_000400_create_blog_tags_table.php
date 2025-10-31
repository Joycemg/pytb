<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_post_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('blog_post_id');
            $table->unsignedBigInteger('blog_tag_id');

            $table->foreign('blog_post_id')
                ->references('id')
                ->on('blog_posts')
                ->cascadeOnDelete();

            $table->foreign('blog_tag_id')
                ->references('id')
                ->on('blog_tags')
                ->cascadeOnDelete();

            $table->primary(['blog_post_id', 'blog_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_tags');
    }
};

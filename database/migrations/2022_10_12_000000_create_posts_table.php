<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->string('post_id')->unique();
            $table->string('user_id');
            $table->string('title');
            $table->string('outline');
            $table->text('body');
            $table->string('conversion');
            $table->string('series');
            $table->integer('rating');
            $table->integer('chara');
            $table->integer('creation');
            $table->string('tags');
            $table->integer('filter')->default(0);
            $table->integer('publishing');
            $table->string('publishing_sub1');
            $table->string('publishing_sub2');
            $table->boolean('searchable');
            $table->integer('view_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}

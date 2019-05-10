<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoreChattyTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->unsignedInteger('id');
            $table->timestampTz('date');
            $table->timestampTz('bump_date');
            $table->primary('id');
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->unsignedInteger('id');
            $table->unsignedInteger('thread_id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('author');
            $table->unsignedInteger('category')->nullable();
            $table->timestampTz('date');
            $table->string('body');
            $table->string('author_c');
            $table->string('body_c');
            $table->primary('id');
            $table->foreign('thread_id')->references('id')->on('threads')->onDelete('cascade');
        });

        Schema::create('post_lols', function(Blueprint $table) {
            $table->unsignedInteger('post_id');
            $table->string('tag',100);
            $table->unsignedInteger('count');
            $table->primary(['post_id','tag']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('post_lols');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('threads');
    }
}

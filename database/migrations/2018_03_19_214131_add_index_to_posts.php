<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexToPosts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('threads', function(Blueprint $table) {
            $table->dropIndex('thread_date_index');
        });
        Schema::table('posts', function(Blueprint $table) {
            $table->index(['id','thread_id'],'posts_id_thread_index');
        });
        Schema::table('posts', function(Blueprint $table) {
            $table->index(['parent_id','date'],'posts_parent_date_index');
        });
        Schema::table('posts', function(Blueprint $table) {
            $table->index('thread_id','posts_thread_index');
        });
        Schema::table('threads', function(Blueprint $table) {
            $table->index(['date','bump_date'],'threads_bump_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('posts', function(Blueprint $table) {
            $table->dropIndex('posts_id_thread_index');
        });
        Schema::table('posts', function(Blueprint $table) {
            $table->dropIndex('posts_parent_date_index');
        });
        Schema::table('posts', function(Blueprint $table) {
            $table->dropIndex('posts_thread_index');
        });
        Schema::table('threads', function(Blueprint $table) {
            $table->dropIndex('threads_bump_index');
        });
        Schema::table('threads', function(Blueprint $table) {
            $table->index(['id','date'],'thread_date_index');
        });
    }
}

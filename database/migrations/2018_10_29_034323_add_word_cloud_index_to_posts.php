<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWordCloudIndexToPosts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('posts', function(Blueprint $table) {
            $table->index(['date', 'category', 'indexed', 'author_c'],'posts_wordcloud_lookup');
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
            $table->dropIndex('posts_wordcloud_lookup');
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangePostsIndexToIncludeDate extends Migration
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
            $table->dropIndex('posts_category_searchindexflag');
        });

        Schema::table('posts', function(Blueprint $table) {
            $table->index(['id','date','category','indexed'],'posts_category_searchindexflag');
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
            $table->dropIndex('posts_category_searchindexflag');
        });
        
        Schema::table('posts', function(Blueprint $table) {
            $table->index(['id','category','indexed'],'posts_category_searchindexflag');
        });
    }
}

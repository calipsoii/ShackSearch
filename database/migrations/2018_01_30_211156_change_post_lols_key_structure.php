<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangePostLolsKeyStructure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::dropIfExists('post_lols');

        Schema::create('post_lols', function(Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->string('tag',100);
            $table->unsignedInteger('count');
            $table->unique(['post_id','tag']);
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
        Schema::dropIfExists('post_lols');

        Schema::create('post_lols', function(Blueprint $table) {
            $table->unsignedInteger('post_id');
            $table->string('tag',100);
            $table->unsignedInteger('count');
            $table->primary(['post_id','tag']);
        });
    }
}

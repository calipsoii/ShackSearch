<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePostCountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('post_counts', function (Blueprint $table) {
            $table->integer('block_id');
            $table->integer('count');
            $table->integer('excluded');
            $table->float('percent');
            $table->timestamps();
        });

        // Add the indexes
        Schema::table('post_counts', function (Blueprint $table) {
            $table->primary('block_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('post_counts');
    }
}

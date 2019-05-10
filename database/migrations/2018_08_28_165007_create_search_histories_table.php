<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSearchHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('search_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user')->nullable();
            $table->string('ip')->nullable();
            $table->string('author')->nullable();
            $table->string('text')->nullable();
            $table->datetime('from');
            $table->datetime('to');
            $table->boolean('root_posts');
            $table->string('engine');
            $table->string('link_target');
            $table->string('sort');
            $table->integer('seconds');
            $table->timestamps();
            $table->index(['id','user','created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('search_histories');
    }
}

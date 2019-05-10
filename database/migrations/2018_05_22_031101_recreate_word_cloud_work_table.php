<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RecreateWordCloudWorkTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::dropIfExists('word_cloud_work');

        Schema::create('word_cloud_work', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('user');
            $table->string('term');
            $table->string('sentiment')->nullable();
            $table->integer('count')->default(0);
            $table->timestamps();
            $table->primary(['id','term']);
            $table->index('user');
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
        Schema::dropIfExists('word_cloud_work');

        Schema::create('word_cloud_work', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user');
            $table->uuid('guid');
            $table->string('term');
            $table->string('count');
            $table->string('sentiment')->nullable();
            $table->timestamps();
        });
    }
}

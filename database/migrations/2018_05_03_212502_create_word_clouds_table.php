<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWordCloudsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('word_clouds', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user');
            $table->uuid('guid');
            $table->timestamp('from');
            $table->timestamp('to');
            $table->text('imagefilepath');
            $table->timestamps();
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
        Schema::dropIfExists('word_clouds');
    }
}

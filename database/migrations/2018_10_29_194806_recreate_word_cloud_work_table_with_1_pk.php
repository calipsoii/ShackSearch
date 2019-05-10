<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RecreateWordCloudWorkTableWith1Pk extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('word_cloud_work', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('word_cloud_id');
            $table->string('user');
            $table->string('term');
            $table->string('sentiment')->nullable();
            $table->integer('count')->nullable();
            $table->float('score')->nullable();
            $table->float('computed_score')->nullable();
            $table->float('doc_freq')->nullable();
            $table->timestamps();
            $table->index(['word_cloud_id','term'],'word_cloud_id_term_index');
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
    }
}

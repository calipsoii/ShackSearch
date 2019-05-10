<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWordCloudPhrasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('word_cloud_phrases', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('wordcloud_id');
            $table->string('term');
            $table->string('phrase');
            $table->integer('phrase_count');
            $table->string('phrase_term_1')->nullable();
            $table->string('phrase_term_2')->nullable();
            $table->string('phrase_term_3')->nullable();
            $table->timestamps();
            $table->index(['wordcloud_id','phrase']);
            $table->index(['wordcloud_id','phrase_count','phrase_term_1','phrase_term_2','phrase_term_3']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('word_cloud_phrases');
    }
}

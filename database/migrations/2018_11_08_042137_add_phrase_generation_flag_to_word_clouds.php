<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPhraseGenerationFlagToWordClouds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('word_clouds', function (Blueprint $table) {
            $table->boolean('generate_phrases')->default(false);
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
        Schema::table('word_clouds', function (Blueprint $table) {
            $table->dropColumn('generate_phrases');
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPhraseCountThresholdToAppsettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('app_settings', function (Blueprint $table) {
            $table->integer('wordcloud_phrases_2term_threshold')->default(2);
            $table->integer('wordcloud_phrases_3term_threshold')->default(3);
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
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('wordcloud_phrases_2term_threshold');
            $table->dropColumn('wordcloud_phrases_3term_threshold');
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDailyCloudPhrasesToAppsettings extends Migration
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
            $table->boolean('chatty_daily_wordcloud_phrases')->default(false);
            $table->boolean('wordcloud_phrases_default')->default(false);
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
            $table->dropColumn('chatty_daily_wordcloud_phrases');
            $table->dropColumn('wordcloud_phrases_default');
        });
    }
}

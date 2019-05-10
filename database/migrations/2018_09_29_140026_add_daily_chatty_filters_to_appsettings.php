<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDailyChattyFiltersToAppsettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('app_settings', function(Blueprint $table) {
            $table->boolean('chatty_daily_wordcloud_ontopic')->default('true');
            $table->boolean('chatty_daily_wordcloud_nws')->default('false');
            $table->boolean('chatty_daily_wordcloud_stupid')->default('true');
            $table->boolean('chatty_daily_wordcloud_political')->default('false');
            $table->boolean('chatty_daily_wordcloud_tangent')->default('true');
            $table->boolean('chatty_daily_wordcloud_informative')->default('true');
            $table->boolean('chatty_daily_wordcloud_nuked')->default('false');
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
        Schema::table('app_settings', function(Blueprint $table) {
            $table->dropColumn('chatty_daily_wordcloud_ontopic');
            $table->dropColumn('chatty_daily_wordcloud_nws');
            $table->dropColumn('chatty_daily_wordcloud_stupid');
            $table->dropColumn('chatty_daily_wordcloud_political');
            $table->dropColumn('chatty_daily_wordcloud_tangent');
            $table->dropColumn('chatty_daily_wordcloud_informative');
            $table->dropColumn('chatty_daily_wordcloud_nuked');
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDailyCloudGuidToAppsettings extends Migration
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
            $table->uuid('chatty_daily_wordcloud_guid')->nullable();
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
            $table->dropColumn('chatty_daily_wordcloud_guid');
        });
    }
}

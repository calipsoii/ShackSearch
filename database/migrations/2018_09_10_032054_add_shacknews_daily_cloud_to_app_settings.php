<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddShacknewsDailyCloudToAppSettings extends Migration
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
            $table->integer('chatty_daily_wordcloud_hours')->default(24);               // Number of hours to query/display for the Chatty daily word cloud
            $table->string('chatty_daily_wordcloud_user')->default('sn_chatty_cloud');  // This breaks if someone registers this account. Fingers crossed they don't.
            $table->integer('chatty_daily_wordcloud_filter')->default(1);               // Which filter to apply to this cloud
            $table->integer('chatty_daily_wordcloud_colorset')->default(1);             // Default colorset to apply to this cloud
            $table->string('chatty_daily_wordcloud_cloud_perms')->default('Chatty');    // Default viewership for the cloud
            $table->string('chatty_daily_wordcloud_table_perms')->default('Chatty');    // Default viewership for the data table
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

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSearchCrawlerUserToAppSettings extends Migration
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
            $table->string('search_crawler_username')->default('SearchCrawler');
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
            $table->dropColumn('search_crawler_username');
        });
    }
}

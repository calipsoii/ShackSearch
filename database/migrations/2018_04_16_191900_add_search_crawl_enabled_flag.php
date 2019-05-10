<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSearchCrawlEnabledFlag extends Migration
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
            $table->boolean('search_crawler_enabled')->default('false');
            $table->integer('search_crawler_posts_to_index')->default(1000);
            $table->timestamp('search_crawler_last_run')->useCurrent=true;
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
            $table->dropColumn('search_crawler_enabled');
            $table->dropColumn('search_crawler_posts_to_index');
            $table->dropColumn('search_crawler_last_run');
        });
    }
}

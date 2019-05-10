<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPaginationValuesToAppsettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // How many records to return for the paginated result list for Events & Logs
        Schema::table('app_settings', function(Blueprint $table) {
            $table->unsignedInteger('events_to_display_per_page')->default(20);
            $table->unsignedInteger('logs_to_display_per_page')->default(20);
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
            $table->dropColumn('events_to_display_per_page');
            $table->dropColumn('logs_to_display_per_page');
        });
    }
}

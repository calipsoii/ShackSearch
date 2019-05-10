<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLoggingLevelToAppSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add a 'logging level' to App Settings that determines how verbose the
        // db_action logging will be. Levels 1-5 with 5 being most verbose.
        Schema::table('app_settings', function(Blueprint $table) {
            $table->integer('logging_level')->default(3);
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
            $table->dropColumn('logging_level');
        });
    }
}

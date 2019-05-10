<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCleanupColsToAppSettings extends Migration
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
            $table->integer('events_days_to_keep')->default(60);
            $table->integer('logs_days_to_keep')->default(60);
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
            $table->dropColumn('events_days_to_keep');
            $table->dropColumn('logs_days_to_keep');
        });
    }
}

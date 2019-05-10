<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDownloaderSettingsToAppSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add a couple informational fields for the mass sync process
        Schema::table('app_settings', function(Blueprint $table) {
            $table->unsignedInteger('mass_sync_working_block')->default('30000');
            $table->unsignedInteger('mass_sync_threads_to_retrieve')->default('100');
            $table->string('mass_sync_username')->default('MassSync');
            $table->timestamp('mass_sync_last_sync_run')->useCurrent=true;
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
            $table->dropColumn('mass_sync_working_block');
            $table->dropColumn('mass_sync_threads_to_retrieve');
            $table->dropColumn('mass_sync_username');
            $table->dropColumn('mass_sync_last_sync_run');
        });
    }
}

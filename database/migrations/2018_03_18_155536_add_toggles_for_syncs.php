<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTogglesForSyncs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add an easy way to shut off the automatic post/event syncs
        Schema::table('app_settings', function(Blueprint $table) {
            $table->boolean('event_poll_enabled')->default('true');
            $table->boolean('mass_post_sync_enabled')->default('true');
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
            $table->dropColumn('event_poll_enabled');
            $table->dropColumn('mass_post_sync_enabled');
        });
    }
}

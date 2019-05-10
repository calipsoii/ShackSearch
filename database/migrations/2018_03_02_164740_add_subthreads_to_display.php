<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSubthreadsToDisplay extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // The number of subthreads to display under each root post in Chatty View (the rest)
        // will be hidden behind a mouse-click/tap
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedInteger('chatty_view_subthreads_to_display')->default('20');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove the 'actively create' flag from app_settings
        Schema::table('app_settings', function(Blueprint $table) {
            $table->dropColumn('chatty_view_subthreads_to_display');
        });
    }
}

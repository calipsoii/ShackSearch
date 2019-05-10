<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChattyViewHoursToDisplay extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add an hours-to-display column for how many hours of threads to show on Chatty view
        Schema::table('app_settings', function(Blueprint $table) {
            $table->unsignedInteger('chatty_view_hours_to_display_thread')->default(18);
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
            $table->dropColumn('chatty_view_hours_to_display_thread');
        });
    }
}

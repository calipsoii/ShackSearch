<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropAlertEmailFromMonitors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('monitors', function(Blueprint $table) {
            $table->dropColumn('notification_email');
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
        Schema::table('monitors', function(Blueprint $table) {
            $table->string('notification_email')->default('mike.fournier+monitors@gmail.com');
        });
    }
}

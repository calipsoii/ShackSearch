<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyAppSettingsTable extends Migration
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
            $table->renameColumn('"lastProcessedEventId"','last_event_id');
            $table->renameColumn('"chattyViewSubthreadCharLength"','subthread_truncate_length');
            $table->string('event_poll_username');
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
            $table->renameColumn('last_event_id','"lastProcessedEventId"');
            $table->renameColumn('subthread_truncate_length','"chattyViewSubthreadCharLength"');
            $table->dropColumn('event_poll_username');
        });
    }
}

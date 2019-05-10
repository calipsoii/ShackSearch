<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLastPolledTimestampToAppsettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add a timestamp that gets updated each time the /events/poll route is followed. Don't know
        // if it makes sense to put it in App Settings but creating a whole new table seems like overkill.
        Schema::table('app_settings', function(Blueprint $table) {
            $table->timestamp('last_event_poll')->useCurrent=true;
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
            $table->dropColumn('last_event_poll');
        });
    }
}

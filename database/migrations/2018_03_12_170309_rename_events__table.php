<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Rename all the tables with camel-case naming to use underscores
        Schema::table('events', function(Blueprint $table) {
            $table->renameColumn('"eventId"','event_id');
            $table->renameColumn('"eventDate"','event_date');
            $table->renameColumn('"eventType"','event_type');
            $table->renameColumn('"eventData"','event_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Restore the camel-casing of the old table
        Schema::table('events', function(Blueprint $table) {
            $table->renameColumn('event_id','"eventId"');
            $table->renameColumn('event_date','"eventDate"');
            $table->renameColumn('event_type','"eventType"');
            $table->renameColumn('event_data','"eventData"');
        });
    }
}

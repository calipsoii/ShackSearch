<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEventProcessedFlag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add a flag that records whether an event was validated as being processed.
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('processed')->default(FALSE);
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
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('processed');
        });
    }
}

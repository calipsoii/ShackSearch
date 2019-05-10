<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTimeTakenAndPercentCompleteToWordClouds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('word_clouds', function(Blueprint $table) {
            $table->integer('percent_complete')->default(0);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
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
        Schema::table('word_clouds', function(Blueprint $table) {
            $table->dropColumn('percent_complete');
            $table->dropColumn('start_time');
            $table->dropColumn('end_time');
        });
    }
}

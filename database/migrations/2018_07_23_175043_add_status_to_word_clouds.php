<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusToWordClouds extends Migration
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
            $table->string('status')->nullable();
            $table->string('share_cloud')->default('self');
            $table->string('share_table_download')->default('self');
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
            $table->dropColumn('status');
        });
    }
}

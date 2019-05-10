<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWordCloudFilterToWordClouds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('word_clouds', function (Blueprint $table) {
            $table->integer('word_cloud_filter');
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
        Schema::table('word_clouds', function (Blueprint $table) {
            $table->dropColumn('word_cloud_filter');
        });
    }
}

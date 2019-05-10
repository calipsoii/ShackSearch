<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SwitchToDatetimezones extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('threads', function(Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('posts', function(Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('post_lols', function(Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('threads', function(Blueprint $table) {
            $table->timestampsTz();
        });

        Schema::table('posts', function(Blueprint $table) {
            $table->timestampsTz();
        });

        Schema::table('post_lols', function(Blueprint $table) {
            $table->timestampsTz();
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
        Schema::table('threads', function(Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('posts', function(Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('post_lols', function(Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('threads', function(Blueprint $table) {
            $table->timestamps();
        });

        Schema::table('posts', function(Blueprint $table) {
            $table->timestamps();
        });

        Schema::table('post_lols', function(Blueprint $table) {
            $table->timestamps();
        });
    }
}

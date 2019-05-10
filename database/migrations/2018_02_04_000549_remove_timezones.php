<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveTimezones extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('db_actions', function (Blueprint $table) {
            $table->dropTimestampsTz();
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->dropTimestampsTz();
            $table->dropColumn('date');
            $table->dropColumn('bump_date');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropTimestampsTz();
            $table->dropColumn('date');
        });

        Schema::table('post_lols', function (Blueprint $table) {
            $table->dropTimestampsTz();
        });

        Schema::table('db_actions', function (Blueprint $table) {
            $table->timestamps();
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->timestamps();
            $table->timestamp('date');
            $table->timestamp('bump_date');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->timestamps();
            $table->timestamp('date');
        });

        Schema::table('post_lols', function (Blueprint $table) {
            $table->timestamps();
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
        Schema::table('db_actions', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->dropTimestamps();
            $table->dropColumn('date');
            $table->dropColumn('bump_date');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropTimestamps();
            $table->dropColumn('date');
        });

        Schema::table('post_lols', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('db_actions', function (Blueprint $table) {
            $table->timestampsTz();
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->timestampsTz();
            $table->timestampTz('date');
            $table->timestampTz('bump_date');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->timestampsTz();
            $table->timestampTz('date');
        });

        Schema::table('post_lols', function (Blueprint $table) {
            $table->timestampsTz();
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddActiveCreateFlag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add an 'actively create' flag to app_settings to indicate whether
        // the app should actively seek out and download missing content.
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('actively_create_threads_posts')->default('false');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove the 'actively create' flag from app_settings
        Schema::table('app_settings', function(Blueprint $table) {
            $table->dropColumn('actively_create_threads_posts');
        });
    }
}

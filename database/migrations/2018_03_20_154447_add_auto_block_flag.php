<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAutoBlockFlag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add a flag to indicate whether the Post Mass Sync process should automatically move
        // to the next block when it finishes the current one.
        Schema::table('app_settings', function(Blueprint $table) {
            $table->boolean('mass_post_sync_auto_block')->default('false');
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
            $table->dropColumn('mass_post_sync_auto_block');
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTimestampsToPostLols extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('post_lols', function(Blueprint $table) {
            $table->timestamps();       // Adds nullable created_at and updated_at DATETIME columns
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
        Schema::table('post_lols', function(Blueprint $table) {
            $table->dropTimestamps();       // Removes nullable created_at and updated_at DATETIME columns
        });
    }
}

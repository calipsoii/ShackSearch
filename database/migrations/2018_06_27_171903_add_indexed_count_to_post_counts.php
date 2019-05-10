<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexedCountToPostCounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('post_counts', function(Blueprint $table) {
            $table->integer('search_indexed')->default(0);
            $table->integer('nuked')->default(0);
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
        Schema::table('post_counts', function(Blueprint $table) {
            $table->dropColumn('search_indexed');
            $table->dropColumn('nuked');
        });
    }
}

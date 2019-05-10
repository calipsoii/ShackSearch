<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveDbColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('db_actions', function(Blueprint $table) {
            $table->dropColumn('action');
            $table->dropColumn('rows_affected');
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
        Schema::table('db_actions', function(Blueprint $table) {
            $table->string('action');                       // Create, Update, Delete
            $table->unsignedInteger('rows_affected');       // 0, 28, 570
        });
    }
}

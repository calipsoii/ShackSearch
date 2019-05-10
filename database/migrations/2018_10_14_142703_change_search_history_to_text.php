<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeSearchHistoryToText extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('search_histories', function (Blueprint $table) {
            $table->text('text')->change();
        });
        Schema::table('search_histories', function (Blueprint $table) {
            $table->text('text')->nullable()->change();
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
        Schema::table('search_histories', function (Blueprint $table) {
            $table->string('text')->change();
        });
        Schema::table('search_histories', function (Blueprint $table) {
            $table->string('text')->nullable()->change();
        });
    }
}

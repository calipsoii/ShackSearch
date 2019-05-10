<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPostCountUpdateFlag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('post_count_enabled')->default('false');
            $table->string('post_count_username')->default('PostCount');
            $table->integer('post_count_bracket_size')->default(100000);
            $table->integer('post_count_max')->default(40000000);
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
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('post_count_enabled');
            $table->dropColumn('post_count_username');
            $table->dropColumn('post_count_bracket_size');
            $table->dropColumn('post_count_max');
        });
    }
}

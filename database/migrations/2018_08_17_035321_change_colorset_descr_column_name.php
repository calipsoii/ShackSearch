<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeColorsetDescrColumnName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('word_cloud_colorsets', function (Blueprint $table) {
            $table->renameColumn('description', 'descr');
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
        Schema::table('word_cloud_colorsets', function (Blueprint $table) {
            $table->renameColumn('descr', 'description');
        });
    }
}

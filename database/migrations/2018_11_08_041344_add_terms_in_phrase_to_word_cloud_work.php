<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTermsInPhraseToWordCloudWork extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('word_cloud_work', function (Blueprint $table) {
            $table->integer('terms_in_phrase')->default(1);
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
        Schema::table('word_cloud_work', function (Blueprint $table) {
            $table->dropColumn('terms_in_phrase');
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProxyPassToAppSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('app_settings', Function(Blueprint $table) {
            $table->string('proxy_password')->default('changeme');
            $table->string('proxy_email')->default('changeme');
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
        Schema::table('app_settings', Function(Blueprint $table) {
            $table->dropColumn('proxy_password');
            $table->dropColumn('proxy_email');
        });
    }
}

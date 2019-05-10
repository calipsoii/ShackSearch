<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUsernameToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Can't make username column unique in the same migration as creation because any default value
        // on existing users will be a duplicate.
        Schema::table('users', function(Blueprint $table) {
            $table->string('username')->nullable();
        });
        Schema::table('users', function(Blueprint $table) {
            $table->dropUnique('users_email_unique');
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
        Schema::table('users', function(Blueprint $table) {
            $table->unique('email');
        });
        Schema::table('users', function(Blueprint $table) {
            $table->dropColumn('username');
        });

    }
}

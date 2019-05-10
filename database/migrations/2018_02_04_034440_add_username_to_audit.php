<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUsernameToAudit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::dropIfExists('db_actions');

        Schema::create('db_actions', function(Blueprint $table) {
            $table->increments('id');
            $table->string('username');
            $table->text('message');                        // "Successfully updated 22 post_lol entries."
            $table->timestamps();                           // created_at and updated_at Eloquent columns
            $table->index(['created_at', 'updated_at']);    // We'll likely be querying/sorting based on these columns
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
        Schema::dropIfExists('db_actions');

        Schema::create('db_actions', function(Blueprint $table) {
            $table->increments('id');
            $table->string('table_affected')->nullable();   // post_lol's etc. Can be null.
            $table->text('message');                        // "Successfully updated 22 post_lol entries."
            $table->timestamps();                         // created_at and updated_at Eloquent columns
            $table->index(['created_at', 'updated_at']);    // We'll likely be querying/sorting based on these columns
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMonitorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('descr');
            $table->integer('max_mins_since_task_last_exec');
            $table->integer('run_freq_mins');
            $table->boolean('last_run_alert_state');
            $table->boolean('last_run_email_sent');
            $table->timestamp('last_run');
            $table->string('notification_email');
            $table->boolean('enabled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('monitors');
    }
}

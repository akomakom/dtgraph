<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAlarmsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('digitemp_alarms')) {

            Schema::create('digitemp_alarms', function (Blueprint $table) {
                $table->increments('alarm_id')->unsigned();
                $table->string('SerialNumber', 17);
                $table->decimal('Fahrenheit', 5, 2);
                
                $table->dateTime('time_raised')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('time_cleared')->nullable();
                $table->dateTime('time_updated')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

                $table->string('alarm_type', 15);
                $table->string('description', 255)->nullable();

                $table->index('SerialNumber');
                $table->index('time_raised');
                $table->index('time_cleared');
                $table->index('alarm_type');
            });

        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('digitemp_alarms');
    }
}

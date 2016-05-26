<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoreDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('digitemp')) {
            // Raw select to create table
//            DB::statement('create table digitemp_daily as (select unix_timestamp(date(time)) as unixtime, TIMESTAMPADD(HOUR, 12, date(time)) as date, SerialNumber, avg(Fahrenheit) as Fahrenheit, max(Fahrenheit) max, min(Fahrenheit) min from digitemp  group by SerialNumber, date(time) order by date(time), SerialNumber)');


            Schema::create('digitemp', function (Blueprint $table) {
                $table->increments('dtKey')->unsigned();
                $table->timestamp('time');
                $table->string('SerialNumber', 17);
                $table->decimal('Fahrenheit', 5, 2);

                $table->index('time')->default('CURRENT_TIMESTAMP');
                $table->index('SerialNumber');
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
        Schema::drop('digitemp');
    }
}

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMetadataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('digitemp_metadata')) {
            // Raw select to create table
//            DB::statement('create table digitemp_daily as (select unix_timestamp(date(time)) as unixtime, TIMESTAMPADD(HOUR, 12, date(time)) as date, SerialNumber, avg(Fahrenheit) as Fahrenheit, max(Fahrenheit) max, min(Fahrenheit) min from digitemp  group by SerialNumber, date(time) order by date(time), SerialNumber)');


            Schema::create('digitemp_metadata', function (Blueprint $table) {
                $table->string('SerialNumber', 17);
                $table->string('name', 15);
                $table->string('description', 255)->nullable();

                $table->float('min', 6, 3)->nullable();
                $table->float('max', 6, 3)->nullable();

                $table->boolean('alarm')->default(false);

                $table->float('maxchange', 6, 3)->unsigned()->nullable();
                $table->integer('maxchange_interval', false, true)->default(3600);
                $table->boolean('maxchange_alarm')->default(false);

                $table->string('color', 15)->default('black');
                $table->boolean('active')->default(true);

                $table->primary('SerialNumber');
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
        //  
        Schema::drop('digitemp_metadata');
    }
}

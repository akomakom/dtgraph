<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDigitempDaily extends Migration
{
    /**
     * Creates a fast lookup table "digitemp_daily" which
     * maintains average/max/min daily reading (pre-)calculations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasTable('digitemp_daily')) {
            echo "Creating digitemp_daily, that could take a while as it goes through all existing readings\n";
            // Raw select to create table
            DB::statement('create table digitemp_daily as (select unix_timestamp(date(time)) as unixtime, TIMESTAMPADD(HOUR, 12, date(time)) as date, SerialNumber, avg(Fahrenheit) as Fahrenheit, max(Fahrenheit) max, min(Fahrenheit) min from digitemp  group by SerialNumber, date(time) order by date(time), SerialNumber)');


            Schema::table('digitemp_daily', function (Blueprint $table) {
                $table->primary(['date', 'SerialNumber']); //for REPLACE below.
                $table->index('SerialNumber');
                $table->index('unixtime');
                $table->index('date');
            });

        }


        //now add a trigger to update this new table every time readings are inserted
        $trigger = '
            create trigger update_digitemp_daily after insert on digitemp
            for each row
            begin
                replace into digitemp_daily values (
                    unix_timestamp(TIMESTAMPADD(HOUR, 12, date(NEW.time))),
                    TIMESTAMPADD(HOUR, 12, date(NEW.time)),
                    NEW.SerialNumber,
                    (
                        select avg(Fahrenheit) from digitemp where SerialNumber = NEW.SerialNumber and time between date(NEW.time) and date(NEW.time) + 1
                    ),
                    (
                        select max(Fahrenheit) from digitemp where SerialNumber = NEW.SerialNumber and time between date(NEW.time) and date(NEW.time) + 1
                    ),
                    (
                        select min(Fahrenheit) from digitemp where SerialNumber = NEW.SerialNumber and time between date(NEW.time) and date(NEW.time) + 1
                    )

                );
            end;
        ';

        DB::unprepared($trigger);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('digitemp_daily');
        DB::unprepared('drop trigger update_digitemp_daily');
    }
}

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

            Schema::create('digitemp', function (Blueprint $table) {
                $table->increments('dtKey')->unsigned();
                $table->timestamp('time');
                $table->string('SerialNumber', 17);
                $table->decimal('Fahrenheit', 5, 2);

                $table->index('time')->default(DB::raw('CURRENT_TIMESTAMP'));
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

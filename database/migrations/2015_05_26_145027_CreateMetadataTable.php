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

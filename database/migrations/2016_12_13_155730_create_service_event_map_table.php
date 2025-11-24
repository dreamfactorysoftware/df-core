<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateServiceEventMapTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'service_event_map',
            function (Blueprint $t){
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('event');
                $t->text('data')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('service_event_map');
    }
}

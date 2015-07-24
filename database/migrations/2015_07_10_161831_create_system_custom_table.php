<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSystemCustomTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //create system_custom
        Schema::create(
            'system_custom',
            function (Blueprint $t){
                $t->string('name')->primary();
                $t->longText('value')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete('set null');
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete('set null');
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
        //drop system_custom
        Schema::dropIfExists('system_custom');
    }

}

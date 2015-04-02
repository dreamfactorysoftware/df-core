<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCorsConfigTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		//Cors config table
        Schema::create(
            'cors_config',
            function(Blueprint $t)
            {
                $t->increments('id');
                $t->string('path');
                $t->unique('path');
                $t->string('origin');
                $t->longText('header');
                $t->integer('method')->default(0);
                $t->integer('max_age')->default(3600);
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
		//Cors config table
        Schema::dropIfExists('cors_config');
	}

}

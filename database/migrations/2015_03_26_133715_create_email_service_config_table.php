<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmailServiceConfigTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		//Email service config table
        Schema::create(
            'email_config',
            function ( Blueprint $t )
            {
                $t->integer( 'service_id' )->unsigned()->primary();
                $t->foreign( 'service_id' )->references( 'id' )->on( 'services' )->onDelete( 'cascade' );
                $t->string('driver');
                $t->string('host')->nullable();
                $t->string('port')->nullable();
                $t->string('encryption')->default('tls');
                $t->longText('username')->nullable(); //encrypted
                $t->longText('password')->nullable(); //encrypted
                $t->string('command')->default('/usr/sbin/sendmail -bs');
                $t->longText('key')->nullable(); //encrypted
                $t->longText('secret')->nullable(); //encrypted
                $t->string('domain')->nullable();
            }
        );

        //Email service parameters config table
        Schema::create(
            'email_parameters_config',
            function( Blueprint $t)
            {
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('service_id')->on('email_config')->onDelete('cascade');
                $t->string('name');
                $t->mediumText('value')->nullable();
                $t->boolean('active')->default(1);
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
		//Email service config table
        Schema::dropIfExists('email_config');

        //Email service parameters config table
        Schema::dropIfExists('email_parameters_config');
	}

}

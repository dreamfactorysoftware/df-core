<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateApiDocsManagement extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		//

		// Service API Docs
		Schema::create(
			'service_doc',
			function ( Blueprint $t )
			{
				$t->increments( 'id' );
				$t->integer( 'service_id' )->unsigned();
				$t->foreign( 'service_id' )->references( 'id' )->on( 'service' )->onDelete( 'cascade' );
				$t->integer( 'format' )->unsigned()->default( 0 );
				$t->text( 'content' )->nullable();
				$t->timestamp( 'created_date' );
				$t->timestamp( 'last_modified_date' );
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
		//

		// Service Docs
		Schema::dropIfExists( 'service_doc' );

	}

}

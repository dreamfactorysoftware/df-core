<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DbForeignKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $driver = Schema::getConnection()->getDriverName();
        $sqlsrv = (('sqlsrv' === $driver) || ('dblib' === $driver));
        // Even though we take care of this scenario in the code,
        // SQL Server does not allow potential cascading loops,
        // so set the default no action and clear out created/modified by another user when deleting a user.
        $userOnDelete = ($sqlsrv ? 'no action' : 'set null');

        // Database Relationship Extras
        Schema::create(
            'db_relationship_extras',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('table');
                $t->string('relationship');
                $t->string('alias')->nullable();
                $t->string('label')->nullable();
                $t->text('description')->nullable();
                $t->boolean('always_fetch')->default(0);
                $t->boolean('flatten')->default(0);
                $t->boolean('flatten_drop_prefix')->default(0);
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
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
        // Database Extras
        Schema::dropIfExists('db_relationship_extras');
    }
}

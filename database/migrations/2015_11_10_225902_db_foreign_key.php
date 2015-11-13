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
        Schema::table('db_field_extras', function (Blueprint $t) {
            $t->integer('ref_service_id')->unsigned()->nullable();
            $t->foreign('ref_service_id')->references('id')->on('service')->onDelete('cascade');
            $t->string('ref_table');
            $t->string('ref_fields');
            $t->string('ref_on_update')->nullable();
            $t->string('ref_on_delete')->nullable();
        });

        // Database Relationship Extras
        Schema::create(
            'db_relationship_extras',
            function (Blueprint $t){
                $t->increments('id');
                $t->integer('service_id')->unsigned()->nullable();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('table');
                $t->string('relationship');
                $t->string('alias')->nullable();
                $t->string('label')->nullable();
                $t->text('description')->nullable();
                $t->boolean('collapse')->default(0);
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
        Schema::table('db_field_extras', function (Blueprint $t) {
            $t->dropColumn('related_service_id');
            $t->dropColumn('related_table');
            $t->dropColumn('related_field');
        });

        // Database Extras
        Schema::dropIfExists('db_relationship_extras');
    }
}

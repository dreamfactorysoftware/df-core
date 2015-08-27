<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DbAlias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('db_table_extras', function (Blueprint $table){
            $table->string('alias')->nullable();
        });
        Schema::table('db_field_extras', function (Blueprint $table){
            $table->string('alias')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('db_table_extras', function (Blueprint $table){
            $table->dropColumn('alias');
        });
        Schema::table('db_field_extras', function (Blueprint $table){
            $table->dropColumn('alias');
        });
    }
}

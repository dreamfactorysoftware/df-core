<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIntegrateioIdFieldToUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('user', 'integrateio_id')) {
            Schema::table(
                'user',
                function (Blueprint $t){
                    $t->integer('integrateio_id')->after('id')->nullable();
                }
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('user', 'integrateio_id')) {
            Schema::table(
                'user',
                function (Blueprint $t){
                    $t->dropColumn('integrateio_id');
                }
            );
        }
    }
}

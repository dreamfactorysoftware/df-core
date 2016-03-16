<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUsernameFieldToUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('user', 'username')) {
            Schema::table(
                'user',
                function (Blueprint $t){
                    $t->string('username')->after('name')->nullable();
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
        if (Schema::hasColumn('user', 'username')) {
            Schema::table(
                'user',
                function (Blueprint $t){
                    $t->dropColumn('username');
                }
            );
        }
    }
}

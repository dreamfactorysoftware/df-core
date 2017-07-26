<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SystemConfigCleanup extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('system_config', 'login_with_user_name')) {
            Schema::table('system_config', function (Blueprint $t) {
                $t->dropColumn('login_with_user_name');
            });
        }
    }
}

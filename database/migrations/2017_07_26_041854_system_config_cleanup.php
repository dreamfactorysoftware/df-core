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
            if ('sqlsrv' == DB::connection()->getDriverName()) {
                $defaultContraint = DB::selectOne("SELECT OBJECT_NAME([default_object_id]) AS name FROM SYS.COLUMNS WHERE [object_id] = OBJECT_ID('[dbo].[system_config]') AND [name] = 'login_with_user_name'");
                DB::statement("ALTER TABLE [dbo].[system_config] DROP CONSTRAINT $defaultContraint->name");
            }

            Schema::table('system_config', function (Blueprint $t) {
                $t->dropColumn('login_with_user_name');
            });
        }
    }
}

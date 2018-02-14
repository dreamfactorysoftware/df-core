<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SystemConfigUpdate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $driver = Schema::getConnection()->getDriverName();
        // Even though we take care of this scenario in the code,
        // SQL Server does not allow potential cascading loops,
        // so set the default no action and clear out created/modified by another user when deleting a user.

        $onDelete = (('sqlsrv' === $driver) ? 'no action' : 'set null');

        if (Schema::hasColumn('system_config', 'db_version')) {
            Schema::table('system_config', function (Blueprint $t) use ($onDelete) {
                // delete the old stuff and create the new config
                $t->dropPrimary('system_config_db_version_primary');
                $t->dropColumn('db_version');
                $t->dropColumn('created_date');
                $t->dropColumn('last_modified_date');
//                $t->dropColumn('created_by_id');
//                $t->dropColumn('last_modified_by_id');
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->integer('invite_email_service_id')->unsigned()->nullable();
                $t->foreign('invite_email_service_id')->references('id')->on('service')->onDelete($onDelete);
                $t->integer('invite_email_template_id')->unsigned()->nullable();
                $t->foreign('invite_email_template_id')->references('id')->on('email_template')->onDelete($onDelete);
                $t->integer('password_email_service_id')->unsigned()->nullable();
                $t->foreign('password_email_service_id')->references('id')->on('service')->onDelete($onDelete);
                $t->integer('password_email_template_id')->unsigned()->nullable();
                $t->foreign('password_email_template_id')->references('id')->on('email_template')->onDelete($onDelete);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}

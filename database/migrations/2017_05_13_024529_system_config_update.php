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
        if (Schema::hasColumn('system_config', 'db_version')) {
            Schema::table('system_config', function (Blueprint $t) {
                // delete the old stuff and create the new config
                $t->dropColumn('db_version');
                $t->dropColumn('created_date');
                $t->dropColumn('last_modified_date');
//                $t->dropColumn('created_by_id');
//                $t->dropColumn('last_modified_by_id');
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->integer('invite_email_service_id')->unsigned()->nullable();
                $t->foreign('invite_email_service_id')->references('id')->on('service')->onDelete('set null');
                $t->integer('invite_email_template_id')->unsigned()->nullable();
                $t->foreign('invite_email_template_id')->references('id')->on('email_template')->onDelete('set null');
                $t->integer('password_email_service_id')->unsigned()->nullable();
                $t->foreign('password_email_service_id')->references('id')->on('service')->onDelete('set null');
                $t->integer('password_email_template_id')->unsigned()->nullable();
                $t->foreign('password_email_template_id')->references('id')->on('email_template')->onDelete('set null');
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

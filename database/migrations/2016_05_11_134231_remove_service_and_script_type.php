<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveServiceAndScriptType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('service_type')) {
            Schema::table('service', function (Blueprint $t){
                $t->dropForeign('service_type_foreign');
            });
        }
        if (Schema::hasTable('script_type')) {
            Schema::table('script_config', function (Blueprint $t){
                $t->dropForeign('script_config_type_foreign');
            });
            Schema::table('event_script', function (Blueprint $t){
                $t->dropForeign('event_script_type_foreign');
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
        if (Schema::hasTable('service_type')) {
            Schema::table('service', function (Blueprint $t){
                $t->foreign('type')->references('name')->on('service_type')->onDelete('cascade');
            });
        }
        if (Schema::hasTable('script_type')) {
            Schema::table('script_config', function (Blueprint $t){
                $t->foreign('type')->references('name')->on('script_type')->onDelete('cascade');
            });
            Schema::table('event_script', function (Blueprint $t){
                $t->foreign('type')->references('name')->on('script_type')->onDelete('cascade');
            });
        }
    }
}

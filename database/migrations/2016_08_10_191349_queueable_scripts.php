<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class QueueableScripts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('script_config', function (Blueprint $t){
            $t->boolean('queued')->default(0)->after('service_id');
            $t->mediumText('content')->nullable()->change();
        });
        Schema::table('event_script', function (Blueprint $t){
            $t->mediumText('content')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('script_config', function (Blueprint $t){
            $t->dropColumn('queued');
        });
    }
}

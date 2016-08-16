<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EventAffectsContent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_script', function (Blueprint $table) {
            $table->boolean('allow_event_modification')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_script', function (Blueprint $table) {
            $table->dropColumn('allow_event_modification');
        });
    }
}

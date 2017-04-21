<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ServiceAddConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('service', 'config')) {
            Schema::table('service',
                function (Blueprint $table) {
                    $table->text('config')->nullable();
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
        if (Schema::hasColumn('service', 'config')) {
            Schema::table('service',
                function (Blueprint $table) {
                    $table->dropColumn('config');
                }
            );
        }
    }
}

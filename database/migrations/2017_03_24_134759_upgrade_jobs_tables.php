<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpgradeJobsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Laravel 5.4 changed the table layout for jobs and failed_jobs
        if (Schema::hasColumn('jobs', 'reserved')) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->dropColumn('reserved');
            });
        }
        if (!Schema::hasColumn('failed_jobs', 'exception')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->longText('exception');
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
        if (DB::getDriverName() !== 'sqlite' && !Schema::hasColumn('jobs', 'reserved')) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->tinyInteger('reserved')->unsigned();
            });
        }
        if (Schema::hasColumn('failed_jobs', 'reserved')) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->dropColumn('exception');
            });
        }
    }
}

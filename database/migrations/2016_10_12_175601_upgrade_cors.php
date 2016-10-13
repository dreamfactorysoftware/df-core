<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpgradeCors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cors_config', function (Blueprint $table) {
            $table->string('description')->nullable()->after('path');
            $table->text('exposed_header')->nullable()->after('header');
            $table->boolean('supports_credentials')->default(0)->after('max_age');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cors_config', function (Blueprint $table) {
            $table->dropColumn(['description', 'exposed_header', 'supports_credentials']);
        });
    }
}

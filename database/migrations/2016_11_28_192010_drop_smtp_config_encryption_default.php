<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropSmtpConfigEncryptionDefault extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('smtp_config', function(Blueprint $t){
            $t->string('encryption')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('smtp_config', function (Blueprint $t){
            $t->string('encryption')->default('tls')->change();
        });
    }
}

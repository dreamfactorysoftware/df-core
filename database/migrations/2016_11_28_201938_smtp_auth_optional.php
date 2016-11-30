<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SmtpAuthOptional extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('smtp_config', function(Blueprint $t){
            $t->text('username')->nullable()->default(null)->change();
            $t->text('password')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('smtp_config', function(Blueprint $t){
            $t->text('username')->change();
            $t->text('password')->change();
        });
    }
}

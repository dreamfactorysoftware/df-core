<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAttachmentSupportToEmailTemplate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('email_template') && !Schema::hasColumn('email_template', 'attachment')) {
            Schema::table('email_template', function (Blueprint $t){
                $t->text('attachment')->nullable()->after('subject');
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
        if (Schema::hasTable('email_template') && Schema::hasColumn('email_template', 'attachment')) {
            Schema::table('email_template', function (Blueprint $t){
                $t->dropColumn('attachment');
            });
        }
    }
}

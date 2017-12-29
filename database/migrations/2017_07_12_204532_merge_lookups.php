<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MergeLookups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $driver = Schema::getConnection()->getDriverName();
        // Even though we take care of this scenario in the code,
        // SQL Server does not allow potential cascading loops,
        // so set the default no action and clear out created/modified by another user when deleting a user.
        $onDelete = (('sqlsrv' === $driver) ? 'no action' : 'set null');

        if (!Schema::hasTable('lookup')) { // make sure this isn't a clean install
            Schema::create('lookup', function (Blueprint $t) use ($onDelete) {
                $t->increments('id');
                $t->integer('app_id')->unsigned()->nullable();
                $t->foreign('app_id')->references('id')->on('app')->onDelete('cascade');
                $t->integer('role_id')->unsigned()->nullable();
                $t->foreign('role_id')->references('id')->on('role')->onDelete('cascade');
                $t->integer('user_id')->unsigned()->nullable();
                $t->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
                $t->string('name');
                $t->text('value')->nullable();
                $t->boolean('private')->default(0);
                $t->text('description')->nullable();
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
            });

            $lookups = [];
            if (Schema::hasTable('app_lookup')) {
                $lookups = array_merge($lookups, DB::table('app_lookup')->get()->toArray());
            }

            if (Schema::hasTable('role_lookup')) {
                $lookups = array_merge($lookups, DB::table('role_lookup')->get()->toArray());
            }

            if (Schema::hasTable('user_lookup')) {
                $lookups = array_merge($lookups, DB::table('user_lookup')->get()->toArray());
            }

            if (Schema::hasTable('system_lookup')) {
                $lookups = array_merge($lookups, DB::table('system_lookup')->get()->toArray());
            }

            foreach ($lookups as $lookup) {
                $lookup = array_except((array)$lookup, 'id');
                try {
                    DB::table('lookup')->insert($lookup);
                    Log::debug('Migrating Lookup: ' . array_get($lookup, 'name'));
                } catch (\Exception $ex) {
                    Log::error('Migrating Lookup Failed for ' . array_get($lookup, 'name') . ': ' . $ex->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lookup');
    }
}

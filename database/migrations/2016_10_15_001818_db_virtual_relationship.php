<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DbVirtualRelationship extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $driver = Schema::getConnection()->getDriverName();
        $sqlsrv = (('sqlsrv' === $driver) || ('dblib' === $driver));
        // Even though we take care of this scenario in the code,
        // SQL Server does not allow potential cascading loops,
        // so set the default no action and clear out created/modified by another user when deleting a user.
        $userOnDelete = ($sqlsrv ? 'no action' : 'set null');
        Schema::create(
            'db_virtual_relationship',
            function (Blueprint $t) use ($userOnDelete) {
                $t->increments('id');
                $t->string('type');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('table');
                $t->string('field');
                $t->integer('ref_service_id')->unsigned();
                $t->foreign('ref_service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('ref_table');
                $t->string('ref_field');
                $t->string('ref_on_update')->nullable();
                $t->string('ref_on_delete')->nullable();
                $t->integer('junction_service_id')->unsigned()->nullable();
                $t->foreign('junction_service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('junction_table')->nullable();
                $t->string('junction_field')->nullable();
                $t->string('junction_ref_field')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        if (Schema::hasColumn('db_field_extras', 'ref_table')) {
            // Insert old virtual belongs_to relationships
            if (!empty($old = DB::table('db_field_extras')->whereNotNull('ref_table')->get([
                'service_id',
                'table',
                'field',
                'ref_service_id',
                'ref_table',
                'ref_fields',
                'ref_on_update',
                'ref_on_delete',
            ]))
            ) {
                Log::debug('Pre-migrating VFK to Virtual Relationships: ' . count($old));
                // change ref_fields to ref_field
                $new = [];
                foreach ($old as $each) {
                    $each = (array)$each;
                    $each['type'] = 'belongs_to';
                    $each['ref_field'] = $each['ref_fields'];
                    unset($each['ref_fields']);
                    $new[] = $each;
                }
                $result = \DreamFactory\Core\Models\DbVirtualRelationship::bulkCreate($new);
                Log::debug('Post-migrating VFK to Virtual Relationships: ' . print_r($result, true));
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
        Schema::dropIfExists('db_virtual_relationship');
    }
}

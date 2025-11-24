<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Create table for tracking Snowflake Marketplace free edition usage limits
 */
class CreateSnowflakeMarketplaceUsageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('snowflake_marketplace_usage', function (Blueprint $table) {
            $table->id();
            $table->date('usage_date')->unique();
            $table->integer('request_count')->default(0);
            $table->string('signature', 64); // HMAC-SHA256 signature for tamper detection
            $table->timestamp('reset_at');
            $table->boolean('tampered')->default(false);
            $table->timestamp('last_request_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('usage_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('snowflake_marketplace_usage');
    }
}

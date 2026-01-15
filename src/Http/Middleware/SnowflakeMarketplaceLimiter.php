<?php

namespace DreamFactory\Core\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Middleware to enforce Snowflake Marketplace free edition daily request limits
 *
 * This middleware:
 * - Limits Snowflake service requests to N per day (default 20)
 * - Uses cryptographic signatures to prevent tampering
 * - Persists usage across container restarts
 * - Detects and prevents circumvention attempts
 * - ALWAYS ACTIVE - designed for dedicated Snowflake Marketplace builds
 */
class SnowflakeMarketplaceLimiter
{
    /**
     * Default daily request limit for Snowflake Marketplace free edition
     */
    const DEFAULT_DAILY_LIMIT = 50;

    /**
     * Default upgrade contact email
     */
    const DEFAULT_UPGRADE_EMAIL = 'snowflake@dreamfactory.com';

    /**
     * Get the daily request limit
     *
     * @return int
     */
    protected function getLimit(): int
    {
        return (int) env('SNOWFLAKE_DAILY_REQUEST_LIMIT', self::DEFAULT_DAILY_LIMIT);
    }

    /**
     * Get the upgrade contact email
     *
     * @return string
     */
    protected function getUpgradeEmail(): string
    {
        return env('SNOWFLAKE_UPGRADE_EMAIL', self::DEFAULT_UPGRADE_EMAIL);
    }

    /**
     * Check if this is a Snowflake service request
     *
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    protected function isSnowflakeServiceRequest($request): bool
    {
        $path = $request->path();

        // Exclude metadata/documentation requests (OpenAPI, schema, etc.)
        // These should not count against usage limits
        if ($request->has('file') || $request->has('schema') || $request->has('describe')) {
            return false;
        }

        // Check if URL matches Snowflake service pattern (e.g., /api/v2/snowflake/*)
        if (preg_match('#^api/v2/([^/]+)#', $path, $matches)) {
            $serviceName = $matches[1];

            // Direct match for service named 'snowflake'
            if ($serviceName === 'snowflake') {
                return true;
            }

            // Check if service is a Snowflake service type in database
            return $this->isSnowflakeServiceName($serviceName);
        }

        return false;
    }

    /**
     * Check if service name corresponds to a Snowflake service
     *
     * @param string $serviceName
     * @return bool
     */
    protected function isSnowflakeServiceName($serviceName): bool
    {
        try {
            // Query the service table to check if this service uses Snowflake
            $service = DB::table('service')
                ->where('name', $serviceName)
                ->where('type', 'snowflake')
                ->first();

            return $service !== null;
        } catch (\Exception $e) {
            // If database query fails, log and continue
            Log::warning('Failed to check Snowflake service name', [
                'service' => $serviceName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle the request
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Only intercept Snowflake service API calls
        if (!$this->isSnowflakeServiceRequest($request)) {
            return $next($request);
        }

        // Get or create today's usage record
        $usage = $this->getOrCreateDailyUsage();

        // Check if limit exceeded
        if ($usage->request_count >= $this->getLimit()) {
            return $this->limitExceededResponse($usage);
        }

        // Increment usage counter with signature
        $this->incrementUsage($usage);

        // Process the request
        $response = $next($request);

        // Add usage headers to response
        return $this->addUsageHeaders($response, $usage);
    }

    /**
     * Get or create daily usage record
     *
     * @return object
     */
    protected function getOrCreateDailyUsage()
    {
        $today = Carbon::now()->toDateString();
        $instanceId = config('app.key');

        $usage = DB::table('snowflake_marketplace_usage')
            ->where('usage_date', $today)
            ->first();

        if (!$usage) {
            $usage = $this->createDailyUsage($today, $instanceId);
        } else {
            // Verify signature to detect tampering
            if (!$this->verifySignature($usage, $instanceId)) {
                // Tampering detected - lock to limit and flag
                DB::table('snowflake_marketplace_usage')
                    ->where('id', $usage->id)
                    ->update([
                        'request_count' => $this->getLimit(),
                        'tampered' => true
                    ]);

                $usage->request_count = $this->getLimit();
                $usage->tampered = true;

                // Log tampering attempt
                Log::warning('Snowflake Marketplace usage tampering detected', [
                    'date' => $today,
                    'expected_signature' => $this->generateSignature(
                        $usage->request_count,
                        $usage->usage_date,
                        $instanceId
                    ),
                    'actual_signature' => $usage->signature
                ]);
            }
        }

        return $usage;
    }

    /**
     * Create a new daily usage record
     *
     * @param string $date
     * @param string $instanceId
     * @return object
     */
    protected function createDailyUsage($date, $instanceId)
    {
        $now = Carbon::now();

        $id = DB::table('snowflake_marketplace_usage')->insertGetId([
            'usage_date' => $date,
            'request_count' => 0,
            'signature' => $this->generateSignature(0, $date, $instanceId),
            'reset_at' => $now->copy()->endOfDay(),
            'created_at' => $now
        ]);

        return DB::table('snowflake_marketplace_usage')->find($id);
    }

    /**
     * Increment usage counter with new signature
     *
     * @param object $usage
     * @return void
     */
    protected function incrementUsage($usage)
    {
        $newCount = $usage->request_count + 1;
        $instanceId = config('app.key');

        DB::table('snowflake_marketplace_usage')
            ->where('id', $usage->id)
            ->update([
                'request_count' => $newCount,
                'signature' => $this->generateSignature($newCount, $usage->usage_date, $instanceId),
                'last_request_at' => Carbon::now()
            ]);
    }

    /**
     * Generate HMAC signature for tamper detection
     *
     * @param int $count
     * @param string $date
     * @param string $instanceId
     * @return string
     */
    protected function generateSignature($count, $date, $instanceId): string
    {
        // Use HMAC-SHA256 with APP_KEY as the secret
        // This prevents tampering without knowing the APP_KEY
        $data = "{$date}:{$count}";
        return hash_hmac('sha256', $data, $instanceId);
    }

    /**
     * Verify the signature matches expected value
     *
     * @param object $usage
     * @param string $instanceId
     * @return bool
     */
    protected function verifySignature($usage, $instanceId): bool
    {
        $expectedSignature = $this->generateSignature(
            $usage->request_count,
            $usage->usage_date,
            $instanceId
        );

        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($usage->signature, $expectedSignature);
    }

    /**
     * Return limit exceeded response
     *
     * @param object $usage
     * @return \Illuminate\Http\Response
     */
    protected function limitExceededResponse($usage)
    {
        $upgradeEmail = $this->getUpgradeEmail();
        $limit = $this->getLimit();

        return response()->json([
            'error' => [
                'code' => 429,
                'message' => 'Daily request limit exceeded',
                'context' => [
                    'detail' => "This free Snowflake Marketplace edition is limited to {$limit} Snowflake API requests per day.",
                    'upgrade_message' => "Contact {$upgradeEmail} to upgrade to unlimited requests.",
                    'limit' => $limit,
                    'used' => $usage->request_count,
                    'reset_at' => $usage->reset_at,
                    'tampered' => $usage->tampered ?? false
                ]
            ]
        ], 429, [
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => strtotime($usage->reset_at),
            'Retry-After' => Carbon::now()->diffInSeconds($usage->reset_at)
        ]);
    }

    /**
     * Add usage headers to response
     *
     * @param \Illuminate\Http\Response $response
     * @param object $usage
     * @return \Illuminate\Http\Response
     */
    protected function addUsageHeaders($response, $usage)
    {
        $limit = $this->getLimit();
        $remaining = max(0, $limit - ($usage->request_count + 1));

        return $response->withHeaders([
            'X-DreamFactory-Snowflake-Limit' => $limit,
            'X-DreamFactory-Snowflake-Remaining' => $remaining,
            'X-DreamFactory-Snowflake-Reset' => $usage->reset_at,
            'X-DreamFactory-Edition' => 'snowflake-marketplace-free'
        ]);
    }
}

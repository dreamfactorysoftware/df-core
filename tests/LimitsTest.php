<?php
/*
 * Limits test
 */

class LimitsTest extends \DreamFactory\Core\Testing\TestCase
{

    // Configurations for various possible combos

    protected $dspOnly = [
        /* API Hits limits */
        'api' => [
            'instance.default.minute' => ['limit' => 1, 'period' => 1],
            'cluster.default.minute' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspRoleOnly = [

        /* API Hits limits */
        'api' => [
            'instance.default.minute' => ['limit' => 1, 'period' => 1],
            'cluster.default.minute'  => ['limit' => 1, 'period' => 1],
            'role:2.minute'  => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspUserOnly = [

        /* API Hits limits */
        'api' => [
            'instance.default.minute' => ['limit' => 1, 'period' => 1],
            'cluster.default'  => ['limit' => 1, 'period' => 1],
            'role:2.minute'  => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'user:1.minute' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiOnly = [

        /* API Hits limits */
        'api' => [
            'instance.default.minute' => ['limit' => 1, 'period' => 1],
            'cluster.default.minute' => ['limit' => 1, 'period' => 1],
            'role:2.minute' => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'user:1.minute' => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'apiName' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiRole = [

        /* API Hits limits */
        'api' => [
            'instance.default.minute' => ['limit' => 1, 'period' => 1],
            'cluster.default.minute' => ['limit' => 1, 'period' => 1],
            'role:2.minute' => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'user:1.minute' => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'apiName.minute' => ['limit' => 1, 'period' => 1], /* replace apiName with the actual API name */
            'apiName.role:2' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiUser = [

        /* API Hits limits */
        'api' => [
            'instance.default.minute' => ['limit' => 1, 'period' => 1],
            'cluster.default.minute' => ['limit' => 1, 'period' => 1],
            'role:2.minute' => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'user:1.minute' => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'apiName.minute' => ['limit' => 1, 'period' => 1], /* replace apiName with the actual API name */
            'apiName.user:1.minute' => ['limit' => 1, 'period' => 1]
        ]
    ];

    protected $dspApiService = [

        /* API Hits limits */
        'api' => [
            'instance.default.minute' => ['limit' => 1, 'period' => 1],
            'cluster.default.minute' => ['limit' => 1, 'period' => 1],
            'role:2.minute' => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'user:1.minute' => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'apiName.minute' => ['limit' => 1, 'period' => 1], /* replace apiName with the actual API name */
            'apiName.serviceName.minute' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiServiceRole = [

        /* API Hits limits */
        'api' => [
            'instance.default.minute' => ['limit' => 1, 'period' => 1],
            'cluster.default.minute' => ['limit' => 1, 'period' => 1],
            'role:2.minute' => ['limit' => 1, 'period' => 1],
            /* replace roleName with the actual role name */
            'user:1.minute' => ['limit' => 1, 'period' => 1],
            /* replace userName with the actual user name */
            'apiName.minute' => ['limit' => 1, 'period' => 1],
            /* replace apiName with the actual API name */
            'apiName.serviceName.minute' => ['limit' => 1, 'period' => 1],
            /* replace serviceName with the actual service name */
            'apiName.serviceName.role:2.minute' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiServiceUser = [

        /* API Hits limits */
        'api' => [
            'instance.default.minute' => ['limit' => 1, 'period' => 1],
            'cluster.default.minute' => ['limit' => 1, 'period' => 1],
            'role:2.minute' => ['limit' => 1, 'period' => 1],
            /* replace roleName with the actual role name */
            'user:1.minute' => ['limit' => 1, 'period' => 1],
            /* replace userName with the actual user name */
            'apiName.minute' => ['limit' => 1, 'period' => 1],
            /* replace apiName with the actual API name */
            'apiName.serviceName.minute' => ['limit' => 1, 'period' => 1],
            /* replace serviceName with the actual service name */
            'apiName.serviceName.user:1.minute' => ['limit' => 1, 'period' => 1]
        ]

    ];

    public function testLimitSet()
    {
        $limits =
            array(
                $this->dspOnly,
                $this->dspRoleOnly,
                $this->dspUserOnly,
                $this->dspApiOnly,
                $this->dspApiRole,
                $this->dspApiUser,
                $this->dspApiService,
                $this->dspApiServiceRole,
                $this->dspApiServiceUser
            );

        $this->setTestMode();

        foreach ($limits as $key => $limit) {
            $this->setLimits($limit);

            $this->call("GET", "/api/v2/user/session");

            $this->checkLimits($limit['api']);
        }

        $this->unsetTestMode();
    }

    public function testOverLimit()
    {
        $limits =
            array(
                $this->dspOnly,
                $this->dspRoleOnly,
                $this->dspUserOnly,
                $this->dspApiOnly,
                $this->dspApiRole,
                $this->dspApiUser,
                $this->dspApiService,
                $this->dspApiServiceRole,
                $this->dspApiServiceUser
            );

        $this->setTestMode();

        foreach ($limits as $limit) {
            $this->setLimits($limit);

            $this->checkOverLimit('/api/v2/user/session');

            foreach ($limit as $key => $value) {
                $this->clearCache($key);
            }
        }

        $this->unsetTestMode();
    }

    public function testNoLimits()
    {
        // Test that everything works when there are no limits set

        $this->call("GET", "/api/v2/user/session");
        $this->call("GET", "/api/v2/user/session");
        $this->call("GET", "/api/v2/user/session");
        $response = $this->call("GET", "/api/v2/user/session");

        //checking for 401 because user/session will return no session found.
        $this->assertEquals(401, $response->getStatusCode());
    }

    private function checkLimits(array $limits)
    {
        foreach ($limits as $key => $limit) {
            $this->assertEquals($limit['limit'], \Cache::get($key, 0), 'Test key: ' . $key);

            $this->clearCache($key);
        }
    }

    private function setLimits(array $limits)
    {
        \Config::set('api_limits', $limits);
    }

    private function checkOverLimit($path)
    {
        $this->call("GET", $path);

        // Make a second call so it's now over the limit
        $response = $this->call("GET", $path);

        $this->assertEquals(429, $response->getStatusCode());
    }

    private function clearCache($key)
    {
        // Make sure we're clean for the next iteration

        \Cache::forget($key);
    }

    private function setTestMode()
    {
        \Config::set('api_limits_test', true);
    }

    private function unsetTestMode()
    {
        \Config::set('api_limits_test', false);
    }
}

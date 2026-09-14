<?php

use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\Session;

/**
 * Regression test for #173.
 *
 * GET /api/v2/{service}/_spec is gated by checkPermission(GET, '_spec') in
 * BaseRestService::handleSpecRequest(), but '_spec' was never returned by
 * getAccessList(), so the role editor (which reads ?as_access_list=true)
 * could not grant it. Every service inherits the base list, so it must be there.
 */
class SpecAccessListTest extends \DreamFactory\Core\Testing\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Session::setUserInfoWithJWT(User::find(1));
    }

    public function testSpecIsGrantableOnEveryServiceType()
    {
        foreach (['db', 'files', 'system'] as $name) {
            $list = ServiceManager::getService($name)->getAccessList();
            $this->assertContains('*', $list, "$name access list should still carry the wildcard");
            $this->assertContains('_spec', $list, "$name access list is missing _spec, so a role cannot be granted it");
        }
    }
}

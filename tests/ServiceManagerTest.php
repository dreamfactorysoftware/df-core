<?php

use \DreamFactory\Core\Enums\Verbs;

class ServiceManagerTest extends \DreamFactory\Core\Testing\TestCase
{
    public function testGetService()
    {
        $result = ServiceManager::getService('system');
        $this->assertInstanceOf(\DreamFactory\Core\Contracts\ServiceInterface::class, $result);
        $this->assertEquals('system', $result->getName());
    }

    public function testGetServiceIdNameMap()
    {
        $result = ServiceManager::getServiceIdNameMap();
        $this->assertArrayHasKey(1, $result);
    }

    public function testGetServiceIdNameActiveOnlyMap()
    {
        $result = ServiceManager::getServiceIdNameMap(true);
        $this->assertArrayHasKey(1, $result);
    }

    public function testGetServiceIdByName()
    {
        $result = ServiceManager::getServiceIdByName('system');
        $this->assertEquals(1,$result);
    }

    public function testGetServiceNameById()
    {
        $result = ServiceManager::getServiceNameById(1);
        $this->assertEquals('system',$result);
    }

    public function testGetServiceById()
    {
        $result = ServiceManager::getServiceById(1);
        $this->assertInstanceOf(\DreamFactory\Core\Contracts\ServiceInterface::class, $result);
        $this->assertEquals('system', $result->getName());
    }

    public function testGetServiceNameTypeMap()
    {
        $result = ServiceManager::getServiceNameTypeMap();
        $this->assertArrayHasKey('db', $result);
    }

    public function testGetServiceTypeByName()
    {
        $result = ServiceManager::getServiceTypeByName('db');
        $this->assertEquals('sqlite', $result);
    }

    public function testGetServiceNames()
    {
        $result = ServiceManager::getServiceNames();
        $this->assertContains('db', $result);
    }

    public function testGetServiceNamesByType()
    {
        $result = ServiceManager::getServiceNamesByType('sqlite');
        $this->assertContains('db', $result);
    }

    public function testGetServiceNamesByGroup()
    {
        $result = ServiceManager::getServiceNamesByGroup('Database');
        $this->assertContains('db', $result);
    }

    public function testGetServiceList()
    {
        $result = ServiceManager::getServiceList();
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('name', $result[0]);
    }

    public function testGetServiceListByType()
    {
        $result = ServiceManager::getServiceListByType('sqlite');
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('name', $result[0]);
    }

    public function testGetServiceListByGroup()
    {
        $result = ServiceManager::getServiceListByGroup('Database');
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('name', $result[0]);
    }

//    public function testPurge()
//    {
//        ServiceManager::purge('system');
//    }
//
    public function testAddType()
    {
        $type = new \DreamFactory\Core\Services\ServiceType(['name' => 'new_type']);
        ServiceManager::addType($type);
        $there = ServiceManager::getServiceType('new_type');
        $this->assertNotEmpty($there);
    }

    public function testGetServiceType()
    {
        $result = ServiceManager::getServiceType('sqlite');
        $this->assertInstanceOf(\DreamFactory\Core\Contracts\ServiceTypeInterface::class, $result);
    }

    public function testGetServiceTypes()
    {
        $result = ServiceManager::getServiceTypes();
        $this->assertArrayHasKey('sqlite', $result);
        $this->assertInstanceOf(\DreamFactory\Core\Contracts\ServiceTypeInterface::class, $result['sqlite']);
        $this->assertEquals('sqlite', $result['sqlite']->getName());
    }

    public function testGetServiceTypeNames()
    {
        $result = ServiceManager::getServiceTypeNames();
        $this->assertContains('sqlite', $result);
    }

    public function testIsAccessException()
    {
        $result = ServiceManager::isAccessException('system', 'environment', Verbs::GET);
        $this->assertTrue($result);
        $result = ServiceManager::isAccessException('system', 'service', Verbs::GET);
        $this->assertFalse($result);
    }

    public function testHandleServiceRequest()
    {
        $request = new \DreamFactory\Core\Utility\ServiceRequest();
        $result = ServiceManager::handleServiceRequest($request, 'system', 'environment');
        $this->assertInstanceOf(\DreamFactory\Core\Utility\ServiceResponse::class, $result);
    }

    public function testHandleRequest()
    {
        $result = ServiceManager::handleRequest('system', Verbs::GET, 'environment');
        $this->assertInstanceOf(\DreamFactory\Core\Utility\ServiceResponse::class, $result);
    }
}
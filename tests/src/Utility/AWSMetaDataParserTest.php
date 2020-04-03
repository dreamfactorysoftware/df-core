<?php

namespace DreamFactory\Core\Utility;

use PHPUnit\Framework\TestCase;

/**
 * @property string instanceIdentity
 */
class AWSMetaDataParserTest extends TestCase
{
    public $metadataServer = 'http://172.17.0.2:8080';

    public function setUp()
    {
        $this->instanceIdentity = <<<TEXT
{
  "accountId" : "116434471183",
  "architecture" : "x86_64",
  "availabilityZone" : "us-east-2c",
  "billingProducts" : null,
  "devpayProductCodes" : null,
  "marketplaceProductCodes" : [ "82g146env9v8ht47fwzh7yquu" ],
  "imageId" : "ami-02bf3771838d602ac",
  "instanceId" : "i-08f52968cacaaccfe",
  "instanceType" : "t2.micro",
  "kernelId" : null,
  "pendingTime" : "2020-03-18T17:49:42Z",
  "privateIp" : "172.31.42.34",
  "ramdiskId" : null,
  "region" : "us-east-2",
  "version" : "2017-09-30"
}
TEXT;
    }

    public function testCreating()
    {
        $metaDataParser = new AWSMetaDataParser();
        $this->assertNotEmpty($metaDataParser->getAWSPublicKey());
    }

    public function testGetToken() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $token = $metaDataParser->getToken();
        $this->assertNotEmpty($token);
    }

    public function testGetToken__rightToken() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $token = $metaDataParser->getToken();
        $this->assertEquals('AQAAAH598_vCAYxU-bQ72s1E9a5_18gHUOAo4veTvVoS8PHN1yUHog==', $token);
    }

    public function testGetInstanceIdentity() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $instanceIdentity = $metaDataParser->getInstanceIdentity();
        $this->assertNotEmpty($instanceIdentity);
    }

    public function testGetInstanceIdentity__rightIdentity() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $instanceIdentity = $metaDataParser->getInstanceIdentity();
        $this->assertEquals($this->instanceIdentity, $instanceIdentity);
    }

    public function testGetVerifiedInstanceIdentity() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $instanceIdentity = $metaDataParser->getVerifiedInstanceIdentity();
        $this->assertNotEmpty($instanceIdentity);
    }

    public function testProductCodes() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $productCode = $metaDataParser->getProductCode();
        $this->assertNotEmpty($productCode);
    }

    public function testProductCodes__rightCode() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $productCode = $metaDataParser->getProductCode();
        $this->assertEquals('82g146env9v8ht47fwzh7yquu', $productCode);
    }


    public function testInstanceId() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $instanceId = $metaDataParser->getInstanceId();
        $this->assertNotEmpty($instanceId);
    }

    public function testInstanceId__rightInstance() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $instanceId = $metaDataParser->getInstanceId();
        $this->assertEquals('i-08f52968cacaaccfe', $instanceId);
    }

}
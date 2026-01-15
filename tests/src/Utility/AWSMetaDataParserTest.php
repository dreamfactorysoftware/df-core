<?php

namespace DreamFactory\Core\Utility;

use PHPUnit\Framework\TestCase;

/**
 * This unit tests requires additional mock server, so all test is disabled
 * To use this test, you must remove _ (underscore) from the method names of this class,
 * change $metadataServer, $instanceId, $token, $instanceIdentity variables.
 *
 * <h2>Mock Metadata Server</h2>
 *
 * Mock server should implement 4 endpoints:
 *
 * <table>
 *   <thead>
 *     <tr>
 *       <th>#</th>
 *       <th>Path</th>
 *       <th>Description</th>
 *     </tr>
 *   </thead>
 *   <tr>
 *     <td>1.</td>
 *     <td>/latest/api/token</td>
 *     <td>Used in IMDSv2. Must be added to all other queries in the X-aws-ec2-metadata-token-ttl-seconds header </td>
 *   </tr>
 *   <tr>
 *     <td>2.</td>
 *     <td>/latest/dynamic/instance-identity/signature</td>
 *     <td>Data that can be used by other parties to verify its origin and authenticity</td>
 *   </tr>
 *   <tr>
 *     <td>3.</td>
 *     <td>/latest/dynamic/instance-identity/pkcs7</td>
 *     <td>Used to verify the document's authenticity and content against the signature</td>
 *   </tr>
 *   <tr>
 *     <td>4.</td>
 *     <td>/latest/dynamic/instance-identity/document</td>
 *     <td>JSON containing instance attributes, such as instance-id, private IP address, etc</td>
 *   </tr>
 * </table>
 *
 * <h2>Mock server example using NodeJS</h2>
 *
 * <code>
 *
 * 'use strict';
 *
 * const express = require('express');
 *
 * const PORT = process.env.PORT || 8080;
 * const HOST = process.env.HOST || '0.0.0.0';
 * const TOKEN = process.env.TOKEN || '<token>';
 *
 * const SIGNATURE = `<real signature>`;
 *
 * const PKCS7 = `<real pkcs7 document>`;
 *
 * const DOCUMENT = `{
 *   "marketplaceProductCodes" : [ "<real product code>" ],
 *   "instanceId" : "<real instance id>",
 * }`;
 *
 * const app = express();
 *
 * app.put('/latest/api/token', (req, res) => {
 *     if (!req.header('X-aws-ec2-metadata-token-ttl-seconds')) {
 *          res.status(500).send('No ttl exists');
 *     }
 *     res.send(TOKEN);
 * });
 *
 * app.use('*', (req, res, next) => {
 *     console.log(req.header('X-aws-ec2-metadata-token'));
 *     if (req.header('X-aws-ec2-metadata-token') === TOKEN) {
 *         next();
 *         return;
 *     }
 *     res.status(401).send('No token exists');
 * });
 *
 * app.get('latest/dynamic/instance-identity/signature', (req, res) => {
 *    res.send(SIGNATURE.toString());
 * });
 *
 * app.get('/latest/dynamic/instance-identity/pkcs7', (req, res) => {
 *     res.send(PKCS7.toString());
 * });
 *
 * app.get('/latest/dynamic/instance-identity/document', (req, res) => {
 *     res.send(DOCUMENT.toString());
 * });
 *
 * app.listen(PORT, HOST);
 * console.log(`Running on http://${HOST}:${PORT}`);
 * </code>
 *
 * @property string instanceIdentity
 * @property string token
 * @property string productCode
 * @property string instanceId
 */
class AWSMetaDataParserTest extends TestCase
{
    /**
     * Mock AWS Metadata Server
     *
     * @var string $metadataServer
     */
    /*public $metadataServer = '';*/

    /*public function setUp()
    {
        $this->instanceIdentity = <<<TEXT
<place here real instance identity>
TEXT;
        $this->token = '<place here real token>';
        $this->productCode = '<place here real product code>';
        $this->instanceId = '<place here real instance id>';
    }

    public function _testCreating()
    {
        $metaDataParser = new AWSMetaDataParser();
        $this->assertNotEmpty($metaDataParser->getAWSPublicKey());
    }

    public function _testGetToken() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $token = $metaDataParser->getToken();
        $this->assertNotEmpty($token);
    }

    public function _testGetToken__rightToken() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $token = $metaDataParser->getToken();
        $this->assertEquals($this->token, $token);
    }

    public function _testGetInstanceIdentity() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $instanceIdentity = $metaDataParser->getInstanceIdentity();
        $this->assertNotEmpty($instanceIdentity);
    }

    public function _testGetVerifiedInstanceIdentity() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $instanceIdentity = $metaDataParser->getVerifiedInstanceIdentity();
        $this->assertNotEmpty($instanceIdentity);
    }

    public function _testProductCodes() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $productCode = $metaDataParser->getProductCode();
        $this->assertNotEmpty($productCode);
    }

    public function _testProductCodes__rightCode() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $productCode = $metaDataParser->getProductCode();
        $this->assertEquals($this->productCode, $productCode);
    }


    public function _testInstanceId() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $instanceId = $metaDataParser->getInstanceId();
        $this->assertNotEmpty($instanceId);
    }

    public function _testInstanceId__rightInstance() {
        $metaDataParser = new AWSMetaDataParser();
        $metaDataParser->metadataServer = $this->metadataServer;

        $instanceId = $metaDataParser->getInstanceId();
        $this->assertEquals($this->instanceId, $instanceId);
    }*/
}

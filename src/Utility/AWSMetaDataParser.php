<?php namespace DreamFactory\Core\Utility;

class AWSMetaDataParser {

    /**
     * AWS public certificate
     * @see https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instance-identity-documents.html#instance-identity-signature-verification-example
     *
     * @var string $awsPublicCertificate
     */
    protected $awsPublicCertificate;
    protected $token;
    protected $tokenTimeToLive = 21600;
    /**
     * AWS Metadata Server URL
     * @see https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instancedata-data-retrieval.html
     *
     * @var string $metadataServer
     */
    public $metadataServer = 'http://169.254.169.254';

    public function __construct()
    {
        $this->awsPublicCertificate = \File::get(__DIR__ . '/AWSMarketplace.pub');
    }

    /**
     * @return string|bool
     */
    /*public function getToken()
    {
        $this->token = Curl::put($this->metadataServer . '/latest/api/token', null, [
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 1,
            CURLOPT_HTTPHEADER => [
                'X-aws-ec2-metadata-token-ttl-seconds: ' . $this->tokenTimeToLive,
            ],
        ]);
        if (Curl::getLastHttpCode() != 200) {
            return false;
        }
        return $this->token;
    }*/

    /**
     * @return string|bool
     */
    /*public function getInstanceIdentity()
    {
        $token = $this->getToken();
        if ($token) {
            $instanceIdentity = Curl::get($this->metadataServer . '/latest/dynamic/instance-identity/document', null, [
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 1,
                CURLOPT_HTTPHEADER => [ 'X-aws-ec2-metadata-token: ' . $token, ],
            ]);
            if (Curl::getLastHttpCode() != 200) {
                return false;
            }
            return $instanceIdentity;
        }
        return false;
    }*/

    /**
     * @return bool|string
     */
    /*protected function getInstanceIdentityPKCS7()
    {
        $token = $this->getToken();
        if ($token) {
            $pkcs7 = Curl::get($this->metadataServer . '/latest/dynamic/instance-identity/pkcs7', null, [
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 1,
                CURLOPT_HTTPHEADER => ['X-aws-ec2-metadata-token: ' . $token,],
            ]);
            if (Curl::getLastHttpCode() != 200) {
                return false;
            }
            return $pkcs7;
        }
        return false;
    }*/

    /**
     * @return string
     */
    /*private function getInstanceIdentitySignature()
    {
        return "-----BEGIN PKCS7-----\n" . $this->getInstanceIdentityPKCS7() . "\n-----END PKCS7-----";
    }*/

    /**
     * @return bool|string
     */
    /*public function getVerifiedInstanceIdentity()
    {
        $document = $this->getInstanceIdentity();
        $signature = $this->getInstanceIdentitySignature();
        if ($this->isVerifiedInstanceIdentity($document, $signature, $this->awsPublicCertificate)) {
            return $document;
        }
        return false;
    }*/

    /**
     * @param string $document
     * @param string $signature
     * @param string $awsPublicCertificate
     * @return bool
     */
    /*public function isVerifiedInstanceIdentity($document, string $signature, string $awsPublicCertificate)
    {
        try {
            $documentFile = tmpfile();
            $documentPath = stream_get_meta_data($documentFile)['uri'];
            $signatureFile = tmpfile();
            $signaturePath = stream_get_meta_data($signatureFile)['uri'];
            $publicKeyFile = tmpfile();
            $publicKeyPath = stream_get_meta_data($publicKeyFile)['uri'];
            fwrite($documentFile, $document);
            fwrite($signatureFile, $signature);
            fwrite($publicKeyFile, $awsPublicCertificate);
            $command = "openssl smime -verify -in ${signaturePath} -inform PEM -content ${documentPath} -certfile ${publicKeyPath} -noverify > /dev/null";
            exec($command, $output, $status);
            fclose($documentFile);
            fclose($signatureFile);
            fclose($publicKeyFile);
            return $status == 0;
        } catch (\Exception $e) {
            return false;
        }
    }*/

    /**
     * @return string|bool
     */
    /*public function getProductCode()
    {
        $instanceIdentity = $this->getVerifiedInstanceIdentity();
        if ($instanceIdentity) {
            $instanceIdentity = json_decode($instanceIdentity);
            return $instanceIdentity->marketplaceProductCodes[0];
        }
        return false;
    }*/

    /**
     * @return string|bool
     */
    /*public function getInstanceId()
    {
        $instanceIdentity = $this->getVerifiedInstanceIdentity();
        if ($instanceIdentity) {
            $instanceIdentity = json_decode($instanceIdentity);
            return $instanceIdentity->instanceId;
        }
        return false;
    }*/

    /**
     * @return string
     */
    /*public function getAWSPublicKey()
    {
        return $this->awsPublicCertificate;
    }*/
}


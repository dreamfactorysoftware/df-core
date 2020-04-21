<?php namespace DreamFactory\Core\Utility;

class AWSMetaDataParser {

    protected $awsPublicCertificate;
    protected $token;
    protected $tokenTimeToLive = 21600;
    public $metadataServer = 'http://169.254.169.254';

    public function __construct()
    {
        $this->awsPublicCertificate = <<<TXT
-----BEGIN CERTIFICATE-----
MIIC7TCCAq0CCQCWukjZ5V4aZzAJBgcqhkjOOAQDMFwxCzAJBgNVBAYTAlVTMRkw
FwYDVQQIExBXYXNoaW5ndG9uIFN0YXRlMRAwDgYDVQQHEwdTZWF0dGxlMSAwHgYD
VQQKExdBbWF6b24gV2ViIFNlcnZpY2VzIExMQzAeFw0xMjAxMDUxMjU2MTJaFw0z
ODAxMDUxMjU2MTJaMFwxCzAJBgNVBAYTAlVTMRkwFwYDVQQIExBXYXNoaW5ndG9u
IFN0YXRlMRAwDgYDVQQHEwdTZWF0dGxlMSAwHgYDVQQKExdBbWF6b24gV2ViIFNl
cnZpY2VzIExMQzCCAbcwggEsBgcqhkjOOAQBMIIBHwKBgQCjkvcS2bb1VQ4yt/5e
ih5OO6kK/n1Lzllr7D8ZwtQP8fOEpp5E2ng+D6Ud1Z1gYipr58Kj3nssSNpI6bX3
VyIQzK7wLclnd/YozqNNmgIyZecN7EglK9ITHJLP+x8FtUpt3QbyYXJdmVMegN6P
hviYt5JH/nYl4hh3Pa1HJdskgQIVALVJ3ER11+Ko4tP6nwvHwh6+ERYRAoGBAI1j
k+tkqMVHuAFcvAGKocTgsjJem6/5qomzJuKDmbJNu9Qxw3rAotXau8Qe+MBcJl/U
hhy1KHVpCGl9fueQ2s6IL0CaO/buycU1CiYQk40KNHCcHfNiZbdlx1E9rpUp7bnF
lRa2v1ntMX3caRVDdbtPEWmdxSCYsYFDk4mZrOLBA4GEAAKBgEbmeve5f8LIE/Gf
MNmP9CM5eovQOGx5ho8WqD+aTebs+k2tn92BBPqeZqpWRa5P/+jrdKml1qx4llHW
MXrs3IgIb6+hUIB+S8dz8/mmO0bpr76RoZVCXYab2CZedFut7qc3WUH9+EUAH5mw
vSeDCOUMYQR7R9LINYwouHIziqQYMAkGByqGSM44BAMDLwAwLAIUWXBlk40xTwSw
7HX32MxXYruse9ACFBNGmdX2ZBrVNGrN9N2f6ROk0k9K
-----END CERTIFICATE-----
TXT;
    }

    /**
     * @return string|null
     */
    public function getToken()
    {
        $this->token = Curl::put($this->metadataServer . '/latest/api/token', null, [
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_HTTPHEADER => [
                'X-aws-ec2-metadata-token-ttl-seconds: ' . $this->tokenTimeToLive,
            ],
        ]);
        if (Curl::getLastHttpCode() != 200) {
            return null;
        }
        return $this->token;
    }

    public function getInstanceIdentity()
    {
        $token = $this->getToken();
        if ($token) {
            $instanceIdentity = Curl::get($this->metadataServer . '/latest/dynamic/instance-identity/document', null, [
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_HTTPHEADER => [ 'X-aws-ec2-metadata-token: ' . $token, ],
            ]);
            if (Curl::getLastHttpCode() != 200) {
                return null;
            }
            return $instanceIdentity;
        }
        return null;
    }

    protected function getInstanceIdentityPKCS7()
    {
        $token = $this->getToken();
        if ($token) {
            $pkcs7 = Curl::get($this->metadataServer . '/latest/dynamic/instance-identity/pkcs7', null, [
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_HTTPHEADER => ['X-aws-ec2-metadata-token: ' . $token,],
            ]);
            if (Curl::getLastHttpCode() != 200) {
                return null;
            }
            return $pkcs7;
        }
        return null;
    }

    private function getInstanceIdentitySignature()
    {
        return "-----BEGIN PKCS7-----\n" . $this->getInstanceIdentityPKCS7() . "\n-----END PKCS7-----";
    }


    public function getVerifiedInstanceIdentity()
    {
        $document = $this->getInstanceIdentity();
        $signature = $this->getInstanceIdentitySignature();
        if ($this->isVerifiedInstanceIdentity($document, $signature, $this->awsPublicCertificate)) {
            return $document;
        }
        return null;
    }

    public function isVerifiedInstanceIdentity($document, string $signature, string $awsPublicCertificate)
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
    }

    /**
     * @return string|null
     */
    public function getProductCode()
    {
        $instanceIdentity = $this->getVerifiedInstanceIdentity();
        if ($instanceIdentity) {
            $instanceIdentity = json_decode($instanceIdentity);
            return $instanceIdentity->marketplaceProductCodes[0];
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getInstanceId()
    {
        $instanceIdentity = $this->getVerifiedInstanceIdentity();
        if ($instanceIdentity) {
            $instanceIdentity = json_decode($instanceIdentity);
            return $instanceIdentity->instanceId;
        }
        return null;
    }

    /**
     * @return string
     */
    public function getAWSPublicKey()
    {
        return $this->awsPublicCertificate;
    }
}


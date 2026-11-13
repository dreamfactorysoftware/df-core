<?php

use DreamFactory\Core\Utility\Redact;

class RedactTest extends \DreamFactory\Core\Testing\TestCase
{
    public function testCredentialBecomesAFingerprintThatDoesNotContainIt()
    {
        $key = '6498a8ad1beb9d84d63035c5d1120c007fad6de706734db9689f8996707e0f7d';
        $out = Redact::credential($key);
        $this->assertMatchesRegularExpression('/^sha256:[0-9a-f]{8}$/', $out);
        $this->assertStringNotContainsString(substr($key, 0, 6), $out);
        $this->assertSame($out, Redact::credential($key), 'same credential, same fingerprint');
        $this->assertNotSame($out, Redact::credential($key . 'x'));
        $this->assertNotSame('sha256:' . substr(hash('sha256', 'Summer2026!'), 0, 8), Redact::credential('Summer2026!'), 'unkeyed hash is a dictionary oracle');
    }

    public function testNoCredentialStaysEmpty()
    {
        $this->assertNull(Redact::credential(null));
        $this->assertSame('', Redact::credential(''));
    }

    public function testCredentialParametersAreRedactedAndOthersAreKept()
    {
        $in = ['filter' => 'id=1', 'api_key' => 'abc123', 'Session_Token' => 'eyJhbGciOi', 'limit' => 10, 'password' => 'p'];
        $out = Redact::params($in);
        $this->assertSame('id=1', $out['filter']);
        $this->assertSame(10, $out['limit']);
        foreach (['api_key', 'Session_Token', 'password'] as $k) {
            $this->assertMatchesRegularExpression('/^sha256:[0-9a-f]{8}$/', $out[$k], $k);
        }
        $this->assertStringNotContainsString('abc123', json_encode($out));
        $this->assertStringNotContainsString('eyJhbGciOi', json_encode($out));
        $this->assertSame('not an array', Redact::params('not an array'));
    }

    public function testTheRequestLogNoLongerWritesRawCredentials()
    {
        foreach (['src/Services/BaseRestService.php', 'src/Http/Controllers/RestController.php'] as $file) {
            $src = file_get_contents(__DIR__ . '/../' . $file);
            $this->assertDoesNotMatchRegularExpression(
                "/=>\\s*\\\$request->getHeader\\('X_DREAMFACTORY_(API_KEY|SESSION_TOKEN)'\\)/",
                $src,
                "$file logs a credential header as it is"
            );
            $this->assertStringContainsString('Redact::params($request->getParameters())', $src, $file);
        }
    }
}

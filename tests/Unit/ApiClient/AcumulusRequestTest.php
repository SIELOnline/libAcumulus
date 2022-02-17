<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Container;

/**
 * Features to test with the AcumulusRequest:
 * - execute
 * and before/after that the getters
 * - getUri
 * - getSubmitMessage (constructSubmitMessage, getBasicSubmit, convertArrayToXml)
 * - getHttpRequest?
 * -
 */
class AcumulusRequestTest extends TestCase
{
    private $acumulusRequest;

    protected function setUp(): void
    {
        $language = 'nl';
        $container = new Container('TestWebShop', $language);
        $this->acumulusRequest = $container->getAcumulusRequest();
    }

    public function testGettersBefore()
    {
        $this->assertNull($this->acumulusRequest->getUri());
        $this->assertNull($this->acumulusRequest->getSubmit());
        $this->assertNull($this->acumulusRequest->getHttpRequest());
    }

    public function testExecuteNoContract()
    {
        $uri = 'uri';
        $message = ['key' => 'value'];
        $this->acumulusRequest->execute($uri, $message, false);
        $this->assertEquals($uri, $this->acumulusRequest->getUri());
        $submit = $this->acumulusRequest->getSubmit();
        $this->assertArrayNotHasKey('contract', $submit);
        $this->assertArrayHasKey('format', $submit);
        $this->assertArrayHasKey('testmode', $submit);
        $this->assertArrayHasKey('lang', $submit);
        $this->assertArrayHasKey('connector', $submit);
        $this->assertEqualsCanonicalizing($message, array_intersect_assoc($submit, $message));
        $this->assertNotNull($this->acumulusRequest->getHttpRequest());
    }

    public function testExecuteContract()
    {
        $uri = 'uri';
        $message = ['key' => 'value'];
        $this->acumulusRequest->execute($uri, $message, true);
        $this->assertEquals($uri, $this->acumulusRequest->getUri());
        $submit = $this->acumulusRequest->getSubmit();
        $this->assertArrayHasKey('contract', $submit);
        $this->assertIsArray($submit['contract']);
        $this->assertArrayHasKey('contractcode', $submit['contract']);
        $this->assertArrayHasKey('username', $submit['contract']);
        $this->assertArrayHasKey('password', $submit['contract']);
        $this->assertArrayHasKey('emailonerror', $submit['contract']);
        $this->assertArrayHasKey('emailonwarning', $submit['contract']);
        $this->assertArrayHasKey('format', $submit);
        $this->assertArrayHasKey('testmode', $submit);
        $this->assertArrayHasKey('lang', $submit);
        $this->assertArrayHasKey('connector', $submit);
        $this->assertEqualsCanonicalizing($message, array_intersect_assoc($submit, $message));
        $this->assertNotNull($this->acumulusRequest->getHttpRequest());
    }
}

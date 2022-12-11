<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Tests\Unit\ApiClient;

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
    /**
     * @var \Siel\Acumulus\ApiClient\AcumulusRequest
     */
    protected $acumulusRequest;
    /**
     * @var \Siel\Acumulus\ApiClient\AcumulusResult
     */
    protected $acumulusResult;
    private /*Container*/ $container;
    private /*ApiRequestResponseExamples*/ $examples;

    protected function setUp(): void
    {
        $language = 'nl';
        $this->container = new Container('Tests\TestWebShop\TestDoubles', $language);
        $this->examples = new ApiRequestResponseExamples();
    }

    private function createAcumulusRequest()
    {
        $this->acumulusRequest = $this->container->createAcumulusRequest();

        $this->assertNull($this->acumulusRequest->getUri());
        $this->assertNull($this->acumulusRequest->getSubmit());
        $this->assertNull($this->acumulusRequest->getHttpRequest());
    }

    private function getAcumulusRequest($uri)
    {
        $this->createAcumulusRequest();
        $submit = $this->examples->getSubmit($uri);
        $needContract = $this->examples->needContract($uri);
        $this->acumulusResult =  $this->acumulusRequest->execute($uri, $submit, $needContract);

        $this->assertSame($uri, $this->acumulusRequest->getUri());
        $fullSubmit = $this->acumulusRequest->getSubmit();
        $this->assertArrayHasKey('format', $fullSubmit);
        $this->assertArrayHasKey('testmode', $fullSubmit);
        $this->assertArrayHasKey('lang', $fullSubmit);
        $this->assertArrayHasKey('connector', $fullSubmit);
        $this->assertEqualsCanonicalizing(
            $submit,
            array_intersect_assoc($fullSubmit, $submit)
        );
        $this->assertNotNull($this->acumulusRequest->getHttpRequest());
        $this->assertSame('POST', $this->acumulusRequest->getHttpRequest()->getMethod());
        $this->assertSame($uri, $this->acumulusRequest->getHttpRequest()->getUri());
        $this->assertIsArray($this->acumulusRequest->getHttpRequest()->getBody());
        $this->assertCount(1, $this->acumulusRequest->getHttpRequest()->getBody());
        $this->assertArrayHasKey('xmlstring', $this->acumulusRequest->getHttpRequest()->getBody());
        $this->assertStringContainsString('<acumulus>', $this->acumulusRequest->getHttpRequest()->getBody()['xmlstring']);
    }

    public function testExecuteNoContract()
    {
        $uri = 'vatinfo';
        $this->getAcumulusRequest($uri);

        $this->assertArrayNotHasKey('contract', $this->acumulusRequest->getSubmit());
    }

    public function testExecuteContract()
    {
        $uri = 'accounts';
        $this->getAcumulusRequest($uri);

        $this->assertArrayHasKey('contract', $this->acumulusRequest->getSubmit());
    }
}

<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\AcumulusRequest;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;

/**
 * Features to test with the {@see AcumulusRequest}:
 * - execute
 * and before/after that the getters
 * - getUri
 * - getSubmitMessage (constructSubmitMessage, getBasicSubmit, convertArrayToXml)
 * - getHttpRequest?
 * -
 */
class AcumulusRequestTest extends TestCase
{
    protected \Siel\Acumulus\TestWebShop\TestDoubles\ApiClient\AcumulusRequest $acumulusRequest;
    protected AcumulusResult $acumulusResult;
    private Container $container;
    private ApiRequestResponseExamples $examples;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $language = 'nl';
        $this->container = new Container('TestWebShop\TestDoubles', $language);
        $this->examples = ApiRequestResponseExamples::getInstance();
    }

    private function createAcumulusRequest(): void
    {
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->acumulusRequest = $this->container->createAcumulusRequest();

        $this->assertNull($this->acumulusRequest->getUri());
        $this->assertNull($this->acumulusRequest->getSubmit());
        $this->assertNull($this->acumulusRequest->getHttpRequest());
    }

    private function getAcumulusRequest(string $uri): void
    {
        $this->createAcumulusRequest();
        $submit = $this->examples->getSubmit($uri);
        $needContract = $this->examples->needContract($uri);
        $this->acumulusResult =  $this->acumulusRequest->execute($uri, $submit, $needContract);

        $this->assertSame($uri, $this->acumulusRequest->getUri());
        $fullSubmit = $this->acumulusRequest->getSubmit();
        $this->assertArrayHasKey(Fld::Format, $fullSubmit);
        $this->assertArrayHasKey(Fld::TestMode, $fullSubmit);
        $this->assertArrayHasKey(fld::Lang, $fullSubmit);
        $this->assertArrayHasKey(Fld::Connector, $fullSubmit);
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

    public function testExecuteNoContract(): void
    {
        $uri = 'vatinfo';
        $this->getAcumulusRequest($uri);

        $this->assertArrayNotHasKey(Fld::Contract, $this->acumulusRequest->getSubmit());
    }

    public function testExecuteContract(): void
    {
        $uri = 'accounts';
        $this->getAcumulusRequest($uri);

        $this->assertArrayHasKey(Fld::Contract, $this->acumulusRequest->getSubmit());
    }

    public function testIsTestMode(): void
    {
        $uri = 'accounts';
        $this->examples->setOptions([Fld::TestMode => Api::TestMode_Normal]);
        $this->getAcumulusRequest($uri);
        $this->assertFalse($this->acumulusRequest->isTestMode());

        $this->examples->setOptions([Fld::TestMode => Api::TestMode_Test]);
        $this->getAcumulusRequest($uri);
        $this->assertTrue($this->acumulusRequest->isTestMode());

        $this->examples->setOptions([Fld::TestMode => Api::TestMode_Test]);
        $this->createAcumulusRequest();
        $this->assertNull($this->acumulusRequest->isTestMode());
    }
}

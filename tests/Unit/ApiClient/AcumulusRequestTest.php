<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\AcumulusRequest;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * Features to test with the {@see AcumulusRequest}:
 * - execute
 * and before/after that the getters:
 * - getUri
 * - getSubmitMessage (constructSubmitMessage, getBasicSubmit, convertArrayToXml)
 * - getHttpRequest?
 * -
 */
class AcumulusRequestTest extends TestCase
{
    use AcumulusContainer;

    protected static string $shopNamespace = 'TestWebShop\TestDoubles';
    protected static string $language = 'en';

    protected \Siel\Acumulus\TestWebShop\TestDoubles\ApiClient\AcumulusRequest $acumulusRequest;
    protected AcumulusResult $acumulusResult;
    private ApiRequestResponseExamples $examples;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->examples = ApiRequestResponseExamples::getInstance();
    }

    private function createAcumulusRequest(): void
    {
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->acumulusRequest = self::getContainer()->createAcumulusRequest();

        self::assertNull($this->acumulusRequest->getUri());
        self::assertNull($this->acumulusRequest->getSubmit());
        self::assertNull($this->acumulusRequest->getHttpRequest());
    }

    private function getAcumulusRequest(string $uri): void
    {
        $this->createAcumulusRequest();
        $submit = $this->examples->getSubmit($uri);
        $needContract = $this->examples->needContract($uri);
        $this->acumulusResult =  $this->acumulusRequest->execute($uri, $submit, $needContract);

        self::assertSame($uri, $this->acumulusRequest->getUri());
        $fullSubmit = $this->acumulusRequest->getSubmit();
        self::assertArrayHasKey(Fld::Format, $fullSubmit);
        self::assertArrayHasKey(Fld::TestMode, $fullSubmit);
        self::assertArrayHasKey(Fld::Lang, $fullSubmit);
        self::assertArrayHasKey(Fld::Connector, $fullSubmit);
        self::assertEqualsCanonicalizing(
            $submit,
            array_intersect_assoc($fullSubmit, $submit)
        );
        self::assertNotNull($this->acumulusRequest->getHttpRequest());
        self::assertSame('POST', $this->acumulusRequest->getHttpRequest()->getMethod());
        self::assertSame($uri, $this->acumulusRequest->getHttpRequest()->getUri());
        self::assertIsArray($this->acumulusRequest->getHttpRequest()->getBody());
        self::assertCount(1, $this->acumulusRequest->getHttpRequest()->getBody());
        self::assertArrayHasKey('xmlstring', $this->acumulusRequest->getHttpRequest()->getBody());
        self::assertStringContainsString('<acumulus>', $this->acumulusRequest->getHttpRequest()->getBody()['xmlstring']);
    }

    public function testExecuteNoContract(): void
    {
        $uri = 'vatinfo';
        $this->getAcumulusRequest($uri);

        self::assertArrayNotHasKey(Fld::Contract, $this->acumulusRequest->getSubmit());
    }

    public function testExecuteContract(): void
    {
        $uri = 'accounts';
        $this->getAcumulusRequest($uri);

        self::assertArrayHasKey(Fld::Contract, $this->acumulusRequest->getSubmit());
    }

    public function testIsTestMode(): void
    {
        $uri = 'accounts';
        $this->examples->setOptions([Fld::TestMode => Api::TestMode_Normal]);
        $this->getAcumulusRequest($uri);
        self::assertFalse($this->acumulusRequest->isTestMode());

        $this->examples->setOptions([Fld::TestMode => Api::TestMode_Test]);
        $this->getAcumulusRequest($uri);
        self::assertTrue($this->acumulusRequest->isTestMode());

        $this->examples->setOptions([Fld::TestMode => Api::TestMode_Test]);
        $this->createAcumulusRequest();
        self::assertNull($this->acumulusRequest->isTestMode());
    }
}

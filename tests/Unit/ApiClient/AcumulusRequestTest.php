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

        static::assertNull($this->acumulusRequest->getUri());
        static::assertNull($this->acumulusRequest->getSubmit());
        static::assertNull($this->acumulusRequest->getHttpRequest());
    }

    private function getAcumulusRequest(string $uri): void
    {
        $this->createAcumulusRequest();
        $submit = $this->examples->getSubmit($uri);
        $needContract = $this->examples->needContract($uri);
        $this->acumulusResult =  $this->acumulusRequest->execute($uri, $submit, $needContract);

        static::assertSame($uri, $this->acumulusRequest->getUri());
        $fullSubmit = $this->acumulusRequest->getSubmit();
        static::assertArrayHasKey(Fld::Format, $fullSubmit);
        static::assertArrayHasKey(Fld::TestMode, $fullSubmit);
        static::assertArrayHasKey(Fld::Lang, $fullSubmit);
        static::assertArrayHasKey(Fld::Connector, $fullSubmit);
        static::assertEqualsCanonicalizing(
            $submit,
            array_intersect_assoc($fullSubmit, $submit)
        );
        static::assertNotNull($this->acumulusRequest->getHttpRequest());
        static::assertSame('POST', $this->acumulusRequest->getHttpRequest()->getMethod());
        static::assertSame($uri, $this->acumulusRequest->getHttpRequest()->getUri());
        static::assertIsArray($this->acumulusRequest->getHttpRequest()->getBody());
        static::assertCount(1, $this->acumulusRequest->getHttpRequest()->getBody());
        static::assertArrayHasKey('xmlstring', $this->acumulusRequest->getHttpRequest()->getBody());
        static::assertStringContainsString('<acumulus>', $this->acumulusRequest->getHttpRequest()->getBody()['xmlstring']);
    }

    public function testExecuteNoContract(): void
    {
        $uri = 'vatinfo';
        $this->getAcumulusRequest($uri);

        static::assertArrayNotHasKey(Fld::Contract, $this->acumulusRequest->getSubmit());
    }

    public function testExecuteContract(): void
    {
        $uri = 'accounts';
        $this->getAcumulusRequest($uri);

        static::assertArrayHasKey(Fld::Contract, $this->acumulusRequest->getSubmit());
    }

    public function testIsTestMode(): void
    {
        $uri = 'accounts';
        $this->examples->setOptions([Fld::TestMode => Api::TestMode_Normal]);
        $this->getAcumulusRequest($uri);
        static::assertFalse($this->acumulusRequest->isTestMode());

        $this->examples->setOptions([Fld::TestMode => Api::TestMode_Test]);
        $this->getAcumulusRequest($uri);
        static::assertTrue($this->acumulusRequest->isTestMode());

        $this->examples->setOptions([Fld::TestMode => Api::TestMode_Test]);
        $this->createAcumulusRequest();
        static::assertNull($this->acumulusRequest->isTestMode());
    }
}

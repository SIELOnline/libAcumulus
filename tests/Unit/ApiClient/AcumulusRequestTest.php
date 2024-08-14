<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\AcumulusRequest;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Data\EmailAsPdfType;
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
        $this->examples = new ApiRequestResponseExamples();
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

    public function testExecuteNoContract(): void
    {
        $uri = 'vatinfo';
        $this->getAcumulusRequest($uri);

        $this->assertArrayNotHasKey('contract', $this->acumulusRequest->getSubmit());
    }

    public function testExecuteContract(): void
    {
        $uri = 'accounts';
        $this->getAcumulusRequest($uri);

        $this->assertArrayHasKey('contract', $this->acumulusRequest->getSubmit());
    }

    public function testSubmitToArray(): void
    {
        $this->createAcumulusRequest();
        $token = 'TOKEN';
        $type = EmailAsPdfType::Invoice;
        /** @var \Siel\Acumulus\Data\EmailInvoiceAsPdf $emailAsPdf */
        $emailAsPdf = $this->container->createAcumulusObject($type);
        $emailAsPdf->emailFrom = 'from@example.com';
        $emailAsPdf->emailTo = 'test@example.com';
        $emailAsPdf->subject = 'test';
        $emailAsPdf->message = 'test1 test2';
        $emailAsPdf->ubl = true;
        $message = [
            'token' => $token,
            'emailaspdf' => $emailAsPdf,
        ];
        $expected = $message;
        $expected['emailaspdf'] = $emailAsPdf->toArray();

        $array = $this->acumulusRequest->submitToArray($message);

        $this->assertEqualsCanonicalizing($expected, $array);
    }
}

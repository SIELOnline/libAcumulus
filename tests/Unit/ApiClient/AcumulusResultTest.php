<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\AcumulusRequest;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\ResultTranslations;
use Siel\Acumulus\Helpers\Translator;

/**
 * An {@see AcumulusResult} has the following features that we want to test:
 * - Properties set via the constructor are returned by their getters
 *   (acumulusRequest and httpResponse) including the derived response.
 * - Other properties are null/empty after creation.
 *
 * - error detection and handling based on response
 * - Status
 *   - getting based on api status and severity
 *   - getting textual description for it.
 * - simplifyResponse()
 */
class AcumulusResultTest extends TestCase
{
    protected AcumulusRequest $acumulusRequest;
    private Container $container;
    private Translator $translator;
    private ApiRequestResponseExamples $examples;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $language = 'nl';
        $this->container = new Container('TestWebShop\TestDoubles', $language);
        $this->translator = $this->container->getTranslator();
        $this->translator->add(new SeverityTranslations());
        $this->translator->add(new ResultTranslations());
        $this->examples = new ApiRequestResponseExamples();
    }

    private function getAcumulusResult(string $uri): AcumulusResult
    {
        $submit = $this->examples->getSubmit($uri);
        $needContract = $this->examples->needContract($uri);
        $this->acumulusRequest = $this->container->createAcumulusRequest();
        $acumulusResult = $this->acumulusRequest->execute($uri, $submit, $needContract);

        $this->assertSame($this->acumulusRequest, $acumulusResult->getAcumulusRequest());
        $this->assertNotNull($acumulusResult->getHttpResponse());
        $this->assertSame($this->acumulusRequest->getHttpRequest(), $acumulusResult->getHttpResponse()->getRequest());

        return $acumulusResult;
    }

    private function t(string $key): string
    {
        return $this->translator->get($key);
    }

    public function testCreate(): AcumulusResult
    {
        $uri = 'accounts';
        $result = $this->getAcumulusResult($uri);

        $this->assertSame($this->acumulusRequest, $result->getAcumulusRequest());
        $this->assertNotNull($result->getHttpResponse());
        $this->assertSame(Severity::Success, $result->getStatus());
        $this->assertSame($this->t('message_response_success'), $result->getStatusText());
        $this->assertSame(Severity::Unknown, $result->getSeverity());
        $this->assertCount(0, $result->getMessages());

        return $result;
    }

    public function testSetMainResponseKeyNoList(): void
    {
        $uri = 'invoice-add';
        $result = $this->getAcumulusResult($uri);
        $result->setMainAcumulusResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $this->assertSame($this->examples->getMainResponse($uri), $result->getMainAcumulusResponse());
    }

    public function testSetMainResponseKeyList(): void
    {
        $uri = 'vatinfo';
        $result = $this->getAcumulusResult($uri);
        $result->setMainAcumulusResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $this->assertSame($this->examples->getMainResponse($uri), $result->getMainAcumulusResponse());
    }

    public function testSetMainResponseKeyEmptyList(): void
    {
        $uri = 'vatinfo-empty-return';
        $result = $this->getAcumulusResult($uri);
        $result->setMainAcumulusResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $this->assertSame($this->examples->getMainResponse($uri), $result->getMainAcumulusResponse());
    }

    public function testNoContract(): void
    {
        $uri = 'no-contract';
        $result = $this->getAcumulusResult($uri);
        $result->setMainAcumulusResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $this->assertSame($this->examples->getMainResponse($uri), $result->getMainAcumulusResponse());
    }

    public function testMaskPasswordsRequest(): void
    {
        $uri = 'invoice-add';
        $result = $this->getAcumulusResult($uri);
        $result->setMainAcumulusResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $message = $result->getAcumulusRequest()->getMaskedRequest();
        $this->assertStringNotContainsString('mysecret', $message);
        $this->assertStringContainsString('REMOVED FOR SECURITY', $message);
    }

    public function testMaskPasswordsResponse(): void
    {
        $uri = 'signup';
        $result = $this->getAcumulusResult($uri);
        $result->setMainAcumulusResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $message = $result->getMaskedResponse();
        $this->assertStringNotContainsString('mysecret', $message);
        $this->assertStringContainsString('REMOVED FOR SECURITY', $message);
    }
}

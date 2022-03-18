<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection PhpDocSignatureInspection
 * @noinspection DuplicatedCode
 */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\ResultTranslations;

/**
 * An AcumulusResult has the following features that we want to test:
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
    /**
     * @var \Siel\Acumulus\ApiClient\AcumulusRequest
     */
    protected $acumulusRequest;
    private /*Container*/ $container;
    private /*Translator*/ $translator;
    private /*ApiRequestResponseExamples*/ $examples;

    protected function setUp(): void
    {
        $language = 'nl';
        $this->container = new Container('TestWebShop\TestDoubles', $language);
        $this->translator = $this->container->getTranslator();
        $this->translator->add(new SeverityTranslations());
        $this->translator->add(new ResultTranslations());
        $this->examples = new ApiRequestResponseExamples();
    }

    private function getAcumulusResult($uri): AcumulusResult
    {
        $submit = $this->examples->getSubmit($uri);
        $needContract = $this->examples->needContract($uri);
        $this->acumulusRequest = $this->container->getAcumulusRequest();
        $acumulusResult = $this->acumulusRequest->execute($uri, $submit, $needContract);

        $this->assertSame($this->acumulusRequest, $acumulusResult->getAcumulusRequest());
        $this->assertNotNull($acumulusResult->getHttpResponse());
        $this->assertSame($this->acumulusRequest->getHttpRequest(), $acumulusResult->getHttpResponse()->getRequest());

        return $acumulusResult;
    }

    private function t($key): string
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

    public function testCreateError()
    {
        $acumulusRequest = $this->container->getAcumulusRequest();
        $httpResponse = null;
        $result = $this->container->getAcumulusResult($acumulusRequest, $httpResponse);

        $this->assertSame($httpResponse, $result->getHttpResponse());
        $this->assertSame(Severity::Unknown, $result->getStatus());
        $this->assertSame($this->t('request_not_yet_sent'), $result->getStatusText());
    }

    public function testSetMainResponseKeyNoList()
    {
        $uri = 'invoice-add';
        $result = $this->getAcumulusResult($uri);
        $result->setMainResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $this->assertSame($this->examples->getMainResponse($uri), $result->getResponse());
    }

    public function testSetMainResponseKeyList()
    {
        $uri = 'vatinfo';
        $result = $this->getAcumulusResult($uri);
        $result->setMainResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $this->assertSame($this->examples->getMainResponse($uri), $result->getResponse());
    }

    public function testSetMainResponseKeyEmptyList()
    {
        $uri = 'vatinfo-empty-return';
        $result = $this->getAcumulusResult($uri);
        $result->setMainResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $this->assertSame($this->examples->getMainResponse($uri), $result->getResponse());
    }

    public function testNoContract()
    {
        $uri = 'no-contract';
        $result = $this->getAcumulusResult($uri);
        $result->setMainResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $this->assertSame($this->examples->getMainResponse($uri), $result->getResponse());
    }

    public function testMaskPasswordsRequest()
    {
        $uri = 'invoice-add';
        $result = $this->getAcumulusResult($uri);
        $result->setMainResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $messages = $result->toLogMessages();
        $this->assertArrayHasKey('Request', $messages);
        $this->assertArrayHasKey('Response', $messages);
        $this->assertArrayNotHasKey('Exception', $messages);
        $this->assertStringNotContainsString('mysecret', $messages['Request']);
        $this->assertStringContainsString('REMOVED FOR SECURITY', $messages['Request']);
    }

    public function testMaskPasswordsResponse()
    {
        $uri = 'signup';
        $result = $this->getAcumulusResult($uri);
        $result->setMainResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));

        $messages = $result->toLogMessages();
        $this->assertArrayHasKey('Request', $messages);
        $this->assertArrayHasKey('Response', $messages);
        $this->assertArrayNotHasKey('Exception', $messages);
        $this->assertStringNotContainsString('mysecret', $messages['Response']);
        $this->assertStringContainsString('REMOVED FOR SECURITY', $messages['Response']);
    }
}

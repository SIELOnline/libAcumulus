<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection PhpDocSignatureInspection
 * @noinspection DuplicatedCode
 */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\ApiClient\Result;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\ResultTranslations;

/**
 * A Result has the following features that we want to test:
 * - Properties are null/empty after creation.
 * - AcumulusRequest setter and getter
 * - httpResponse setter and getter
 * - error detection and handling based on response
 * - Status
 *   - getting based on api status and severity
 *   - getting textual description for it.
 * - Response, setMainResponse, simplifyResponse (based on setHttpResponse())
 *
 */
class ResultTest extends TestCase
{
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

    private function createAcumulusResult(): Result
    {
        return $this->container->getResult();
    }

    private function createAndExecuteAcumulusRequest(string $uri, array $submit, bool $needContract): array
    {
        $acumulusRequest = $this->container->getAcumulusRequest();
        return [$acumulusRequest, $acumulusRequest->execute($uri, $submit, $needContract)];
    }

    private function t($key): string
    {
        return $this->translator->get($key);
    }

    public function testCreate()
    {
        $result = $this->createAcumulusResult();
        $this->assertSame(Severity::Unknown, $result->getStatus());
        $this->assertSame($this->t('request_not_yet_sent'), $result->getStatusText());
        $this->assertSame(Severity::Unknown, $result->getSeverity());
        $this->assertCount(0, $result->getMessages());
        $this->assertFalse($result->hasRealMessages());
        $this->assertFalse($result->hasError());
        $this->assertNull($result->getByCode(701));
        $this->assertNull($result->getByCodeTag('N1'));
        $this->assertNull($result->getAcumulusRequest());
        $this->assertNull($result->getHttpResponse());
    }

    public function testSetAcumulusRequest(): Result
    {
        $acumulusRequest = $this->container->getAcumulusRequest();
        $uri = 'uri';
        $submit = $this->examples->getSubmit('accounts');
        $acumulusRequest->execute($uri, $submit, false);

        $result = $this->container->getResult();
        $result->setAcumulusRequest($acumulusRequest);
        $this->assertSame($acumulusRequest, $result->getAcumulusRequest());
        return $result;
    }

    /**
     * @depends testSetAcumulusRequest
     */
    public function testSetHttpResponse(Result $result): Result
    {
        $httpCode = 200;
        $httpResponse = new HttpResponse(
            '',
            $this->examples->getResponseBody('accounts'),
            ['http_code' => $httpCode],
            new HttpRequest());
        $result->setHttpResponse($httpResponse);

        $this->assertSame($httpResponse, $result->getHttpResponse());
        $this->assertSame(Severity::Success, $result->getStatus());
        $this->assertSame($this->t('message_response_success'), $result->getStatusText());
        $this->assertSame(Severity::Unknown, $result->getSeverity());
        $this->assertCount(0, $result->getMessages());
        $this->assertFalse($result->hasRealMessages());
        $this->assertFalse($result->hasError());
        $this->assertNull($result->getByCode(701));
        $this->assertNull($result->getByCodeTag('N1'));

        return $result;
    }

    /**
     * @depends testSetHttpResponse
     */
    public function testSetMainResponse(Result $result)
    {
        $result->setMainResponseKey('accounts', true);
        $this->assertSame($this->examples->getMainResponse('accounts'), $result->getResponse());
    }

    public function testCreateByAcumulusRequestExecute()
    {
        $uri = 'uri';
        $submit = ['format' => 'json'];
        [$acumulusRequest, $result] = $this->createAndExecuteAcumulusRequest($uri, $submit, false);

        $this->assertSame($acumulusRequest, $result->getAcumulusRequest());
        $this->assertSame(Severity::Success, $result->getStatus());
        $this->assertSame($this->t(Severity::Success), $result->getStatusText());
        $this->assertSame(Severity::Unknown, $result->getSeverity());
        $this->assertCount(0, $result->getMessages());
        $this->assertFalse($result->hasRealMessages());
        $this->assertFalse($result->hasError());
        $this->assertNull($result->getByCode(701));
        $this->assertNull($result->getByCodeTag('N1'));
        $this->assertNotNull($result->getHttpResponse());
    }

    public function testSetHttpResponseEmptyList()
    {
        $acumulusRequest = $this->container->getAcumulusRequest();
        $uri = 'uri';
        $submit = $this->examples->getSubmit('vatinfo-empty-return');
        $acumulusRequest->execute($uri, $submit, false);
        $httpCode = 200;
        $httpResponse = new HttpResponse(
            '',
            $this->examples->getResponseBody('vatinfo-empty-return'),
            ['http_code' => $httpCode],
            new HttpRequest());
        $result = $this->createAcumulusResult();
        $result->setAcumulusRequest($acumulusRequest);
        $result->setHttpResponse($httpResponse);

        $this->assertSame($httpResponse, $result->getHttpResponse());
        $this->assertSame(Severity::Success, $result->getStatus());
        $this->assertSame($this->t('message_response_success'), $result->getStatusText());
        $this->assertSame(Severity::Unknown, $result->getSeverity());
        $this->assertCount(0, $result->getMessages());
        $this->assertFalse($result->hasRealMessages());
        $this->assertFalse($result->hasError());
        $this->assertNull($result->getByCode(701));
        $this->assertNull($result->getByCodeTag('N1'));

        $result->setMainResponseKey('vatinfo', true);
        $this->assertSame($this->examples->getMainResponse('vatinfo-empty-return'), $result->getResponse());
    }

    public function testSetHttpResponseWithError()
    {
        $acumulusRequest = $this->container->getAcumulusRequest();
        $uri = 'uri';
        $submit = $this->examples->getSubmit('no_contract');
        $acumulusRequest->execute($uri, $submit, false);
        $httpCode = 200;
        $httpResponse = new HttpResponse(
            '',
            $this->examples->getResponseBody('no_contract'),
            ['http_code' => $httpCode],
            new HttpRequest());
        $result = $this->createAcumulusResult();
        $result->setAcumulusRequest($acumulusRequest);
        $result->setHttpResponse($httpResponse);

        $this->assertSame($httpResponse, $result->getHttpResponse());
        $this->assertSame(Severity::Error, $result->getStatus());
        $this->assertSame($this->t('message_response_error'), $result->getStatusText());
        $this->assertSame(Severity::Error, $result->getSeverity());
        $this->assertCount(1, $result->getMessages());
        $this->assertTrue($result->hasRealMessages());
        $this->assertTrue($result->hasError());
        $this->assertNull($result->getByCode(701));
        $this->assertNull($result->getByCodeTag('N1'));
        $this->assertInstanceOf(Message::class, $result->getByCode(403));
        $this->assertInstanceOf(Message::class, $result->getByCode('403 Forbidden'));
        $this->assertInstanceOf(Message::class, $result->getByCodeTag('AF1001MCS'));

        $result->setMainResponseKey('vatinfo', true);
        $this->assertEmpty($result->getResponse());
    }

    public function testMaskPasswordsRequest()
    {
        $acumulusRequest = $this->container->getAcumulusRequest();
        $uri = 'uri';
        $submit = $this->examples->getSubmit('accounts');
        $acumulusRequest->execute($uri, $submit, false);
        $httpCode = 200;
        $httpResponse = new HttpResponse(
            '',
            $this->examples->getResponseBody('accounts'),
            ['http_code' => $httpCode],
            new HttpRequest());
        $result = $this->createAcumulusResult();
        $result->setAcumulusRequest($acumulusRequest);
        $result->setHttpResponse($httpResponse);

        $messages = $result->toLogMessages(false);
        $this->assertArrayHasKey('Request', $messages);
        $this->assertArrayHasKey('Response', $messages);
        $this->assertArrayNotHasKey('Exception', $messages);
        $this->assertStringNotContainsString('mysecret', $messages['Request']);
        $this->assertStringContainsString('REMOVED FOR SECURITY', $messages['Request']);
    }

    public function testMaskPasswordsResponse()
    {
        $acumulusRequest = $this->container->getAcumulusRequest();
        $uri = 'uri';
        $submit = $this->examples->getSubmit('signup');
        $acumulusRequest->execute($uri, $submit, false);
        $httpCode = 200;
        $httpResponse = new HttpResponse(
            '',
            $this->examples->getResponseBody('signup'),
            ['http_code' => $httpCode],
            new HttpRequest());
        $result = $this->createAcumulusResult();
        $result->setAcumulusRequest($acumulusRequest);
        $result->setHttpResponse($httpResponse);

        $messages = $result->toLogMessages(false);
        $this->assertArrayHasKey('Request', $messages);
        $this->assertArrayHasKey('Response', $messages);
        $this->assertArrayNotHasKey('Exception', $messages);
        $this->assertStringNotContainsString('mysecret', $messages['Response']);
        $this->assertStringContainsString('REMOVED FOR SECURITY', $messages['Response']);
    }
}

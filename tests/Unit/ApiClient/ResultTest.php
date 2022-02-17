<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection PhpDocSignatureInspection
 */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\ApiClient\Result;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\ResultTranslations;
use Siel\Acumulus\TestWebShop\ApiClient\AcumulusRequest;

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
        $this->container = new Container('TestWebShop', $language);
        Translator::$instance = $this->container->getTranslator();
        $this->translator = Translator::$instance;
        $this->translator->add(new SeverityTranslations());
        $this->translator->add(new ResultTranslations());
        $this->examples = new ApiRequestResponseExamples();
    }

    private function t($key): string
    {
        return $this->translator->get($key);
    }

    public function testCreate(): Result
    {
        $result = new Result();
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

        return $result;
    }

    /**
     * @depends testCreate
     */
    public function testSetRequest(Result $result): Result
    {
        $acumulusRequest = $this->container->getAcumulusRequest();
        $uri = 'uri';
        $message = ['key' => 'value'];
        $acumulusRequest->execute($uri, $message, false);
        $result->setAcumulusRequest($acumulusRequest);

        $this->assertSame($acumulusRequest, $result->getAcumulusRequest());

        return $result;
    }

    /**
     * @depends testSetRequest
     */
    public function testSetHttpResponse(Result $result): Result
    {
        $httpCode = 200;
        $httpResponse = new HttpResponse(
            '',
            $this->examples->getResponse('accounts'),
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
        $this->assertSame($this->examples->getResponseArray('accounts'), $result->getResponse());
    }

    /**
     * @depends testSetRequest
     */
    public function testSetHttpResponseEmptyList(Result $result): Result
    {
        $httpCode = 200;
        $httpResponse = new HttpResponse(
            '',
            $this->examples->getResponse('vatinfo-empty-return'),
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
     * @depends testSetHttpResponseEmptyList
     */
    public function testSetMainResponseEmptyList(Result $result)
    {
        $result->setMainResponseKey('vatinfo', true);
        $this->assertSame($this->examples->getResponseArray('vatinfo-empty-return'), $result->getResponse());
    }

    /**
     * @depends testSetRequest
     */
    public function testSetHttpResponseWithError(Result $result): Result
    {
        $httpCode = 200;
        $httpResponse = new HttpResponse(
            '',
            $this->examples->getResponse('no_contract'),
            ['http_code' => $httpCode],
            new HttpRequest());
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

        return $result;
    }

    /**
     * @depends testSetHttpResponseWithError
     */
    public function testSetMainResponseWithError(Result $result)
    {
        $result->setMainResponseKey('vatinfo', true);
        $this->assertEmpty($result->getResponse());
    }
}

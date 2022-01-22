<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection PhpDocSignatureInspection
 */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\ApiClient\HttpResponse;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\ApiClient\Result;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\ResultTranslations;

/**
 * A Result has the following features that we want to test:
 * - (Raw) request:
 *   - set
 *   - password hiding
 *   - formatting for logging/notifying
 * - (Raw) response
 *   - set raw response
 *   - formatting for logging/notifying
 *   - set and simplify response
 * - Status
 *   - getting based on api status and severity
 *   - getting textual description for it.
 */
class ResultTest extends TestCase
{
    private Translator $translator;
    private ApiRequestResponseExamples $examples;

    protected function setUp(): void
    {
        Translator::$instance = new Translator('nl');
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
        $this->assertEquals(Severity::Unknown, $result->getStatus());
        $this->assertEquals($this->t('request_not_yet_sent'), $result->getStatusText());
        $this->assertEquals(Severity::Unknown, $result->getSeverity());
        $this->assertCount(0, $result->getMessages());
        $this->assertFalse($result->hasRealMessages());
        $this->assertFalse($result->hasError());
        $this->assertNull($result->getByCode(701));
        $this->assertNull($result->getByCodeTag('N1'));

        return $result;
    }

    /**
     * @depends testCreate
     */
    public function testSetResponse(Result $result): Result
    {
        $result->setMainResponseKey('accounts', true)
            ->setResponse(json_decode($this->examples->getResponse('accounts'), true));
        $this->assertEquals(Severity::Success, $result->getStatus());
        $this->assertEquals($this->t('message_response_success'), $result->getStatusText());
        $this->assertCount(2, $result->getMessages());
        $this->assertEquals(Severity::Log, $result->getSeverity());
        $this->assertEquals($this->examples->getResponseArray('accounts'), $result->getResponse());

        return $result;
    }

    public function testSetResponseEmptyList()
    {
        $result = new Result();
        $httpRequest = new HttpRequest(curl_init());
        $httpRequest->post('test', ['xmlstring' => $this->examples->getRequest('vatinfo-empty-return')]);
        $httpResponse = new HttpResponse(
            '',
            $this->examples->getResponse('vatinfo-empty-return'),
            ['http_code' => 200, 'request_header' => ''],
            $httpRequest
        );
        $result->setHttpRequest($httpRequest);
        $result->setHttpResponse($httpResponse);
        $result->setMainResponseKey('vatinfo', true)
            ->setResponse(json_decode($this->examples->getResponse('vatinfo-empty-return'), true));
        $this->assertEquals(Severity::Success, $result->getStatus());
        $this->assertEquals($this->t('message_response_success'), $result->getStatusText());
        $this->assertEquals(Severity::Log, $result->getSeverity());
        $this->assertCount(0, $result->getMessages());
        $this->assertFalse($result->hasRealMessages());
        $this->assertFalse($result->hasError());
        $this->assertNull($result->getByCode(701));
        $this->assertNull($result->getByCodeTag('N1'));
        $this->assertEquals($this->examples->getResponseArray('vatinfo-empty-return'), $result->getResponse());
    }

    public function testResultWithError()
    {
        $result = new Result();
        $result->setMainResponseKey('vatinfo', true)
            ->setResponse(json_decode($this->examples->getResponse('no_contract'), true));
        $this->assertEquals(Severity::Error, $result->getStatus());
        $this->assertEquals($this->t('message_response_error'), $result->getStatusText());
        $this->assertEquals(Severity::Error, $result->getSeverity());
        $this->assertCount(3, $result->getMessages());
        $this->assertTrue($result->hasRealMessages());
        $this->assertTrue($result->hasError());
        $this->assertNull($result->getByCode(701));
        $this->assertNull($result->getByCodeTag('N1'));
        $this->assertInstanceOf(Message::class, $result->getByCode(403));
        $this->assertInstanceOf(Message::class, $result->getByCode('403 Forbidden'));
        $this->assertInstanceOf(Message::class, $result->getByCodeTag('AF1001MCS'));
    }
}

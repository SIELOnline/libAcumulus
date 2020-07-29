<?php /** @noinspection PhpDocSignatureInspection */
namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
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

    private function t($key)
    {
        return $this->translator->get($key);
    }

    public function testCreate()
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
    public function testSetRawRequest(Result $result)
    {
        $result->setRawRequest($this->examples->getRequest('accounts'));
        $this->assertCount(1, $result->getMessages());
        $this->assertStringNotContainsString('<password>mysecret</password>', $result->getByCodeTag(Result::CodeTagRawRequest)->getText());
        $this->assertStringContainsString('<password>REMOVED FOR SECURITY</password>', $result->getByCodeTag(Result::CodeTagRawRequest)->getText());

        return $result;
    }

    /**
     * @depends testSetRawRequest
     */
    public function testSetRawResponse(Result $result)
    {
        $result->setRawResponse($this->examples->getResponse('accounts'));
        $this->assertCount(2, $result->getMessages());
        $this->assertEquals($this->examples->getResponse('accounts'), $result->getByCodeTag(Result::CodeTagRawResponse)->getText());
        $this->assertEquals(Severity::Unknown, $result->getStatus());
        $this->assertEquals(Severity::Log, $result->getSeverity());

        return $result;
    }

    /**
     * @depends testSetRawResponse
     */
    public function testSetResponse(Result $result)
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
        $result->setRawRequest($this->examples->getRequest('vatinfo-empty-return'));
        $result->setRawResponse($this->examples->getResponse('vatinfo-empty-return'));
        $result->setMainResponseKey('vatinfo', true)
            ->setResponse(json_decode($this->examples->getResponse('vatinfo-empty-return'), true));
        $this->assertEquals(Severity::Success, $result->getStatus());
        $this->assertEquals($this->t('message_response_success'), $result->getStatusText());
        $this->assertEquals(Severity::Log, $result->getSeverity());
        $this->assertCount(2, $result->getMessages());
        $this->assertFalse($result->hasRealMessages());
        $this->assertFalse($result->hasError());
        $this->assertNull($result->getByCode(701));
        $this->assertNull($result->getByCodeTag('N1'));
        $this->assertEquals($this->examples->getResponseArray('vatinfo-empty-return'), $result->getResponse());
    }

    public function testResultWithError()
    {
        $result = new Result();
        $result->setRawRequest($this->examples->getRequest('no_contract'));
        $result->setRawResponse($this->examples->getResponse('no_contract'));
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

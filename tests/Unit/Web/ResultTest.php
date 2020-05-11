<?php /** @noinspection PhpDocSignatureInspection */
namespace Siel\Acumulus\Unit\Web;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Web\Result;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Web\Translations;

/**
 * A Result has the following features:
 * - (Raw) request:
 *   - set
 *   - password hiding
 *   - formatting for logging/notifying
 * - isSent
 * - (Raw) response
 *   - set raw response
 *   - set and simplify response
 *   - setting status
 */
class ResultTest extends TestCase
{
    private $translator;
    private $examples;

    protected function setUp(): void
    {
        $this->translator = new Translator('nl');
        $this->translator->add(new SeverityTranslations());
        $this->translator->add(new Translations());
        $this->examples = new ApiRequestResponseExamples();
    }

    private function t($key)
    {
        return $this->translator->get($key);
    }

    public function testCreate()
    {
        $result = new Result($this->translator);
        $this->assertEquals(Severity::Unknown, $result->getStatus());
        $this->assertEquals($this->t('request_not_yet_sent'), $result->getStatusText());
        $this->assertEquals(Severity::Unknown, $result->getSeverity());
        $this->assertFalse($result->isSent());
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
    public function testIsSent(Result $result)
    {
        $result->setIsSent(false);
        $this->assertFalse($result->isSent());

        $result->setIsSent(true);
        $this->assertTrue($result->isSent());

        return $result;
    }

    /**
     * @depends testIsSent
     */
    public function testSetRawRequest(Result $result)
    {
        $result->setRawRequest($this->examples->getRequest(0));
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
        $result->setRawResponse($this->examples->getResponse(0));
        $this->assertCount(2, $result->getMessages());
        $this->assertEquals($this->examples->getResponse(0), $result->getByCodeTag(Result::CodeTagRawResponse)->getText());
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
            ->setResponse(json_decode($this->examples->getResponse(0), true));
        $this->assertEquals(Severity::Success, $result->getStatus());
        $this->assertEquals($this->t('message_response_success'), $result->getStatusText());
        $this->assertCount(2, $result->getMessages());
        $this->assertEquals(Severity::Log, $result->getSeverity());
        $this->assertEquals($this->examples->getResponseArray(0), $result->getResponse());

        return $result;
    }

    public function testResultWithError()
    {
        $result = new Result($this->translator);
        $result->setIsSent(true);
        $result->setRawRequest($this->examples->getRequest(2));
        $result->setRawResponse($this->examples->getResponse(2));
        $result->setMainResponseKey('vatinfo', true)
            ->setResponse(json_decode($this->examples->getResponse(2), true));
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

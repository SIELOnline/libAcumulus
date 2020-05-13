<?php
namespace Siel\Acumulus\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Severity;

class MessageTest extends TestCase
{
    protected function setUp(): void
    {
        $translator = new Translator('nl');
        $translator->add(new SeverityTranslations());
        Message::setTranslator($translator);
    }

    public function testCreateWithAllParams1()
    {
        $message = new Message('Message 701', Severity::Error, 'S1', 0);
        $this->assertEquals('Message 701', $message->getText());
        $this->assertEquals(Severity::Error, $message->getSeverity());
        $this->assertEquals(0, $message->getCode());
        $this->assertEquals('S1', $message->getCodeTag());
        $this->assertEquals('', $message->getField());
        $this->assertNull($message->getException());
        return $message;
    }

    public function testCreateWithAllParams2()
    {
        $message = new Message('Message 701 empty codes', Severity::Success, '', 0);
        $this->assertEquals('Message 701 empty codes', $message->getText());
        $this->assertEquals(Severity::Success, $message->getSeverity());
        $this->assertEquals(0, $message->getCode());
        $this->assertEquals('', $message->getCodeTag());
        $this->assertEquals('', $message->getField());
        $this->assertNull($message->getException());
        return $message;
    }

    public function testCreateWithArray()
    {
        $message = new Message(['code' => 702, 'codetag' => 'W2', 'message' => 'Message 702'], Severity::Warning);
        $this->assertEquals('Message 702', $message->getText());
        $this->assertEquals(Severity::Warning, $message->getSeverity());
        $this->assertEquals(702, $message->getCode());
        $this->assertEquals('W2', $message->getCodeTag());
        $this->assertEquals('', $message->getField());
        $this->assertNull($message->getException());
        return $message;
    }

    public function testCreateWithException()
    {
        $e = new RuntimeException('Message 703', 703);
        $message = new Message($e);
        $this->assertEquals('Message 703', $message->getText());
        $this->assertEquals(Severity::Exception, $message->getSeverity());
        $this->assertEquals(703, $message->getCode());
        $this->assertEquals('', $message->getCodeTag());
        $this->assertEquals('', $message->getField());
        $this->assertEquals($e, $message->getException());
        return $message;
    }

    public function testCreateFormFieldError()
    {
        $message = new Message('Not a valid e-mail address', Severity::Error, 'email');
        $this->assertEquals('Not a valid e-mail address', $message->getText());
        $this->assertEquals(Severity::Error, $message->getSeverity());
        $this->assertEquals(0, $message->getCode());
        $this->assertEquals('', $message->getCodeTag());
        $this->assertEquals('email', $message->getField());
        $this->assertNull($message->getException());
        return $message;
    }

    /**
     * @depends testCreateWithAllParams1
     * @depends testCreateWithAllParams2
     *
     * @param \Siel\Acumulus\Helpers\Message $message1
     * @param \Siel\Acumulus\Helpers\Message $message2
     */
    public function testToString(Message $message1, Message $message2)
    {
        $this->assertEquals('S1: Message 701', (string) $message1);
        $this->assertEquals('Message 701 empty codes', (string) $message2);
    }

    /**
     * @depends testCreateWithAllParams1
     * @depends testCreateWithAllParams2
     * @depends testCreateWithArray
     * @depends testCreateWithException
     * @depends testCreateFormFieldError
     *
     * @param \Siel\Acumulus\Helpers\Message $message1
     * @param \Siel\Acumulus\Helpers\Message $message2
     * @param \Siel\Acumulus\Helpers\Message $message3
     * @param \Siel\Acumulus\Helpers\Message $message4
     * @param \Siel\Acumulus\Helpers\Message $message5
     */
    public function testFormat(Message $message1, Message $message2, Message $message3, Message $message4, Message $message5)
    {
        $this->assertEquals('S1: Message 701', $message1->format(Message::Format_Plain));
        $this->assertEquals('Message 701 empty codes', $message2->format(Message::Format_Plain));
        $this->assertEquals('<span>702, W2:</span> <span>Message 702</span>', $message3->format(Message::Format_Html));

        $this->assertEquals('Ernstige fout: 703: Message 703', $message4->format(Message::Format_PlainWithSeverity));
        $this->assertEquals('<span>Waarschuwing:</span> <span>702, W2:</span> <span>Message 702</span>', $message3->format(Message::Format_HtmlWithSeverity));

        $this->assertEquals("* S1: Message 701\n", $message1->format(Message::Format_PlainList));
        $this->assertEquals('<li><span>702, W2:</span> <span>Message 702</span></li>', $message3->format(Message::Format_HtmlList));

        $this->assertEquals('Not a valid e-mail address', $message5->format(Message::Format_Plain));
        $this->assertEquals('Fout: Not a valid e-mail address', $message5->format(Message::Format_PlainWithSeverity));
    }
}

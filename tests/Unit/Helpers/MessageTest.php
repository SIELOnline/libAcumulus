<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Severity;

/**
 * Tests for the {@see \Siel\Acumulus\Tests\Unit\Helpers\MessageTest} class.
 */
class MessageTest extends TestCase
{
    protected static Translator $translator;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function setUpBeforeClass(): void
    {
        self::$translator = new Translator('nl');
        self::$translator->add(new SeverityTranslations());
    }

    public function testCreateWithAllParams1(): Message
    {
        $message = Message::create('Message 701', Severity::Error, 'S1')->setTranslator(self::$translator);
        self::assertSame('Message 701', $message->getText());
        self::assertSame(Severity::Error, $message->getSeverity());
        self::assertSame('S1', $message->getCode());
        self::assertSame('', $message->getCodeTag());
        self::assertSame('', $message->getField());
        self::assertNull($message->getException());
        return $message;
    }

    public function testCreateWithAllParams2(): Message
    {
        $message = Message::create('Message 701 empty codes', Severity::Success)->setTranslator(self::$translator);
        self::assertSame('Message 701 empty codes', $message->getText());
        self::assertSame(Severity::Success, $message->getSeverity());
        self::assertSame(0, $message->getCode());
        self::assertSame('', $message->getCodeTag());
        self::assertSame('', $message->getField());
        self::assertNull($message->getException());
        return $message;
    }

    public function testCreateWithArray(): Message
    {
        $message = Message::createFromApiMessage(['code' => 702, 'codetag' => 'W2', 'message' => 'Message 702'], Severity::Warning)->setTranslator(self::$translator);
        self::assertSame('Message 702', $message->getText());
        self::assertSame(Severity::Warning, $message->getSeverity());
        self::assertSame(702, $message->getCode());
        self::assertSame('W2', $message->getCodeTag());
        self::assertSame('', $message->getField());
        self::assertNull($message->getException());
        return $message;
    }

    public function testCreateWithException(): Message
    {
        $e = new RuntimeException('Message 703', 703);
        $message = Message::createFromException($e)->setTranslator(self::$translator);
        self::assertSame('Message 703', $message->getText());
        self::assertSame(Severity::Exception, $message->getSeverity());
        self::assertSame(703, $message->getCode());
        self::assertSame('', $message->getCodeTag());
        self::assertSame('', $message->getField());
        self::assertSame($e, $message->getException());
        return $message;
    }

    public function testCreateFormFieldError(): Message
    {
        $message = Message::createForFormField('Not a valid e-mail address', Severity::Error, 'email')->setTranslator(self::$translator);
        self::assertSame('Not a valid e-mail address', $message->getText());
        self::assertSame(Severity::Error, $message->getSeverity());
        self::assertSame(0, $message->getCode());
        self::assertSame('', $message->getCodeTag());
        self::assertSame('email', $message->getField());
        self::assertNull($message->getException());
        return $message;
    }

    /**
     * @depends testCreateWithAllParams1
     * @depends testCreateWithAllParams2
     *
     * @param \Siel\Acumulus\Helpers\Message $message1
     * @param \Siel\Acumulus\Helpers\Message $message2
     */
    public function testToString(Message $message1, Message $message2): void
    {
        self::assertSame('S1: Message 701', (string) $message1);
        self::assertSame('Message 701 empty codes', (string) $message2);
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
    public function testFormat(Message $message1, Message $message2, Message $message3, Message $message4, Message $message5): void
    {
        self::assertSame('S1: Message 701', $message1->format(Message::Format_Plain));
        self::assertSame('Message 701 empty codes', $message2->format(Message::Format_Plain));
        self::assertSame('<span>702, W2:</span> <span>Message 702</span>', $message3->format(Message::Format_Html));

        self::assertSame('Ernstige fout: 703: Message 703', $message4->format(Message::Format_PlainWithSeverity));
        self::assertSame('<span>Waarschuwing:</span> <span>702, W2:</span> <span>Message 702</span>', $message3->format(Message::Format_HtmlWithSeverity));

        self::assertSame('• S1: Message 701', $message1->format(Message::Format_PlainList));
        self::assertSame('<li><span>702, W2:</span> <span>Message 702</span></li>', $message3->format(Message::Format_HtmlList));

        self::assertSame('Not a valid e-mail address', $message5->format(Message::Format_Plain));
        self::assertSame('Fout: Not a valid e-mail address', $message5->format(Message::Format_PlainWithSeverity));
    }
}

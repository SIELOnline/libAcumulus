<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * Tests for the {@see \Siel\Acumulus\Tests\Unit\Helpers\MessageTest} class.
 */
class MessageTest extends TestCase
{
    use AcumulusContainer;

    protected static Translator $translator;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function setUpBeforeClass(): void
    {
        self::$translator = new Translator('nl', self::getContainer()->getLog());
        self::$translator->add(new SeverityTranslations());
    }

    public function testCreateWithAllParams1(): Message
    {
        $message = Message::create('Message 701', Severity::Error, 'S1')->setTranslator(self::$translator);
        static::assertSame('Message 701', $message->getText());
        static::assertSame(Severity::Error, $message->getSeverity());
        static::assertSame('S1', $message->getCode());
        static::assertSame('', $message->getCodeTag());
        static::assertSame('', $message->getField());
        static::assertNull($message->getException());
        return $message;
    }

    public function testCreateWithAllParams2(): Message
    {
        $message = Message::create('Message 701 empty codes', Severity::Success)->setTranslator(self::$translator);
        static::assertSame('Message 701 empty codes', $message->getText());
        static::assertSame(Severity::Success, $message->getSeverity());
        static::assertSame(0, $message->getCode());
        static::assertSame('', $message->getCodeTag());
        static::assertSame('', $message->getField());
        static::assertNull($message->getException());
        return $message;
    }

    public function testCreateWithArray(): Message
    {
        $message = Message::createFromApiMessage(['code' => 702, 'codetag' => 'W2', 'message' => 'Message 702'], Severity::Warning)->setTranslator(self::$translator);
        static::assertSame('Message 702', $message->getText());
        static::assertSame(Severity::Warning, $message->getSeverity());
        static::assertSame(702, $message->getCode());
        static::assertSame('W2', $message->getCodeTag());
        static::assertSame('', $message->getField());
        static::assertNull($message->getException());
        return $message;
    }

    public function testCreateWithException(): Message
    {
        $e = new RuntimeException('Message 703', 703);
        $message = Message::createFromException($e)->setTranslator(self::$translator);
        static::assertSame('Message 703', $message->getText());
        static::assertSame(Severity::Exception, $message->getSeverity());
        static::assertSame(703, $message->getCode());
        static::assertSame('', $message->getCodeTag());
        static::assertSame('', $message->getField());
        static::assertSame($e, $message->getException());
        return $message;
    }

    public function testCreateFormFieldError(): Message
    {
        $message = Message::createForFormField('Not a valid e-mail address', Severity::Error, 'email')->setTranslator(self::$translator);
        static::assertSame('Not a valid e-mail address', $message->getText());
        static::assertSame(Severity::Error, $message->getSeverity());
        static::assertSame(0, $message->getCode());
        static::assertSame('', $message->getCodeTag());
        static::assertSame('email', $message->getField());
        static::assertNull($message->getException());
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
        static::assertSame('S1: Message 701', (string) $message1);
        static::assertSame('Message 701 empty codes', (string) $message2);
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
        static::assertSame('S1: Message 701', $message1->format(Message::Format_Plain));
        static::assertSame('Message 701 empty codes', $message2->format(Message::Format_Plain));
        static::assertSame('<span>702, W2:</span> <span>Message 702</span>', $message3->format(Message::Format_Html));

        static::assertSame('Ernstige fout: 703: Message 703', $message4->format(Message::Format_PlainWithSeverity));
        static::assertSame('<span>Waarschuwing:</span> <span>702, W2:</span> <span>Message 702</span>', $message3->format(Message::Format_HtmlWithSeverity));

        static::assertSame('• S1: Message 701', $message1->format(Message::Format_PlainList));
        static::assertSame('<li><span>702, W2:</span> <span>Message 702</span></li>', $message3->format(Message::Format_HtmlList));

        static::assertSame('Not a valid e-mail address', $message5->format(Message::Format_Plain));
        static::assertSame('Fout: Not a valid e-mail address', $message5->format(Message::Format_PlainWithSeverity));
    }
}

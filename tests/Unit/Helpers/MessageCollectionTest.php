<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Translator;

/**
 * Tests for the {@see MessageCollection} class.
 */
class MessageCollectionTest extends TestCase
{
    protected Translator $translator;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->translator = new Translator('nl');
        $this->translator->add(new SeverityTranslations());
    }

    public function testCreateMessageCollection(): MessageCollection
    {
        $collection = new MessageCollection($this->translator);
        self::assertSame(Severity::Unknown, $collection->getSeverity());
        self::assertFalse($collection->hasRealMessages());
        self::assertFalse($collection->hasError());
        self::assertNull($collection->getByCode(403));
        self::assertNull($collection->getByCode('403'));
        self::assertNull($collection->getByCodeTag('E2'));
        self::assertEmpty($collection->getByField('email'));
        self::assertCount(0, $collection->getMessages());
        self::assertCount(0, $collection->getMessages(Severity::Log));
        self::assertCount(0, $collection->getMessages(Severity::Success));
        self::assertCount(0, $collection->getMessages(Severity::Log | Severity::Success));
        self::assertCount(0, $collection->getMessages(Severity::Info));
        self::assertCount(0, $collection->getMessages(Severity::Error));

        return $collection;
    }

    /**
     * @depends testCreateMessageCollection
     *
     * @param \Siel\Acumulus\Helpers\MessageCollection $collection
     *
     * @return \Siel\Acumulus\Helpers\MessageCollection
     */
    public function testAddLogMessage(MessageCollection $collection): MessageCollection
    {
        $collection->createAndAddMessage('log', Severity::Log);

        self::assertSame(Severity::Log, $collection->getSeverity());
        self::assertFalse($collection->hasRealMessages());
        self::assertFalse($collection->hasError());
        self::assertNull($collection->getByCode(403));
        self::assertNull($collection->getByCode('403'));
        self::assertNull($collection->getByCodeTag('E2'));
        self::assertEmpty($collection->getByField('email'));
        self::assertCount(1, $collection->getMessages());
        self::assertCount(1, $collection->getMessages(Severity::Log));
        self::assertCount(0, $collection->getMessages(Severity::Success));
        self::assertCount(1, $collection->getMessages(Severity::Log | Severity::Success));
        self::assertCount(0, $collection->getMessages(Severity::Info));
        self::assertCount(0, $collection->getMessages(Severity::Error));

        return $collection;
    }

    /**
     * @depends testAddLogMessage
     *
     * @param \Siel\Acumulus\Helpers\MessageCollection $collection
     *
     * @return \Siel\Acumulus\Helpers\MessageCollection
     */
    public function testAddMessage(MessageCollection $collection): MessageCollection
    {
        $collection->createAndAddMessage('suc6', Severity::Success);

        self::assertSame(Severity::Success, $collection->getSeverity());
        self::assertFalse($collection->hasRealMessages());
        self::assertFalse($collection->hasError());
        self::assertNull($collection->getByCode(403));
        self::assertNull($collection->getByCode('403'));
        self::assertNull($collection->getByCodeTag('E2'));
        self::assertEmpty($collection->getByField('email'));
        self::assertCount(2, $collection->getMessages());
        self::assertCount(1, $collection->getMessages(Severity::Log));
        self::assertCount(1, $collection->getMessages(Severity::Success));
        self::assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        self::assertCount(0, $collection->getMessages(Severity::Info));
        self::assertCount(0, $collection->getMessages(Severity::Error));

        return $collection;
    }

    /**
     * @depends testAddMessage
     *
     * @param \Siel\Acumulus\Helpers\MessageCollection $collection
     *
     * @return \Siel\Acumulus\Helpers\MessageCollection
     */
    public function testAddMessage2(MessageCollection $collection): MessageCollection
    {
        $collection->addMessages([Message::create('Message 702', Severity::Error, '403 Forbidden')]);

        self::assertSame(Severity::Error, $collection->getSeverity());
        self::assertTrue($collection->hasError());
        self::assertNull($collection->getByCode(701));
        self::assertInstanceOf(Message::class, $collection->getByCode('403 Forbidden'));
        self::assertInstanceOf(Message::class, $collection->getByCode('403 forbidden'));
        self::assertInstanceOf(Message::class, $collection->getByCode(403));
        self::assertInstanceOf(Message::class, $collection->getByCode('403'));
        self::assertInstanceOf(Message::class, $collection->getByCode('forbidden'));
        self::assertNull($collection->getByCodeTag('E1'));
        self::assertEmpty($collection->getByField('email'));
        self::assertCount(3, $collection->getMessages());
        self::assertCount(1, $collection->getMessages(Severity::Log));
        self::assertCount(1, $collection->getMessages(Severity::Success));
        self::assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        self::assertCount(0, $collection->getMessages(Severity::Info));
        self::assertCount(1, $collection->getMessages(Severity::Error));

        return $collection;
    }

    /**
     * @depends testAddMessage2
     *
     * @param \Siel\Acumulus\Helpers\MessageCollection $collection
     *
     * @return \Siel\Acumulus\Helpers\MessageCollection
     */
    public function testAddMessages(MessageCollection $collection): MessageCollection
    {
        $messages = [
            Message::create('Message 703', Severity::Info, 700),
            Message::create('Message 704', Severity::Info, 700),
            Message::createForFormField('Not a valid e-mail address', Severity::Notice, 'email'),
            Message::createFromApiMessage(['code' => 705, 'codetag' => 'W2', 'message' => 'Message 705'],Severity::Warning),
        ];
        $collection->addMessages($messages, Severity::Warning);

        self::assertSame(Severity::Error, $collection->getSeverity());
        self::assertTrue($collection->hasError());
        self::assertInstanceOf(Message::class, $collection->getByCode(700));
        self::assertNull($collection->getByCode(701));
        self::assertInstanceOf(Message::class, $collection->getByCode(403));
        self::assertNull($collection->getByCodeTag('E1'));
        self::assertCount(1, $collection->getByField('email'));
        self::assertContainsOnlyInstancesOf(Message::class, $collection->getByField('email'));
        self::assertCount(7, $collection->getMessages());
        self::assertContainsOnlyInstancesOf(Message::class, $collection->getMessages());
        self::assertCount(1, $collection->getMessages(Severity::Log));
        self::assertCount(1, $collection->getMessages(Severity::Success));
        self::assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        self::assertCount(2, $collection->getMessages(Severity::Info));
        self::assertCount(1, $collection->getMessages(Severity::Notice));
        self::assertCount(1, $collection->getMessages(Severity::Warning));
        self::assertCount(2, $collection->getMessages(Severity::WarningOrWorse));
        self::assertCount(1, $collection->getMessages(Severity::Error));

        return $collection;
    }

    /**
     * @depends testAddMessages
     *
     * @param \Siel\Acumulus\Helpers\MessageCollection $collection
     */
    public function testFormatMessages(MessageCollection $collection): void
    {
        $format = $collection->formatMessages(Message::Format_Plain);

        self::assertCount(7, $format);
        self::assertSame('log', $format[0]);
        self::assertSame('suc6', $format[1]);

        $format = $collection->formatMessages(Message::Format_HtmlList);

        self::assertIsString($format);
        self::assertStringStartsWith('<ul>', $format);
        self::assertStringEndsWith("</ul>\n", $format);
        self::assertCount(8, explode('<li>', $format));
    }
}

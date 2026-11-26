<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * Tests for the {@see MessageCollection} class.
 */
class MessageCollectionTest extends TestCase
{
    use AcumulusContainer;

    protected Translator $translator;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->translator = new Translator('nl', self::getContainer()->getLog());
        $this->translator->add(new SeverityTranslations());
    }

    public function testCreateMessageCollection(): MessageCollection
    {
        $collection = new MessageCollection($this->translator);
        static::assertSame(Severity::Unknown, $collection->getSeverity());
        static::assertFalse($collection->hasRealMessages());
        static::assertFalse($collection->hasError());
        static::assertNull($collection->getByCode(403));
        static::assertNull($collection->getByCode('403'));
        static::assertNull($collection->getByCodeTag('E2'));
        static::assertEmpty($collection->getByField('email'));
        static::assertCount(0, $collection->getMessages());
        static::assertCount(0, $collection->getMessages(Severity::Log));
        static::assertCount(0, $collection->getMessages(Severity::Success));
        static::assertCount(0, $collection->getMessages(Severity::Log | Severity::Success));
        static::assertCount(0, $collection->getMessages(Severity::Info));
        static::assertCount(0, $collection->getMessages(Severity::Error));

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

        static::assertSame(Severity::Log, $collection->getSeverity());
        static::assertFalse($collection->hasRealMessages());
        static::assertFalse($collection->hasError());
        static::assertNull($collection->getByCode(403));
        static::assertNull($collection->getByCode('403'));
        static::assertNull($collection->getByCodeTag('E2'));
        static::assertEmpty($collection->getByField('email'));
        static::assertCount(1, $collection->getMessages());
        static::assertCount(1, $collection->getMessages(Severity::Log));
        static::assertCount(0, $collection->getMessages(Severity::Success));
        static::assertCount(1, $collection->getMessages(Severity::Log | Severity::Success));
        static::assertCount(0, $collection->getMessages(Severity::Info));
        static::assertCount(0, $collection->getMessages(Severity::Error));

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

        static::assertSame(Severity::Success, $collection->getSeverity());
        static::assertFalse($collection->hasRealMessages());
        static::assertFalse($collection->hasError());
        static::assertNull($collection->getByCode(403));
        static::assertNull($collection->getByCode('403'));
        static::assertNull($collection->getByCodeTag('E2'));
        static::assertEmpty($collection->getByField('email'));
        static::assertCount(2, $collection->getMessages());
        static::assertCount(1, $collection->getMessages(Severity::Log));
        static::assertCount(1, $collection->getMessages(Severity::Success));
        static::assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        static::assertCount(0, $collection->getMessages(Severity::Info));
        static::assertCount(0, $collection->getMessages(Severity::Error));

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

        static::assertSame(Severity::Error, $collection->getSeverity());
        static::assertTrue($collection->hasError());
        static::assertNull($collection->getByCode(701));
        static::assertInstanceOf(Message::class, $collection->getByCode('403 Forbidden'));
        static::assertInstanceOf(Message::class, $collection->getByCode('403 forbidden'));
        static::assertInstanceOf(Message::class, $collection->getByCode(403));
        static::assertInstanceOf(Message::class, $collection->getByCode('403'));
        static::assertInstanceOf(Message::class, $collection->getByCode('forbidden'));
        static::assertNull($collection->getByCodeTag('E1'));
        static::assertEmpty($collection->getByField('email'));
        static::assertCount(3, $collection->getMessages());
        static::assertCount(1, $collection->getMessages(Severity::Log));
        static::assertCount(1, $collection->getMessages(Severity::Success));
        static::assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        static::assertCount(0, $collection->getMessages(Severity::Info));
        static::assertCount(1, $collection->getMessages(Severity::Error));

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

        static::assertSame(Severity::Error, $collection->getSeverity());
        static::assertTrue($collection->hasError());
        static::assertInstanceOf(Message::class, $collection->getByCode(700));
        static::assertNull($collection->getByCode(701));
        static::assertInstanceOf(Message::class, $collection->getByCode(403));
        static::assertNull($collection->getByCodeTag('E1'));
        static::assertCount(1, $collection->getByField('email'));
        static::assertContainsOnlyInstancesOf(Message::class, $collection->getByField('email'));
        static::assertCount(7, $collection->getMessages());
        static::assertContainsOnlyInstancesOf(Message::class, $collection->getMessages());
        static::assertCount(1, $collection->getMessages(Severity::Log));
        static::assertCount(1, $collection->getMessages(Severity::Success));
        static::assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        static::assertCount(2, $collection->getMessages(Severity::Info));
        static::assertCount(1, $collection->getMessages(Severity::Notice));
        static::assertCount(1, $collection->getMessages(Severity::Warning));
        static::assertCount(2, $collection->getMessages(Severity::WarningOrWorse));
        static::assertCount(1, $collection->getMessages(Severity::Error));

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

        static::assertCount(7, $format);
        static::assertSame('log', $format[0]);
        static::assertSame('suc6', $format[1]);

        $format = $collection->formatMessages(Message::Format_HtmlList);

        static::assertIsString($format);
        static::assertStringStartsWith('<ul>', $format);
        static::assertStringEndsWith("</ul>\n", $format);
        static::assertCount(8, explode('<li>', $format));
    }
}

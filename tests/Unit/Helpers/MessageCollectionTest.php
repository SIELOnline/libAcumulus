<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

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
        $this->assertSame(Severity::Unknown, $collection->getSeverity());
        $this->assertFalse($collection->hasRealMessages());
        $this->assertFalse($collection->hasError());
        $this->assertNull($collection->getByCode(403));
        $this->assertNull($collection->getByCode('403'));
        $this->assertNull($collection->getByCodeTag('E2'));
        $this->assertEmpty($collection->getByField('email'));
        $this->assertCount(0, $collection->getMessages());
        $this->assertCount(0, $collection->getMessages(Severity::Log));
        $this->assertCount(0, $collection->getMessages(Severity::Success));
        $this->assertCount(0, $collection->getMessages(Severity::Log | Severity::Success));
        $this->assertCount(0, $collection->getMessages(Severity::Info));
        $this->assertCount(0, $collection->getMessages(Severity::Error));

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

        $this->assertSame(Severity::Log, $collection->getSeverity());
        $this->assertFalse($collection->hasRealMessages());
        $this->assertFalse($collection->hasError());
        $this->assertNull($collection->getByCode(403));
        $this->assertNull($collection->getByCode('403'));
        $this->assertNull($collection->getByCodeTag('E2'));
        $this->assertEmpty($collection->getByField('email'));
        $this->assertCount(1, $collection->getMessages());
        $this->assertCount(1, $collection->getMessages(Severity::Log));
        $this->assertCount(0, $collection->getMessages(Severity::Success));
        $this->assertCount(1, $collection->getMessages(Severity::Log | Severity::Success));
        $this->assertCount(0, $collection->getMessages(Severity::Info));
        $this->assertCount(0, $collection->getMessages(Severity::Error));

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

        $this->assertSame(Severity::Success, $collection->getSeverity());
        $this->assertFalse($collection->hasRealMessages());
        $this->assertFalse($collection->hasError());
        $this->assertNull($collection->getByCode(403));
        $this->assertNull($collection->getByCode('403'));
        $this->assertNull($collection->getByCodeTag('E2'));
        $this->assertEmpty($collection->getByField('email'));
        $this->assertCount(2, $collection->getMessages());
        $this->assertCount(1, $collection->getMessages(Severity::Log));
        $this->assertCount(1, $collection->getMessages(Severity::Success));
        $this->assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        $this->assertCount(0, $collection->getMessages(Severity::Info));
        $this->assertCount(0, $collection->getMessages(Severity::Error));

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

        $this->assertSame(Severity::Error, $collection->getSeverity());
        $this->assertTrue($collection->hasError());
        $this->assertNull($collection->getByCode(701));
        $this->assertInstanceOf(Message::class, $collection->getByCode('403 Forbidden'));
        $this->assertInstanceOf(Message::class, $collection->getByCode('403 forbidden'));
        $this->assertInstanceOf(Message::class, $collection->getByCode(403));
        $this->assertInstanceOf(Message::class, $collection->getByCode('403'));
        $this->assertInstanceOf(Message::class, $collection->getByCode('forbidden'));
        $this->assertNull($collection->getByCodeTag('E1'));
        $this->assertEmpty($collection->getByField('email'));
        $this->assertCount(3, $collection->getMessages());
        $this->assertCount(1, $collection->getMessages(Severity::Log));
        $this->assertCount(1, $collection->getMessages(Severity::Success));
        $this->assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        $this->assertCount(0, $collection->getMessages(Severity::Info));
        $this->assertCount(1, $collection->getMessages(Severity::Error));

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

        $this->assertSame(Severity::Error, $collection->getSeverity());
        $this->assertTrue($collection->hasError());
        $this->assertInstanceOf(Message::class, $collection->getByCode(700));
        $this->assertNull($collection->getByCode(701));
        $this->assertInstanceOf(Message::class, $collection->getByCode(403));
        $this->assertNull($collection->getByCodeTag('E1'));
        $this->assertCount(1, $collection->getByField('email'));
        $this->assertContainsOnlyInstancesOf(Message::class, $collection->getByField('email'));
        $this->assertCount(7, $collection->getMessages());
        $this->assertContainsOnlyInstancesOf(Message::class, $collection->getMessages());
        $this->assertCount(1, $collection->getMessages(Severity::Log));
        $this->assertCount(1, $collection->getMessages(Severity::Success));
        $this->assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        $this->assertCount(2, $collection->getMessages(Severity::Info));
        $this->assertCount(1, $collection->getMessages(Severity::Notice));
        $this->assertCount(1, $collection->getMessages(Severity::Warning));
        $this->assertCount(2, $collection->getMessages(Severity::WarningOrWorse));
        $this->assertCount(1, $collection->getMessages(Severity::Error));

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

        $this->assertCount(7, $format);
        $this->assertSame('log', $format[0]);
        $this->assertSame('suc6', $format[1]);

        $format = $collection->formatMessages(Message::Format_HtmlList);

        $this->assertIsString($format);
        $this->assertStringStartsWith('<ul>', $format);
        $this->assertStringEndsWith("</ul>\n", $format);
        $this->assertCount(8, explode('<li>', $format));
    }
}

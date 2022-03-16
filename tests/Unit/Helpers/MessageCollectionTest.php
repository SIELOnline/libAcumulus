<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

namespace Siel\Acumulus\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\SeverityTranslations;
use Siel\Acumulus\Helpers\Translator;

class MessageCollectionTest extends TestCase
{

    /**
     * @var \Siel\Acumulus\Helpers\Translator
     */
    protected $translator;

    protected function setUp(): void
    {
        $this->translator = new Translator('nl');
        $this->translator->add(new SeverityTranslations());
    }

    public function testCreateMessageCollection(): MessageCollection
    {
        $collection = new MessageCollection($this->translator);
        $this->assertEquals(Severity::Unknown, $collection->getSeverity());
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
        $collection->addMessage('log', Severity::Log);

        $this->assertEquals(Severity::Log, $collection->getSeverity());
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
        $collection->addMessage('suc6', Severity::Success);

        $this->assertEquals(Severity::Success, $collection->getSeverity());
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
        $collection->copyMessages([Message::create('Message 702', Severity::Error, '403 Forbidden')]);

        $this->assertEquals(Severity::Error, $collection->getSeverity());
        $this->assertTrue($collection->hasError());
        $this->assertNull($collection->getByCode(701));
        $this->assertInstanceOf(Message::class, $collection->getByCode(403));
        $this->assertNull($collection->getByCode('403'));
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
        $collection->copyMessages($messages, Severity::Warning);

        $this->assertEquals(Severity::Error, $collection->getSeverity());
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
    public function testFormatMessages(MessageCollection $collection)
    {
        $format = $collection->formatMessages(Message::Format_Plain);

        $this->assertCount(7, $format);
        $this->assertEquals('log', $format[0]);
        $this->assertEquals('suc6', $format[1]);

        $format = $collection->formatMessages(Message::Format_HtmlList);

        $this->assertIsString($format);
        $this->assertStringStartsWith('<ul>', $format);
        $this->assertStringEndsWith("</ul>\n", $format);
        $this->assertCount(8, explode('<li>', $format));
    }
}

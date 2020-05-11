<?php
namespace Siel\Acumulus\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;

class MessageCollectionTest extends TestCase
{
    public function testCreateMessageCollection()
    {
        $collection = new MessageCollection();
        $this->assertEquals(Severity::Unknown, $collection->getSeverity());
        $this->assertFalse($collection->hasRealMessages());
        $this->assertFalse($collection->hasError());
        $this->assertNull($collection->getByCode(403));
        $this->assertNull($collection->getByCode('403'));
        $this->assertNull($collection->getByCodeTag('E2'));
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
    public function testAddLogMessage(MessageCollection $collection)
    {
        $collection->addMessage(Severity::Log, 0, '', 'log');

        $this->assertEquals(Severity::Log, $collection->getSeverity());
        $this->assertFalse($collection->hasRealMessages());
        $this->assertFalse($collection->hasError());
        $this->assertNull($collection->getByCode(403));
        $this->assertNull($collection->getByCode('403'));
        $this->assertNull($collection->getByCodeTag('E2'));
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
    public function testAddMessage(MessageCollection $collection)
    {
        $collection->addMessage(Severity::Success, 0, '', 'suc6');

        $this->assertEquals(Severity::Success, $collection->getSeverity());
        $this->assertFalse($collection->hasRealMessages());
        $this->assertFalse($collection->hasError());
        $this->assertNull($collection->getByCode(403));
        $this->assertNull($collection->getByCode('403'));
        $this->assertNull($collection->getByCodeTag('E2'));
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
    public function testAddMessage2(MessageCollection $collection)
    {
        $collection->addMessage(new Message(Severity::Error, '403 Forbidden', 'E2', 'Message 702'));

        $this->assertEquals(Severity::Error, $collection->getSeverity());
        $this->assertTrue($collection->hasError());
        $this->assertNull($collection->getByCode(701));
        $this->assertInstanceOf(Message::class, $collection->getByCode(403));
        $this->assertNull($collection->getByCode('403'));
        $this->assertNull($collection->getByCodeTag('E1'));
        $this->assertInstanceOf(Message::class, $collection->getByCodeTag('E2'));
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
    public function testAddMessages(MessageCollection $collection)
    {
        $messages = [
          new Message(Severity::Info, 700, 'I0', 'Message 703'),
          new Message(Severity::Info, 700, 'I0', 'Message 704'),
        ];
        $collection->addMessages($messages);

        $this->assertEquals(Severity::Error, $collection->getSeverity());
        $this->assertTrue($collection->hasError());
        $this->assertInstanceOf(Message::class, $collection->getByCode(700));
        $this->assertNull($collection->getByCode(701));
        $this->assertInstanceOf(Message::class, $collection->getByCode(403));
        $this->assertInstanceOf(Message::class, $collection->getByCodeTag('I0'));
        $this->assertNull($collection->getByCodeTag('E1'));
        $this->assertInstanceOf(Message::class, $collection->getByCodeTag('E2'));
        $this->assertCount(5, $collection->getMessages());
        $this->assertCount(1, $collection->getMessages(Severity::Log));
        $this->assertCount(1, $collection->getMessages(Severity::Success));
        $this->assertCount(2, $collection->getMessages(Severity::Log | Severity::Success));
        $this->assertCount(2, $collection->getMessages(Severity::Info));
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

        $this->assertCount(5, $format);
        $this->assertEquals('log', $format[0]);
        $this->assertEquals('suc6', $format[1]);

        $format = $collection->formatMessages(Message::Format_HtmlList);

        $this->assertIsString($format);
        $this->assertStringStartsWith('<ul>', $format);
        $this->assertStringEndsWith("</ul>\n", $format);
        $this->assertCount(6, explode('<li>', $format));
    }
}

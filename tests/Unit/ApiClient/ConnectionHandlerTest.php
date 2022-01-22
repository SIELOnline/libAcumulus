<?php
/** @noinspection PhpStaticAsDynamicMethodCallInspection */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;

/**
 * Test curl handle creation and reuse based on scheme + host.
 */
class ConnectionHandlerTest extends TestCase
{
    public function testCreate()
    {
        $connectionHandler = new ConnectionHandler();
        $ch1 = $connectionHandler->get('https://www.example.com/example-resource');
        $this->assertIsResource($ch1);
        $this->assertEquals(1, $connectionHandler->getCount());

        $ch2 = $connectionHandler->get('https://www.example.com/example-resource2');
        $this->assertIsResource($ch2);
        $this->assertEquals($ch1, $ch2);
        $this->assertEquals(1, $connectionHandler->getCount());

        $ch3 = $connectionHandler->get('https://www.example.com:80/example-resource');
        $this->assertIsResource($ch3);
        $this->assertNotEquals($ch1, $ch3);
        $this->assertEquals(2, $connectionHandler->getCount());

        $ch4 = $connectionHandler->get('https://example.com/example-resource');
        $this->assertIsResource($ch4);
        $this->assertNotEquals($ch1, $ch4);
        $this->assertNotEquals($ch3, $ch4);
        $this->assertEquals(3, $connectionHandler->getCount());

        // Test close.
        $connectionHandler->close('https://www.example.com/example-resource');
        $this->assertEquals(2, $connectionHandler->getCount());

        // Other resources are not touched.
        $ch5 = $connectionHandler->get('https://example.com/example-resource2');
        $this->assertEquals($ch4, $ch5);

        // but closed resource no longer exists and thus is recreated.
        $ch6 = $connectionHandler->get('https://www.example.com/example-resource2');
        $this->assertNotEquals($ch2, $ch6);
    }
}

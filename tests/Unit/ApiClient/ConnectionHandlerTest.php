<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\TestWebShop\TestDoubles\ApiClient\ConnectionHandler;

/**
 * Test curl handle creation and reuse based on scheme + host.
 */
class ConnectionHandlerTest extends TestCase
{
    protected function getConnectionHandler(): ConnectionHandler
    {
        return ConnectionHandler::getInstance();
    }

    public function testCreate(): void
    {
        $connectionHandler = $this->getConnectionHandler();
        $ch1 = $connectionHandler->get('https://www.example.com/example-resource');
        self::assertSame(1, $connectionHandler->getCount());

        $ch2 = $connectionHandler->get('https://www.example.com/example-resource2');
        self::assertSame($ch1, $ch2);
        self::assertSame(1, $connectionHandler->getCount());

        $ch3 = $connectionHandler->get('https://www.example.com:80/example-resource');
        self::assertNotSame($ch1, $ch3);
        self::assertSame(2, $connectionHandler->getCount());

        $ch4 = $connectionHandler->get('https://example.com/example-resource');
        self::assertNotSame($ch1, $ch4);
        self::assertNotSame($ch3, $ch4);
        self::assertSame(3, $connectionHandler->getCount());

        // Test close.
        $connectionHandler->close('https://www.example.com/example-resource');
        self::assertSame(2, $connectionHandler->getCount());

        // Other resources are not touched.
        $ch5 = $connectionHandler->get('https://example.com/example-resource2');
        self::assertSame($ch4, $ch5);

        // but closed resource no longer exists and thus is recreated.
        $ch6 = $connectionHandler->get('https://www.example.com/example-resource2');
        self::assertNotSame($ch2, $ch6);
    }
}

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
        static::assertSame(1, $connectionHandler->getCount());

        $ch2 = $connectionHandler->get('https://www.example.com/example-resource2');
        static::assertSame($ch1, $ch2);
        static::assertSame(1, $connectionHandler->getCount());

        $ch3 = $connectionHandler->get('https://www.example.com:80/example-resource');
        static::assertNotSame($ch1, $ch3);
        static::assertSame(2, $connectionHandler->getCount());

        $ch4 = $connectionHandler->get('https://example.com/example-resource');
        static::assertNotSame($ch1, $ch4);
        static::assertNotSame($ch3, $ch4);
        static::assertSame(3, $connectionHandler->getCount());

        // Test close.
        $connectionHandler->close('https://www.example.com/example-resource');
        static::assertSame(2, $connectionHandler->getCount());

        // Other resources are not touched.
        $ch5 = $connectionHandler->get('https://example.com/example-resource2');
        static::assertSame($ch4, $ch5);

        // but closed resource no longer exists and thus is recreated.
        $ch6 = $connectionHandler->get('https://www.example.com/example-resource2');
        static::assertNotSame($ch2, $ch6);
    }
}

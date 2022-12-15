<?php
/**
 * @noinspection PhpMissingDocCommentInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Tests\TestWebShop\TestDoubles\ApiClient\ConnectionHandler;

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
        $isPHP8 = version_compare(phpversion(), '8', '>=');

        $connectionHandler = $this->getConnectionHandler();
        $ch1 = $connectionHandler->get('https://www.example.com/example-resource');
        if ($isPHP8) {
            $this->assertInstanceOf('CurlHandle', $ch1);
        } else {
            $this->assertIsResource($ch1);
        }
        $this->assertSame(1, $connectionHandler->getCount());

        $ch2 = $connectionHandler->get('https://www.example.com/example-resource2');
        if ($isPHP8) {
            $this->assertInstanceOf('CurlHandle', $ch2);
        } else {
            $this->assertIsResource($ch2);
        }
        $this->assertSame($ch1, $ch2);
        $this->assertSame(1, $connectionHandler->getCount());

        $ch3 = $connectionHandler->get('https://www.example.com:80/example-resource');
        if ($isPHP8) {
            $this->assertInstanceOf('CurlHandle', $ch3);
        } else {
            $this->assertIsResource($ch3);
        }
        $this->assertNotSame($ch1, $ch3);
        $this->assertSame(2, $connectionHandler->getCount());

        $ch4 = $connectionHandler->get('https://example.com/example-resource');
        if ($isPHP8) {
            $this->assertInstanceOf('CurlHandle', $ch4);
        } else {
            $this->assertIsResource($ch4);
        }
        $this->assertNotSame($ch1, $ch4);
        $this->assertNotSame($ch3, $ch4);
        $this->assertSame(3, $connectionHandler->getCount());

        // Test close.
        $connectionHandler->close('https://www.example.com/example-resource');
        $this->assertSame(2, $connectionHandler->getCount());

        // Other resources are not touched.
        $ch5 = $connectionHandler->get('https://example.com/example-resource2');
        $this->assertSame($ch4, $ch5);

        // but closed resource no longer exists and thus is recreated.
        $ch6 = $connectionHandler->get('https://www.example.com/example-resource2');
        $this->assertNotSame($ch2, $ch6);
    }
}

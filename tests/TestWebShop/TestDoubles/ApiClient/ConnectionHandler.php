<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\TestDoubles\ApiClient;

use Siel\Acumulus\ApiClient\ConnectionHandler as BaseConnectionHandler;

use function count;

/**
 * A child class of Siel\Acumulus\ApiClient\HttpCommunicator to get access to
 * the count of connections.
 */
class ConnectionHandler extends BaseConnectionHandler
{
    private static ConnectionHandler $instance;

    /**
     * Singleton pattern.
     *
     * (PHP8: define return type as static)
     *
     * @return static
     */
    public static function getInstance(): BaseConnectionHandler
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function getCount(): int
    {
        return count($this->curlHandles);
    }
}

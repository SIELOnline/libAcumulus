<?php
namespace Siel\Acumulus\Tests\TestWebShop\TestDoubles\ApiClient;

/**
 * A child class of Siel\Acumulus\ApiClient\HttpCommunicator to get access to
 * the count of connections.
 */
class ConnectionHandler extends \Siel\Acumulus\ApiClient\ConnectionHandler
{
    private static /*?ConnectionHandler*/ $instance = null;

    /**
     * Singleton pattern.
     *
     * (PHP8: define return type as static)
     *
     * @return static
     */
    public static function getInstance(): \Siel\Acumulus\ApiClient\ConnectionHandler
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function getCount(): int
    {
        return count($this->curlHandles);
    }
}

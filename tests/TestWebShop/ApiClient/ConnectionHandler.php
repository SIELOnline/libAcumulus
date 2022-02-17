<?php
namespace Siel\Acumulus\TestWebShop\ApiClient;

/**
 * A child class of Siel\Acumulus\ApiClient\HttpCommunicator to get access to
 * the count of connections.
 */
class ConnectionHandler extends \Siel\Acumulus\ApiClient\ConnectionHandler
{
    public function getCount(): int
    {
        return count($this->curlHandles);
    }
}

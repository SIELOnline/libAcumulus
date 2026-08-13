<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Invoice;

use RuntimeException;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Invoice\WrapperTrait;
use Siel\Acumulus\Whmcs\Helpers\LocalApiTrait;

/**
 * Client represents the client of a {@see Source}.
 */
class Client
{
    use WrapperTrait;
    use LocalApiTrait;

    protected function setShopObject(): void
    {
        $this->shopObject = $this->localApi()->getClient($this->getId());
    }

    protected function setId(): void
    {
        throw new RuntimeException('This method is not expected to be called in WHMCS');
    }

    /**
     * @var Source
     *   A Source for this Client.
     */
    protected Source $source;

    public function __construct(int|string|object|array|null $clientOrId, Source $source, Container $container)
    {
        $this->source = $source;
        $this->initializeWrapper($clientOrId, $container);
    }

    public function getSource(): Source
    {
        return $this->source;
    }

}

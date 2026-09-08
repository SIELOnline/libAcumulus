<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Container;

/**
 * Client represents the client of a {@see Source}.
 */
abstract class Client
{
    use WrapperTrait;

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

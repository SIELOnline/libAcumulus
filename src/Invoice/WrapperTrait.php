<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use RuntimeException;
use Siel\Acumulus\Helpers\Container;

/**
 * WrapperTrait implements an adapter/wrapper pattern for objects from the webshop.
 *
 * It implements id and object setting and retrieval. It also sets access to the
 * container as it turns out that access to the container comes
 */
trait WrapperTrait
{
    protected Container $container;
    protected int $id;
    protected object|array $shopObject;

    /**
     * Initializes the references to the wrapped object.
     *
     * @throw RuntimeException
     */
    private function initializeWrapper(int|string|object|array|null $idOrSource, Container $container): void
    {
        $this->container = $container;
        if (empty($idOrSource)) {
            throw new RuntimeException('Nothing to wrap');
        } elseif (is_scalar($idOrSource)) {
            $this->id = (int) $idOrSource;
            $this->setShopObject();
        } else {
            $this->shopObject = $idOrSource;
            $this->setId();
        }
    }

    /**
     * Sets the wrapped shop object based on an id.
     *
     * Only called by the constructor as this wrapper object should be
     * "immutable": it should only represent one source over its lifetime.
     *
     * @throws \RuntimeException
     */
    abstract protected function setShopObject(): void;

    /**
     * Sets the id based on the wrapped object.
     *
     * Only called by the constructor as this wrapper object should be
     * "immutable": it should only represent one source over its lifetime.
     */
    abstract protected function setId(): void;

    protected function getContainer(): Container
    {
        return $this->container;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getShopObject(): object|array
    {
        return $this->shopObject;
    }
}

<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use RuntimeException;
use Siel\Acumulus\Helpers\Container;

use function function_exists;
use function get_class;
use function is_scalar;
use function sprintf;

/**
 * WrapperTrait implements an adapter/wrapper pattern for objects from the webshop.
 *
 * It implements id and object setting and retrieval. It also sets access to the
 * container as it turns out that access to the container comes
 */
trait WrapperTrait
{
    private Container $container;
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

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t(string $key): string
    {
        return $this->getContainer()->getTranslator()->get($key);
    }

    public function getType(): string
    {
        $class = get_class($this);
        return substr($class, strrpos($class, '\\') + 1);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getReference(): int|string
    {
        return $this->getId();
    }

    public function getLabel(int $case = -1): string
    {
        $label = $this->t($this->getType());
        if ($case !== -1) {
            if (function_exists('mb_convert_case')) {
                $label = mb_convert_case($label, $case);
            } else {
                switch ($case) {
                    case MB_CASE_LOWER:
                        $label = strtolower($label);
                        break;
                    case MB_CASE_UPPER:
                        $label = strtoupper($label);
                        break;
                    case MB_CASE_TITLE:
                        $label = ucfirst($label);
                        break;
                }
            }
        }
        return $label;
    }
    public function getLabelReference(int $case = -1): string
    {
        $class = $this->getLabel($case);
        return sprintf('%s %s', $class, $this->getReference());
    }

    public function getShopObject(): object|array
    {
        return $this->shopObject;
    }
}

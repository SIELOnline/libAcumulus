<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Invoice\Product;

/**
 * ProductManager provides functionality to manage products and their stock.
 *
 * Acumulus allows to define a catalogue of products and services that can be sold,
 * including a.o. their nature and stock
 */
class ProductManager
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
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
        return $this->getTranslator()->get($key);
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getTranslator(): Translator
    {
        return $this->getContainer()->getTranslator();
    }

    protected function getLog(): Log
    {
        return $this->getContainer()->getLog();
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->getConfig();
    }

    public function updateStockForOrderItem(Item $item, int|float $change): void
    {
        $product = $item->getProduct();
        if ($product instanceof Product) {
            if (!Number::isZero($change, 0.00001)) {
                // @todo: implement
            } else {
                $this->getLog()->warning(
                    'Zero change in stock for item %d of %s %d',
                    $item->getId(),
                    $this->t($item->getSource()->getType()),
                    $item->getSource()->getId(),
                );
            }
        } else {
            $this->getLog()->error('No product found for item %d', $item->getId());
        }
    }
}

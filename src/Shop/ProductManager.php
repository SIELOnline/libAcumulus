<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Product\Product;

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

    /**
     * Updates the stock at Acumulus.
     *
     * @param \Siel\Acumulus\Invoice\Item $item
     *   The item line at the origin of the stock change, refers to the product for which
     *   to update the stock.
     * @param int|float $change
     *   The change in stock.
     *
     * @todo: setting if we are doing stock management at all, check if the current
     *   product manages stock (or is a service or ...)
     */
    public function updateStockForItem(Item $item, int|float $change, string $trigger): void
    {
        $result = $this->getContainer()->createStockTransactionResult($trigger);
        if (!$this->getContainer()->getShopCapabilities()->hasStockManagement() || !$this->getConfig()->get('stockManagementEnabled')) {
            return;
        }
        $product = $item->getProduct();
        if ($product === null) {
            // @todo: does this merit an e-mail?
            $this->getLog()->error('No product found for item %d: cannot update stock with %+f', $item->getId(), $change);
            return;
        }
        if (Number::isZero($change, 0.00001)) {
            $this->getLog()->warning(
                'Zero change in stock for item %d of %s %d',
                $item->getId(),
                $this->t($item->getSource()->getType()),
                $item->getSource()->getId(),
            );
            return;
        }

        // @todo: implement:
        //   - collect and complete StockTransaction object: map or lookup productId
        //   - send it
        //   - check result: send mail on error (so, the checks above should set a send
        //     status, so we can mail on not sending due to one of the reasons above?)
        $productWithStock = $product->getStockManagingProduct();
        $this->getContainer()->getLog()->info(
            'Changing stock for product %d (%s) (stock managed by %d) line item %d (of %s %d) with %+f',
            $product->getId(),
            $product->getReferenceForAcumulusLookup(),
            $productWithStock->getId(),
            $item->getId(),
            $this->t($item->getSource()->getType()),
            $item->getSource()->getId(),
            $change,
        );
    }
}

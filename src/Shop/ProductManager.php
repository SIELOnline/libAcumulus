<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\ApiClient\AcumulusErrorException;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Product\Product;
use Siel\Acumulus\Product\StockTransactionResult;

use UnexpectedValueException;

use function count;
use function sprintf;

/**
 * ProductManager provides functionality to manage products and their stock.
 *
 * Acumulus allows to define a catalogue of products and services that can be sold,
 * including a.o. their nature and stock
 */
class ProductManager
{
    private Container $container;

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
     * Description.
     *
     * @param string $reference
     *
     * @return array|null
     *   A "product" array being a keyed array with keys:
     *   - 'productid'
     *   - 'productnature'
     *   - 'productdescription'
     *   - 'producttagid'
     *   - 'productcontactid'
     *   - 'productprice'
     *   - 'productvatrate'
     *   - 'productsku'
     *   - 'productstockamount'
     *   - 'productean'
     *   - 'producthash'
     *   - 'productnotes'
     *   Or null if not found.
     *
     * @throws \UnexpectedValueException  More than 1 result was returned
     */
    public function getAcumulusProductByReference(string $reference): ?array
    {
        // Try to look up the product at Acumulus.
        $acumulus = $this->getContainer()->getAcumulusApiClient();
        // Limit results to max. 2 to prevent very large response in case of a filter that
        // matches too many products.
        $result = $acumulus->getPicklistProducts($reference, 2);
        if (!$result->hasError()) {
            $products = $result->getMainAcumulusResponse();
            if (count($products) === 0) {
                // No match.
                return null;
            } elseif (count($products) === 1) {
                // 1 match.
                return $products[0];
            } else {
                // Multiple matches.
                throw new UnexpectedValueException(
                    sprintf(
                        'Search for reference "%s" resulted in at least 2 products ("%s" and "%s")',
                        $reference,
                        $products[0]['productdescription'],
                        $products[1]['productdescription']
                    )
                );
            }
        } else {
            throw new AcumulusErrorException($result);
        }
    }

    /**
     * Updates the stock at Acumulus.
     *
     * We assume that if we get here, stock management for the product itself has been
     * enabled (assuming that the shop allows to disable it per product)
     *
     * @param \Siel\Acumulus\Invoice\Item $item
     *   The item line at the origin of the stock change, refers to the product for which
     *   to update the stock.
     * @param int|float $change
     *   The change in stock.
     */
    public function updateStockForItem(Item $item, int|float $change, string $trigger): StockTransactionResult
    {
        $result = $this->getContainer()->createStockTransactionResult($trigger);
        $product = $item->getProduct();
        if (!$this->getContainer()->getShopCapabilities()->hasStockManagement() || !$this->getConfig()->get('stockManagementEnabled')) {
            $result->setSendStatus(StockTransactionResult::NotSent_StockManagementNotEnabled);
        } elseif ($product === null) {
            $result->setSendStatus(StockTransactionResult::NotSent_NoProduct);
        } elseif (Number::isZero($change, 0.00001)) {
            $result->setSendStatus(StockTransactionResult::NotSent_ZeroChange);
        } elseif ($this->isTestMode()) {
            $result->setSendStatus(InvoiceAddResult::Sent_TestMode);
        } else {
            $result->setSendStatus(InvoiceAddResult::Sent_New);
        }

        if (($result->getSendStatus() & StockTransactionResult::NotSent_Mask) === 0) {
            $productWithStock = $product->getStockManagingProduct();
            $this->createAndSendStockTransaction($productWithStock, $change, $item, $result);
        }

        // Mail and log the result.
        $this->logStockTransactionResult($item, $change, $product, $result);
        $this->mailStockTransactionResult($result, $product);

        return $result;
    }

    protected function createAndSendStockTransaction(
        Product $product,
        int|float $change,
        Item $item,
        StockTransactionResult $result
    ): void {
        $stockTransaction = $this->createStockTransaction($product, $change, $item, $result);
        if (!$result->isSendingPrevented()) {
            $this->sendStockTransaction($stockTransaction, $result);
        }
    }

    protected function createStockTransaction(
        Product $product,
        float|int $change,
        Item $item,
        StockTransactionResult $result
    ): StockTransaction {
        $stockTransaction = $this->getContainer()->getCollectorManager()->collectStockTransactionForItemLine(
            $product,
            $change,
            $item,
            $result
        );
        $result->setStockTransaction($stockTransaction);
        $this->getContainer()->getCompletor(DataType::StockTransaction)->complete($stockTransaction, $result);
        return $stockTransaction;
    }

    protected function sendStockTransaction(StockTransaction $stockTransaction, StockTransactionResult $result): void
    {
        $apiResult = $this->getContainer()->getAcumulusApiClient()->stockTransaction($stockTransaction);
        $result->setAcumulusResult($apiResult);
    }

    /**
     * Sends an email with the results of sending a stock transaction.
     *
     * The mail is only sent when sending the stock transaction failed, sending was
     * prevented, or if testmode is active and will be sent to the shop administrator
     * ('emailonerror' setting).
     *
     * @return bool
     *   Success.
     */
    protected function mailStockTransactionResult(StockTransactionResult $result, Product $product): bool
    {
        $pluginSettings = $this->getConfig()->getPluginSettings();
        $addReqResp = $pluginSettings['debug'] === Config::Send_SendAndMailOnError
            ? StockTransactionResult::AddReqResp_WithOther
            : StockTransactionResult::AddReqResp_Always;
        if ($addReqResp === StockTransactionResult::AddReqResp_Always || $result->hasRealMessages()) {
            return $this->getContainer()->getMailer()->mailStockTransactionResult($result, $product);
        }
        return true;
    }

    /**
     * Indicates if we are in test mode.
     *
     * @return bool
     *   True if we are in test mode, false otherwise.
     */
    protected function isTestMode(): bool
    {
        return $this->getConfig()->get('debug') === Config::Send_TestMode;
    }

    /**
     * Description.
     */
    private function logStockTransactionResult(Item $item, int|float $change, ?Product $product, StockTransactionResult $result): void
    {
//        $invoiceSourceText = sprintf(
//            $this->t('message_invoice_source'),
//            $this->t($source->getType()),
//            $source->getReference()
//        );
//        $logText = sprintf(
//            $this->t('message_invoice_send'),
//            $result->getTrigger(),
//            $invoiceSourceText,
//            $result->getLogText($addReqResp)
//        );
        // @todo: determine text and level of the message to log.
        $severity = $result->getSeverity();
        $logText ='@todo';
        $this->getLog()->log($severity, $logText);
    }
}

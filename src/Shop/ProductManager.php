<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\ApiClient\AcumulusErrorException;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Result;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Mail\Mail;
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

    protected function getMail(): Mail
    {
        return $this->getContainer()->getMail('StockTransactionMail', 'Product');
    }

    /**
     * Returns the Acumulus product that matches the given reference.
     *
     * The product matching strategy:
     * - If the 'productMatchAcumulusField' setting is not empty:
     *   The product that has an exact match with $reference in the field specified by the
     *   'productMatchAcumulusField' setting.
     * - If the 'productMatchAcumulusField' setting is empty:
     *   The product that has a partial match on $reference in one of the fields used to
     *   filter by the product picklist API call.
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
        $result = $acumulus->getPicklistProducts($reference);
        if ($result->hasError()) {
            throw new AcumulusErrorException($result);
        }

        return $this->matchAcumulusProductInList($result->getMainAcumulusResponse(), $reference);
    }

    /**
     * Extracts 1 product from a list of products based on filter and
     * 'productMatchAcumulusField' setting.
     */
    protected function matchAcumulusProductInList(array $products, string $reference): ?array
    {
        // What to return depends on the 'productMatchAcumulusField' setting:
        $acumulusField = $this->getConfig()->get('productMatchAcumulusField');
        return $acumulusField !== ''
            ? $this->getAcumulusProductByExactMatch($products, $acumulusField, $reference)
            : $this->getSingleProduct($products, $reference);
    }

    protected function getAcumulusProductByExactMatch(array $products, mixed $acumulusField, string $reference): ?array
    {
        $matchingProducts = [];
        foreach ($products as $product) {
            if ($product[$acumulusField] === $reference) {
                $matchingProducts[] = $product;
            }
        }
        return $this->getSingleProduct($matchingProducts, $reference);
    }

    /**
     * @return array|null
     *   - The single product in $products if $products contains exactly 1 product
     *   - null if $products is empty.
     *
     * @throws \UnexpectedValueException  $products contains more than 1 product.
     */
    protected function getSingleProduct(array $products, string $reference): ?array
    {
        if (count($products) === 0) {
            // No match.
            return null;
        } elseif (count($products) === 1) {
            // 1 match.
            return $products[0];
        } else {
            // Multiple matches.
            $message = sprintf("Search for reference '%s' resulted in %d products", $reference, count($products));
            $messageDetailFormat = count($products) === 2 ? " ('%s' and '%s')" : " ('%s', '%s', and others)";
            $messageDetail = sprintf($messageDetailFormat, $products[0][Fld::ProductDescription], $products[1][Fld::ProductDescription]);
            throw new UnexpectedValueException($message . $messageDetail);
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
            $result->createAndAddMessage($result->getSendStatusText(), Severity::Error);
        } elseif (Number::isZero($change, 0.00001)) {
            $result->setSendStatus(StockTransactionResult::NotSent_ZeroChange);
            $result->createAndAddMessage($result->getSendStatusText(), Severity::Error);
        } elseif ($this->isTestMode()) {
            $result->setSendStatus(Result::Sent_TestMode);
        } else {
            $result->setSendStatus(Result::Sent_New);
        }

        if (!$result->isSendingPrevented()) {
            $productWithStock = $product->getStockManagingProduct();
            $this->createAndSendStockTransaction($productWithStock, $change, $item, $result);
        }

        // Mail and log the result.
        $this->mailStockTransactionResult($item, $change, $product, $result);
        $this->logStockTransactionResult($item, $change, $product, $result);

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
    protected function mailStockTransactionResult(Item $item, int|float $change, ?Product $product, StockTransactionResult $result): bool
    {
        $pluginSettings = $this->getConfig()->getPluginSettings();
        $addReqResp = $pluginSettings['debug'] === Config::Send_SendAndMailOnError
            ? Result::AddReqResp_WithOther
            : Result::AddReqResp_Always;
        if ($addReqResp === Result::AddReqResp_Always || $result->hasRealMessages()) {
            return $this->getMail()->createAndSend([
                'source' => $item->getSource(),
                'item' => $item,
                'product' => $product,
                'change' => $change,
                'result' => $result,
            ]);
        }
        return true;
    }

    /**
     * Logs the result of the stock transaction.
     */
    private function logStockTransactionResult(
        Item $item,
        int|float $change,
        ?Product $product,
        StockTransactionResult $result,
        int $addReqResp = Result::AddReqResp_WithOther
    ): void {
        $stockTransactionText = sprintf(
            $this->t('message_stock_transaction_source'),
            $product?->getReference() ?? '',
            $change
        );
        $severity = $result->getSeverity();
        $this->getLog()->log(
            $severity,
            $this->t('message_stock_transaction_send'),
            $result->getTrigger(),
            $this->t($item->getSource()->getLabelReference()),
            $item->getLabelReference(),
            $stockTransactionText,
            $result->getLogText($addReqResp)
        );
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
}

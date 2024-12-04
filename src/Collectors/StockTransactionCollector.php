<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use ArrayObject;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Product\StockTransactionResult;
use UnexpectedValueException;

use function sprintf;

/**
 * Collects stock transaction data from the shop.
 *
 * properties that are mapped:
 * - int $productId
 * - float $stockAmount
 * - string $stockDescription
 *
 * Properties that are computed using logic:
 * - int $productId (optional, if it was not mapped)
 *
 * Properties that are not set:
 * - \DateTimeInterface $stockDate
 *
 * @method StockTransaction collect(PropertySources $propertySources, ?ArrayObject $fieldSpecifications = null)
 */
class StockTransactionCollector extends Collector
{
    /**
     * This override will try to fetch the product id of the product at Acumulus if not
     * already set. The collectMappedFields will only set it, if it stored locally, i.e.
     * if it has been looked up before.
     *
     * @param \Siel\Acumulus\Data\StockTransaction $acumulusObject
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        /** @var \Siel\Acumulus\Product\StockTransactionResult $localResult */
        $localResult = $propertySources->get('localResult');
        if (empty($acumulusObject->productId)) {
            $reference = $acumulusObject->metadataGet(Meta::MatchShopValue);
            if (!empty($reference)) {
                // Try to look up the product at Acumulus.
                try {
                    $acumulusProduct = $this->getContainer()->getProductManager()->getAcumulusProductByReference($reference);
                    if ($acumulusProduct !== null) {
                        $productId = (int) $acumulusProduct[Fld::ProductId];
                        $acumulusObject->productId = $productId;
                        $acumulusObject->metadataSet(Meta::AcumulusProductIdSource, 'remote');
                        /** @var \Siel\Acumulus\Product\Product $product */
                        $product = $propertySources->get('product');
                        $product->setAcumulusId($productId);
                    } else {
                        $localResult->createAndAddMessage(
                            sprintf("Search for reference '%s' resulted in no products", $reference),
                            Severity::Error
                        );
                        $localResult->setSendStatus(StockTransactionResult::NotSent_NoMatchInAcumulus);
                    }
                } catch (UnexpectedValueException $e) {
                    $localResult->createAndAddMessage($e->getMessage(), Severity::Error);
                    $localResult->setSendStatus(StockTransactionResult::NotSent_TooManyMatchesInAcumulus);
                }
            } else {
                $localResult->createAndAddMessage(
                    sprintf("Search field '%s' is empty", $acumulusObject->metadataGet(Meta::MatchShopFieldSpecification)),
                    Severity::Error
                );
                $localResult->setSendStatus(StockTransactionResult::NotSent_NoMatchValueInProduct);
            }
        } else {
            $acumulusObject->metadataSet(Meta::AcumulusProductIdSource, 'local');
        }
    }
}

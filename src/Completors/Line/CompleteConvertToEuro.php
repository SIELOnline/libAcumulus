<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Line;

use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Meta;

/**
 * CompleteConvertToEuro converts all amounts in a line to Euro.
 */
class CompleteConvertToEuro extends RecursiveLineCompletor
{
    protected function complete1(Line $line, ...$args): void
    {
        $this->convertToEuro($line, $args[0]);
    }

    protected function convertToEuro(Line $line, Currency $currency): void
    {
        if ($currency->shouldConvert()) {
            $line->unitPrice = $currency->convertAmount($line->unitPrice);
            // Cost price will probably be in shop's default currency, i.e. euros.
            $line->costPrice = $currency->convertAmount($line->costPrice);
            $this->convertMetaAmount($line, Meta::UnitPriceInc, $currency);
            $this->convertMetaAmount($line, Meta::VatAmount, $currency);
            $this->convertMetaAmount($line, Meta::LineAmount, $currency);
            $this->convertMetaAmount($line, Meta::LineAmountInc, $currency);
            $this->convertMetaAmount($line, Meta::LineVatAmount, $currency);
            $this->convertMetaAmount($line, Meta::LineDiscountAmount, $currency);
            $this->convertMetaAmount($line, Meta::LineDiscountAmountInc, $currency);
            $this->convertMetaAmount($line, Meta::LineDiscountVatAmount, $currency);
        }
    }

    /**
     * Helper method to convert a metadata amount field to euros.
     *
     * @return bool
     *   Whether the amount was converted.
     */
    protected function convertMetaAmount(Line $line, string $metaKey, Currency $currency): bool
    {
        if ($line->metadataExists($metaKey)) {
            $line->metadataSet($metaKey, $currency->convertAmount($line->metadataGet($metaKey)));
            return true;
        }
        return false;
    }
}

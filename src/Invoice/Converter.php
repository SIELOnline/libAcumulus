<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

use function array_key_exists;
use function is_array;

/**
 * Converter converts between the new AcumulusObject and the old array storage.
 *
 * @legacy remove when all shops are fully converted to new architecture and the old
 *   Completor has been removed.
 */
class Converter
{
    /**
     * Converts the lines in $linesArray to {@see Line}s and adds them to $invoice.
     *
     * @param array $linesArray
     *   The elements being either a {@see Line} or an array representing a line.
     */
    public static function getInvoiceLinesFromArray(array $linesArray, Invoice $invoice): void
    {
        foreach ($linesArray as $line) {
            if (is_array($line)) {
                $line = static::getLineFromArray($line);
            }
            $invoice->addLine($line);
        }
    }

    public static function getLineFromArray(array $lineArray): Line
    {
        /** @var \Siel\Acumulus\Data\Line $line */
        $line = Container::getContainer()->createAcumulusObject(DataType::Line);
        foreach ($lineArray as $key => $value) {
            $propertyName = static::getProperty($key);
            if ($propertyName === Meta::ChildrenLines) {
                foreach ($value as $child) {
                    $line->addChild(static::getLineFromArray($child));
                }
            } else {
                $line[$propertyName] = $value;
            }
        }
        return $line;
    }

    private static function getProperty(string $key): string
    {
        $properties = [
            Tag::ItemNumber => Fld::ItemNumber,
            Tag::Product => Fld::Product,
            Tag::Nature => Fld::Nature,
            Tag::UnitPrice => Fld::UnitPrice,
            Tag::VatRate => Fld::VatRate,
            Tag::Quantity => Fld::Quantity,
            Tag::CostPrice => Fld::CostPrice,
        ];
        return array_key_exists($key, $properties) ? $properties[$key] : $key;
    }

}

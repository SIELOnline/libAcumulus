<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;

use function is_array;

/**
 * Converter converts between the new AcumulusObject and the old array storage.
 *
 * @legacy cam be removed
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
            $propertyName = $key;
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
}

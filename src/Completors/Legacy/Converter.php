<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Legacy;

use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Tag;

use function array_key_exists;

/**
 * Converter converts between the new AcumulusObject and the old array storage.
 *
 * @legacy: conversion code.
 */
class Converter
{
    public static function getInvoiceLinesFromArray(array $invoiceArray, Invoice $invoice): Invoice
    {
        foreach ($invoiceArray[Tag::Customer][Tag::Invoice][Tag::Line] as $line) {
            $invoice->addLine(static::getLineFromArray($line));
        }
        return $invoice;
    }

    public static function getLineFromArray(array $lineArray): Line
    {
        /** @var \Siel\Acumulus\Data\Line $line */
        $line = Container::getContainer()->createAcumulusObject(DataType::Line);
        foreach ($lineArray as $key => $value) {
            $line[static::getProperty($key)] = $value;
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

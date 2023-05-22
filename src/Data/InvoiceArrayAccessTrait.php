<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Tag;

/**
 * Allows access to Invoices with array bracket syntax and Acumulus tags (all lower case).
 *
 * This trait overrides
 * {@see \Siel\Acumulus\Data\AcumulusObjectArrayAccessTrait::getOffsetMappings()}.
 *
 */
trait InvoiceArrayAccessTrait
{
    protected function getOffsetMappings(): array
    {
        $result = parent::getOffsetMappings();
        $result[Tag::Line] = 'lines';
        return $result;
    }
}

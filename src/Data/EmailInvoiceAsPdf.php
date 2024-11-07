<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;
use Siel\Acumulus\Fld;

/**
 * Represents an emailAsPdf part of an Acumulus API invoice object or direct
 * e-mail invoice as pdf request.
 *
 * @property ?bool $ubl
 *
 * @method bool setUbl(bool|int|null $value, int $mode = PropertySet::Always)
 */
class EmailInvoiceAsPdf extends EmailAsPdf
{
    protected function getPropertyDefinitions(): array
    {
        return array_merge(
            parent::getPropertyDefinitions(),
            [
                ['name' => Fld::Ubl, 'type' => 'bool', 'allowedValues' => [Api::UblInclude_No, Api::UblInclude_Yes]],
            ]
        );
    }
}

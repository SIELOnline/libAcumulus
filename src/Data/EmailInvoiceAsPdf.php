<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;

/**
 * Represents an emailAsPdf part of an Acumulus API invoice object or direct
 * e-mail invoice as pdf request.
 *
 * Field names are copied from the API, though capitals are introduced for
 * readability and to prevent PhpStorm typo inspections.
 *
 * Metadata can be added via the {@see MetadataCollection} methods.
 *
 * @property ?bool $ubl
 *
 * @method bool setUbl(?bool $value, int $mode = PropertySet::Always)
 */
class EmailInvoiceAsPdf extends EmailAsPdf
{
    protected function getPropertyDefinitions(): array
    {
        return parent::getPropertyDefinitions() + [
            ['name' => 'ubl', 'type' => 'bool', 'allowedValues' => [Api::UblInclude_No, Api::UblInclude_Yes]],
        ];
    }
}

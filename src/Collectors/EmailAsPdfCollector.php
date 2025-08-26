<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use ArrayObject;
use Siel\Acumulus\Data\EmailAsPdf;

/**
 * Collects emailAsPdf data from the shop and the module's settings.
 *
 * Properties that are mapped:
 * - string $emailTo
 * - string $emailBcc
 * - string $emailFrom
 * - string $subject
 * - bool $gfx
 * - bool $ubl (invoices only)
 *
 * Properties that are based on configuration and thus are not set here:
 * - ubl
 *
 * Properties that are not set:
 * - string $message
 * - bool $confirmReading
 * @method EmailAsPdf collect(PropertySources $propertySources, ?ArrayObject $fieldSpecifications = null)
 */
class EmailAsPdfCollector extends SubTypedCollector
{
    /**
     * This override changes the {@see \Siel\Acumulus\Data\AcumulusObject} type as
     * {@see \Siel\Acumulus\Data\EmailAsPdfType} has 2 real subclasses, not just subtypes.
     */
    protected function getAcumulusObjectType(): string
    {
        return $this->subType;
    }
}

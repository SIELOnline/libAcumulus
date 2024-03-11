<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\EmailAsPdfType;

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
 * - none
 *
 * Properties that are not set:
 * - string $message
 * - bool $confirmReading
 */
class EmailAsPdfCollector extends Collector
{
    private string $type;

    protected function getAcumulusObjectType(): string
    {
        return $this->type;
    }

    public function collect(array $propertySources, array $fieldSpecifications): AcumulusObject
    {
        $this->type = $fieldSpecifications['emailAsPdfType'] ?? EmailAsPdfType::Invoice;
        unset($fieldSpecifications['emailAsPdfType']);
        return parent::collect($propertySources, $fieldSpecifications);
    }

}

<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\EmailInvoiceAsPdf;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;

/**
 * CompleteByConfig adds configuration-based values.
 */
class CompleteByConfig extends BaseCompletorTask
{
    /**
     * Adds some values based on configuration.
     *
     * The following fields are set based on their corresponding config value:
     * - Ubl.
     *
     * @param \Siel\Acumulus\Data\EmailInvoiceAsPdf $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        $ubl = $this->configGet('ubl');
        $acumulusObject->setUbl($ubl);
    }
}

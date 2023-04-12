<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Customer;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;

/**
 * CompleteEmail validates or fills the e-mail address.
 */
class CompleteByConfig extends BaseCompletorTask
{
    /**
     * Adds some values based on configuration.
     *
     * The corresponding value from config is added to the following fields:
     * - type (int, Api::CustomerType_Debtor, Api::CustomerType_Creditor,
     *   Api::CustomerType_Relation).
     * - contactStatus (bool, Api::ContactStatus_Disabled,
     *   Api::ContactStatus_Active)
     * - overwriteIfExists (bool, Api::OverwriteIfExists_No,
     *   Api::OverwriteIfExists_yes)
     *
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        $acumulusObject->type = $this->configGet('defaultCustomerType');
        $acumulusObject->contactStatus = $this->configGet('contactStatus');
        $acumulusObject->overwriteIfExists = $this->configGet('overwriteIfExists');
    }
}

<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Customer;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;

/**
 * CompleteByConfig adds configuration based values to a Customer object.
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
     * - disableDuplicates (bool, Api::DisableDuplicates_No,
     *   Api::DisableDuplicates_yes)
     *
     * @param \Siel\Acumulus\Data\Customer $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        $customerType = $this->configGet('defaultCustomerType');
        if (!empty($customerType)) {
            $acumulusObject->type = $customerType;
        }
        $acumulusObject->contactStatus = $this->configGet('contactStatus');
        $acumulusObject->overwriteIfExists = $this->configGet('overwriteIfExists');
        $acumulusObject->disableDuplicates = $this->configGet('disableDuplicates');
    }
}

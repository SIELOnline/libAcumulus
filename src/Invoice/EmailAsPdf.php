<?php

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Api;

/**
 * @property string $emailTo
 * @property ?string $emailBcc
 * @property ?string $emailFrom
 * @property ?string $subject
 * @property ?string $message
 * @property ?int $confirmReading
 * @property ?int $ubl
 */
class EmailAsPdf extends AcumulusObject
{
    protected static array $propertyDefinitions = [
        ['name' => 'emailTo', 'type' =>'string', 'required' => true],
        ['name' => 'emailBcc', 'type' =>'string'],
        ['name' => 'emailFrom', 'type' =>'string'],
        ['name' => 'subject', 'type' =>'string'],
        ['name' => 'message', 'type' =>'string'],
        ['name' => 'confirmReading', 'type' =>'int', 'allowedValues' => [Api::ConfirmReading_No, Api::ConfirmReading_Yes]],
        ['name' => 'ubl', 'type' =>'int', 'allowedValues' => [Api::UblInclude_No, Api::UblInclude_Yes]],
    ];
}

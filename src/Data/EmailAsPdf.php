<?php

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Api;

/**
 * @property ?string $emailTo
 * @property ?string $emailBcc
 * @property ?string $emailFrom
 * @property ?string $subject
 * @property ?string $message
 * @property ?int $confirmReading
 * @property ?int $ubl
 *
 * @method bool setEmailTo(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setEmailBcc(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setEmailFrom(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setSubject(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setMessage(?string $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setConfirmReading(?int $value, int $mode = AcumulusProperty::Set_Always)
 * @method bool setUbl(?int $value, int $mode = AcumulusProperty::Set_Always)
 */
class EmailAsPdf extends AcumulusObject
{
    protected static array $propertyDefinitions = [
        ['name' => 'emailTo', 'type' =>'string', 'required' => true],
        ['name' => 'emailBcc', 'type' =>'string'],
        ['name' => 'emailFrom', 'type' =>'string'],
        ['name' => 'subject', 'type' =>'string'],
        ['name' => 'message', 'type' =>'string'],
        ['name' => 'confirmReading', 'type' =>'bool', 'allowedValues' => [Api::ConfirmReading_No, Api::ConfirmReading_Yes]],
        ['name' => 'ubl', 'type' =>'bool', 'allowedValues' => [Api::UblInclude_No, Api::UblInclude_Yes]],
        ['name' => 'gfx', 'type' =>'bool', 'allowedValues' => [Api::ApplyGraphics_No, Api::ApplyGraphics_Yes]],
    ];
}

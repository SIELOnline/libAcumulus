<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Fld;

/**
 * Represents the <contract> part of the
 * {@link https://www.siel.nl/acumulus/API/Basic_Submit/ "basic submit"} part of an
 * Acumulus API request.
 *
 * For a few API calls the contract part is not needed.
 * The {@see \Siel\Acumulus\Data\BasicSubmit} object will know this and will not
 * instantiate this Contract object if not needed.
 *
 * @property ?string $contractCode
 * @property ?string $userName
 * @property ?string $password
 * @property ?string $emailOnError
 * @property ?string $emailOnWarning
 *
 * @method bool setContractCode(?string $value, int $mode = PropertySet::Always)
 * @method bool setUserName(?string $value, int $mode = PropertySet::Always)
 * @method bool setPassword(?string $value, int $mode = PropertySet::Always)
 * @method bool setEmailOnError(?string $value, int $mode = PropertySet::Always)
 * @method bool setEmailOnWarning(?string $value, int $mode = PropertySet::Always)
 */
class Contract extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => Fld::ContractCode, 'type' => 'string'],
            ['name' => Fld::UserName, 'type' => 'string'],
            ['name' => Fld::Password, 'type' => 'string'],
            ['name' => Fld::EmailOnError, 'type' => 'string'],
            ['name' => Fld::EmailOnWarning, 'type' => 'string'],
        ];
    }
}

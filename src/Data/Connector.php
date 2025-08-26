<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

use Siel\Acumulus\Fld;

/**
 * Represents the <connector> part of the
 * {@link https://www.siel.nl/acumulus/API/Basic_Submit/ "basic submit"} part of an
 * Acumulus API request.
 *
 * The connector part contains information related to the client system.
 *
 * @property ?string $application
 * @property ?string $webKoppel
 * @property ?string $development
 * @property ?string $remark
 * @property ?string $sourceUri
 *
 * @method bool setApplication(?string $value, int $mode = PropertySet::Always)
 * @method bool setWebKoppel(?string $value, int $mode = PropertySet::Always)
 * @method bool setDevelopment(?string $value, int $mode = PropertySet::Always)
 * @method bool setRemark(?string $value, int $mode = PropertySet::Always)
 * @method bool setSourceUri(?string $value, int $mode = PropertySet::Always)
 */
class Connector extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => Fld::Application, 'type' => 'string'],
            ['name' => Fld::WebKoppel, 'type' => 'string'],
            ['name' => Fld::Development, 'type' => 'string'],
            ['name' => Fld::Remark, 'type' => 'string'],
            ['name' => Fld::SourceUri, 'type' => 'string'],
        ];
    }
}

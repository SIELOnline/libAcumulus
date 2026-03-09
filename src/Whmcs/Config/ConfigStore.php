<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Config;

use SensitiveParameter;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;
use Siel\Acumulus\Meta;
use WHMCS\Database\Capsule;

/**
 * Implements the connection to the WHMCS config component.
 */
class ConfigStore extends BaseConfigStore
{

    public function load(): array
    {
        return json_decode(
            Capsule::table('tbladdonmodules')
                ->where('module', $this->configKey)
                ->where('setting', $this->configKey)
                ->first(['setting', 'value'])
                ?->value ?? '{}',
            flags: Meta::JsonFlags
        );
    }

    public function save(#[SensitiveParameter] array $values): bool
    {
        return Capsule::table('tbladdonmodules')->updateOrInsert(
            ['module' => $this->configKey, 'setting' => $this->configKey],
            ['value' => json_encode($values, Meta::JsonFlags)]
        );
    }
}

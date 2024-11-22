<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Config;

use SensitiveParameter;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;
use Siel\Acumulus\Meta;

/**
 * Implements the connection to the TestWebShop config component.
 *
 * Create your own config.json with the following properties (values to be
 * filled in):
 * {"contractcode":"","username":"","password":"","emailonerror":"","logLevel":1}
 */
class ConfigStore extends BaseConfigStore
{
    private string $configDir = __DIR__ . '/../../../../config';
    private string $configFile;

    public function __construct()
    {
        $this->configFile = $this->configDir . '/config.json';
    }

    public function load(): array
    {
        /** @noinspection JsonEncodingApiUsageInspection */
        return is_readable($this->configFile) ? json_decode(file_get_contents($this->configFile), true) : [];
    }

    public function save(#[SensitiveParameter] array $values): bool
    {
        /** @noinspection JsonEncodingApiUsageInspection */
        file_put_contents($this->configFile, json_encode($values, Meta::JsonFlags | JSON_FORCE_OBJECT));
        return true;
    }
}

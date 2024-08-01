<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Config;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Extension;
use Joomla\Database\DatabaseInterface;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;
use Siel\Acumulus\Meta;

/**
 * Implements the connection to the Joomla config component.
 */
class ConfigStore extends BaSeConfigStore
{
    public function load(): array
    {
        $extension = ExtensionHelper::getExtensionRecord('com_acumulus', 'component');
        $values = $extension->custom_data;
        return !empty($values) ? json_decode($values, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    public function save(array $values): bool
    {
        $extensionTable = new Extension(Factory::getContainer()->get(DatabaseInterface::class));
        $extensionTable->load(['element' => 'com_acumulus']);
        /** @noinspection JsonEncodingApiUsageInspection  false positive */
        $extensionTable->custom_data = json_encode($values, Meta::JsonFlags | JSON_FORCE_OBJECT);
        return $extensionTable->store();
    }
}

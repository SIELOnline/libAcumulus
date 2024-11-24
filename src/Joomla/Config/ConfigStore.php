<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection SensitiveParameter is PHP 8.2
 */

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Config;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Extension;
use Joomla\Database\DatabaseInterface;
use SensitiveParameter;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;
use Siel\Acumulus\Meta;

/**
 * Implements the connection to the Joomla config component.
 */
class ConfigStore extends BaSeConfigStore
{
    public function load(): array
    {
        // Do not use the ExtensionHelper class: it caches the extension records without
        // any way to clear that cache. So when we update the config values (on one of the
        // config forms or during an update) we do not get the fresh values.
        // $extension = ExtensionHelper::getExtensionRecord('com_acumulus', 'component');
        // $values = $extension->custom_data;
        // Instead we load from the database
        $extensionTable = new Extension(Factory::getContainer()->get(DatabaseInterface::class));
        $extensionTable->load(['element' => 'com_acumulus']);
        $values = $extensionTable->custom_data;
        return !empty($values) ? json_decode($values, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    public function save(#[SensitiveParameter] array $values): bool
    {
        $extensionTable = new Extension(Factory::getContainer()->get(DatabaseInterface::class));
        $extensionTable->load(['element' => 'com_acumulus']);
        /** @noinspection JsonEncodingApiUsageInspection  false positive */
        $extensionTable->custom_data = json_encode($values, Meta::JsonFlags | JSON_FORCE_OBJECT);
        return $extensionTable->store();
    }
}

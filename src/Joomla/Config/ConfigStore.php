<?php
namespace Siel\Acumulus\Joomla\Config;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Extension;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the Joomla config component.
 */
class ConfigStore extends BaSeConfigStore
{
    /**
     * {@inheritdoc}
     */
    public function load(): array
    {
        /** @noinspection PhpDeprecationInspection : Deprecated as of J4 */
        $extensionTable = new Extension(Factory::getDbo());
        $extensionTable->load(array('element' => 'com_acumulus'));
        $values = $extensionTable->get('custom_data');
        return !empty($values) ? json_decode($values, true) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values): bool
    {
        /** @noinspection PhpDeprecationInspection : Deprecated as of J4 */
        $extensionTable = new Extension(Factory::getDbo());
        $extensionTable->load(['element' => 'com_acumulus']);
        $extensionTable->set('custom_data', json_encode($values, JSON_FORCE_OBJECT));
        return $extensionTable->store();
    }
}

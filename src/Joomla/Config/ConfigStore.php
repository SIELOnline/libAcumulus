<?php
namespace Siel\Acumulus\Joomla\Config;

use JFactory;
use JTableExtension;
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
        $extensionTable = new JtableExtension(JFactory::getDbo());
        $extensionTable->load(array('element' => 'com_acumulus'));
        $values = $extensionTable->get('custom_data');
        return !empty($values) ? json_decode($values, true) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values): bool
    {
        $extensionTable = new JtableExtension(JFactory::getDbo());
        $extensionTable->load(['element' => 'com_acumulus']);
        $extensionTable->set('custom_data', json_encode($values, JSON_FORCE_OBJECT));
        return $extensionTable->store();
    }
}

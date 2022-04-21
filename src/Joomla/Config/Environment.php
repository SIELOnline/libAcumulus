<?php
namespace Siel\Acumulus\Joomla\Config;

use JFactory;
use JTable;
use JTableExtension;
use Siel\Acumulus\Config\Environment as EnvironmentBase;

/**
 * Defines common Joomla environment values for web shops running on Joomla.
 */
class Environment extends EnvironmentBase
{
    /**
     * {@inheritdoc}
     */
    public function setShopEnvironment(): void
    {
        /** @var JTableExtension $extension */
        $extension = JTable::getInstance('extension');

        $id = $extension->find(['element' => 'com_acumulus', 'type' => 'component']);
        if (!empty($id)) {
            if ($extension->load($id)) {
                /** @noinspection PhpUndefinedFieldInspection */
                $componentInfo = json_decode($extension->manifest_cache, true);
                $this->data['moduleVersion'] = $componentInfo['version'];
            }
        }

        $id = $extension->find(['element' => 'com_' . strtolower($this->data['shopName']), 'type' => 'component']);
        if (!empty($id)) {
            if ($extension->load($id)) {
                /** @noinspection PhpUndefinedFieldInspection */
                $componentInfo = json_decode($extension->manifest_cache, true);
                $this->data['shopVersion'] = $componentInfo['version'];
            }
        }

        $this->data['cmsName'] = 'Joomla';
        $this->data['cmsVersion'] = JVERSION;
    }

    protected function executeQuery(string $query): array
    {
        return JFactory::getDbo()->setQuery($query)->loadAssocList();
    }
}

<?php
namespace Siel\Acumulus\Joomla\Config;

use JRoute;
use JTable;
use JTableExtension;
use JUri;
use Siel\Acumulus\Config\Environment as EnvironmentBase;

/**
 * Defines common Joomla capabilities for web shops running on Joomla.
 */
class Environment extends EnvironmentBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopEnvironment(): array
    {
        /** @var JTableExtension $extension */
        $extension = JTable::getInstance('extension');

        $id = $extension->find(['element' => 'com_acumulus', 'type' => 'component']);
        $extension->load($id);
        /** @noinspection PhpUndefinedFieldInspection */
        $componentInfo = json_decode($extension->manifest_cache, true);
        $moduleVersion = $componentInfo['version'];

        $id = $extension->find(['element' => 'com_' . strtolower($this->shopName), 'type' => 'component']);
        $extension->load($id);
        /** @noinspection PhpUndefinedFieldInspection */
        $componentInfo = json_decode($extension->manifest_cache, true);
        $shopVersion = $componentInfo['version'];

        $joomlaVersion = JVERSION;

        return [
            'moduleVersion' => $moduleVersion,
            'shopName' => $this->shopName,
            'shopVersion' => $shopVersion,
            'cmsName' => 'Joomla',
            'cmsVersion' => $joomlaVersion,
        ];
    }
}

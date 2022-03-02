<?php
namespace Siel\Acumulus\Joomla\Config;

use JRoute;
use JTable;
use JTableExtension;
use JUri;
use Siel\Acumulus\Config\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines common Joomla capabilities for web shops running on Joomla.
 */
abstract class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopEnvironment()
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

        $environment = [
            'moduleVersion' => $moduleVersion,
            'shopName' => $this->shopName,
            'shopVersion' => "$shopVersion (CMS: Joomla $joomlaVersion)",
        ];

        return $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($linkType)
    {
        switch ($linkType) {
            case 'config':
                return JRoute::_('index.php?option=com_acumulus&task=config');
            case 'advanced':
                return JRoute::_('index.php?option=com_acumulus&task=advanced');
            case 'batch':
                return JRoute::_('index.php?option=com_acumulus&task=batch');
            case 'register':
                return JRoute::_('index.php?option=com_acumulus&task=register');
            case 'invoice':
                return JRoute::_('index.php?option=com_acumulus&task=invoice');
            case 'logo':
                return JURI::root(true) . '/administrator/components/com_acumulus/media/siel-logo.svg';
        }
        return parent::getLink($linkType);
    }
}

<?php
namespace Siel\Acumulus\Joomla\Shop;

use JRoute;
use JTable;
use JTableExtension;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines Joomla webshops specific capabilities.
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

        $id = $extension->find(array('element' => 'com_acumulus', 'type' => 'component'));
        $extension->load($id);
        /** @noinspection PhpUndefinedFieldInspection */
        $componentInfo = json_decode($extension->manifest_cache, true);
        $moduleVersion = $componentInfo['version'];

        $id = $extension->find(array('element' => 'com_' . strtolower($this->shopName), 'type' => 'component'));
        $extension->load($id);
        /** @noinspection PhpUndefinedFieldInspection */
        $componentInfo = json_decode($extension->manifest_cache, true);
        $shopVersion = $componentInfo['version'];

        $joomlaVersion = JVERSION;

        $environment = array(
            'moduleVersion' => $moduleVersion,
            'shopName' => $this->shopName,
            'shopVersion' => "$shopVersion (CMS: Joomla $joomlaVersion)",
        );

        return $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($formType)
    {
        switch ($formType) {
            case 'config':
                return JRoute::_('index.php?option=com_acumulus&task=config');
            case 'advanced':
                return JRoute::_('index.php?option=com_acumulus&task=advanced');
            case 'batch':
                return JRoute::_('index.php?option=com_acumulus&task=batch');
        }
        return parent::getLink($formType);
    }
}

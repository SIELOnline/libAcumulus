<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Config;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseInterface;
use Siel\Acumulus\Config\Environment as EnvironmentBase;

/**
 * Defines common Joomla environment values for web shops running on Joomla.
 */
class Environment extends EnvironmentBase
{
    /**
     * @throws \JsonException
     */
    protected function setShopEnvironment(): void
    {
        /** @var \Joomla\CMS\Table\Extension $extension */
        $extension = ExtensionHelper::getExtensionRecord('com_acumulus', 'component');
        if ($extension !== null) {
            $componentInfo = json_decode($extension->manifest_cache, true, 512, JSON_THROW_ON_ERROR);
            $this->data['moduleVersion'] = $componentInfo['version'];
        }

        $extension = ExtensionHelper::getExtensionRecord('com_' . strtolower($this->data['shopName']), 'component');
        if ($extension !== null) {
            $componentInfo = json_decode($extension->manifest_cache, true, 512, JSON_THROW_ON_ERROR);
            $this->data['shopVersion'] = $componentInfo['version'];
        }

        $this->data['cmsName'] = Version::PRODUCT;
        $this->data['cmsVersion'] = (new Version())->getShortVersion();
    }

    protected function executeQuery(string $query): array
    {
        return Factory::getContainer()->get(DatabaseInterface::class)->setQuery($query)->loadAssocList();
    }
}

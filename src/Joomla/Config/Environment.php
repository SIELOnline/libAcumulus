<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Config;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Siel\Acumulus\Config\Environment as EnvironmentBase;

/**
 * Defines common Joomla environment values for web shops running on Joomla.
 */
class Environment extends EnvironmentBase
{
    protected function setShopEnvironment(): void
    {
        /** @var \Joomla\CMS\Table\Extension $extension */
        /** @noinspection PhpDeprecationInspection : Deprecated as of J4 */
        $extension = Table::getInstance('extension');

        $id = $extension->find(['element' => 'com_acumulus', 'type' => 'component']);
        if (!empty($id) && $extension->load($id)) {
            /** @noinspection PhpUndefinedFieldInspection */
            $componentInfo = json_decode($extension->manifest_cache, true);
            $this->data['moduleVersion'] = $componentInfo['version'];
        }

        $id = $extension->find(['element' => 'com_' . strtolower($this->data['shopName']), 'type' => 'component']);
        if (!empty($id) && $extension->load($id)) {
            /** @noinspection PhpUndefinedFieldInspection */
            $componentInfo = json_decode($extension->manifest_cache, true);
            $this->data['shopVersion'] = $componentInfo['version'];
        }

        $this->data['cmsName'] = 'Joomla';
        $this->data['cmsVersion'] = JVERSION;
    }

    protected function executeQuery(string $query): array
    {
        /** @noinspection PhpDeprecationInspection : Deprecated as of J4 */
        return Factory::getDbo()->setQuery($query)->loadAssocList();
    }
}

<?php
namespace Siel\Acumulus\Magento\Config;

use Exception;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Siel\Acumulus\Config\Environment as EnvironmentBase;
use Siel\Acumulus\Magento\Helpers\Registry;

/**
 * Defines the Magento 2 web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    /**
     * {@inheritdoc}
     */
    protected function setShopEnvironment(): void
    {
        /** @var \Magento\Framework\App\ProductMetadataInterface $productMetadata */
        $productMetadata = Registry::getInstance()->get(ProductMetadataInterface::class);
        try {
            $version = $productMetadata->getVersion();
        } catch (Exception $e) {
            // In CLI mode (php bin/magento ...) getVersion() throws an
            // exception.
            $version = 'UNKNOWN';
        }
        $this->data['moduleVersion'] = Registry::getInstance()->getModuleVersion('Siel_AcumulusMa2');
        $this->data['shopVersion'] = $version;
    }

    protected function executeQuery(string $query): array
    {
        /** @var \Magento\Framework\App\ResourceConnection $resource */
        $resource = Registry::getInstance()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        return $connection->fetchAll($query);
    }
}
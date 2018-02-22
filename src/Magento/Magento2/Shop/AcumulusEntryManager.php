<?php
namespace Siel\Acumulus\Magento\Magento2\Shop;

use Siel\Acumulus\Helpers\ContainerInterface;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Magento\Magento2\Helpers\Registry;
use Siel\Acumulus\Magento\Shop\AcumulusEntryManager as BaseAcumulusEntryManager;

/**
 * Implements Magento 2 specific methods for the acumulus entry model class.
 */
class AcumulusEntryManager extends BaseAcumulusEntryManager
{
    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container, Log $log)
    {
        parent::__construct($container, $log);
        $this->model = Registry::getInstance()->get('Siel\AcumulusMa2\Model\Entry');
    }
}

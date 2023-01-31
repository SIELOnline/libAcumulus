<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Helpers;

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\OpenCart\Helpers\OcHelper as BaseOcHelper;

use function in_array;

/**
 * OC3 specific OcHelper methods.
 */
class OcHelper extends BaseOcHelper
{
    /**
     * {@inheritDoc}
     */
    public function __construct($registry, Container $acumulusContainer)
    {
        $this->languageSettingKey = 'config_admin_language';
        parent::__construct($registry, $acumulusContainer);
    }

    /**
     * {@inheritDoc}
     */
    protected function addEvents(): void
    {
        $this->addEvent('acumulus', 'catalog/model/*/addOrder/after', 'eventOrderUpdate');
        $this->addEvent('acumulus', 'catalog/model/*/addOrderHistory/after', 'eventOrderUpdate');
        $this->addEvent('acumulus', 'admin/model/*/addOrder/after', 'eventOrderUpdate');
        $this->addEvent('acumulus', 'admin/model/*/addOrderHistory/after', 'eventOrderUpdate');
        $this->addEvent('acumulus', 'admin/view/common/column_left/before', 'eventViewColumnLeft');
        $this->addEvent('acumulus', 'admin/controller/sale/order/info/before', 'eventControllerSaleOrderInfo');
        $this->addEvent('acumulus', 'admin/view/sale/order_info/before', 'eventViewSaleOrderInfo');
    }

    /**
     * {@inheritDoc}
     */
    protected function addEvent(string $code, string $trigger, string $method, bool $status = true, int $sort_order = 0): void
    {
        /** @var \ModelSettingEvent $model */
        $model = $this->registry->getModel('setting/event');
        $model->addEvent(
            $code,
            $trigger,
            $this->registry->getRoute($method, $code),
            (int) $status,
            $sort_order,
        );
    }
}

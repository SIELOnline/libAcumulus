<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\OpenCart\Helpers\OcHelper as BaseOcHelper;

use function in_array;

/**
 * OC4 specific OcHelper methods.
 */
class OcHelper extends BaseOcHelper
{
    public function __construct($registry, Container $acumulusContainer)
    {
        $this->languageSettingKey = 'config_language_admin';
        parent::__construct($registry, $acumulusContainer);
    }

    protected function addEvents(): void
    {
        // @todo: make them less specific to catch events from other plugins as well, see phpdoc on parent.
        $this->addEvent('acumulus', 'catalog/model/checkout/order/addOrder/after', 'eventOrderUpdate');
        $this->addEvent('acumulus', 'catalog/model/checkout/order/addHistory/after', 'eventOrderUpdate');
        $this->addEvent('acumulus', 'admin/view/common/column_left/before', 'eventViewColumnLeft');
        $this->addEvent('acumulus', 'admin/controller/sale/order|info/before', 'eventControllerSaleOrderInfo');
        $this->addEvent('acumulus', 'admin/view/sale/order_info/before', 'eventViewSaleOrderInfo');
    }

    protected function addEvent(string $code, string $trigger, string $method, bool $status = true, int $sort_order = 1): void
    {
        /** @var \Opencart\Admin\Model\Setting\Event $model */
        $model = $this->registry->getModel('setting/event');
        $model->addEvent([
            'code' => $code,
            'description' => '',
            'trigger' => $trigger,
            'action' => $this->registry->getRoute($method, $code),
            'status' => $status,
            'sort_order' => $sort_order,
        ]);
    }
}

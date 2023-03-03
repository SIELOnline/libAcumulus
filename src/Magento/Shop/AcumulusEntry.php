<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Shop;

use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the Magento specific acumulus entry model class.
 *
 * This class is a bridge between the Acumulus library and the way that Magento
 * models are modelled.
 */
class AcumulusEntry extends BaseAcumulusEntry
{
    protected function get(string $field)
    {
        /** @var \Siel\AcumulusMa2\Model\Entry $entry */
        $entry = $this->getRecord();
        return $entry->getData($field);
    }
}

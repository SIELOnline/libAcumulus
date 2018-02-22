<?php
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
    /**
     * {@inheritdoc}
     */
    protected function get($field)
    {
        /** @var \Siel_Acumulus_Model_Entry|\Siel\AcumulusMa2\Model\Entry $entry */
        $entry = $this->getRecord();
        return $entry->getData($field);
    }
}

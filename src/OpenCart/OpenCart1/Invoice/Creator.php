<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Invoice;

use Siel\Acumulus\OpenCart\Invoice\Creator as BaseCreator;

/**
 * OC1 compatibility overrides.
 */
class Creator extends BaseCreator
{
    /**
     * Returns the query to get the tax class id for a given total type.
     *
     * In OC23 and lower the tax class ids for total lines are either stored
     * under:
     * - key = '{$module}_tax_class_id', e.g. flat_tax_class_id or
     *   weight_tax_class_id.
     * - key = '{$code}_tax_class_id', e.g. handling_tax_class_id
     *   or low_order_fee_tax_class_id.
     *
     * @param string $code
     *   The type of total line, e.g. shipping, handling or low_order_fee
     *
     * @return string
     *   The query to execute.
     *
     * @throws \Exception
     */
    protected function getTotalLineTaxClassLookupQuery($code)
    {
        $prefix = DB_PREFIX;
        $code = $this->getRegistry()->db->escape($code);
        $query = "SELECT `code` FROM {$prefix}extension WHERE `type` = '{$code}'";
        $records = $this->getRegistry()->db->query($query);
        $modules = [];
        foreach ($records->rows as $row) {
            $modules[] = reset($row);
        }

        if (!empty($modules)) {
            // Total line type has pluggable modules.
            $modules = "('" . implode("','", $modules) . "')";
            return "SELECT distinct `value` FROM {$prefix}setting WHERE `group` IN $modules and `key` LIKE '%_tax_class_id'";
        } else {
            // Total line type has only 1 OpenCart provided module.
            return "SELECT distinct `value` FROM {$prefix}setting WHERE `group` = '$code' and `key` = '{$code}_tax_class_id'";
        }
    }
}

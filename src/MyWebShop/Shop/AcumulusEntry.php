<?php
namespace Siel\Acumulus\MyWebShop\Shop;

use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the MyWebShop specific acumulus entry model class.
 *
 * @todo: Follow MyWebShop's table column naming scheme. So, you may have to override some of the column names.
 */
class AcumulusEntry extends BaseAcumulusEntry
{
    // @todo: remove or adapt by defining all differing column names.
    static protected $keyEntryId = 'id_entry';
}

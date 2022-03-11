<?php
namespace Siel\Acumulus\TestWebShop\Shop;

use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the TestWebShop specific acumulus entry model class.
 *
 * @todo: Follow TestWebShop's table column naming scheme. So, you may have to override some of the column names.
 */
class AcumulusEntry extends BaseAcumulusEntry
{
    // @todo: remove or adapt by defining all differing column names.
    protected static $keyEntryId = 'id_entry';
}

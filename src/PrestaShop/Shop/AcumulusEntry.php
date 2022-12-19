<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the PrestaShop specific acumulus entry model class.
 *
 * PrestaShop has its own way of naming id columns, and that is the only change.
 */
class AcumulusEntry extends BaseAcumulusEntry
{
    protected static string $keyEntryId = 'id_entry';
}

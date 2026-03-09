<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * AcumulusEntry contains WHMCS specific overrides of the base AcumulusEntry class.
 */
class AcumulusEntry extends BaseAcumulusEntry
{
    public static string $keyId = 'id';
    public static string $keyEntryId = 'entryid';
    public static string $keySourceType = 'sourcetype';
    public static string $keySourceId = 'sourceid';
    public static string $keyCreated = 'created_at';
    public static string $keyUpdated = 'updated_at';
}

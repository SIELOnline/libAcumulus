<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Shop;

use DateTimeZone;
use Joomla\CMS\Factory;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * AcumulusEntry contains Joomla specific overrides for the AcumulusEntry class.
 */
class AcumulusEntry extends BaseAcumulusEntry
{
    protected function getDefaultTimeZone(): DateTimeZone
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return new DateTimeZone(Factory::getApplication()->get('offset'));
    }
}

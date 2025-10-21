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
    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \Exception
     */
    protected function getDefaultTimeZone(): DateTimeZone
    {
        /** @noinspection NullPointerExceptionInspection */
        return new DateTimeZone(Factory::getApplication()->get('offset'));
    }
}

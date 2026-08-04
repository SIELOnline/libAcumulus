<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Helpers;

use Siel\Acumulus\Helpers\Container;

/**
 * LocalApiTrait provides access to the LocalApi class.
 */
trait LocalApiTrait
{
    protected function localApi(): LocalApi
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Container::getContainer()->getInstance('LocalApi', 'Helpers');
    }
}

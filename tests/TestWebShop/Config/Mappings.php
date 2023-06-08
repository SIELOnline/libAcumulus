<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Config;

/**
 * Mappings does foo.
 */
class Mappings extends \Siel\Acumulus\Config\Mappings
{
    /**
     * @noinspection PhpOverridingMethodVisibilityInspection  Just for testing.
     */
    public function getOverriddenValues(array $mappings, array $existingMappings): array
    {
        return parent::getOverriddenValues($mappings, $existingMappings);
    }
}

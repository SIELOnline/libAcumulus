<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Data;

use Siel\Acumulus\Data\AcumulusObject;

/**
 * ComplexTestObject contains another AcumulusObject and an array of other AcumulusObjects.
 */
class ComplexTestObject extends AcumulusObject
{
    protected function getPropertyDefinitions(): array
    {
        return [
            ['name' => 'itemNumber', 'type' => 'string'],
        ];
    }

    public SimpleTestObject $simple;
    /** @var SimpleTestObject[] */
    public array $list = [];
}

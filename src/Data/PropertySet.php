<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * PropertySet defines the possible modes to set the value of an
 * {@see AcumulusProperty}.
 *
 * @nth: PHP8.1: enumeration.
 */
interface PropertySet
{
    /**
     * Always set the property to the given value.
     */
    public const Always = 0;
    /**
     * Set the property to the given value if the property does not already have
     * a value.
     */
    public const NotOverwrite = 1;
    /**
     * Set the property to the given value if the given value is not empty.
     */
    public const NotEmpty = 2;
    /**
     * Set the property to the given value if the property does not already have
     * a value and the given value is not empty.
     */
    public const NotOverwriteNotEmpty = self::NotOverwrite | self::NotEmpty;
}

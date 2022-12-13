<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;

/**
 * A Collector collects information from the host environment to place it into
 * an {@see AcumulusObject}. Each child class of {@see AcumulusObject} will have
 * an accompanying Collector class.
 */
interface CollectorInterface
{
    /**
     * Populates and returns an {@see \Siel\Acumulus\Data\AcumulusObject}.
     *
     * - Creates an {@see \Siel\Acumulus\Data\AcumulusObject}.
     * - Populates its fields as far as possible.
     * - Adds metadata that might be used in the completor phase, or for logging
     *   or support.
     * - Returns the {@see \Siel\Acumulus\Data\AcumulusObject}.
     *
     * Values for fields may come from:
     * - A field pattern. In most cases, a field pattern will come from the
     *   module configuration. Field patterns may be as simple as a literal
     *   string; the value of 1 property from one of the property sources; to a
     *   (complex) combination of these. The {@see \Siel\Acumulus\Helpers\Token}
     *   class is typically used to compute a value given a field pattern. As
     *   this option gives a lot of flexibility to the user to override default
     *   behaviour via simple configuration, this way should, if possible, be
     *   preferred over the next one.
     * - Internal logic. If getting a value based on a field pattern may not
     *   suffice, normally when database lookups or multiple calls to the
     *   internal webshop API are required, getting the value for a field will
     *   be hardcoded in a - webshop specific - child class of this class.
     *   Think of things like looking up an ISO country code based on an
     *   internal country id, or getting a tax rate based on tax class id of the
     *   product and address data from the customer.
     *
     * @param array[] $propertySources
     *   The objects that serve as a source for property extraction. Note that
     *    the {@see \Siel\Acumulus\Helpers\Token} class can also call
     *    (parameterless) methods on the property sources.
     * @param string[] $fieldDefinitions
     *   The patterns for the fields that can be collected via a simple mapping.
     *   A pattern can be a constant value or a pattern that contains references
     *   to properties or methods defined on one of the property sources.
     *
     * @return \Siel\Acumulus\Data\AcumulusObject
     *   The AcumulusObject with its fields filled based on the
     *   $propertySources, the $fieldDefinitions, and the logic of a class
     *   implementing this interface.
     */
    public function collect(array $propertySources, array $fieldDefinitions): AcumulusObject;
}

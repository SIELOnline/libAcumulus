<?php

namespace Siel\Acumulus\Collectors;

/**
 * Collector is the base class for a collector
 */
interface CollectorInterface
{
    /**
     * Returns an {@see \Siel\Acumulus\Data\AcumulusObject}
     *
     * Creates an {@see \Siel\Acumulus\Data\AcumulusObject} by collecting
     * values for all its fields and metadata that might be added to the object.
     *
     * Values for fields may come from:
     * - A field pattern. In most cases, a field pattern will come from the
     *   module configuration. Field patterns may be as simple as a literal
     *   string; the value of 1 property from one of the property sources; to a
     *   (complex) combination of these. The {@see \Siel\Acumulus\Helpers\Token}
     *   class is used to compute a value given a field pattern. As this option
     *   gives a lot of flexibility to the user to override default behavior via
     *   simple configuration, this way should, if possible, be preferred over
     *   the next one.
     * - Internal logic. If getting a value based on a field pattern may not
     *   suffice, normally when database lookups or multiple calls to the
     *   internal webshop API are required, getting the value for a field will
     *   be hardcoded in a - webshop specific - child class of this class.
     *   Think of things like looking up an ISO country code based on an
     *   internal country id, or getting a tax rate based on tax class id of the
     *   product and address data from the customer.
     *
     * @param array $propertySources
     *   The objects to use with field mappings (token expansion).
     * @param array $fieldMappings
     *   The patterns for the fields that can be collected via a simple mapping.
     *
     * @return \Siel\Acumulus\Data\AcumulusObject
     *   The AcumulusObject with its fields filled based on the
     *   $propertySources, the $fieldMappings, and the logic of a more
     *   specialised child of this class.
     */
    public function collect(array $propertySources, array $fieldMappings): AcumulusObject;
}

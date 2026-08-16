<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

/**
 * Provides form element mapping functionality.
 *
 * This library uses its own form definitions. Some webshops or CMSs provide
 * their own form building elements. To get the form rendered, our own form
 * definition needs to be mapped to a form object or array of the web shop/CMS
 * form subsystem. This abstract base class only defines a logger property
 * and an entry point to perform the mapping
 *
 * To comply with shop-specific form building, it is supposed to be overridden
 * per shop that uses this way of form building. For now those are: Magento,
 * PrestaShop, and WHMCS.
 *
 * SECURITY REMARKS
 * ----------------
 * - A FormMapper uses the webshop's or CMS's form subsystem, and as such it may
 *   assume safe rendering is the responsibility of the CMS/web shop.
 * - However, if the form subsystem declines this responsibility, our form
 *   mapper will have to sanitise texts, values, options and such before
 *   handing them over to the form subsystem.
 * - Current webs hops that offer a form subsystem:
 *     - Magento: sanitises.
 *     - PrestaShop: sanitises.
 *     - WHMCS: unknown.
 */
abstract class FormMapper
{
    protected Log $log;

    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Maps an Acumulus form definition onto the web shop defined form elements.
     *
     * @return array|object|null
     *   A set of objects that define the web shop specific form equivalent of
     *   the Acumulus form definition. May be null if the actual rendering takes
     *   place in the mapping phase.
     */
    abstract public function map(Form $form): array|object|null;
}

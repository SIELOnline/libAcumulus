<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

/**
 * Provides form element mapping functionality.
 *
 * This library uses its own form definitions. Some web shops or CMSs provide
 * their own form building elements. To get the form rendered, our own form
 * definition needs to be mapped to a form object or array of the web shop/CMS
 * form subsystem. This abstract base class only defines a logger property
 * and an entry point to perform the mapping
 *
 * To comply with shop specific form building, it is supposed to be overridden
 * per shop that uses this way of form building. For now those are: Magento,
 * and PrestaShop.
 *
 * SECURITY REMARKS
 * ----------------
 * - A FormMapper uses the web shop's or CMS's form subsystem and as such it may
 *   assume safe rendering is the responsibility of the CMS/web shop.
 * - If however, the form sub system declines this responsibility, our form
 *   mapper will have to sanitize texts, values, options and such before
 *   handing them over to the form sub system.
 * - Current webs hops that offer a form sub system:
 *     * Magento: sanitizes.
 *     * PrestaShop: sanitizes.
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
     * @param \Siel\Acumulus\Helpers\Form $form
     *
     * @return mixed|void
     *   A set of objects that define the web shop specific form equivalent of
     *   the Acumulus form definition. May be void if the actual rendering takes
     *   place in the mapping phase.
     */
    abstract public function map(Form $form);
}

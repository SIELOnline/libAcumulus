<?php
namespace Siel\Acumulus\Helpers;

/**
 * Provides form element mapping functionality for those shops that provide
 * their own form building elements.This basic implementation only defines a
 * logger property.
 * To comply with shop specific form building, it is supposed to be overridden
 * per shop that uses this way of form building. For now those are: Magento,
 * WordPress.
 *
 * SECURITY REMARKS
 * ----------------
 * - A FormMapper uses the web shop's or CMS's form sub system and as such it
 *   may assume safe rendering is the responsibility of the CMS/web shop.
 * - If however, the form sub system declines this responsibility, our form
 *   mapper will have to sanitize texts, values, options and such  before
 *   handing them over to the form sub system.
 * - Currently supported web shops that offer a form sub system:
 *   * Magento: sanitizes.
 *   * PrestaShop: sanitizes.
 */
abstract class FormMapper
{
    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /**
     * FormMapper constructor.
     *
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Maps an Acumulus form definition onto the webshop defined form elements.
     *
     * @param \Siel\Acumulus\Helpers\Form $form
     *
     * @return array[]|void
     *   A set of objects that define the webshop specific form equivalent of
     *   the Acumulus form definition. May be void if the actual rendering takes
     *   place in the mapping phase.
     */
    abstract function map(Form $form);
}

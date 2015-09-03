<?php
namespace Siel\Acumulus\Shop;

/**
 * InjectorInterface defines an interface to retrieve:
 * - Instances of web shop specific overrides of the base classes and interfaces
 *   that are defined in the common package.
 * - Singleton instances from other namespaces.
 * - Instances that require some injection arguments in their constructor, that
 *   the implementing object can pass.
 */
interface InjectorInterface {

  /**
   * @return \Siel\Acumulus\Helpers\TranslatorInterface
   */
  public function getTranslator();

  /**
   * @return \Siel\Acumulus\Web\Service
   */
  public function getService();

  /**
   * Creates a wrapper object for a source object identified by the given
   * parameters.
   *
   * @param string $invoiceSourceType
   *   The type of the invoice source to get.
   * @param string $invoiceSourceId
   *   The id of the invoice source to get.
   *
   * @return \Siel\Acumulus\Invoice\Source
   *   A wrapper object around a shop specific invoice source object.
   */
  public function getSource($invoiceSourceType, $invoiceSourceId);

  /**
   * @return \Siel\Acumulus\Invoice\Completor
   */
  public function getCompletor();

  /**
   * @return \Siel\Acumulus\Invoice\Creator
   */
  public function getCreator();

  /**
   * @return \Siel\Acumulus\Helpers\Mailer
   */
  public function getMailer();

  /**
   * @return \Siel\Acumulus\Shop\InvoiceManager
   */
  public function getManager();

  /**
   * @return \Siel\Acumulus\Shop\AcumulusEntryModel
   */
  public function getAcumulusEntryModel();

}

<?php
namespace Siel\Acumulus\Shop;

/**
 * Represents acumulus entry records that ties orders or credit notes from the
 * web shop to entries in Acumulus.
 *
 * Acumulus identifies entries by their entry id (boekstuknummer in het
 * Nederlands). To access an entry via the API, one must also supply a token
 * that is generated based on the contents of the entry. This entry id and token
 * are stored together with an id for the order or credit note from the web
 * shop.
 *
 * Usages (not (all of them are) yet implemented):
 * - Prevent that an invoice for a given order or credit note is sent twice.
 * - Show additional information on order list screens
 * - Update payment status
 * - Resend Acumulus invoice PDF.
 */
abstract class AcumulusEntryModel {

  /**
   * Returns the Acumulus entry record for the given entry id.
   *
   * @param string $entryId
   *   the entry id to look up.
   *
   * @return array|object|null
   *   Acumulus entry record for the given entry id or null if the entry id is
   *   unknown.
   */
  abstract public function getByEntryId($entryId);

  /**
   * Returns the Acumulus entry record for the given invoice source.
   *
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   *   The source object (order, credit note) for which the invoice was created.
   *
   * @return array|object|null
   *   Acumulus entry record for the given invoice source or null if no invoice
   *   has yet been created in Acumulus for this invoice source.
   */
  public function getByInvoiceSource($invoiceSource) {
    return $this->getByInvoiceSourceId($invoiceSource->getType(), $invoiceSource->getId());
  }

  /**
   * Returns the Acumulus entry record for the given invoice source.
   *
   * @param string $invoiceSourceType
   *   The type of the invoice source
   * @param string $invoiceSourceId
   *   The id of the invoice source for which the invoice was created.
   *
   * @return array|object|null
   *   Acumulus entry record for the given invoice source or null if no invoice
   *   has yet been created in Acumulus for this invoice source.
   */
  abstract public function getByInvoiceSourceId($invoiceSourceType, $invoiceSourceId);

  /**
   * Saves the Acumulus entry for the given order in the web shop's database.
   *
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   *   The source object (order, credit note) for which the invoice was created.
   * @param $entryId
   *   The Acumulus entry Id assigned to the invoice for this order.
   * @param $token
   *   The Acumulus token to be used to access the invoice for this order via
   *   the Acumulus API.
   *
   * @return bool
   *   Success.
   */
  public function save($invoiceSource, $entryId, $token) {
    $record = $this->getByInvoiceSource($invoiceSource);
    $now = $this->sqlNow();
    if (empty($record)) {
      $this->insert($invoiceSource, $entryId, $token, $now);
    }
    else {
      $this->update($record, $entryId, $token, $now);
    }
  }

  /**
   * Returns the current time as a string valid to be used as a sql timestamp.
   *
   * @return string
   */
  abstract protected function sqlNow();

  /**
   * Inserts an Acumulus entry for the given order in the web shop's database.
   *
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   *   The source object (order, credit note) for which the invoice was created.
   * @param $entryId
   *   The Acumulus entry Id assigned to the invoice for this order.
   * @param $token
   *   The Acumulus token to be used to access the invoice for this order via
   *   the Acumulus API.
   * @param string $created
   *   The current time, to be used for the created and updated field.
   *
   * @return bool
   *   Success.
   */
  abstract protected function insert($invoiceSource, $entryId, $token, $created);

  /**
   * Updates the Acumulus entry for the given invoice source.
   *
   * @param array|object $record
   *   The existing record for the invoice source to be updated.
   * @param string $entryId
   *   The new Acumulus entry id for the invoice source.
   * @param string $token
   *   The new Acumulus token for the invoice source.
   * @param string $updated
   *   The current time, to be used for the updated field.
   *
   * @return bool
   *   Success.
   */
  abstract protected function update($record, $entryId, $token, $updated);

  /**
   * @return bool
   */
  abstract public function install();

  /**
   * @return bool
   */
  abstract public function uninstall();
}

<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Web\ConfigInterface as WebConfigInterface;

/**
 * Provides functionality to manage invoices.
 */
abstract class InvoiceManager {

  /** @var \Siel\Acumulus\Shop\Config */
  protected $config;

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator;

  /** @var \Siel\Acumulus\Invoice\Completor */
  protected $completor;

  /**
   * @param Config $config
   * @param TranslatorInterface $translator
   */
  public function __construct(Config $config, TranslatorInterface $translator) {
    $this->config = $config;

    $this->translator = $translator;
    require_once(dirname(__FILE__) . '/Translations.php');
    $translations = new Translations();
    $this->translator->add($translations);
  }

  /**
   * Helper method to translate strings.
   *
   * @param string $key
   *  The key to get a translation for.
   *
   * @return string
   *   The translation for the given key or the key itself if no translation
   *   could be found.
   */
  protected function t($key) {
    return $this->translator->get($key);
  }

  /**
   * Returns a list of invoice source types supported by this shop.
   *
   * The default implementation returns order and credit note. Override if the
   * specific shop supports other types or does not support credit notes.
   *
   * @return string[]
   *   The list of supported invoice source types.
   */
  public function getSupportedInvoiceSourceTypes() {
    return array(
      Source::Order,
      Source::CreditNote,
    );
  }

  /**
   * Returns a list of existing invoice sources for the given id range.
   *
   * @param string $invoiceSourceType
   * @param string $InvoiceSourceIdFrom
   * @param string $InvoiceSourceIdTo
   *
   * @return \Siel\Acumulus\Invoice\Source[]
   *   An array of invoice sources of the given source type.
   */
  abstract public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo);

  /**
   * Returns a list of existing invoice sources for the given reference range.
   *
   * Should be overridden when the reference is not the internal id.
   *
   * @param string $invoiceSourceType
   * @param string $InvoiceSourceReferenceFrom
   * @param string $InvoiceSourceReferenceTo
   *
   * @return \Siel\Acumulus\Invoice\Source[]
   *   An array of invoice sources of the given source type.
   */
  public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo) {
    return $this->getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo);
  }

  /**
   * Returns a list of existing invoice sources for the given date range.
   *
   * @param string $invoiceSourceType
   * @param string $dateFrom
   *   Date in yyyy-mm-dd format.
   * @param string $dateTo
   *   Date in yyyy-mm-dd format.
   *
   * @return \Siel\Acumulus\Invoice\Source[]
   *   An array of invoice sources of the given source type.
   */
  abstract public function getInvoiceSourcesByDateRange($invoiceSourceType, $dateFrom, $dateTo);

  /**
   * Sends multiple invoices to Acumulus.
   *
   * @param \Siel\Acumulus\Invoice\Source[] $invoiceSources
   * @param bool $forceSend
   * @param string[] $log
   *
   * @return bool
   *   Success.
   */
  public function sendMultiple(array $invoiceSources, $forceSend, array &$log) {
    $success = true;
    $entryModel = $this->getAcumulusEntryModel();
    $time_limit = ini_get('max_execution_time');
    /** @var Source $invoiceSource */
    foreach ($invoiceSources as $invoiceSource) {
      // Try to keep the script running, but note that other systems involved,
      // think the (Apache) web server, may have their own time-out.
      set_time_limit($time_limit);

      $status = $this->send($invoiceSource, $forceSend);
      switch ($status) {
        case WebConfigInterface::Status_Success:
          $message = 'message_batch_send_1_success';
          break;
        case WebConfigInterface::Status_Errors:
        case WebConfigInterface::Status_Exception:
          $message = 'message_batch_send_1_errors';
          $success = false;
          break;
        case WebConfigInterface::Status_Warnings:
          $message = 'message_batch_send_1_warnings';
          break;
        case WebConfigInterface::Status_NotSend:
        default:
          $message = 'message_batch_send_1_skipped';
          break;
      }
      $log[$invoiceSource->getId()] = sprintf($this->t($message), $invoiceSource->getType(), $invoiceSource->getReference());
    }
    return $success;
  }

  /**
   * Creates and sends an invoice to Acumulus for an order.
   *
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   *   The source object (order, credit note) for which the invoice was created.
   * @param bool $forceSend
   *   force sending the invoice even if an invoice has already been sent for
   *   the given order.
   *
   * @return int
   *   Status.
   */
  public function send($invoiceSource, $forceSend = false) {
    if ($forceSend || !$this->getAcumulusEntryModel()->getByInvoiceSource($invoiceSource)) {
      $invoice = $this->getCreator()->create($invoiceSource);
      $this->triggerInvoiceCreated($invoice, $invoiceSource);

      $localMessages = array();
      $invoice = $this->getCompletor()->complete($invoice, $invoiceSource, $localMessages);
      $this->triggerInvoiceCompleted($invoice, $invoiceSource);

      $service = $this->config->getService();
      $result  = $service->invoiceAdd($invoice);
      $result = $service->mergeLocalMessages($result, $localMessages);

      // Store entry id and token.
      if (!empty($result['invoice'])) {
        $this->getAcumulusEntryModel()->save($invoiceSource, $result['invoice']['entryid'], $result['invoice']['token']);
      }

      // Send a mail if there are messages.
      $messages = $service->resultToMessages($result);
      if (!empty($messages)) {
        $this->mailInvoiceAddResult($result, $messages, $invoiceSource);
      }

      return $result['status'];
    }
    return WebConfigInterface::Status_NotSend;
  }

  /**
   * Sends an email with the results of a sent invoice.
   *
   * The mail is sent to the shop administrator (emailonerror setting).
   *
   * @param array $result
   * @param array $messages
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   *
   * @return bool
   *   Success.
   */
  abstract protected function mailInvoiceAddResult(array $result, array $messages, $invoiceSource);

  /**
   * @return \Siel\Acumulus\Invoice\Completor
   */
  protected function getCompletor() {
    return $this->config->getCompletor();
  }

  /**
   * @param string $invoiceSourceType
   *   The type of the invoice source.
   * @param string $invoiceSourceId
   *   The id of the invoice source to get.
   *
   * @return \Siel\Acumulus\Invoice\Source
   */
  protected function getSource($invoiceSourceType, $invoiceSourceId) {
    return $this->config->getSource($invoiceSourceType, $invoiceSourceId);
  }

  /**
   * @return \Siel\Acumulus\Invoice\Creator
   */
  protected function getCreator() {
    return $this->config->getCreator();
  }

  /**
   * @return \Siel\Acumulus\Helpers\Mailer
   */
  protected function getMailer() {
    return $this->config->getMailer();
  }

  /**
   * @return \Siel\Acumulus\Shop\AcumulusEntryModel
   */
  protected function getAcumulusEntryModel() {
    return $this->config->getAcumulusEntryModel();
  }

  /**
   * Triggers an event that an invoice for Acumulus has been created and is
   * ready to be sent.
   *
   * This allows to inject custom behavior to alter the invoice just before
   * sending.
   *
   * @param array $invoice
   *   The invoice that has been created.
   * @param Source $invoiceSource
   *   The source object (order, credit note) for which the invoice was created.
   */
  protected function triggerInvoiceCreated(&$invoice, $invoiceSource) {
    // Default implementation: no event
  }

  /**
   * Triggers an event that an invoice for Acumulus has been created and
   * completed and is ready to be sent.
   *
   * This allows to inject custom behavior to alter the invoice just before
   * sending.
   *
   * @param array $invoice
   *   The invoice that has been created and completed.
   * @param Source $invoiceSource
   *   The source object (order, credit note) for which the invoice was created.
   */
  protected function triggerInvoiceCompleted(&$invoice, $invoiceSource) {
    // Default implementation: no event
  }

}

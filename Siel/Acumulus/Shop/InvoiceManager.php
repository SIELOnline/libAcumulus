<?php
namespace Siel\Acumulus\Shop;

use DateTime;
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
   * @param DateTime $dateFrom
   * @param DateTime $dateTo
   *
   * @return \Siel\Acumulus\Invoice\Source[]
   *   An array of invoice sources of the given source type.
   */
  abstract public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo);

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
        case WebConfigInterface::Status_NotSent:
        default:
          $message = 'message_batch_send_1_skipped';
          break;
      }
      $log[$invoiceSource->getId()] = sprintf($this->t($message), $this->t($invoiceSource->getType()), $invoiceSource->getReference());
    }
    return $success;
  }

  /**
   * Processes an invoice source status change event.
   *
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   *   The source whom status changed.
   * @param mixed $newStatus
   *   The new status of the invoice source. May be left out in which case a
   *   comparison based on trigger event and status is not performed. This is
   *   used to send credit notes to acumulus.
   *
   * @return int
   *   Status.
   */
  public function sourceStatusChange(Source $invoiceSource, $newStatus = FALSE) {
    $this->config->getLog()->debug('InvoiceManager::sourceStatusChange(%s %d, %s)', $invoiceSource->getType(), $invoiceSource->getId(), $newStatus === NULL ? 'null' : $newStatus === FALSE ? 'false' : (string) $newStatus);
    $result = WebConfigInterface::Status_NotSent;
    $shopSettings = $this->config->getShopSettings();
    if (($shopSettings['triggerInvoiceSendEvent'] == Config::TriggerInvoiceSendEvent_OrderStatus
         && $newStatus == $shopSettings['triggerOrderStatus'])
        || $newStatus === FALSE) {
      $result = $this->send($invoiceSource, FALSE);
    }
    return $result;
  }

  /**
   * Processes an invoice create event.
   *
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   *   The source for which a shop invoice was created.
   *
   * @return int
   *   Status
   */
  public function invoiceCreate(Source $invoiceSource) {
    $this->config->getLog()->debug('InvoiceManager::invoiceCreate(%s %d)', $invoiceSource->getType(), $invoiceSource->getId());
    $result = WebConfigInterface::Status_NotSent;
    $shopSettings = $this->config->getShopSettings();
    if ($shopSettings['triggerInvoiceSendEvent'] == Config::TriggerInvoiceSendEvent_InvoiceCreate) {
      $result = $this->send($invoiceSource, FALSE);
    }
    return $result;
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
  public function send(Source $invoiceSource, $forceSend = false) {
    $result = WebConfigInterface::Status_NotSent;
    if ($forceSend || !$this->getAcumulusEntryModel()->getByInvoiceSource($invoiceSource)) {
      $invoice = $this->getCreator()->create($invoiceSource);

      $this->triggerInvoiceCreated($invoice, $invoiceSource);

      if ($invoice !== NULL) {
        $localMessages = array();
        $invoice = $this->getCompletor()->complete($invoice, $invoiceSource, $localMessages);

        $this->triggerInvoiceCompleted($invoice, $invoiceSource);

        if ($invoice !== NULL) {
          $service = $this->config->getService();
          $result = $service->invoiceAdd($invoice);
          $result = $service->mergeLocalMessages($result, $localMessages);

          $this->triggerInvoiceSent($invoice, $invoiceSource, $result);

          // Check if an entryid was created and store entry id and token.
          if (!empty($result['invoice']['entryid'])) {
            $this->getAcumulusEntryModel()->save($invoiceSource, $result['invoice']['entryid'], $result['invoice']['token']);
          }

          // Send a mail if there are messages.
          $messages = $service->resultToMessages($result);
          if (!empty($messages)) {
            $this->config->getLog()->debug('InvoiceManager::send(%s %d, %s) result: %s',
              $invoiceSource->getType(), $invoiceSource->getId(), $forceSend ? 'true' : 'false', $service->messagesToText($messages));
            $this->mailInvoiceAddResult($result, $messages, $invoiceSource);
          }

          $result = $result['status'];
        }
        else {
          $this->config->getLog()->debug('InvoiceManager::send(%s %d, %s): invoiceCompleted prevented sending',
            $invoiceSource->getType(), $invoiceSource->getId(), $forceSend ? 'true' : 'false');
        }
      }
      else {
        $this->config->getLog()->debug('InvoiceManager::send(%s %d, %s): invoiceCreated prevented sending',
          $invoiceSource->getType(), $invoiceSource->getId(), $forceSend ? 'true' : 'false');
      }
    }
    else {
      $this->config->getLog()->debug('InvoiceManager::send(%s %d, %s): not sent',
        $invoiceSource->getType(), $invoiceSource->getId(), $forceSend ? 'true' : 'false');
    }
    return $result;
  }

  /**
   * Sends an email with the results of a sent invoice.
   *
   * The mail is sent to the shop administrator (emailonerror setting).
   *
   * @param array $result
   * @param string[] $messages
   * @param \Siel\Acumulus\Invoice\Source $invoiceSource
   *
   * @return bool
   *   Success.
   */
  protected function mailInvoiceAddResult(array $result, array $messages, Source $invoiceSource) {
    $mailer = $this->getMailer();
    return $mailer->sendInvoiceAddMailResult($result, $messages, $invoiceSource->getType(), $invoiceSource->getReference());
  }

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
  protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource) {
    // Default implementation: no event.
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
  protected function triggerInvoiceCompleted(array &$invoice, Source $invoiceSource) {
    // Default implementation: no event.
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
   * @param array $result
   *   The result as sent back by Acumulus. This array contains the following
   *   keys:
   *   - invoice: array
   *     - invoicenumber: string
   *     - token: string
   *     - entryid: string
   *   - errors: array
   *     - error: array
   *       - code: string
   *       - codetag: string
   *       - message: string
   *     - counterrors: int
   *   - warnings: array
   *     - warning: array
   *       - code: string
   *       - codetag: string
   *       - message: string
   *     - countwarnings: int
   */
  protected function triggerInvoiceSent(array $invoice, Source $invoiceSource, array $result) {
    // Default implementation: no event.
  }

  protected function getIsoDate(DateTime $date) {
    return $date->format('Y-m-d H:i:s');
  }
}

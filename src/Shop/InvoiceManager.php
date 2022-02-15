<?php
namespace Siel\Acumulus\Shop;

use DateTime;
use Exception;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;
use Siel\Acumulus\Helpers\Severity;

/**
 * Provides functionality to manage invoices.
 *
 * The features of this class include:
 * - Retrieval of webshop invoice sources (orders or refunds).
 * - Handle order status changes.
 * - Handle refund creation or credit memo sending.
 * - Handle batch sending
 * - Create and send an invoice to Acumulus for a given invoice source,
 *   including triggering our own events and processing the result.
 */
abstract class InvoiceManager
{
    /** @var \Siel\Acumulus\Helpers\Container */
    protected $container;

    /**
     * @param \Siel\Acumulus\Helpers\Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
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
    protected function t($key)
    {
        return $this->getTranslator()->get($key);
    }

    /**
     * Returns a Translator instance.
     *
     * @return \Siel\Acumulus\Helpers\Translator
     */
    protected function getTranslator()
    {
        return $this->container->getTranslator();
    }

    /**
     * Returns a Logger instance.
     *
     * @return \Siel\Acumulus\Helpers\Log
     */
    protected function getLog()
    {
        return $this->container->getLog();
    }

    /**
     * Returns a Config instance.
     *
     * @return \Siel\Acumulus\Config\Config
     */
    protected function getConfig()
    {
        return $this->container->getConfig();
    }

    /**
     * Returns an AcumulusEntryManager instance.
     *
     * @return \Siel\Acumulus\Shop\AcumulusEntryManager
     */
    protected function getAcumulusEntryManager()
    {
        return $this->container->getAcumulusEntryManager();
    }

    /**
     * Returns an Acumulus api-client instance.
     *
     * @return \Siel\Acumulus\ApiClient\Acumulus
     */
    protected function getAcumulusApiClient()
    {
        return $this->container->getAcumulusApiClient();
    }

    /**
     * Returns a Mailer instance.
     *
     * @return \Siel\Acumulus\Helpers\Mailer
     */
    protected function getMailer()
    {
        return $this->container->getMailer();
    }


    /**
     * Returns a new Source instance.
     *
     * @param string $invoiceSourceType
     * @param int|object|array $idOrSource
     *
     * @return \Siel\Acumulus\Invoice\Source
     */
    protected function getSource($invoiceSourceType, $idOrSource)
    {
        return $this->container->getSource($invoiceSourceType, $idOrSource);
    }
    /**
     * Returns a Creator instance.
     *
     * @return \Siel\Acumulus\Invoice\Creator
     */
    protected function getCreator()
    {
        return $this->container->getCreator();
    }

    /**
     * Returns a Completor instance.
     *
     * @return \Siel\Acumulus\Invoice\Completor
     */
    protected function getCompletor()
    {
        return $this->container->getCompletor();
    }

    /**
     * Returns a result instance.
     *
     * @param string $trigger
     *
     * @return \Siel\Acumulus\Invoice\Result
     */
    protected function getInvoiceResult($trigger)
    {
        return $this->container->getInvoiceResult($trigger);
    }

    /**
     * Indicates if we are in test mode.
     *
     * @return bool
     *   True if we ar ein test mode, false otherwise
     */
    protected function isTestMode()
    {
        $pluginSettings = $this->getConfig()->getPluginSettings();

        return $pluginSettings['debug'] == Config::Send_TestMode;
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
     * @param string $invoiceSourceReferenceFrom
     * @param string $invoiceSourceReferenceTo
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo)
    {
        return $this->getInvoiceSourcesByIdRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo);
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
     * Creates a set of Invoice Sources given their ids or shop specific sources.
     *
     * @param string $invoiceSourceType
     * @param array $idsOrSources
     *   An array with shop specific orders or credit notes or just their ids.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   A non keyed array with invoice Sources.
     */
    public function getSourcesByIdsOrSources($invoiceSourceType, array $idsOrSources)
    {
        $results = [];
        foreach ($idsOrSources as $sourceId) {
            $results[] = $this->getSourceByIdOrSource($invoiceSourceType, $sourceId);
        }
        return $results;
    }

    /**
     * Creates a source given its type and id.
     *
     * @param string $invoiceSourceType
     * @param int|array|object $idOrSource
     *   A shop specific order or credit note or just its ids.
     *
     * @return \Siel\Acumulus\Invoice\Source
     *   An invoice Source.
     */
    protected function getSourceByIdOrSource($invoiceSourceType, $idOrSource)
    {
        return $this->getSource($invoiceSourceType, $idOrSource);
    }

    /**
     * Sends multiple invoices to Acumulus.
     *
     * @param \Siel\Acumulus\Invoice\Source[] $invoiceSources
     * @param bool $forceSend
     *   If true, force sending the invoices even if an invoice has already been
     *   sent for a given invoice source.
     * @param bool $dryRun
     *   If true, return the reason/status only but do not actually send the
     *   invoice, nor mail the result or store the result.
     * @param string[] $log
     *
     * @return bool
     *   Success.
     */
    public function sendMultiple(array $invoiceSources, $forceSend, $dryRun, array &$log)
    {
        $errorLogged = false;
        $success = true;
        $time_limit = ini_get('max_execution_time');
        foreach ($invoiceSources as $invoiceSource) {
            // Try to keep the script running, but note that other systems
            // involved, like the (Apache) web server, may have their own
            // time-out. Use @ to prevent messages like "Warning:
            // set_time_limit(): Cannot set max execution time limit due to
            // system policy in ...".
            if (!@set_time_limit($time_limit) && !$errorLogged) {
                $this->getLog()->warning('InvoiceManager::sendMultiple(): could not set time limit.');
                $errorLogged = true;
            }

            $result = $this->getInvoiceResult('InvoiceManager::sendMultiple()');
            $result = $this->createAndSend($invoiceSource, $result, $forceSend, $dryRun);
            $success = $success && !$result->hasError();
            $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
            // @todo: we are kind of loosing error messages here.
            $log[$invoiceSource->getId()] = $this->getSendResultLogText($invoiceSource, $result,Result::AddReqResp_Never);
        }
        return $success;
    }
    /**
     * Sends 1 invoice to Acumulus.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The invoice source to send the invoice for.
     * @param bool $forceSend
     *   If true, force sending the invoices even if an invoice has already been
     *   sent for a given invoice source.
     *
     * @return bool
     *   Success.
     */
    public function send1(Source $invoiceSource, $forceSend)
    {
        $result = $this->getInvoiceResult('InvoiceManager::send1()');
        $result = $this->createAndSend($invoiceSource, $result, $forceSend);
        $success = !$result->hasError();
        $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
        // @todo: we are loosing error messages here.
        return $success;
    }

    /**
     * Processes an invoice source status change event.
     *
     * For now, we don't look at credit note statuses, they are always sent.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source whose status has changed.
     *
     * @return \Siel\Acumulus\Invoice\Result
     *   The result of sending (or not sending) the invoice.
     */
    public function sourceStatusChange(Source $invoiceSource)
    {
        $result = $this->getInvoiceResult('InvoiceManager::sourceStatusChange()');
        $status = $invoiceSource->getStatus();
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($invoiceSource->getType() === Source::Order) {
            $doSend = in_array($status, $shopEventSettings['triggerOrderStatus']);
            $arguments = [$status, implode(',', $shopEventSettings['triggerOrderStatus'])];
            $notSendReason = Result::NotSent_WrongStatus;
        } else {
            $doSend = $shopEventSettings['triggerCreditNoteEvent'] === Config::TriggerCreditNoteEvent_Create;
            $arguments = [];
            $notSendReason = Result::NotSent_TriggerCreditNoteEventNotEnabled;
        }
        if ($doSend) {
            $result = $this->createAndSend($invoiceSource, $result);
            // Add argument to send status, this will add the current status and
            // the set of statuses on which to send to the log line.
            $result->setSendStatus($result->getSendStatus(), $arguments);
        } else {
            $result->setSendStatus($notSendReason, $arguments);
        }
        $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
        return $result;
    }

    /**
     * Processes an invoice create event.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source for which a shop invoice was created.
     *
     * @return \Siel\Acumulus\Invoice\Result
     *   The result of sending (or not sending) the invoice.
     *
     * @noinspection PhpUnused
     */
    public function invoiceCreate(Source $invoiceSource)
    {
        $result = $this->getInvoiceResult('InvoiceManager::invoiceCreate()');
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] == Config::TriggerInvoiceEvent_Create) {
            $result = $this->createAndSend($invoiceSource, $result);
        } else {
            $result->setSendStatus(Result::NotSent_TriggerInvoiceCreateNotEnabled);
        }
        $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
        return $result;
    }

    /**
     * Processes a shop invoice send event.
     *
     * This is the invoice created by the shop and that is now sent/mailed to
     * the customer.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source for which a shop invoice was created.
     *
     * @return \Siel\Acumulus\Invoice\Result
     *   The result of sending (or not sending) the invoice.
     *
     * @noinspection PhpUnused
     */
    public function invoiceSend(Source $invoiceSource)
    {
        $result = $this->getInvoiceResult('InvoiceManager::invoiceSend()');
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] == Config::TriggerInvoiceEvent_Send) {
            $result = $this->createAndSend($invoiceSource, $result);
        } else {
            $result->setSendStatus(Result::NotSent_TriggerInvoiceSentNotEnabled);
        }
        $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
        return $result;
    }

    /**
     * Creates and sends an invoice to Acumulus for an order.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was
     *   created.
     * @param \Siel\Acumulus\Invoice\Result $result
     * @param bool $forceSend
     *   If true, force sending the invoice even if an invoice has already been
     *   sent for the given invoice source.
     * @param bool $dryRun
     *   If true, return the reason/status only but do not actually send the
     *   invoice, nor mail the result or store the result.
     *
     * @return \Siel\Acumulus\Invoice\Result
     *   The result of sending (or not sending) the invoice.
     */
    protected function createAndSend(Source $invoiceSource, Result $result, $forceSend = false, $dryRun = false)
    {
        if ($this->isTestMode()) {
            $result->setSendStatus(Result::Send_TestMode);
        } elseif (($acumulusEntry = $this->getAcumulusEntryManager()->getByInvoiceSource($invoiceSource, false)) === null) {
            $result->setSendStatus(Result::Send_New);
        } elseif ($forceSend) {
            $result->setSendStatus(Result::Send_Forced);
        } elseif ($acumulusEntry->hasLockExpired()) {
            $result->setSendStatus(Result::Send_LockExpired);
        } elseif ($acumulusEntry->isSendLock()) {
            $result->setSendStatus(Result::NotSent_LockedForSending);
        } else {
            $result->setSendStatus(Result::NotSent_AlreadySent);
        }

        if (($result->getSendStatus() & Result::Send_Mask) !== 0) {
            $invoice = $this->getCreator()->create($invoiceSource);

            // Do not send 0-amount invoices, if set so.
            $shopEventSettings = $this->getConfig()->getShopEventSettings();
            if ($shopEventSettings['sendEmptyInvoice'] || !$this->isEmptyInvoice($invoice)) {
                // Trigger the InvoiceCreated event.
                $this->triggerInvoiceCreated($invoice, $invoiceSource, $result);

                // If the invoice is not set to null, we continue by completing it.
                if ($invoice !== null) {
                    // @todo: handle verification errors here. Currently they
                    //  get severity Error, should perhaps become Exception.
                    $invoice = $this->getCompletor()->complete($invoice, $invoiceSource, $result);

                    // Trigger the InvoiceCompleted event.
                    $this->triggerInvoiceSendBefore($invoice, $invoiceSource, $result);

                    // If the invoice is not set to null, we continue by sending it.
                    if ($invoice !== null) {
                        if (!$result->hasError()) {
                            if (!$dryRun) {
                                $result = $this->lockAndSend($invoice, $invoiceSource, $result);
                            } else {
                                $result->setSendStatus(Result::NotSent_DryRun);
                            }
                        } else {
                            $result->setSendStatus(Result::NotSent_LocalErrors);
                        }
                    } else {
                        $result->setSendStatus(Result::NotSent_EventInvoiceCompleted);
                    }
                } else {
                    $result->setSendStatus(Result::NotSent_EventInvoiceCreated);
                }
            } else {
                $result->setSendStatus(Result::NotSent_EmptyInvoice);
            }
        }

        return $result;
    }

    /**
     * Locks, if needed, the invoice for sending and, if acquired, sends it.
     *
     * NOTE: the mechanism used to lock and verify if we got the lock is not
     * atomic, nor foolproof for all possible situations. However, it is a
     * relatively easy to understand solution that will catch 99,9% of the
     * situations. If double sending still occurs, some warning mechanisms are
     * built in (were already built in) to delete one of the entries in Acumulus
     * and warn the user.
     *
     * After sending the invoice:
     * - The invoice sent event gets triggered.
     * - A mail with the results may be sent.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     * @param array $invoice
     * @param \Siel\Acumulus\Invoice\Result $result
     *
     * @return \Siel\Acumulus\Invoice\Result
     *   The result structure of the invoice add API call merged with any local
     *   messages.
     */
    protected function lockAndSend(array $invoice, Source $invoiceSource, Result $result)
    {
        $doLock = !$this->isTestMode() && in_array($result->getSendStatus(), [Result::Send_New, Result::Send_LockExpired]);

        if ($doLock) {
            // Check if we may expect an expired lock and, if so, remove it.
            if ($result->getSendStatus() === Result::Send_LockExpired) {
                $lockStatus = $this->getAcumulusEntryManager()->deleteLock($invoiceSource);
                if ($lockStatus === AcumulusEntry::Lock_BecameRealEntry) {
                    // Bail out: invoice already sent after all.
                    return $result->setSendStatus(Result::NotSent_AlreadySent);
                }
            }

            // Acquire lock.
            if (!$this->getAcumulusEntryManager()->lockForSending($invoiceSource)) {
                // Bail out: Lock not acquired.
                return $result->setSendStatus(Result::NotSent_LockedForSending);
            }
        }

        try {
            $result = $this->doSend($invoice, $invoiceSource, $result);
        } catch (Exception $e) {
            $result->addMessage($e);
        }

        // When everything went well, the lock will have been replaced by a real
        // entry. So we only delete the lock in case of errors.
        if ($doLock && $result->hasError()) {
            // deleteLock() is expected to return AcumulusEntry::Lock_Deleted,
            // so we don't act on that return status. With any of the other
            // statuses it is unclear what happened and what the status will be
            // in Acumulus: tell user to check.
            if (($lockStatus = $this->getAcumulusEntryManager()->deleteLock($invoiceSource)) !== AcumulusEntry::Lock_Deleted) {
                $code = $lockStatus === AcumulusEntry::Lock_NoLongerExists ? 903 : 904;
                $result->addMessage(
                    sprintf($this->t('message_warning_delete_lock_failed'), $this->t($invoiceSource->getType())),
                    Severity::Warning, '', $code
                );
            }
        }

        // Trigger the InvoiceSent event.
        $this->triggerInvoiceSendAfter($invoice, $invoiceSource, $result);

        // Send a mail if there are messages.
        $this->mailInvoiceAddResult($result, $invoiceSource);

        return $result;
    }

    /**
     * Unconditionally sends the invoice and update the Acumulus entries table.
     *
     * After sending the invoice:
     * - A successful result gets saved to the acumulus entries table.
     * - If an older submission exists, it will be deleted from Acumulus.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     * @param array $invoice
     * @param \Siel\Acumulus\Invoice\Result $result
     *
     * @return \Siel\Acumulus\Invoice\Result
     *   The result structure of the invoice add API call merged with any local
     *   messages.
     */
    protected function doSend(array $invoice, Source $invoiceSource, Result $result)
    {
        /** @var \Siel\Acumulus\Invoice\Result $result */
        $result = $this->getAcumulusApiClient()->invoiceAdd($invoice, $result);// Store the reference between the source of the webshop invoice and the

        // Save Acumulus entry:
        // - If we were sending in test mode or there were errors, no invoice
        //   will have been created in Acumulus: nothing to store.
        // - If the invoice was sent as a concept, the entry id and token will
        //   be empty, but we will receive a concept id and store that instead.
        if (!$this->isTestMode() && !$result->hasError()) {
            // If we are going to overwrite an existing entry, we want to delete
            // that from Acumulus.
            $acumulusEntryManager = $this->getAcumulusEntryManager();
            $oldEntry = $acumulusEntryManager->getByInvoiceSource($invoiceSource, true);

            $response = $result->getResponse();
            if (!empty($response['token']) && !empty('entryid')) {
                // A real entry.
                $token = $response['token'];
                $id = $response['entryid'];
            } elseif (!empty($response['conceptid'])) {
                // A concept.
                $token = null;
                $id = $response['conceptid'];
            } else {
                // An error (or old API version).
                $token = null;
                $id = null;
            }
            $saved = $acumulusEntryManager->save($invoiceSource, $id, $token);

            // If we successfully saved the new entry, we may delete the old one
            // if there is one and it's not a concept.
            if ($saved && $oldEntry && $oldEntry->getEntryId()) {
                $entryId = $oldEntry->getEntryId();
                // @todo: clean up on receiving P2XFELO12?
                $deleteResult = $this->getAcumulusApiClient()->setDeleteStatus($entryId, API::Entry_Delete);
                if ($deleteResult->hasRealMessages()) {
                    // Add messages to result but not if the entry has already
                    // the delete status or does not exist at all (anymore).
                    if ($deleteResult->getByCodeTag('P2XFELO12')) {
                        // Could not delete the old entry (already deleted or
                        // does not exist at all (anymore)): add as a warning so
                        // this info will be mailed to the user.
                        $result->addMessage(
                            sprintf($this->t('message_warning_old_entry_not_deleted'), $this->t($invoiceSource->getType()), $entryId),
                            Severity::Warning, '', 902
                        );
                    } else {
                        $result->addMessages($deleteResult->getMessages(Severity::InfoOrWorse), true);
                    }
                } else {
                    // Successfully deleted the old entry: add a notice so this
                    // info will be mailed to the user.
                    $result->addMessage(
                        sprintf($this->t('message_warning_old_entry_deleted'), $this->t($invoiceSource->getType()), $entryId),
                        Severity::Notice, '', 901
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Sends an email with the results of a sent invoice.
     *
     * The mail is sent to the shop administrator (emailonerror setting).
     *
     * @param \Siel\Acumulus\Invoice\Result $result
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *
     * @return bool
     *   Success.
     */
    protected function mailInvoiceAddResult(Result $result, Source $invoiceSource)
    {
        $pluginSettings = $this->getConfig()->getPluginSettings();
        $addReqResp = $pluginSettings['debug'] === Config::Send_SendAndMailOnError ? Result::AddReqResp_WithOther : Result::AddReqResp_Always;
        if ($addReqResp === Result::AddReqResp_Always || ($addReqResp === Result::AddReqResp_WithOther && $result->hasRealMessages())) {
            return $this->getMailer()->sendInvoiceAddMailResult($result, $invoiceSource->getType(), $invoiceSource->getReference());
        }
        return true;
    }

    /**
     * Returns whether an invoice is empty (free products only).
     *
     * @param array $invoice
     *
     * @return bool
     *   True if the invoice amount (inc. VAT) is €0,-.
     */
    protected function isEmptyInvoice(array $invoice)
    {
        return Number::isZero($invoice[Tag::Customer][Tag::Invoice][Meta::InvoiceAmountInc]);
    }

    /**
     * Triggers an event that an invoice for Acumulus has been created and is
     * ready to be completed and sent.
     *
     * This allows to inject custom behavior to alter the invoice just before
     * completing and sending.
     *
     * It is not advised to use this event, use the invoice completed event
     * instead. Main difference is that with this event the invoice is still in
     * quite a raw state, while with the invoice completed event the invoice is
     * as it will be sent. A valid reason to use this event after all, could be
     * to correct/complete it prior to the strategy completor phase that may
     * complete some invoices in a bogus way.
     *
     * @param array $invoice
     *   The invoice that has been created.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was created.
     * @param \Siel\Acumulus\Invoice\Result $localResult
     *   Any locally generated messages.
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource, Result $localResult)
    {
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
     * @param \Siel\Acumulus\Invoice\Result $localResult
     *   Any locally generated messages.
     */
    protected function triggerInvoiceSendBefore(array &$invoice, Source $invoiceSource, Result $localResult)
    {
        // Default implementation: no event.
    }

    /**
     * Triggers an event after an invoice for Acumulus has been sent.
     *
     * This allows to inject custom behavior to react to invoice sending.
     *
     * @param array $invoice
     *   The invoice that has been sent.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was sent.
     * @param \Siel\Acumulus\Invoice\Result $result
     *   The result as sent back by Acumulus.
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, Result $result)
    {
        // Default implementation: no event.
    }

    /**
     * Returns the given DateTime in a format that the actual database layer
     * accepts for comparison in a SELECT query.
     *
     * This default implementation returns the DateTime as a string in ISO format
     * (yyyy-mm-dd hh:mm:ss).
     *
     * @param \DateTime $date
     *
     * @return string
     */
    protected function getSqlDate(DateTime $date)
    {
        return $date->format(Api::Format_TimeStamp);
    }

    /**
     * Helper method to retrieve the values from 1 column of a query result.
     *
     * @param array $dbResults
     * @param string $key
     *
     * @return int[]
     */
    protected function getCol(array $dbResults, $key)
    {
        $results = [];
        foreach ($dbResults as $dbResult) {
            $results[] = (int) $dbResult[$key];
        }
        return $results;
    }

    /**
     * Returns a string that details the result of the invoice sending.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     * @param \Siel\Acumulus\Invoice\Result $result
     *
     * @param int $addReqResp
     *   Whether to add the raw request and response.
     *   One of the Result::AddReqResp_... constants
     *
     * @return string
     */
    protected function getSendResultLogText(Source $invoiceSource, Result $result, $addReqResp = Result::AddReqResp_WithOther)
    {
        $invoiceSourceText = sprintf($this->t('message_invoice_source'),
            $this->t($invoiceSource->getType()),
            $invoiceSource->getReference()
        );

        return sprintf($this->t('message_invoice_send'),
            $result->getTrigger(),
            $invoiceSourceText,
            $result->getLogText($addReqResp)
        );
    }
}

<?php
/**
 * @noinspection EfferentObjectCouplingInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use DateTime;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Mailer;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\Creator;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;
use Siel\Acumulus\Helpers\Severity;

use function count;
use function in_array;

/**
 * Provides functionality to manage invoices.
 *
 * The features of this class include:
 * - Retrieval of web shop invoice sources (orders or refunds).
 * - Handle order status changes.
 * - Handle refund creation or credit memo sending.
 * - Handle batch sending
 * - Create and send an invoice to Acumulus for a given invoice source,
 *   including triggering our own events and processing the result.
 */
abstract class InvoiceManager
{
    protected Container $container;

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
    protected function t(string $key): string
    {
        return $this->getTranslator()->get($key);
    }

    protected function getTranslator(): Translator
    {
        return $this->container->getTranslator();
    }

    protected function getLog(): Log
    {
        return $this->container->getLog();
    }

    protected function getConfig(): Config
    {
        return $this->container->getConfig();
    }

    protected function getShopCapabilities(): ShopCapabilities
    {
        return $this->container->getShopCapabilities();
    }

    protected function getAcumulusEntryManager(): AcumulusEntryManager
    {
        return $this->container->getAcumulusEntryManager();
    }

    protected function getAcumulusApiClient(): Acumulus
    {
        return $this->container->getAcumulusApiClient();
    }

    protected function getMailer(): Mailer
    {
        return $this->container->getMailer();
    }

    /**
     * Returns a new Source instance.
     *
     * @param int|object|array $idOrSource
     */
    protected function getSource(string $invoiceSourceType, $idOrSource): Source
    {
        return $this->container->createSource($invoiceSourceType, $idOrSource);
    }

    protected function getCreator(): Creator
    {
        // @todo: switch to Collect/Complete
        return $this->container->getCreator();
    }

    protected function getCompletor(): Completor
    {
        return $this->container->getCompletor();
    }

    protected function getInvoiceCreate(): InvoiceCreate
    {
       return $this->container->getInvoiceCreate();
    }

    protected function getInvoiceSend(): InvoiceSend
    {
        return $this->container->getInvoiceSend();
    }

    /**
     * Returns a result instance.
     *
     * @param string $trigger
     *   A human-readable text explaining the reason why this invoice should or
     *   should not be sent.
     */
    protected function createInvoiceAddResult(string $trigger): InvoiceAddResult
    {
        return $this->container->createInvoiceAddResult($trigger);
    }

    /**
     * Indicates if we are in test mode.
     *
     * @return bool
     *   True if we are in test mode, false otherwise.
     */
    protected function isTestMode(): bool
    {
        $pluginSettings = $this->getConfig()->getPluginSettings();

        return $pluginSettings['debug'] === Config::Send_TestMode;
    }

    /**
     * Returns a list of existing invoice sources for the given id range.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    abstract public function getInvoiceSourcesByIdRange(
        string $invoiceSourceType,
        int $invoiceSourceIdFrom,
        int $invoiceSourceIdTo
    ): array;

    /**
     * Returns a list of existing invoice sources for the given reference range.
     * Should be overridden when the reference is not the internal id.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    public function getInvoiceSourcesByReferenceRange(
        string $invoiceSourceType,
        string $invoiceSourceReferenceFrom,
        string $invoiceSourceReferenceTo
    ): array {
        return $this->getInvoiceSourcesByIdRange($invoiceSourceType, (int) $invoiceSourceReferenceFrom, (int) $invoiceSourceReferenceTo);
    }

    /**
     * Returns a list of existing invoice sources for the given date range.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    abstract public function getInvoiceSourcesByDateRange(
        string $invoiceSourceType,
        DateTime $dateFrom,
        DateTime $dateTo
    ): array;

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
    public function getSourcesByIdsOrSources(string $invoiceSourceType, array $idsOrSources): array
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
     * @param int|array|object $idOrSource
     *   A shop specific order or credit note or just its ids.
     *
     * @return \Siel\Acumulus\Invoice\Source
     *   An invoice Source.
     */
    protected function getSourceByIdOrSource(string $invoiceSourceType, $idOrSource): Source
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
     *   An array to add a (human-readable) send result per invoice sent to.
     *
     * @return bool
     *   Success.
     *
     * @todo: change parameter $forceSend to an int: the 3 options of the batch form field 'send_mode'.
     */
    public function sendMultiple(array $invoiceSources, bool $forceSend, bool $dryRun, array &$log): bool
    {
        $canResetTimer = true;
        $success = true;
        $time_limit = ini_get('max_execution_time');
        foreach ($invoiceSources as $invoiceSource) {
            // Try to keep the script running, but note that other systems
            // involved, like the (Apache) web server, may have their own
            // time-out.
            if ($canResetTimer && !ini_set('max_execution_time', $time_limit)) {
                $this->getLog()->warning('InvoiceManager::sendMultiple(): could not set time limit.');
                $canResetTimer = false;
            }

            $result = $this->createInvoiceAddResult('InvoiceManager::sendMultiple()');
            $result = $this->createAndSend($invoiceSource, $result, $forceSend, $dryRun);
            $success = $success && !$result->hasError();
            $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
            $log[$invoiceSource->getId()] = $this->getSendResultLogText($invoiceSource, $result,InvoiceAddResult::AddReqResp_Never);
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
     * @return InvoiceAddResult
     *   The InvoiceAddResult of sending the invoice for this Source to Acumulus.
     */
    public function send1(Source $invoiceSource, bool $forceSend): InvoiceAddResult
    {
        $result = $this->createInvoiceAddResult('InvoiceManager::send1()');
        $result = $this->createAndSend($invoiceSource, $result, $forceSend);
        $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
        return $result;
    }

    /**
     * Processes an invoice source status change event.
     *
     * For now, we don't look at credit note statuses, they are always sent.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source whose status has changed.
     *
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   The result of sending (or not sending) the invoice.
     */
    public function sourceStatusChange(Source $invoiceSource): InvoiceAddResult
    {
        $result = $this->createInvoiceAddResult('InvoiceManager::sourceStatusChange()');
        $status = $invoiceSource->getStatus();
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($invoiceSource->getType() === Source::Order) {
            // Set $arguments, this will add the current status and the set of
            // statuses on which to send to the log line.
            $arguments = [$status, implode(',', $shopEventSettings['triggerOrderStatus'])];
            $sendStatus = in_array($status, $shopEventSettings['triggerOrderStatus'], false)
                ? InvoiceAddResult::SendStatus_Unknown
                : InvoiceAddResult::NotSent_WrongStatus;
        } else {
            $arguments = [];
            $sendStatus = $shopEventSettings['triggerCreditNoteEvent'] === Config::TriggerCreditNoteEvent_Create
                ? InvoiceAddResult::SendStatus_Unknown
                : InvoiceAddResult::NotSent_TriggerCreditNoteEventNotEnabled;
        }
        if ($sendStatus === InvoiceAddResult::SendStatus_Unknown) {
            $result = $this->createAndSend($invoiceSource, $result);
            $sendStatus = $result->getSendStatus();
        }
        $result->setSendStatus($sendStatus, $arguments);
        $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
        return $result;
    }

    /**
     * Processes an invoice create event.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source for which a shop invoice was created.
     *
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   The result of sending (or not sending) the invoice.
     *
     * @noinspection PhpUnused
     */
    public function invoiceCreate(Source $invoiceSource): InvoiceAddResult
    {
        $result = $this->createInvoiceAddResult('InvoiceManager::invoiceCreate()');
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] === Config::TriggerInvoiceEvent_Create) {
            $result = $this->createAndSend($invoiceSource, $result);
        } else {
            $result->setSendStatus(InvoiceAddResult::NotSent_TriggerInvoiceCreateNotEnabled);
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
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   The result of sending (or not sending) the invoice.
     *
     * @noinspection PhpUnused
     */
    public function invoiceSend(Source $invoiceSource): InvoiceAddResult
    {
        $result = $this->createInvoiceAddResult('InvoiceManager::invoiceSend()');
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] === Config::TriggerInvoiceEvent_Send) {
            $result = $this->createAndSend($invoiceSource, $result);
        } else {
            $result->setSendStatus(InvoiceAddResult::NotSent_TriggerInvoiceSentNotEnabled);
        }
        $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
        return $result;
    }

    protected function createAndSend(
        Source $invoiceSource,
        InvoiceAddResult $result,
        bool $forceSend = false,
        bool $dryRun = false
    ): InvoiceAddResult
    {
        if ($this->getShopCapabilities()->usesNewCode()) {
            $this->createAndSendNew($invoiceSource, $result, $forceSend, $dryRun);
            return $result;
        } else {
            return $this->createAndSendLegacy($invoiceSource, $result, $forceSend, $dryRun);
        }
    }

    protected function createAndSendNew(
        Source $invoiceSource,
        InvoiceAddResult $result,
        bool $forceSend = false,
        bool $dryRun = false
    ): void {
        $this->getInvoiceSend()->setBasicSendStatus($invoiceSource, $result, $forceSend);
        $invoice = $this->getInvoiceCreate()->create($invoiceSource, $result);
        if ($invoice !== null && !$result->isSendingPrevented()) {
            $this->getInvoiceSend()->send($invoice, $invoiceSource, $result, $dryRun);
        }
    }

    /**
     * Creates and sends an invoice to Acumulus for an order.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was
     *   created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $result
     * @param bool $forceSend
     *   If true, force sending the invoice even if an invoice has already been
     *   sent for the given invoice source.
     * @param bool $dryRun
     *   If true, return the reason/status only but do not actually send the
     *   invoice, nor mail the result or store the result.
     *
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   The result of sending (or not sending) the invoice.
     *
     * @legacy: old way of creating and sending invoices.
     */
    protected function createAndSendLegacy(
        Source $invoiceSource,
        InvoiceAddResult $result,
        bool $forceSend = false,
        bool $dryRun = false
    ): InvoiceAddResult {
        // Get the basic reason for sending or not sending.
        if ($this->isTestMode()) {
            $result->setSendStatus(InvoiceAddResult::Sent_TestMode);
        } elseif (($acumulusEntry = $this->getAcumulusEntryManager()->getByInvoiceSource($invoiceSource, false)) === null) {
            $result->setSendStatus(InvoiceAddResult::Sent_New);
        } elseif ($forceSend) {
            $result->setSendStatus(InvoiceAddResult::Sent_Forced);
        } elseif ($acumulusEntry->hasLockExpired()) {
            $result->setSendStatus(InvoiceAddResult::Sent_LockExpired);
        } elseif ($acumulusEntry->isSendLock()) {
            return $result->setSendStatus(InvoiceAddResult::NotSent_AlreadyLocked);
        } else {
            return $result->setSendStatus(InvoiceAddResult::NotSent_AlreadySent);
        }

        // Create the raw invoice.
        $invoice = $this->getCreator()->create($invoiceSource);
        $this->triggerInvoiceCreated($invoice, $invoiceSource, $result);
        // If the invoice is set to null, we do not send it.
        if ($invoice === null) {
            return $result->setSendStatus(InvoiceAddResult::NotSent_EventInvoiceCreateAfter);
        }

        // @todo: handle verification errors here. Currently, they
        //   get severity Error, should perhaps become Exception.
        $invoice = $this->getCompletor()->complete($invoice, $invoiceSource, $result);
        $this->triggerInvoiceSendBefore($invoice, $invoiceSource, $result);
        // If the invoice is set to null, we do not send it.
        if ($invoice === null) {
            return $result->setSendStatus(InvoiceAddResult::NotSent_EventInvoiceSendBefore);
        }

        // Some last checks that can prevent sending:
        // - If an error was set by the completor (or the event).
        if ($result->hasError()) {
            return $result->setSendStatus(InvoiceAddResult::NotSent_LocalErrors);
        }
        // - Edge case: no invoice lines, will fail on the API.
        if (count($invoice[Tag::Customer][Tag::Invoice][Tag::Line]) <= 0) {
            return $result->setSendStatus(InvoiceAddResult::NotSent_NoInvoiceLines);
        }
        // - If the invoice has a 0 total amount, and the user does  not
        //   want to send those.
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if (!$shopEventSettings['sendEmptyInvoice'] && $this->isEmptyInvoice($invoice)) {
            return $result->setSendStatus(InvoiceAddResult::NotSent_EmptyInvoice);
        }
        // - If we are doing a dry-run.
        if ($dryRun) {
            return $result->setSendStatus(InvoiceAddResult::NotSent_DryRun);
        }

        return $this->lockAndSend($invoice, $invoiceSource, $result);
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
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   The result structure of the invoice add API call merged with any local
     *   messages.
     *
     * @legacy: old way of creating and sending invoices.
     */
    protected function lockAndSend(array $invoice, Source $invoiceSource, InvoiceAddResult $result): InvoiceAddResult
    {
        $doLock = !$this->isTestMode() && in_array(
                $result->getSendStatus(),
                [InvoiceAddResult::Sent_New, InvoiceAddResult::Sent_LockExpired],
                true
            );

        if ($doLock) {
            // Check if we may expect an expired lock and, if so, remove it.
            if ($result->getSendStatus() === InvoiceAddResult::Sent_LockExpired) {
                $lockStatus = $this->getAcumulusEntryManager()->deleteLock($invoiceSource);
                if ($lockStatus === AcumulusEntry::Lock_BecameRealEntry) {
                    // Bail out: invoice already sent after all.
                    return $result->setSendStatus(InvoiceAddResult::NotSent_AlreadySent);
                }
            }

            // Acquire lock.
            if (!$this->getAcumulusEntryManager()->lockForSending($invoiceSource)) {
                // Bail out: Lock not acquired.
                return $result->setSendStatus(InvoiceAddResult::NotSent_LockNotAcquired);
            }
        }

        $result = $this->doSend($invoice, $invoiceSource, $result);

        // When everything went well, the lock will have been replaced by a real
        // entry. So we only delete the lock in case of errors.
        //
        // deleteLock() is expected to return AcumulusEntry::Lock_Deleted,
        // so we don't act on that return status. With any of the other
        // statuses it is unclear what happened and what the status will be
        // in Acumulus: tell user to check.
        if ($doLock
            && $result->hasError()
            && ($lockStatus = $this->getAcumulusEntryManager()->deleteLock($invoiceSource)) !== AcumulusEntry::Lock_Deleted
        ) {
            $code = $lockStatus === AcumulusEntry::Lock_NoLongerExists ? 903 : 904;
            $result->createAndAddMessage(
                sprintf($this->t('message_warning_delete_lock_failed'), $this->t($invoiceSource->getType())),
                Severity::Warning,
                $code
            );
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
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   The result structure of the invoice add API call merged with any local
     *   messages.
     *
     * @legacy: old way of creating and sending invoices.
     */
    protected function doSend(array $invoice, Source $invoiceSource, InvoiceAddResult $invoiceAddResult): InvoiceAddResult
    {
        $apiResult = $this->getAcumulusApiClient()->invoiceAdd($invoice);
        $invoiceAddResult->setAcumulusResult($apiResult);

        // Save Acumulus entry:
        // - If we were sending in test mode or there were errors, no invoice
        //   will have been created in Acumulus: nothing to store.
        // - If the invoice was sent as a concept, the entry id and token will
        //   be empty, but we will receive a concept id and store that instead.
        if (!$this->isTestMode() && !$apiResult->hasError()) {
            // If we are going to overwrite an existing entry, we want to delete
            // that from Acumulus.
            $acumulusEntryManager = $this->getAcumulusEntryManager();
            $oldEntry = $acumulusEntryManager->getByInvoiceSource($invoiceSource);

            $invoiceInfo = $apiResult->getMainAcumulusResponse();
            if (!empty($invoiceInfo['token']) && !empty('entryid')) {
                // A real entry.
                $token = $invoiceInfo['token'];
                $id = $invoiceInfo['entryid'];
            } elseif (!empty($invoiceInfo['conceptid'])) {
                // A concept.
                $token = null;
                $id = $invoiceInfo['conceptid'];
            } else {
                // An error (or old API version).
                $token = null;
                $id = null;
            }
            $saved = $acumulusEntryManager->save($invoiceSource, $id, $token);

            // If we successfully saved the new entry, we may delete the old one
            // if there is one, and it's not a concept.
            if ($saved && $oldEntry && $oldEntry->getEntryId()) {
                $entryId = $oldEntry->getEntryId();
                $deleteResult = $this->getAcumulusApiClient()->setDeleteStatus($entryId, Api::Entry_Delete);
                if ($deleteResult->hasError()) {
                    // Add message(s) to result but not if the entry has already
                    // been deleted or does not exist at all (anymore).
                    if ($deleteResult->isNotFound()) {
                        // Could not delete the old entry: does no longer exist.
                        $invoiceAddResult->createAndAddMessage(
                            sprintf($this->t('message_warning_old_entry_not_found'), $this->t($invoiceSource->getType())),
                            Severity::Warning,
                            902
                        );
                    } else {
                        // Could not delete the old entry: already moved to the waste bin.
                        $invoiceAddResult->createAndAddMessage(
                            sprintf($this->t('message_warning_old_entry_already_deleted'), $this->t($invoiceSource->getType()),
                                $entryId),
                            Severity::Warning,
                            902
                        );
                    }
                } elseif ($deleteResult->hasRealMessages()) {
                    // Add other messages as well but do not try to interpret them.
                    $invoiceAddResult->addMessages($deleteResult->getMessages(Severity::InfoOrWorse), Severity::Warning);
                } else {
                    // Successfully deleted the old entry: add a notice so this
                    // info will be mailed to the user.
                    $invoiceAddResult->createAndAddMessage(
                        sprintf($this->t('message_warning_old_entry_deleted'), $this->t($invoiceSource->getType()), $entryId),
                        Severity::Notice,
                        901
                    );
                }
            }
        }

        return $invoiceAddResult;
    }

    /**
     * Sends an email with the results of sending an invoice.
     *
     * The mail is sent to the shop administrator ('emailonerror' setting).
     *
     * @return bool
     *   Success.
     *
     * @legacy: old way of creating and sending invoices.
     */
    protected function mailInvoiceAddResult(InvoiceAddResult $result, Source $invoiceSource): bool
    {
        $pluginSettings = $this->getConfig()->getPluginSettings();
        $addReqResp = $pluginSettings['debug'] === Config::Send_SendAndMailOnError
            ? InvoiceAddResult::AddReqResp_WithOther
            : InvoiceAddResult::AddReqResp_Always;
        if ($addReqResp === InvoiceAddResult::AddReqResp_Always || $result->hasRealMessages()) {
            return $this->getMailer()->sendInvoiceAddMailResult($result, $invoiceSource->getType(), $invoiceSource->getReference());
        }
        return true;
    }

    /**
     * Sends the Acumulus invoice as a pdf to the customer.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The invoice source for which to mail the invoice to the customer.
     *
     * @throws \RuntimeException
     *   No Acumulus entry for this source or entry does not contain a token.
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   Error while sending the mail.
     */
    public function emailInvoiceAsPdf(Source $invoiceSource): AcumulusResult
    {
        $acumulusEntry = $this->getAcumulusEntryManager()->getByInvoiceSource($invoiceSource);
        if ($acumulusEntry === null) {
            throw new RuntimeException('No Acumulus entry for $invoiceSource');
        }
        $token = $acumulusEntry->getToken();
        // If sent as concept, token will be null.
        if ($token === null) {
            throw new RuntimeException('No Acumulus token for $invoiceSource');
        }
        $emailAsPdf = $this->getCreator()->createEmailAsPdf($invoiceSource);
        return $this->getAcumulusApiClient()->emailInvoiceAsPdf($token, $emailAsPdf);
    }

    /**
     * Sends the Acumulus invoice as a pdf to the customer.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The invoice source for which to mail the packing slip.
     *
     * @throws \RuntimeException
     *   No Acumulus entry for this source or entry does not contain a token.
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   Error while sending the mail.
     */
    public function emailPackingSlipAsPdf(Source $invoiceSource): AcumulusResult
    {
        $acumulusEntry = $this->getAcumulusEntryManager()->getByInvoiceSource($invoiceSource);
        if ($acumulusEntry === null) {
            throw new RuntimeException('No Acumulus entry for $invoiceSource');
        }
        $token = $acumulusEntry->getToken();
        // If sent as concept, token will be null.
        if ($token === null) {
            throw new RuntimeException('No Acumulus token for $invoiceSource');
        }

        $emailAsPdf = $this->getCreator()->createEmailAsPdf($invoiceSource, false);
        return $this->getAcumulusApiClient()->emailPackingSlipAsPdf($token, $emailAsPdf);
    }

    /**
     * Returns whether an invoice is empty (free products only).
     *
     * @return bool
     *   True if the invoice amount (inc. VAT) is €0,-.
     */
    protected function isEmptyInvoice(array $invoice): bool
    {
        return Number::isZero($invoice[Tag::Customer][Tag::Invoice][Meta::Totals]->amountInc);
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
     * @param array|null $invoice
     *   The invoice that has been created. May be null on return.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Any locally generated messages.
     *
     * @legacy: old way of event handling.
     */
    abstract protected function triggerInvoiceCreated(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void;

    /**
     * Triggers an event that an invoice for Acumulus has been created and
     * completed and is ready to be sent.
     *
     * This allows to inject custom behavior to alter the invoice just before
     * sending.
     *
     * @param array|null $invoice
     *   The invoice that has been created. May be null on return.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was created.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $localResult
     *   Any locally generated messages.
     *
     * @legacy: old way of event handling.
     */
    abstract protected function triggerInvoiceSendBefore(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void;

    /**
     * Triggers an event after an invoice for Acumulus has been sent.
     *
     * This allows to inject custom behavior to react to invoice sending.
     *
     * @param array $invoice
     *   The invoice that has been sent.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was sent.
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $result
     *   The result as sent back by Acumulus.
     *
     * @legacy: old way of event handling.
     */
    abstract protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, InvoiceAddResult $result): void;

    /**
     * Returns the given DateTime in a format that the actual database layer
     * accepts for comparison in a SELECT query.
     *
     * This default implementation returns the DateTime as a string in ISO format
     * (yyyy-mm-dd hh:mm:ss).
     */
    protected function getSqlDate(DateTime $date): string
    {
        return $date->format(Api::Format_TimeStamp);
    }

    /**
     * Returns a string that details the result of the invoice sending.
     *
     * @param int $addReqResp
     *   Whether to add the raw request and response.
     *   One of the {@see Result}::AddReqResp_... constants.
     */
    protected function getSendResultLogText(
        Source $invoiceSource,
        InvoiceAddResult $result,
        int $addReqResp = InvoiceAddResult::AddReqResp_WithOther
    ): string {
        $invoiceSourceText = sprintf(
            $this->t('message_invoice_source'),
            $this->t($invoiceSource->getType()),
            $invoiceSource->getReference()
        );
        return sprintf(
            $this->t('message_invoice_send'),
            $result->getTrigger(),
            $invoiceSourceText,
            $result->getLogText($addReqResp)
        );
    }
}

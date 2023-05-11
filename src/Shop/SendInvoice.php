<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Collectors\CollectorManager;
use Siel\Acumulus\Completors\InvoiceCompletor;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Event;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Mailer;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Tag;

use function count;
use function in_array;

/**
 * SendInvoice handles the task of creating and sending an invoice to Acumulus.
 */
class SendInvoice
{
    private Container $container;

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

    /**
     * @return \Siel\Acumulus\Helpers\Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    protected function getTranslator(): Translator
    {
        return $this->getContainer()->getTranslator();
    }

    protected function getLog(): Log
    {
        return $this->getContainer()->getLog();
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->getConfig();
    }

    protected function getAcumulusEntryManager(): AcumulusEntryManager
    {
        return $this->getContainer()->getAcumulusEntryManager();
    }

    protected function getAcumulusApiClient(): Acumulus
    {
        return $this->getContainer()->getAcumulusApiClient();
    }

    protected function getEvent(): Event
    {
        return $this->getContainer()->getEvent();
    }

    protected function getMailer(): Mailer
    {
        return $this->getContainer()->getMailer();
    }

    protected function getCollectorManager(): CollectorManager
    {
        return $this->getContainer()->getCollectorManager();
    }

    protected function getInvoiceCompletor(): InvoiceCompletor
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getContainer()->getCompletor(DataType::Invoice);
    }

    /**
     * Description.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $result
     * @param bool $forceSend
     * @param bool $dryRun
     *
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   Description.
     */
    public function createAndSend(
        Source $invoiceSource,
        InvoiceAddResult $result,
        bool $forceSend = false,
        bool $dryRun = false
    ): InvoiceAddResult {
        $invoice = $this->create($invoiceSource, $result, $forceSend);
        if ($invoice !== null && !$result->isSendingPrevented()) {
            $this->send($invoice, $invoiceSource, $result, $dryRun);
        }
        return $result;
    }

    protected function create(Source $invoiceSource, InvoiceAddResult $result, bool $forceSend): ?Invoice
    {
        $this->setBasicSendStatus($invoiceSource, $result, $forceSend);
        $this->getEvent()->triggerInvoiceCreateBefore($invoiceSource, $result);
        if (!$result->isSendingPrevented()) {
            $invoice = $this->getCollectorManager()->collectInvoice($invoiceSource);
            $this->getEvent()->triggerInvoiceCreateAfter($invoice, $invoiceSource, $result);
            /** @noinspection PhpConditionAlreadyCheckedInspection */
            if (!$result->isSendingPrevented()) {
                // @todo: handle verification errors here. Currently, they
                //   get severity Error, should perhaps become Exception.
                $this->getInvoiceCompletor()->complete($invoice, $result);
            }
        }
        return $invoice ?? null;
    }

    /**
     * Sets the (basic)
     * {@see \Siel\Acumulus\Invoice\InvoiceAddResult::getSendStatus()}.
     */
    protected function setBasicSendStatus(Source $invoiceSource, InvoiceAddResult $result, bool $forceSend): void
    {
        $acumulusEntry = $this->getAcumulusEntryManager()->getByInvoiceSource($invoiceSource, false);
        if ($this->isTestMode()) {
            $result->setSendStatus(InvoiceAddResult::Send_TestMode);
        } elseif ($acumulusEntry === null) {
            $result->setSendStatus(InvoiceAddResult::Send_New);
        } elseif ($forceSend) {
            $result->setSendStatus(InvoiceAddResult::Send_Forced);
        } elseif ($acumulusEntry->hasLockExpired()) {
            $result->setSendStatus(InvoiceAddResult::Send_LockExpired);
        } elseif ($acumulusEntry->isSendLock()) {
            $result->setSendStatus(InvoiceAddResult::NotSent_AlreadyLocked);
        } else {
            $result->setSendStatus(InvoiceAddResult::NotSent_AlreadySent);
        }
    }

    protected function send(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result, bool $dryRun): void
    {
        $this->getEvent()->triggerInvoiceSendBefore($invoice, $result);
        if (!$result->isSendingPrevented()) {
            // Some last checks that can also still prevent sending.
            $this->checkBeforeSending($invoice, $result, $dryRun);
            /** @noinspection PhpConditionAlreadyCheckedInspection */
            if (!$result->isSendingPrevented()) {
                $this->lockAndSend($invoice, $invoiceSource, $result);
            }
        }
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
     */
    protected function lockAndSend(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $result): InvoiceAddResult
    {
        $didLock = $this->lock($invoiceSource, $result);
        if (!$result->isSendingPrevented()) {
            $this->doSend($invoice, $invoiceSource, $result);
        }

        // When everything went well, the lock will have been replaced by a real
        // entry. So we only delete the lock in case of errors.
        //
        // deleteLock() is expected to return AcumulusEntry::Lock_Deleted,
        // so we don't act on that return status. With any of the other
        // statuses it is unclear what happened and what the status will be
        // in Acumulus: tell user to check.
        if ($didLock
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
        $this->getEvent()->triggerInvoiceSendAfter($invoice, $invoiceSource, $result);

        // Send a mail if there are messages.
        $this->mailInvoiceAddResult($result, $invoiceSource);

        return $result;
    }

    /**
     * Locks the $invoiceSource for sending.
     *
     * @return bool
     *   True if a lock was needed, false if no lock was needed.
     */
    protected function lock(Source $invoiceSource, InvoiceAddResult $result): bool
    {
        $doLock = !$this->isTestMode()
            && in_array($result->getSendStatus(), [InvoiceAddResult::Send_New, InvoiceAddResult::Send_LockExpired], true);
        if ($doLock) {
            // Check if we may expect an expired lock and, if so, remove it.
            if ($result->getSendStatus() === InvoiceAddResult::Send_LockExpired) {
                $lockStatus = $this->getAcumulusEntryManager()->deleteLock($invoiceSource);
                if ($lockStatus === AcumulusEntry::Lock_BecameRealEntry) {
                    // Bail out: invoice already sent after all.
                    $result->setSendStatus(InvoiceAddResult::NotSent_AlreadySent);
                }
            }

            // Acquire lock.
            if (!$this->getAcumulusEntryManager()->lockForSending($invoiceSource)) {
                // Bail out: Lock not acquired.
                $result->setSendStatus(InvoiceAddResult::NotSent_LockNotAcquired);
            }
        }
        return $doLock;
    }

    /**
     * Unconditionally sends the invoice and update the Acumulus entries table.
     *
     * After sending the invoice:
     * - A successful result gets saved to the acumulus entries table.
     * - If an older submission exists, it will be deleted from Acumulus.
     */
    protected function doSend(Invoice $invoice, Source $invoiceSource, InvoiceAddResult $invoiceAddResult): void
    {
        $apiResult = $this->getAcumulusApiClient()->invoiceAdd($invoice);
        $invoiceAddResult->setAcumulusResult($apiResult);
        // Save Acumulus entry if we were not sending in test mode and there
        // were no errors.
        if (!$this->isTestMode() && !$apiResult->hasError()) {
            $this->saveAcumulusEntry($invoiceSource, $apiResult, $invoiceAddResult);
        }
    }

    /**
     * Saves the Acumulus entry details with fields identifying the invoice source.
     *
     * After sending the invoice:
     * - A successful result gets saved to the acumulus entries table.
     * - If an older submission exists, it will be deleted from Acumulus.
     */
    protected function saveAcumulusEntry(Source $invoiceSource, AcumulusResult $apiResult, InvoiceAddResult $invoiceAddResult): void
    {
        // Save Acumulus entry:
        // - If the invoice was sent as a concept, the entry id and token will
        //   be empty, but we will receive a concept id and store that instead.
        // If we are going to overwrite an existing entry, we want to delete
        // that from Acumulus.
        /** @noinspection DuplicatedCode */
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

        // If we successfully saved the new entry, we may delete the old one if
        // there is one, and it's not a concept.
        if ($saved && $oldEntry && $oldEntry->getEntryId()) {
            $entryId = $oldEntry->getEntryId();
            $deleteResult = $this->getAcumulusApiClient()->setDeleteStatus($entryId, Api::Entry_Delete);
            if ($deleteResult->hasError()) {
                // Add a warning to result but not if the entry has already been
                // deleted or does not exist at all (anymore).
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
                // Successfully deleted the old entry: add a notice so this info
                // will be mailed to the user.
                $invoiceAddResult->createAndAddMessage(
                    sprintf($this->t('message_warning_old_entry_deleted'), $this->t($invoiceSource->getType()), $entryId),
                    Severity::Notice,
                    901
                );
            }
        }
    }

    /**
     * Checks for a number of conditions that can prevent sending the invoice.
     *
     * The conditions that are checked:
     * - Dry run: do not actually send.
     * - Errors were encountered during the creation of the invoice.
     * - Edge case: no invoice lines: will fail on the API.
     * - If the invoice has a 0 total amount, and the user does not want to send
     *   those.
     */
    protected function checkBeforeSending(Invoice $invoice, InvoiceAddResult $result, bool $dryRun): void
    {
        // - Dry run: do not actually send:
        if ($dryRun) {
            $result->setSendStatus(InvoiceAddResult::NotSent_DryRun);
        }
        // - We encountered errors during the creation of the invoice.
        if ($result->hasError()) {
            $result->setSendStatus(InvoiceAddResult::NotSent_LocalErrors);
        }
        // - Edge case: no invoice lines: will fail on the API.
        if (count($invoice[Tag::Customer][Tag::Invoice][Tag::Line]) <= 0) {
            $result->setSendStatus(InvoiceAddResult::NotSent_NoInvoiceLines);
        }
        // - If the invoice has a 0 total amount, and the user does not want to
        //   send those.
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if (!$shopEventSettings['sendEmptyInvoice'] && $invoice->isZeroAmount()) {
            $result->setSendStatus(InvoiceAddResult::NotSent_EmptyInvoice);
        }
    }

    /**
     * Sends an email with the results of sending an invoice.
     *
     * The mail is sent to the shop administrator ('emailonerror' setting).
     *
     * @return bool
     *   Success.
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
}

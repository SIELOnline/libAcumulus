<?php
namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Result;
use Siel\Acumulus\Invoice\ResultTranslations;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Tag;

/**
 * Provides functionality to manage invoices.
 *
 * The features of this class include:
 * - Retrieval of webshop invoice sources (orders or refunds).
 * - Handle order state changes.
 * - Handle refund creation or credit memo sending.
 * - Handle batch sending
 * - Create and send an invoice to Acumulus for a given invoice source,
 *   including triggering our own events and processing the result.
 *
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

        $translations = new ResultTranslations();
        $this->getTranslator()->add($translations);
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
     * Returns a Service instance.
     *
     * @return \Siel\Acumulus\Web\Service
     */
    protected function getService()
    {
        return $this->container->getService();
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
        $testMode = $pluginSettings['debug'] == PluginConfig::Send_TestMode;
        return $testMode;
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
        $results = array();
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
        $this->getTranslator()->add(new ResultTranslations());
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
            $result = $this->send($invoiceSource, $result, $forceSend, $dryRun);
            $success = $success && !$result->hasError();
            $this->getLog()->notice($this->getSendResultLogText($invoiceSource, $result));
            $log[$invoiceSource->getId()] = $this->getSendResultLogText($invoiceSource, $result,Result::AddReqResp_Never);
        }
        return $success;
    }

    /**
     * Processes an invoice source status change event.
     *
     * For now we don't look at credit note states, they are always sent.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source whose status has changed.
     *
     * @return \Siel\Acumulus\Invoice\Result
     *   The result of sending (or not sending) the invoice.
     */
    public function sourceStatusChange(Source $invoiceSource)
    {
        $status = $invoiceSource->getStatus();
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        $result = $this->getInvoiceResult('InvoiceManager::sourceStatusChange()');
        if ($invoiceSource->getType() === Source::CreditNote || in_array($status, $shopEventSettings['triggerOrderStatus'])) {
            $result = $this->send($invoiceSource, $result);
        } else {
            $result->setSendStatus(Result::NotSent_WrongStatus, array($status, implode(',', $shopEventSettings['triggerOrderStatus'])));
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
     */
    public function invoiceCreate(Source $invoiceSource)
    {
        $result = $this->getInvoiceResult('InvoiceManager::invoiceCreate()');
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] == PluginConfig::TriggerInvoiceEvent_Create) {
            $result = $this->send($invoiceSource, $result);
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
     */
    public function invoiceSend(Source $invoiceSource)
    {
        $result = $this->getInvoiceResult('InvoiceManager::invoiceSend()');
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] == PluginConfig::TriggerInvoiceEvent_Send) {
            $result = $this->send($invoiceSource, $result);
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
    public function send(Source $invoiceSource, Result $result, $forceSend = false, $dryRun = false)
    {
        if ($this->isTestMode()) {
            $result->setSendStatus(Result::Sent_TestMode);
        } elseif ($this->getAcumulusEntryManager()->getByInvoiceSource($invoiceSource) === null) {
            $result->setSendStatus(Result::Sent_New);
        } elseif ($forceSend) {
            $result->setSendStatus(Result::Sent_Forced);
        } else {
            $result->setSendStatus(Result::NotSent_AlreadySent);
        }

        if ($result->getSendStatus() !== Result::NotSent_AlreadySent) {
            $invoice = $this->getCreator()->create($invoiceSource);

            // Do not send 0-amount invoices, if set so.
            $shopEventSettings = $this->getConfig()->getShopEventSettings();
            if ($shopEventSettings['sendEmptyInvoice'] || !$this->isEmptyInvoice($invoice)) {
                // Trigger the InvoiceCreated event.
                $this->triggerInvoiceCreated($invoice, $invoiceSource, $result);

                // If the invoice is not set to null, we continue by completing it.
                if ($invoice !== null) {
                    $invoice = $this->getCompletor()->complete($invoice, $invoiceSource, $result);

                    // Trigger the InvoiceCompleted event.
                    $this->triggerInvoiceSendBefore($invoice, $invoiceSource, $result);

                    // If the invoice is not set to null, we continue by sending it.
                    if ($invoice !== null) {
                        if (!$result->hasError()) {
                            if (!$dryRun) {
                                $result = $this->doSend($invoice, $invoiceSource, $result);
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
     * Unconditionally sends the invoice.
     *
     * After sending the invoice:
     * - A successful result gets saved to the acumulus entries table.
     * - The invoice sent event gets triggered
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
    protected function doSend(array $invoice, Source $invoiceSource, Result $result)
    {
        /** @var \Siel\Acumulus\Invoice\Result $result */
        $result = $this->getService()->invoiceAdd($invoice, $result);

        // if we are going to overwrite an existing entry, we want to delete
        // that from Acumulus upon success.
        $deleteOldEntry = false;
        $acumulusEntryManager = $this->getAcumulusEntryManager();
        if ($result->getSendStatus() === Result::Sent_Forced) {
            $oldEntry = $acumulusEntryManager->getByInvoiceSource($invoiceSource);
        }

        // Check if an entryid was created and store entry id and token.
        $newEntry = $result->getResponse();
        if (!empty($newEntry['entryid'])) {
            $deleteOldEntry = (bool) $acumulusEntryManager->save($invoiceSource, $newEntry['entryid'], $newEntry['token']);
        } else {
            // If the invoice was sent as a concept, no entryid will be returned
            // but we still want to prevent sending it again: check for the
            // concept status, the absence of errors and non test-mode.
            $isConcept = $invoice[Tag::Customer][Tag::Invoice]['concept'] == Api::Concept_Yes;
            if ($isConcept && !$result->hasError() && !$this->isTestMode()) {
                $deleteOldEntry = (bool) $acumulusEntryManager->save($invoiceSource, null, null);
            }
        }

        // Delete if there is an old entry and we successfully saved the new entry.
        if ($deleteOldEntry && isset($oldEntry)) {
            // But only if the old entry was not a concept as concepts cannot be deleted.
            $entryId = $oldEntry->getEntryId();
            if (!empty($entryId)) {
                $deleteResult = $this->getService()->setDeleteStatus($entryId, API::Entry_Delete);
                if ($deleteResult->hasMessages()) {
                    // Add messages to result but not if the entry has already the
                    // delete status or does not exist at all (anymore).
                    if ($deleteResult->hasCodeTag('P2XFELO12')) {
                        // Successfully deleted the ld entry: add a warning so this
                        // info will be  mailed to the user.
                        $result->addWarning(902, '',
                            sprintf($this->t('message_warning_old_entry_not_deleted'), $this->t($invoiceSource->getType()), $entryId));
                    } else {
                        $result->mergeMessages($deleteResult, true);
                    }
                } else {
                    // Successfully deleted the old entry: add a warning so this
                    // info will be  mailed to the user.
                    $result->addNotice(901, '',
                        sprintf($this->t('message_warning_old_entry_deleted'), $this->t($invoiceSource->getType()), $entryId));
                }
            }
        }

        // Trigger the InvoiceSent event.
        $this->triggerInvoiceSendAfter($invoice, $invoiceSource, $result);

        // Send a mail if there are messages.
        $this->mailInvoiceAddResult($result, $invoiceSource);

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
        $addReqResp = $pluginSettings['debug'] === PluginConfig::Send_SendAndMailOnError ? Result::AddReqResp_WithOther : Result::AddReqResp_Always;
        if ($addReqResp === Result::AddReqResp_Always || ($addReqResp === Result::AddReqResp_WithOther && $result->hasMessages())) {
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
     * @return int|string
     */
    protected function getSqlDate(DateTime $date)
    {
        return $date->format(PluginConfig::TimeStampFormat_Sql);
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
        $results = array();
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
        $logMessage = sprintf($this->t('message_invoice_send'),
            $result->getTrigger(),
            $invoiceSourceText,
            $result->getLogText($addReqResp)
        );
        return $logMessage;
    }
}

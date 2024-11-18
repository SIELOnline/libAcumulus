<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use DateTimeInterface;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdf;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Config\Config;

use function array_key_exists;
use function in_array;
use function ini_get;
use function sprintf;

/**
 * InvoiceManager provides functionality to manage invoices.
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

    protected function getContainer(): Container
    {
        return $this->container;
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

    protected function getShopCapabilities(): ShopCapabilities
    {
        return $this->getContainer()->getShopCapabilities();
    }

    protected function getAcumulusEntryManager(): AcumulusEntryManager
    {
        return $this->getContainer()->getAcumulusEntryManager();
    }

    protected function getAcumulusApiClient(): Acumulus
    {
        return $this->getContainer()->getAcumulusApiClient();
    }

    /**
     * Returns a new Source instance.
     */
    protected function getSource(string $sourceType, object|int|array $idOrSource): Source
    {
        return $this->getContainer()->createSource($sourceType, $idOrSource);
    }

    protected function getInvoiceCreate(): InvoiceCreate
    {
        return $this->getContainer()->getInvoiceCreate();
    }

    protected function getInvoiceSend(): InvoiceSend
    {
        return $this->getContainer()->getInvoiceSend();
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
        return $this->getContainer()->createInvoiceAddResult($trigger);
    }

    /**
     * Returns a list of existing invoice sources for the given id range.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    abstract public function getInvoiceSourcesByIdRange(string $sourceType, int $idFrom, int $idTo): array;

    /**
     * Returns a list of existing invoice sources for the given reference range.
     * Should be overridden when the reference is not the internal id.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     *
     * @todo: don't let overrides have to call parent when no results are found.
     */
    public function getInvoiceSourcesByReferenceRange(
        string $sourceType,
        string $referenceFrom,
        string $referenceTo,
        bool $fallbackToId
    ): array {
        return $fallbackToId ? $this->getInvoiceSourcesByIdRange($sourceType, (int) $referenceFrom, (int) $referenceTo) : [];
    }

    /**
     * Returns a list of existing invoice sources for the given date range.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    abstract public function getInvoiceSourcesByDateRange(
        string $sourceType,
        DateTimeInterface $dateFrom,
        DateTimeInterface $dateTo
    ): array;

    /**
     * Returns a list of existing invoice sources for the given filters.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     *
     * @todo: can we indicate whether we filtered on references or ids?
     */
    public function getInvoiceSourcesByFilters(string $sourceType, array $filters): array
    {
        $lists = [];
        /** @var array $filter */
        foreach ($filters as $filter) {
            if (array_key_exists('date_from', $filter)) {
                $lists[] = $this->getInvoiceSourcesByDateRange($sourceType, $filter['date_from'], $filter['date_to']);
            }
            if (array_key_exists('reference_from', $filter)) {
                $lists[] = $this->getInvoiceSourcesByReferenceRange(
                    $sourceType,
                    $filter['reference_from'],
                    $filter['reference_to'],
                    true
                );
            }
            if (array_key_exists('statuses', $filter)) {
                $statuses = $filter['statuses'];
            }
        }
        $list = array_intersect(...$lists);
        if (isset($statuses)) {
            $list = array_filter($list, static function (Source $item) use ($statuses) {
                return array_key_exists($item->getStatus(), $statuses);
            });
        }
        return $list;
    }

    /**
     * Creates a set of Invoice Sources given their ids or shop specific sources.
     *
     * @param string $sourceType
     * @param array $idsOrSources
     *   An array with shop specific orders or credit notes or just their ids.
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   A non keyed array with invoice Sources.
     */
    public function getSourcesByIdsOrSources(string $sourceType, array $idsOrSources): array
    {
        $results = [];
        foreach ($idsOrSources as $sourceId) {
            $results[] = $this->getSourceByIdOrSource($sourceType, $sourceId);
        }
        return $results;
    }

    /**
     * Creates a source given its type and id.
     *
     * @param object|int|array $idOrSource
     *   A shop specific order or credit note or just its ids.
     *
     * @return \Siel\Acumulus\Invoice\Source
     *   An invoice Source.
     */
    protected function getSourceByIdOrSource(string $sourceType, object|int|array $idOrSource): Source
    {
        return $this->getSource($sourceType, $idOrSource);
    }

    /**
     * Sends multiple invoices to Acumulus.
     *
     * @param \Siel\Acumulus\Invoice\Source[] $sources
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
    public function sendMultiple(array $sources, bool $forceSend, bool $dryRun, array &$log): bool
    {
        $canResetTimer = true;
        $success = true;
        $time_limit = ini_get('max_execution_time');
        foreach ($sources as $source) {
            // Try to keep the script running, but note that other systems
            // involved, like the (Apache) web server, may have their own
            // time-out.
            if ($canResetTimer && !ini_set('max_execution_time', $time_limit)) {
                $this->getLog()->warning('InvoiceManager::sendMultiple(): could not set time limit.');
                $canResetTimer = false;
            }

            $result = $this->createInvoiceAddResult('InvoiceManager::sendMultiple()');
            $result = $this->createAndSend($source, $result, $forceSend, $dryRun);
            $success = $success && !$result->hasError();
            $this->getLog()->notice($this->getSendResultLogText($source, $result));
            $log[$source->getId()] = $this->getSendResultLogText($source, $result, InvoiceAddResult::AddReqResp_Never);
        }
        return $success;
    }

    /**
     * Sends 1 invoice to Acumulus.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The invoice source to send the invoice for.
     * @param bool $forceSend
     *   If true, force sending the invoices even if an invoice has already been
     *   sent for a given invoice source.
     *
     * @return InvoiceAddResult
     *   The InvoiceAddResult of sending the invoice for this Source to Acumulus.
     */
    public function send1(Source $source, bool $forceSend): InvoiceAddResult
    {
        $result = $this->createInvoiceAddResult('InvoiceManager::send1()');
        $result = $this->createAndSend($source, $result, $forceSend);
        $this->getLog()->notice($this->getSendResultLogText($source, $result));
        return $result;
    }

    /**
     * Processes an invoice source status change event.
     *
     * For now, we don't look at credit note statuses, they are always sent.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The source whose status has changed.
     *
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   The result of sending (or not sending) the invoice.
     */
    public function sourceStatusChange(Source $source): InvoiceAddResult
    {
        $result = $this->createInvoiceAddResult('InvoiceManager::sourceStatusChange()');
        $status = $source->getStatus();
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($source->getType() === Source::Order) {
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
            $result = $this->createAndSend($source, $result);
            $sendStatus = $result->getSendStatus();
        }
        $result->setSendStatus($sendStatus, $arguments);
        $this->getLog()->notice($this->getSendResultLogText($source, $result));
        return $result;
    }

    /**
     * Processes an invoice create event.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The source for which a shop invoice was created.
     *
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   The result of sending (or not sending) the invoice.
     *
     * @noinspection PhpUnused
     */
    public function invoiceCreate(Source $source): InvoiceAddResult
    {
        $result = $this->createInvoiceAddResult('InvoiceManager::invoiceCreate()');
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] === Config::TriggerInvoiceEvent_Create) {
            $result = $this->createAndSend($source, $result);
        } else {
            $result->setSendStatus(InvoiceAddResult::NotSent_TriggerInvoiceCreateNotEnabled);
        }
        $this->getLog()->notice($this->getSendResultLogText($source, $result));
        return $result;
    }

    /**
     * Processes a shop invoice send event.
     *
     * This is the invoice created by the shop and that is now sent/mailed to
     * the customer.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The source for which a shop invoice was created.
     *
     * @return \Siel\Acumulus\Invoice\InvoiceAddResult
     *   The result of sending (or not sending) the invoice.
     *
     * @noinspection PhpUnused
     */
    public function invoiceSend(Source $source): InvoiceAddResult
    {
        $result = $this->createInvoiceAddResult('InvoiceManager::invoiceSend()');
        $shopEventSettings = $this->getConfig()->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] === Config::TriggerInvoiceEvent_Send) {
            $result = $this->createAndSend($source, $result);
        } else {
            $result->setSendStatus(InvoiceAddResult::NotSent_TriggerInvoiceSentNotEnabled);
        }
        $this->getLog()->notice($this->getSendResultLogText($source, $result));
        return $result;
    }

    protected function createAndSend(
        Source $source,
        InvoiceAddResult $result,
        bool $forceSend = false,
        bool $dryRun = false
    ): InvoiceAddResult {
        $this->getInvoiceSend()->setBasicSendStatus($source, $result, $forceSend);
        $invoice = $this->getInvoiceCreate()->create($source, $result);
        if ($invoice !== null && !$result->isSendingPrevented()) {
            $this->getInvoiceSend()->send($invoice, $source, $result, $dryRun);
        }
        return $result;
    }

    /**
     * Sends the Acumulus invoice as a pdf to the customer.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The invoice source for which to mail the invoice to the customer.
     *
     * @throws \RuntimeException
     *   No Acumulus entry for this source or entry does not contain a token.
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   Error while sending the mail.
     */
    public function emailInvoiceAsPdf(Source $source): AcumulusResult
    {
        $acumulusEntry = $this->getAcumulusEntryManager()->getByInvoiceSource($source);
        if ($acumulusEntry === null) {
            throw new RuntimeException('No Acumulus entry for $invoiceSource');
        }
        $token = $acumulusEntry->getToken();
        // If sent as concept, token will be null.
        if ($token === null) {
            throw new RuntimeException('No Acumulus token for $invoiceSource');
        }
        /** @var \Siel\Acumulus\Data\EmailInvoiceAsPdf $emailAsPdf */
        $emailAsPdf = $this->createEmailAsPdf($source);
        return $this->getAcumulusApiClient()->emailInvoiceAsPdf($token, $emailAsPdf);
    }

    /**
     * Sends the Acumulus invoice as a pdf to the customer.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The invoice source for which to mail the packing slip.
     *
     * @throws \RuntimeException
     *   No Acumulus entry for this source or entry does not contain a token.
     * @throws \Siel\Acumulus\ApiClient\AcumulusException
     *   Error while sending the mail.
     */
    public function emailPackingSlipAsPdf(Source $source): AcumulusResult
    {
        $acumulusEntry = $this->getAcumulusEntryManager()->getByInvoiceSource($source);
        if ($acumulusEntry === null) {
            throw new RuntimeException('No Acumulus entry for $invoiceSource');
        }
        $token = $acumulusEntry->getToken();
        // If sent as concept, token will be null.
        if ($token === null) {
            throw new RuntimeException('No Acumulus token for $invoiceSource');
        }

        /** @var \Siel\Acumulus\Data\EmailPackingSlipAsPdf $emailAsPdf */
        $emailAsPdf = $this->createEmailAsPdf($source, false);
        return $this->getAcumulusApiClient()->emailPackingSlipAsPdf($token, $emailAsPdf);
    }

    protected function createEmailAsPdf(Source $source, bool $forInvoice = true): EmailAsPdf
    {
        $type = $forInvoice ? EmailAsPdfType::Invoice : EmailAsPdfType::PackingSlip;
        $collectorManager = $this->getContainer()->getCollectorManager();
        $collectorManager->getPropertySources()->add('source', $source);
        $emailAsPdf = $collectorManager->collectEmailAsPdf($type);
        /** @var \Siel\Acumulus\Completors\EmailInvoiceAsPdfCompletor $completor */
        $completor = $this->getContainer()->getCompletor(DataType::EmailAsPdf);
        $completor->complete($emailAsPdf, new MessageCollection($this->getTranslator()));
        return $emailAsPdf;
    }

    /**
     * Returns the given DateTimeInterface in a format that the actual database layer
     * accepts for comparison in a SELECT query.
     *
     * This default implementation returns the DateTimeInterface as a string in ISO format
     * (yyyy-mm-dd hh:mm:ss).
     */
    protected function getSqlDate(DateTimeInterface $date): string
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
        Source $source,
        InvoiceAddResult $result,
        int $addReqResp = InvoiceAddResult::AddReqResp_WithOther
    ): string {
        $invoiceSourceText = sprintf(
            $this->t('message_invoice_source'),
            $this->t($source->getType()),
            $source->getReference()
        );
        return sprintf(
            $this->t('message_invoice_send'),
            $result->getTrigger(),
            $invoiceSourceText,
            $result->getLogText($addReqResp)
        );
    }
}

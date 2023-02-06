<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Shop;

use DateTimeZone;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Invoice\InvoiceAddResult;

use function count;

/**
 * This override provides Joomla specific db helper methods and defines
 * and dispatches Joomla events for the events defined by our library.
 */
abstract class InvoiceManager extends BaseInvoiceManager
{
    /**
     * Helper method that executes a query to retrieve a list of invoice source
     * ids and returns a list of invoice sources for these ids.
     *
     * @param string $invoiceSourceType
     * @param string $query
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   A non keyed array with invoice Sources.
     */
    protected function getSourcesByQuery(string $invoiceSourceType, string $query): array
    {
        $sourceIds = $this->loadColumn($query);
        return $this->getSourcesByIdsOrSources($invoiceSourceType, $sourceIds);
    }

    /**
     * Helper method to execute a query and return the 1st column from the
     * results.
     *
     * @param string $query
     *
     * @return int[]
     *   A non keyed array with the values of the 1st results of the query result.
     */
    protected function loadColumn(string $query): array
    {
        /** @noinspection NullPointerExceptionInspection */
        return $this->getDb()->setQuery($query)->loadColumn();
    }

    /**
     * Helper method to get the db object.
     *
     * @return \Joomla\Database\DatabaseDriver|\JDatabaseDriver|null
     */
    protected function getDb()
    {
        /** @noinspection PhpDeprecationInspection  Deprecated as of J4. */
        return Factory::getDbo();
    }

    /**
     * Helper method that returns a date in the correct and escaped sql format.
     *
     * @param string $dateStr
     *   Date in yyyy-mm-dd format.
     *
     * @return string
     *   The date string SQL datetime format.
     *
     * @throws \Exception
     */
    protected function toSql(string $dateStr): string
    {
        /** @noinspection NullPointerExceptionInspection */
        $tz = new DateTimeZone(Factory::getApplication()->get('offset'));
        $date = new Date($dateStr);
        $date->setTimezone($tz);
        return $date->toSql(true);
    }

    /**
     * {@inheritdoc}
     *
     * This Joomla override dispatches the 'onAcumulusInvoiceCreated' event.
     *
     * @throws \Exception
     *
     * @noinspection PhpDeprecationInspection  Deprecated as of J4.
     */
    protected function triggerInvoiceCreated(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        PluginHelper::importPlugin('acumulus');
        /** @noinspection NullPointerExceptionInspection */
        $results = Factory::getApplication()->triggerEvent('onAcumulusInvoiceCreated', [&$invoice, $invoiceSource, $localResult]);
        if (count(
                array_filter($results, static function ($value) {
                    return $value === false;
                })
            ) >= 1
        ) {
            $invoice = null;
        }
    }

    /**
     * {@inheritdoc}
     *
     * This Joomla override dispatches the 'onAcumulusInvoiceSendBefore' event.
     *
     * @throws \Exception
     *
     * @noinspection PhpDeprecationInspection
     *   Deprecated as of J4.
     * @noinspection NullPointerExceptionInspection
     */
    protected function triggerInvoiceSendBefore(?array &$invoice, Source $invoiceSource, InvoiceAddResult $localResult): void
    {
        PluginHelper::importPlugin('acumulus');
        $results = Factory::getApplication()->triggerEvent('onAcumulusInvoiceSendBefore', [&$invoice, $invoiceSource, $localResult]);
        if (count(
                array_filter($results, static function ($value) {
                    return $value === false;
                })
            ) >= 1
        ) {
            $invoice = null;
        }
    }

    /**
     * {@inheritdoc}
     *
     * This Joomla override dispatches the 'onAcumulusInvoiceSent' event.
     *
     * @throws \Exception
     *
     * @noinspection PhpDeprecationInspection
     *   Deprecated as of J4.
     * @noinspection NullPointerExceptionInspection
     */
    protected function triggerInvoiceSendAfter(array $invoice, Source $invoiceSource, InvoiceAddResult $result): void
    {
        PluginHelper::importPlugin('acumulus');
        Factory::getApplication()->triggerEvent('onAcumulusInvoiceSendAfter', [$invoice, $invoiceSource, $result]);
    }
}

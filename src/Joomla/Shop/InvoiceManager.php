<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Shop;

use DateTimeZone;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;

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
     * @param string $sourceType
     * @param string $query
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   A non keyed array with invoice Sources.
     */
    protected function getSourcesByQuery(string $sourceType, string $query): array
    {
        $sourceIds = $this->loadColumn($query);
        return $this->getSourcesByIdsOrSources($sourceType, $sourceIds);
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
        /** @noinspection PhpUndefinedMethodInspection {@see DatabaseInterface::setQuery()} */
        return $this->getDb()->setQuery($query)->loadColumn();
    }

    /**
     * Helper method to get the db object.
     */
    protected function getDb(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Helper method that returns a date in the correct and escaped sql format.
     *
     * @param string $dateStr
     *   Date in yyyy-mm-dd format.
     *
     * @return string
     *   The date string in SQL datetime format.
     *
     * @throws \Exception
     */
    protected function toSql(string $dateStr): string
    {
        /** @noinspection PhpUndefinedMethodInspection  {@see \Joomla\Application\ConfigurationAwareApplicationInterface::get()} */
        return (new Date($dateStr, new DateTimeZone(Factory::getApplication()->get('offset'))))->toSql(true);
    }
}

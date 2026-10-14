<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Shop;

use DateTimeInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use WHMCS\Database\Capsule;

/**
 * Implements the WHMCS specific parts of the invoice manager.
 */
class InvoiceManager extends BaseInvoiceManager
{
    protected static array $sourceTypeTableInfo = [
        Source::CreditNote => [ // @todo: add support for credit notes.
            'table' => 'tblinvoices',
            'idField' => 'id',
            'refField' => 'invoicenum',
            'dateField' => 'date_refunded',
        ],
        Source::Invoice => [
            'table' => 'tblinvoices',
            'idField' => 'id',
            'refField' => 'invoicenum',
            'dateField' => 'date',
        ],
    ];

    public function getInvoiceSourcesByIdRange(string $sourceType, int $idFrom, int $idTo): array
    {
        $tableInfo = static::$sourceTypeTableInfo[$sourceType];
        $records = Capsule::table($tableInfo['table'])
            ->whereBetween($tableInfo['idField'], [$idFrom, $idTo])
            ->orderBy($tableInfo['idField'])
            ->get();
        return $this->getSourcesByIdsOrSources($sourceType, $records);
    }

    public function getInvoiceSourcesByReferenceRange(string $sourceType, string $referenceFrom, string $referenceTo, bool $fallbackToId): array
    {
        $results = [];
        $tableInfo = static::$sourceTypeTableInfo[$sourceType];
        if (!empty($tableInfo['refField'])) {
            $records = Capsule::table($tableInfo['table'])
                ->whereBetween($tableInfo['refField'], [$referenceFrom, $referenceTo])
                ->orderBy($tableInfo['refField'])
                ->get('id');
            $results = $this->getSourcesByIdsOrSources($sourceType, $records);
        }
        if (empty($results) && $fallbackToId) {
            $results = parent::getInvoiceSourcesByReferenceRange($sourceType, $referenceFrom, $referenceTo, $fallbackToId);
        }
        return $results;
    }

    public function getInvoiceSourcesByDateRange(string $sourceType, DateTimeInterface $dateFrom, DateTimeInterface $dateTo): array
    {
        $tableInfo = static::$sourceTypeTableInfo[$sourceType];
        $records = Capsule::table($tableInfo['table'])
            ->whereBetween($tableInfo['dateField'], [$dateFrom, $dateTo])
            ->orderBy($tableInfo['dateField'])
            ->get();
        return $this->getSourcesByIdsOrSources($sourceType, $records);
    }
}

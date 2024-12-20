<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Shop;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Product\StockTransactionResult;
use Siel\Acumulus\Shop\ProductManager;
use Siel\Acumulus\Tests\AcumulusTestUtils;
use Siel\Acumulus\Mail\Mailer;

/**
 * ProductManagerTest tests the {@see ProductManager}.
 *
 * This is an integration test, thus end-to-end with communication with the SIEL API.
 */
class ProductManagerTest extends TestCase
{
    use AcumulusTestUtils;

    private int $mailCount;

    private function getLog(): Log
    {
        return self::getContainer()->getLog();
    }

    private function getMailer(): Mailer
    {
        return static::getContainer()->getMailer();
    }

    private function getProductManager(): ProductManager
    {
        return static::getContainer()->getProductManager();
    }

    public function searchProvider(): array
    {
        return [
            'TEST-GRO' => ['TEST-GRO', [1833637]],
            'test-gro' => ['test-gro', [1833637]],
            'test' => ['test', [0 => '1833636', 1 => '1833637', 2 => '1833638', 3 => '1833639', 4 => '1833642', 5 => '1833643',]],
        ];
    }

    /**
     * Tests that the Acumulus product picklist filter is not case-sensitive.
     *
     * @dataProvider searchProvider
     */
    public function testGetAcumulusProducts(string $filter, array $productIds): void
    {
        $productManager = $this->getProductManager();
        $products = $productManager->getAcumulusProducts($filter);
        self::assertEqualsCanonicalizing($productIds, array_column($products, Fld::ProductId));
    }

    public function referenceProvider(): array
    {
        return [
            'TEST-GRO' => ['TEST-GRO', Fld::ProductEan, 1833637],
            'test-gro' => ['test-gro', Fld::ProductEan, null],
        ];
    }

    /**
     * Tests that the getAcumulusProductByRerference method is case-sensitive.
     *
     * @dataProvider referenceProvider
     */
    public function testGetAcumulusProductByReference(string $reference, string $acumulusField, ?int $productId): void
    {
        $productManager = $this->getProductManager();
        $product = $productManager->getAcumulusProductByReference($acumulusField, $reference);
        if ($productId === null) {
            self::assertNull($product);
        } else {
            self::assertSame($productId, (int) $product[Fld::ProductId]);
        }
    }

    public function itemProvider1(): array
    {
        return [
            'item 3 sku' => [3, 4, Fld::ProductSku, StockTransactionResult::NotSent_NoMatchInAcumulus],
            'item 3 ean' => [3, 4, Fld::ProductEan, 1833637],
            'item 3 free' => [3, 3, '', 1833637],
            'item 4 sku' => [4, 5, Fld::ProductSku, StockTransactionResult::NotSent_TooManyMatchesInAcumulus],
            'item 4 ean' => [4, 6, Fld::ProductEan, StockTransactionResult::NotSent_NoMatchInAcumulus],
            'item 4 free' => [4, 1, '', StockTransactionResult::NotSent_TooManyMatchesInAcumulus],
            'item 5 ean' => [5, -1, Fld::ProductEan, StockTransactionResult::NotSent_NoMatchInAcumulus],
            'item 5 free' => [5, -1, '', 1833642],
            'item 6 ean' => [6, -2, Fld::ProductEan, 1833636],
            'item 6 ean zero change' => [6, 0, Fld::ProductEan, StockTransactionResult::NotSent_ZeroChange],
            'item 6 free' => [6, -1, '', StockTransactionResult::NotSent_TooManyMatchesInAcumulus],
            'item 7 acumulusId' => [7, -1, Fld::ProductSku, 1833636, 'local'],
        ];
    }

    /**
     * @dataProvider itemProvider1
     */
    public function testUpdateStockWithConfig(
        int $itemId,
        int|float $change,
        string $acumulusField,
        int|string $acumulusProductIdOrError,
        string $acumulusProductIdSource = 'remote'
    ): void {
        $config = static::getContainer()->getConfig();
        $productMatchShopField = $config->set('productMatchShopField', '[product::getShopObject()::sku]');
        $productMatchAcumulusField = $config->set('productMatchAcumulusField', $acumulusField);
        $debug = $config->set('debug', $itemId === 17 ? Config::Send_SendAndMail : Config::Send_SendAndMailOnError);
        try {
            $this->updateStock($itemId, $change, $acumulusProductIdOrError, $acumulusProductIdSource);
        } finally {
            $config->set('productMatchShopField', $productMatchShopField);
            $config->set('productMatchAcumulusField', $productMatchAcumulusField);
            $config->set('debug', $debug);
        }
    }

    public function itemProvider2(): array
    {
        return [
            'item 5 sku' => [5, 2, Fld::ProductSku, 1833642],
            'item 6 sku' => [6, -1, Fld::ProductSku, 1833638],
            'item 8 acumulusId 404' => [8, -1, Fld::ProductSku, 'AA5B85AA', 'local'],
        ];
    }

    /**
     * @dataProvider itemProvider2
     */
    public function testUpdateStockWithMapping(
        int $itemId,
        int|float $change,
        string $acumulusField,
        int|string $acumulusProductIdOrError,
        string $acumulusProductIdSource = 'remote'
    ): void {
        $config = static::getContainer()->getConfig();
        $productMatchShopField = $config->set('productMatchShopField', 'mapping');
        $productMatchAcumulusField = $config->set('productMatchAcumulusField', $acumulusField);
        $debug = $config->set('debug', $itemId === 17 ? Config::Send_SendAndMail : Config::Send_SendAndMailOnError);
        $this->getContainer()->getMappings()->save([
            DataType::Product => [
                Meta::MatchShopFieldSpecification => '[product::getReference()]',
            ],
        ]);

        try {
            $this->updateStock($itemId, $change, $acumulusProductIdOrError, $acumulusProductIdSource);
        } finally {
            $config->set('productMatchShopField', $productMatchShopField);
            $config->set('productMatchAcumulusField', $productMatchAcumulusField);
            $config->set('debug', $debug);
        }
    }

    /**
     * Updates the stock based on a line item.
     */
    private function updateStock(int $itemId, float|int $change, int|string $acumulusProductIdOrError, string $acumulusProductIdSource): void
    {
        $source = static::getContainer()->createSource(Source::Order, 1);
        $item = static::getContainer()->createItem($itemId, $source);
        $this->mailCount = $this->getMailer()->getMailCount();
        $productManager = $this->getProductManager();
        $result = $productManager->updateStockForItem($item, $change, 'ProductManagerTest::testUpdateStock');
        $this->checkLog();
        if ($result->isSendingPrevented()) {
            self::assertSame($acumulusProductIdOrError, $result->getSendStatus());
            $this->checkMail();
        } elseif ($result->hasError()) {
            self::assertNotNull($result->getByCodeTag($acumulusProductIdOrError));
            $this->checkMail();
        } else {
            self::assertSame($acumulusProductIdOrError, (int) $result->getMainApiResponse()[Fld::ProductId]);
            self::assertSame(
                $acumulusProductIdSource,
                $result->getAcumulusResult()->getAcumulusRequest()->getSubmit()['stock'][Meta::AcumulusProductIdSource]
            );
            $stockAmount = (float) $result->getMainApiResponse()[Fld::StockAmount];

            // Correct the stock, so that continuous (successful) testing  will not change the actual levels at Acumulus.
            $result = $productManager->updateStockForItem($item, -$change, 'ProductManagerTest::returnStock');
            self::assertSame($acumulusProductIdOrError, (int) $result->getMainApiResponse()[Fld::ProductId]);
            self::assertSame(
                $result->getAcumulusResult()->getAcumulusRequest()->getSubmit()['stock'][Meta::AcumulusProductIdSource],
                'local'
            );
            self::assertSame($stockAmount - $change, (float) $result->getMainApiResponse()[Fld::StockAmount]);
        }
    }

    /**
     * Checks the log messages.
     *
     * @todo: can this (and checkMail() be moved to test utils?
     */
    private function checkLog(): void
    {
        $loggedMessages = $this->getLog()->getLoggedMessages();
        $logMessage = end($loggedMessages);

        // dataName() returns the key of the actual data set.
        $name = str_replace(' ', '-', $this->dataName()) . '-' . static::getContainer()->getLanguage();
        $this->saveTestLogMessage($name, $logMessage);
        $expected = $this->getTestLogMessage($name);
        static::assertSame($expected, $logMessage);
    }

    /**
     * Checks the mail messages.
     */
    private function checkMail(): void
    {
        static::assertSame($this->mailCount + 1, $this->getMailer()->getMailCount());
        $mailSent = $this->getMailer()->getMailSent($this->mailCount);
        static::assertIsArray($mailSent);

        // dataName() returns the key of the actual data set.
        $name = str_replace(' ', '-', $this->dataName()) . '-' . static::getContainer()->getLanguage();
        $this->saveTestMail($name, $mailSent);
        $expected = $this->getTestMail($name);
        static::assertSame($expected, $mailSent);
    }
}

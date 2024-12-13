<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Shop;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Product\StockTransactionResult;
use Siel\Acumulus\Shop\ProductManager;
use Siel\Acumulus\Tests\AcumulusTestUtils;
use Siel\Acumulus\TestWebShop\Mail\Mailer;

/**
 * ProductManagerTest tests the {@see ProductManager}.
 *
 * This is an integration test, thus end-to-end with communication with the SIEL API.
 */
class ProductManagerTest extends TestCase
{
    use AcumulusTestUtils;

    private int $mailCount;

    protected static function createContainer(): Container
    {
        $container = new Container('TestWebShop', 'nl');
        $container->addTranslations('Translations', 'Invoice');
        return $container;
    }

    private function getMailer(): Mailer
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getContainer()->getMailer();
    }

    private function getProductManager(): ProductManager
    {
        return $this->getContainer()->getProductManager();
    }

    public function itemProvider(): array
    {
        return [
            'item 3 sku' => [3, 13, 4, Fld::ProductSku, StockTransactionResult::NotSent_NoMatchInAcumulus],
            'item 3 ean' => [3, 13, 4, Fld::ProductEan, 1833637],
            'item 3 free' => [3, 13, 3, '', 1833637],
            'item 4 sku' => [4, 14, 5, Fld::ProductSku, StockTransactionResult::NotSent_TooManyMatchesInAcumulus],
            'item 4 ean' => [4, 14, 6, Fld::ProductEan, StockTransactionResult::NotSent_NoMatchInAcumulus],
            'item 4 free' => [4, 14, 1, '', StockTransactionResult::NotSent_TooManyMatchesInAcumulus],
            'item 5 sku' => [5, 15, 2, Fld::ProductSku, 1833642],
            'item 5 ean' => [5, 15, -1, Fld::ProductEan, StockTransactionResult::NotSent_NoMatchInAcumulus],
            'item 5 free' => [5, 15, -1, '', 1833642],
            'item 6 sku' => [6, 16, -1, Fld::ProductSku, 1833638],
            'item 6 ean' => [6, 16, -2, Fld::ProductEan, 1833636],
            'item 6 ean zero change' => [6, 16, 0, Fld::ProductEan, StockTransactionResult::NotSent_ZeroChange],
            'item 6 free' => [6, 16, -1, '', StockTransactionResult::NotSent_TooManyMatchesInAcumulus],
            'item 7 acumulusId' => [7, 17, -1, Fld::ProductSku, 1833636, 'local'],
            'item 8 acumulusId 404' => [8, 18, -1, Fld::ProductSku, 'AA5B85AA', 'local'],
        ];
    }

    /**
     * @dataProvider itemProvider
     */
    public function testUpdateStock(
        int $itemId,
        int $productId,
        int|float $change,
        string $acumulusField,
        int|string $acumulusProductIdOrError,
        string $acumulusProductIdSource = 'remote'
    ): void {
        $config = $this->getContainer()->getConfig();
        $source = $this->getContainer()->createSource(Source::Order, 1);
        $item = $this->getContainer()->createItem($itemId, $source);
        $product = $item->getProduct();
        self::assertSame($itemId, $item->getId());
        self::assertSame($productId, $product->getId());

        $this->mailCount = $this->getMailer()->getMailCount();
        $productMatchShopField = $config->set('productMatchShopField', '[product::getShopObject()::sku]');
        $productMatchAcumulusField = $config->set('productMatchAcumulusField', $acumulusField);
        try {
            $productManager = $this->getProductManager();
            $result = $productManager->updateStockForItem($item, $change, __METHOD__);
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
                $result = $productManager->updateStockForItem($item, -$change, __METHOD__);
                self::assertSame($acumulusProductIdOrError, (int) $result->getMainApiResponse()[Fld::ProductId]);
                self::assertSame(
                    $result->getAcumulusResult()->getAcumulusRequest()->getSubmit()['stock'][Meta::AcumulusProductIdSource],
                    'local'
                );
                self::assertSame($stockAmount - $change, (float) $result->getMainApiResponse()[Fld::StockAmount]);
            }
        } finally {
            $config->set('productMatchShopField', $productMatchShopField);
            $config->set('productMatchAcumulusField', $productMatchAcumulusField);
        }
    }

    /**
     * Checks the mail.
     */
    public function checkMail(): void
    {
        $this->assertSame($this->mailCount + 1, $this->getMailer()->getMailCount());
        $mailSent = $this->getMailer()->getMailSent($this->mailCount);
        $this->assertIsArray($mailSent);

        // dataName() returns the key of the actual data set.
        $name = str_replace(' ', '-', $this->dataName()) . '-' . $this->getContainer()->getLanguage();
        $this->saveTestMail($this->getTestsPath() . '/Data', $name, $mailSent);
        $expected = $this->getTestMail($this->getTestsPath() . '/Data', $name);
        $this->assertSame($expected, $mailSent);
    }
}

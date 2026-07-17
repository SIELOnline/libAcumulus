<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Shop;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Shop\AcumulusEntry;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\TestWebShop\Invoice\Source;
use Siel\Acumulus\TestWebShop\Shop\AcumulusEntryManager;

/**
 * AcumulusEntryManagerTest tests the {@see \Siel\Acumulus\Shop\AcumulusEntryManager} class.
 *
 * This is the base manager class for managing {@see \Siel\Acumulus\Shop\AcumulusEntry Acumulus entries},
 * so the tests focus on the basic actions, not on db(-library) specifics.
 */
class AcumulusEntryManagerTest extends TestCase
{
    use AcumulusContainer;

    private const testEntry1 = 1234;
    private const testToken1 = '1234abcd';
    private const testEntry2 = 5678;
    private const testToken2 = '5678efgh';
    private const testEntry3 = 9012;
    private const testToken3 = '9012ijkl';

    protected static string $shopNamespace = 'TestWebShop\TestDoubles';

    private function getAcumulusEntryManager(): AcumulusEntryManager
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return static::getContainer()->getAcumulusEntryManager();
    }

    private function getSource1(): Source
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return static::getContainer()->createSource(Source::Order, 7);
    }

    private function getSource2(): Source
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return static::getContainer()->createSource(Source::CreditNote, 1967);
    }

    public function testLockForSending(): void
    {
        $now = new DateTimeImmutable((new DateTimeImmutable())->format(Api::Format_TimeStamp));
        $manager = $this->getAcumulusEntryManager();
        $invoiceSource = $this->getSource1();
        static::assertTrue($manager->lockForSending($invoiceSource));
        $acumulusEntry1 = $manager->getByInvoiceSource($invoiceSource);
        static::assertNull($acumulusEntry1);
        $acumulusEntry1 = $manager->getByInvoiceSource($invoiceSource, false);
        static::assertNotNull($acumulusEntry1);

        static::assertSame(AcumulusEntry::lockToken, $acumulusEntry1->getToken());
        static::assertSame(AcumulusEntry::lockEntryId, $acumulusEntry1->getRecord()[AcumulusEntry::$keyEntryId]);
        static::assertNull($acumulusEntry1->getEntryId());
        static::assertNull($acumulusEntry1->getConceptId());
        static::assertSame($invoiceSource->getType(), $acumulusEntry1->getSourceType());
        static::assertSame($invoiceSource->getId(), $acumulusEntry1->getSourceId());
        static::assertGreaterThanOrEqual($now, $acumulusEntry1->getUpdated());
    }

    /**
     * @depends testLockForSending
     */
    public function testDeleteLock(): void
    {
        $manager = $this->getAcumulusEntryManager();
        $invoiceSource = $this->getSource1();

        // Tests that lock still exists (because of @depends)
        $acumulusEntry1 = $manager->getByInvoiceSource($invoiceSource, false);
        static::assertNotNull($acumulusEntry1);
        static::assertSame(AcumulusEntry::lockToken, $acumulusEntry1->getToken());
        static::assertNull($acumulusEntry1->getEntryId());
        static::assertSame(AcumulusEntry::lockEntryId, $acumulusEntry1->getRecord()[AcumulusEntry::$keyEntryId]);

        static::assertSame(AcumulusEntry::Lock_Deleted, $manager->deleteLock($invoiceSource));
        $acumulusEntry1 = $manager->getByInvoiceSource($invoiceSource);
        static::assertNull($acumulusEntry1);
    }

    public function testSaveInsertGet(): void
    {
        $manager = $this->getAcumulusEntryManager();
        $invoiceSource1 = $this->getSource1();
        $invoiceSource2 = $this->getSource2();

        // Assert entries do not yet exist.
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry1);
        static::assertNull($acumulusEntry1);
        $acumulusEntry2 = $manager->getByEntryId(self::testEntry2);
        static::assertNull($acumulusEntry2);

        // Insert entry 1
        // Get current time without fraction as that may/will be lost in the underlying
        // storage method of Acumulus entries as well.
        $now = new DateTimeImmutable((new DateTimeImmutable())->format(Api::Format_TimeStamp));
        static::assertTrue($manager->save($invoiceSource1, self::testEntry1, self::testToken1));

        // Assert entry 1 exists and has correct values and entry 2 does still not exist.
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry1);
        static::assertSame(self::testEntry1, $acumulusEntry1->getEntryId());
        static::assertNull($acumulusEntry1->getConceptId());
        static::assertSame(self::testToken1, $acumulusEntry1->getToken());
        static::assertSame($invoiceSource1->getType(), $acumulusEntry1->getSourceType());
        static::assertSame($invoiceSource1->getId(), $acumulusEntry1->getSourceId());
        static::assertGreaterThanOrEqual($now, $acumulusEntry1->getCreated());
        static::assertGreaterThanOrEqual($now, $acumulusEntry1->getUpdated());
        $acumulusEntry2 = $manager->getByEntryId(self::testEntry2);
        static::assertNull($acumulusEntry2);

        // Insert entry 2
        static::assertTrue($manager->save($invoiceSource2, self::testEntry2, self::testToken2));

        // Assert entry 1 still exists and has not changed and entry 2 now exists as well.
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry1);
        static::assertSame(self::testEntry1, $acumulusEntry1->getEntryId());
        static::assertSame(self::testToken1, $acumulusEntry1->getToken());
        static::assertSame($invoiceSource1->getType(), $acumulusEntry1->getSourceType());
        static::assertSame($invoiceSource1->getId(), $acumulusEntry1->getSourceId());
        static::assertGreaterThanOrEqual($now, $acumulusEntry1->getCreated());
        static::assertGreaterThanOrEqual($now, $acumulusEntry1->getUpdated());

        $acumulusEntry2 = $manager->getByEntryId(self::testEntry2);
        static::assertSame(self::testEntry2, $acumulusEntry2->getEntryId());
        static::assertNull($acumulusEntry2->getConceptId());
        static::assertSame(self::testToken2, $acumulusEntry2->getToken());
        static::assertSame($invoiceSource2->getType(), $acumulusEntry2->getSourceType());
        static::assertSame($invoiceSource2->getId(), $acumulusEntry2->getSourceId());
        static::assertGreaterThanOrEqual($now, $acumulusEntry2->getCreated());
        static::assertGreaterThanOrEqual($now, $acumulusEntry2->getUpdated());
    }

    /**
     * @depends testSaveInsertGet
     */
    public function testUpdate(): void
    {
        sleep(1);

        $manager = $this->getAcumulusEntryManager();
        $invoiceSource1 = $this->getSource1();
        $invoiceSource2 = $this->getSource2();

        // Assert entries do exist (because of the @depends).
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry1);
        static::assertNotNull($acumulusEntry1);
        $acumulusEntry2 = $manager->getByEntryId(self::testEntry2);
        static::assertNotNull($acumulusEntry2);

        // Update entry 1
        $now = new DateTimeImmutable((new DateTimeImmutable())->format(Api::Format_TimeStamp));
        static::assertTrue($manager->save($invoiceSource1, self::testEntry3, self::testToken3));

        // Assert entry for invoice source 1 has changed.
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry1);
        static::assertNull($acumulusEntry1);
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry3);
        static::assertSame(self::testEntry3, $acumulusEntry1->getEntryId());
        static::assertSame(self::testToken3, $acumulusEntry1->getToken());
        static::assertSame($invoiceSource1->getType(), $acumulusEntry1->getSourceType());
        static::assertSame($invoiceSource1->getId(), $acumulusEntry1->getSourceId());
        static::assertLessThan($now, $acumulusEntry1->getCreated());
        static::assertGreaterThanOrEqual($now, $acumulusEntry1->getUpdated());

        // and entry2 has not changed.
        $acumulusEntry2 = $manager->getByEntryId(self::testEntry2);
        static::assertSame(self::testEntry2, $acumulusEntry2->getEntryId());
        static::assertSame(self::testToken2, $acumulusEntry2->getToken());
        static::assertSame($invoiceSource2->getType(), $acumulusEntry2->getSourceType());
        static::assertSame($invoiceSource2->getId(), $acumulusEntry2->getSourceId());
        static::assertLessThan($now, $acumulusEntry2->getCreated());
        static::assertLessThan($now, $acumulusEntry2->getUpdated());
    }

    /**
     * @depends testUpdate
     */
    public function testDelete(): void
    {
        $manager = $this->getAcumulusEntryManager();
        $invoiceSource1 = $this->getSource1();
        $invoiceSource2 = $this->getSource2();

        // Assert entries do exist (because of the @depends).
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry3);
        static::assertNotNull($acumulusEntry1);
        $acumulusEntry2 = $manager->getByInvoiceSource($invoiceSource2);
        static::assertNotNull($acumulusEntry2);

        // Delete an entry
        static::assertTrue($manager->delete($acumulusEntry2));

        // Assert that entry 2 has been deleted
        $acumulusEntry2 = $manager->getByEntryId(self::testEntry2);
        static::assertNull($acumulusEntry2);
        // and that entry 1 still exists and has not changed.
        $now = new DateTimeImmutable((new DateTimeImmutable())->format(Api::Format_TimeStamp));
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry3);
        static::assertSame(self::testEntry3, $acumulusEntry1->getEntryId());
        static::assertSame(self::testToken3, $acumulusEntry1->getToken());
        static::assertSame($invoiceSource1->getType(), $acumulusEntry1->getSourceType());
        static::assertSame($invoiceSource1->getId(), $acumulusEntry1->getSourceId());
        static::assertLessThanOrEqual($now, $acumulusEntry1->getCreated());
        static::assertLessThanOrEqual($now, $acumulusEntry1->getUpdated());
    }

    /**
     * @depends testDelete
     */
    public function testDeleteByEntryId(): void
    {
        $manager = $this->getAcumulusEntryManager();
        $invoiceSource1 = $this->getSource1();

        // Assert entries do (or not) exist (because of the @depends).
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry1);
        static::assertNull($acumulusEntry1);
        $acumulusEntry1 = $manager->getByInvoiceSource($invoiceSource1);
        static::assertNotNull($acumulusEntry1);
        $acumulusEntry2 = $manager->getByEntryId(self::testEntry2);
        static::assertNull($acumulusEntry2);

        // Delete by old and actual entry id.
        static::assertTrue($manager->deleteByEntryId(self::testEntry1));
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry3);
        static::assertNotNull($acumulusEntry1);
        $acumulusEntry1 = $manager->getByEntryId(self::testEntry1);
        static::assertNull($acumulusEntry1);
    }
}

<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;

/**
 * CompleteAddEmailAsPdfSectionTest tests the
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteAddEmailAsPdfSection} class.
 */
class CompleteAddEmailAsPdfSectionTest extends TestCase
{
    private Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('TestWebShop', 'nl');
        $this->container->addTranslations('Translations', 'Invoice');
    }

    /**
     * @return \Siel\Acumulus\Helpers\Container
     */
    private function getContainer(): Container
    {
        return $this->container;
    }

    private function getInvoice(): Invoice
    {
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $this->getContainer()->createAcumulusObject(DataType::Invoice);
        return $invoice;
    }

    public function testComplete(): void
    {
        $config = $this->getContainer()->getConfig();
        $completor = $this->getContainer()->getCompletorTask('Invoice','AddEmailAsPdfSection');
        $invoice = $this->getInvoice();
        $config->set('emailAsPdf', true);
        $completor->complete($invoice);
        $this->assertTrue($invoice->metadataGet(Meta::AddEmailAsPdfSection));

        $config->set('emailAsPdf', false);
        $completor->complete($invoice);
        $this->assertFalse($invoice->metadataGet(Meta::AddEmailAsPdfSection));
    }
}

<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteAddEmailAsPdfSectionTest tests the
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteAddEmailAsPdfSection} class.
 */
class CompleteAddEmailAsPdfSectionTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

    public function testComplete(): void
    {
        $config = self::getContainer()->getConfig();
        $completor = self::getContainer()->getCompletorTask('Invoice','AddEmailAsPdfSection');
        $invoice = $this->getInvoice();
        $config->set('emailAsPdf', true);
        $completor->complete($invoice);
        $this->assertTrue($invoice->metadataGet(Meta::AddEmailAsPdfSection));

        $invoice = $this->getInvoice();
        $config->set('emailAsPdf', false);
        $completor->complete($invoice);
        $this->assertFalse($invoice->metadataGet(Meta::AddEmailAsPdfSection));
    }

    public function testCompleteAlreadyTrue(): void
    {
        $config = self::getContainer()->getConfig();
        $completor = self::getContainer()->getCompletorTask('Invoice','AddEmailAsPdfSection');
        $invoice = $this->getInvoice();
        $invoice->metadataSet(Meta::AddEmailAsPdfSection, true);

        $config->set('emailAsPdf', false);
        $completor->complete($invoice);
        $this->assertTrue($invoice->metadataGet(Meta::AddEmailAsPdfSection));
    }

    public function testCompleteAlreadyFalse(): void
    {
        $config = self::getContainer()->getConfig();
        $completor = self::getContainer()->getCompletorTask('Invoice','AddEmailAsPdfSection');
        $invoice = $this->getInvoice();
        $invoice->metadataSet(Meta::AddEmailAsPdfSection, false);

        $config->set('emailAsPdf', true);
        $completor->complete($invoice);
        $this->assertFalse($invoice->metadataGet(Meta::AddEmailAsPdfSection));
    }
}

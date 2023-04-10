<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;

/**
 * CompleteConceptTest test {@see CompleteConcept}.
 */
class CompleteConceptTest extends TestCase
{
    private Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('Tests\TestWebShop', 'nl');
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
        $completor = $this->getContainer()->getInvoiceCompletor('Concept');

        // Plugin, no warning = false
        $invoice = $this->getInvoice();
        $completor->complete($invoice, Config::Concept_Plugin);
        $this->assertFalse($invoice->concept);

        // No, no warning = false
        $invoice = $this->getInvoice();
        $completor->complete($invoice, Api::Concept_No);
        $this->assertFalse($invoice->concept);

        // Yes, no warning = true
        $invoice = $this->getInvoice();
        $completor->complete($invoice, Api::Concept_Yes);
        $this->assertTrue($invoice->concept);

        // Test not overwrite.
        $completor->complete($invoice, Api::Concept_No);
        $this->assertTrue($invoice->concept);

        $line = $this->getContainer()->createAcumulusObject(DataType::Line);
        $line->metadataAdd(Meta::Warning, 'warning');

        // Plugin, warning = true
        /** @var \Siel\Acumulus\Data\Line $line */
        $invoice = $this->getInvoice();
        $invoice->addLine($line);
        $completor->complete($invoice, Config::Concept_Plugin);
        $this->assertTrue($invoice->concept);

        // No, warning = false
        $invoice = $this->getInvoice();
        $invoice->addLine($line);
        $completor->complete($invoice, Api::Concept_No);
        $this->assertFalse($invoice->concept);

        // Yes, warning = true
        $invoice = $this->getInvoice();
        $invoice->addLine($line);
        $completor->complete($invoice, Api::Concept_Yes);
        $this->assertTrue($invoice->concept);
    }
}
